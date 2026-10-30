<?php

namespace Tests\Feature\Registration;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\RegistrationEngine\Models\ApplicabilityAssessment;
use App\Domains\RegistrationEngine\Models\AssessmentResult;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire;
use App\Domains\RegistrationEngine\Services\RegistrationRoadmapGenerator;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RegulatoryCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Ai\Ai;
use Tests\TestCase;

class RegistrationRoadmapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected RegistrationRoadmapGenerator $generator;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Organisation $orgA;
    protected Organisation $orgB;
    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegulatoryCatalogSeeder::class);
        $this->generator = new RegistrationRoadmapGenerator();

        $this->tenantA = Tenant::create([
            'name' => 'Highlands Engineering Ltd',
            'slug' => 'highlands-eng',
            'status' => 'ACTIVE',
        ]);

        $this->userA = User::create([
            'name' => 'Farai Chiweshe',
            'email' => 'farai@highlands.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->orgA = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Highlands Engineering Solutions (Pvt) Ltd',
            'trading_name' => 'Highlands Engineering',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Bulawayo Foundry Ltd',
            'slug' => 'bulawayo-foundry',
            'status' => 'ACTIVE',
        ]);

        $this->orgB = Organisation::create([
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Bulawayo Foundry & Smelting (Pvt) Ltd',
            'trading_name' => 'Bulawayo Foundry',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);
    }

    public function test_generates_and_persists_auditable_registration_roadmap(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'employee_count' => 6,
            'pays_wages' => true,
            'annual_turnover_usd' => 45000.00,
            'bids_on_government_tenders' => true,
            'has_physical_premises' => true,
            'wants_voluntary_vat' => false,
        ];

        $response = $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: $profile,
            assessmentDate: Carbon::parse('2026-03-01'),
            assessedByUserId: $this->userA->id
        );

        $this->assertNotNull($response['assessment_id']);
        $this->assertSame($this->tenantA->id, $response['tenant_id']);
        $this->assertSame($this->orgA->id, $response['organisation_id']);
        $this->assertFalse($response['is_idempotent_replay']);

        // Check summary counts
        $this->assertGreaterThan(0, $response['summary']['mandatory_count']);
        $this->assertGreaterThan(0, $response['summary']['conditional_count']);

        // Verify DAG topological ordering: CIPZ_INCORP must be sequence #1
        $firstItem = $response['roadmap'][0];
        $this->assertSame('CIPZ_INCORP', $firstItem['obligation_code']);
        $this->assertSame(1, $firstItem['sequence_number']);

        // Verify persistence in database
        $this->assertDatabaseHas('applicability_assessments', [
            'id' => $response['assessment_id'],
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
        ]);

        $this->assertDatabaseHas('assessment_results', [
            'tenant_id' => $this->tenantA->id,
            'assessment_id' => $response['assessment_id'],
            'decision' => 'MANDATORY',
        ]);

        // Verify obligation instances were created without marking COMPLIANT
        $instances = ObligationInstance::where('organisation_id', $this->orgA->id)->get();
        $this->assertNotEmpty($instances);
        foreach ($instances as $inst) {
            $this->assertNotSame('COMPLIANT', $inst->status);
        }
    }

    public function test_enforces_tenant_isolation_in_roadmap_generation(): void
    {
        // 1. Cannot generate assessment for organisation belonging to another tenant
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgB->id, // Org B belongs to Tenant B!
            businessProfile: ['entity_type' => 'PBC']
        );
    }

    public function test_cannot_bind_questionnaire_from_another_tenant(): void
    {
        // Create questionnaire under tenant B
        $foreignQuestionnaire = RegistrationQuestionnaire::create([
            'tenant_id' => $this->tenantB->id,
            'organisation_id' => $this->orgB->id,
            'answers' => ['entity_type' => 'PBC'],
            'status' => 'COMPLETED',
        ]);

        $this->expectException(InvalidArgumentException::class);

        // Attempt to pass foreign questionnaire ID under Tenant A
        $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: ['entity_type' => 'PBC'],
            questionnaireId: $foreignQuestionnaire->id
        );
    }

    public function test_idempotent_repeated_requests_return_existing_assessment(): void
    {
        $idempotencyKey = 'SESSION-IDEMPOTENCY-XYZ-1234';
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'employee_count' => 2,
            'pays_wages' => true,
            'annual_turnover_usd' => 15000.00,
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
            'wants_voluntary_vat' => false,
        ];

        // First call
        $first = $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: $profile,
            idempotencyKey: $idempotencyKey
        );
        $this->assertFalse($first['is_idempotent_replay']);
        $firstId = $first['assessment_id'];

        $countBefore = ApplicabilityAssessment::count();

        // Second call with same idempotency key
        $second = $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: $profile,
            idempotencyKey: $idempotencyKey
        );

        $this->assertTrue($second['is_idempotent_replay']);
        $this->assertSame($firstId, $second['assessment_id']);
        $this->assertSame($countBefore, ApplicabilityAssessment::count()); // No duplicate assessment row
    }

    public function test_historical_assessment_records_are_immutable(): void
    {
        $profile1 = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'employee_count' => 1,
            'pays_wages' => true,
            'annual_turnover_usd' => 10000.00,
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
            'wants_voluntary_vat' => false,
        ];

        $res1 = $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: $profile1,
            assessmentDate: Carbon::parse('2026-01-15')
        );

        // Later, circumstances change (turnover jumps past VAT threshold, tenders enabled)
        $profile2 = array_merge($profile1, [
            'annual_turnover_usd' => 80000.00,
            'bids_on_government_tenders' => true,
        ]);

        $res2 = $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: $profile2,
            assessmentDate: Carbon::parse('2026-06-20')
        );

        // Verify two distinct historical assessment records exist
        $this->assertNotSame($res1['assessment_id'], $res2['assessment_id']);
        $this->assertSame(2, ApplicabilityAssessment::where('organisation_id', $this->orgA->id)->count());

        // Verify first assessment's VAT result is STILL NOT_APPLICABLE (immutable history)
        $historicalVatResult = AssessmentResult::where('assessment_id', $res1['assessment_id'])
            ->whereHas('obligationDefinition', fn ($q) => $q->where('code', 'ZIMRA_VAT_REG'))
            ->first();
        $this->assertSame('NOT_APPLICABLE', $historicalVatResult->decision);

        // Verify second assessment's VAT result is CONDITIONAL
        $newVatResult = AssessmentResult::where('assessment_id', $res2['assessment_id'])
            ->whereHas('obligationDefinition', fn ($q) => $q->where('code', 'ZIMRA_VAT_REG'))
            ->first();
        $this->assertSame('CONDITIONAL', $newVatResult->decision);
    }

    public function test_zero_ai_api_calls_made_during_roadmap_generation(): void
    {
        Ai::spy();

        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'employee_count' => 4,
            'pays_wages' => true,
            'annual_turnover_usd' => 20000.00,
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
            'wants_voluntary_vat' => false,
        ];

        $this->generator->generate(
            tenantId: $this->tenantA->id,
            organisationId: $this->orgA->id,
            businessProfile: $profile
        );

        Ai::shouldNotHaveReceived('chat');
    }
}
