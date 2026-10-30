<?php

namespace Tests\Feature\Registration;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\RegistrationEngine\Models\ApplicabilityAssessment;
use App\Domains\RegistrationEngine\Models\AssessmentResult;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RegulatorySource;
use App\Domains\RegistrationEngine\Models\RulePack;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RegulatoryCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RegulatoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Organisation $orgA;

    protected function setUp(): void
    {
        parent::setUp();

        // Run Regulatory Catalog Seeder
        $this->seed(RegulatoryCatalogSeeder::class);

        $this->tenantA = Tenant::create([
            'name' => 'Manicaland Forestry Ltd',
            'slug' => 'manicaland-forestry',
            'status' => 'ACTIVE',
        ]);

        $this->userA = User::create([
            'name' => 'Kudzanai Dube',
            'email' => 'kudzanai@manicalandforestry.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->orgA = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Manicaland Timber & Forestry (Pvt) Ltd',
            'trading_name' => 'Manica Timber',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Midlands Haulage Ltd',
            'slug' => 'midlands-haulage',
            'status' => 'ACTIVE',
        ]);

        $this->userB = User::create([
            'name' => 'Ruvimbo Ndlovu',
            'email' => 'ruvimbo@midlandshaulage.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        app(TenantContext::class)->setTenant($this->tenantA);
        $this->actingAs($this->userA);
    }

    public function test_regulatory_authorities_and_categories_are_properly_distinguished(): void
    {
        // 1. Assert Authorities Seeded
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'CIPZ', 'category' => 'STATUTORY_REGULATOR']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'ZIMRA', 'category' => 'STATUTORY_REGULATOR']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'NSSA', 'category' => 'STATUTORY_REGULATOR']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'ZIMDEF', 'category' => 'STATUTORY_REGULATOR']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'PRAZ', 'category' => 'PROCUREMENT_PLATFORM']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'COH', 'category' => 'LOCAL_AUTHORITY']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'NEC_ENG', 'category' => 'SECTOR_NEC']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'ECZ', 'category' => 'PROFESSIONAL_COUNCIL']);
        $this->assertDatabaseHas('regulatory_authorities', ['code' => 'CZI', 'category' => 'INDUSTRY_ASSOCIATION']);

        // 2. Query Scopes
        $statutoryRegulators = RegulatoryAuthority::category('STATUTORY_REGULATOR')->get();
        $this->assertTrue($statutoryRegulators->contains('code', 'CIPZ'));
        $this->assertTrue($statutoryRegulators->contains('code', 'ZIMRA'));
        $this->assertFalse($statutoryRegulators->contains('code', 'CZI'));
    }

    public function test_regulatory_sources_enforce_verification_status(): void
    {
        $verifiedSources = RegulatorySource::verified()->get();
        $this->assertTrue($verifiedSources->contains('citation', 'Companies and Other Business Entities Act [Chapter 24:31]'));
        $this->assertTrue($verifiedSources->contains('citation', 'Income Tax Act [Chapter 23:06]'));

        // CZI is an association constitution, stored as REQUIRES_REVIEW / not VERIFIED_OFFICIAL
        $unverifiedSource = RegulatorySource::where('citation', 'CZI Membership Charter & Constitution')->firstOrFail();
        $this->assertFalse($unverifiedSource->isVerified());
        $this->assertFalse($verifiedSources->contains('id', $unverifiedSource->id));
    }

    public function test_obligation_definitions_distinguish_legal_classifications(): void
    {
        // Mandatory Statutory
        $cipz = ObligationDefinition::where('code', 'CIPZ_INCORP')->firstOrFail();
        $this->assertTrue($cipz->isMandatoryStatutory());
        $this->assertFalse($cipz->isConditionalStatutory());

        // Conditional Statutory (VAT applies if turnover threshold met)
        $vat = ObligationDefinition::where('code', 'ZIMRA_VAT_REG')->firstOrFail();
        $this->assertTrue($vat->isConditionalStatutory());
        $this->assertEquals(25000.00, $vat->applicability_criteria['min_annual_turnover_usd']);

        // Collective Bargaining Agreement
        $necEng = ObligationDefinition::where('code', 'NEC_ENG_REG')->firstOrFail();
        $this->assertTrue($necEng->isCollectiveBargaining());

        // Tender Specific (PRAZ e-GP)
        $praz = ObligationDefinition::where('code', 'PRAZ_SUPPLIER_REG')->firstOrFail();
        $this->assertTrue($praz->isTenderSpecific());
        $this->assertContains('ITF263_CLEARANCE', $praz->prerequisites);
        $this->assertContains('NSSA_EMPLOYER_REG', $praz->prerequisites);

        // Optional Association
        $czi = ObligationDefinition::where('code', 'CZI_MEMBERSHIP')->firstOrFail();
        $this->assertTrue($czi->isOptionalAssociation());
        $this->assertFalse($czi->isVerified());
    }

    public function test_rule_version_governance_enforces_dual_key_approval_and_superseding(): void
    {
        $pack = RulePack::where('code', 'ZIMRA_CORE_TAX_RULES')->firstOrFail();
        $source = RegulatorySource::where('citation', 'Income Tax Act [Chapter 23:06]')->firstOrFail();

        // 1. Propose New Draft Version (Editor: User A)
        $draftVersion = RuleVersion::create([
            'rule_pack_id' => $pack->id,
            'source_id' => $source->id,
            'version_code' => '2027.1',
            'effective_from' => '2027-01-01',
            'status' => 'DRAFT',
            'verification_status' => 'REQUIRES_REVIEW',
            'parameters' => ['withholding_tax_rate_without_itf263' => 0.35],
            'change_summary' => 'Proposed 2027 Finance Bill adjustments.',
        ]);

        $draftVersion->submitForReview($this->userA->id);
        $this->assertEquals('PENDING_REVIEW', $draftVersion->status);

        // 2. Dual-Key Enforcement: Proposer cannot approve their own rule
        try {
            $draftVersion->approve($this->userA->id);
            $this->fail('Expected InvalidArgumentException when proposer attempts self-approval.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Dual-key separation violated', $e->getMessage());
        }

        // 3. Independent Legal Officer (User B) Approves
        $draftVersion->approve($this->userB->id);
        $this->assertEquals('APPROVED', $draftVersion->status);
        $this->assertTrue($draftVersion->isVerified());
        $this->assertEquals($this->userB->id, $draftVersion->approved_by_user_id);

        // 4. Activate Version and Verify Automatic Superseding of Previous Version
        $activeOldVersion = RuleVersion::where('rule_pack_id', $pack->id)
            ->where('version_code', '2026.1')
            ->firstOrFail();
        $this->assertEquals('ACTIVE', $activeOldVersion->status);

        $draftVersion->activate();
        $this->assertEquals('ACTIVE', $draftVersion->status);

        $activeOldVersion->refresh();
        $this->assertEquals('SUPERSEDED', $activeOldVersion->status);
        $this->assertEquals('2026-12-31', $activeOldVersion->effective_to->toDateString());
    }

    public function test_assessment_results_link_exact_rule_versions_and_preserve_tenant_isolation(): void
    {
        // 1. Tenant A completes a Discovery Questionnaire
        $questionnaire = RegistrationQuestionnaire::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'status' => 'COMPLETED',
            'current_step' => 5,
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'primary_sector' => 'FORESTRY_TIMBER',
                'employee_count' => 8,
                'intends_to_bid_government_tenders' => true,
            ],
            'completed_at' => Carbon::now(),
            'completed_by_user_id' => $this->userA->id,
        ]);

        $assessment = ApplicabilityAssessment::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'INITIAL_REGISTRATION',
            'evaluation_inputs' => $questionnaire->answers,
            'assessed_by_user_id' => $this->userA->id,
            'generated_at' => Carbon::now(),
        ]);

        $cipzDef = ObligationDefinition::where('code', 'CIPZ_INCORP')->firstOrFail();
        $cipzRule = RuleVersion::where('version_code', '2024.1')->firstOrFail();

        // 2. Record Assessment Result with exact rule version citation and decision
        $result = AssessmentResult::create([
            'tenant_id' => $this->tenantA->id,
            'assessment_id' => $assessment->id,
            'obligation_definition_id' => $cipzDef->id,
            'rule_version_id' => $cipzRule->id,
            'decision' => 'APPLICABLE',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'trigger_reasons' => ['entity_type: PRIVATE_LIMITED_COMPANY requires legal incorporation.'],
            'rule_source_citation' => 'COBE Act [Chapter 24:31], s. 16',
            'evaluated_at' => Carbon::now(),
        ]);

        $instance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'definition_id' => $cipzDef->id,
            'assessment_result_id' => $result->id,
            'status' => 'IN_PROGRESS',
            'evidence_status' => 'USER_DECLARED', // Declared without verified certificate
            'declared_registration_number' => 'COBE-7712/2026',
        ]);

        // 3. User declared certificate does NOT equal verified by issuer (Guideline 4)
        $this->assertTrue($instance->isDeclaredOnly());
        $this->assertFalse($instance->isVerifiedByIssuer());
        $this->assertFalse($instance->isCompliant());

        // 4. Verify Tenant B Cannot See Tenant A's Questionnaires, Assessments, or Results
        $context = app(TenantContext::class);
        $context->setTenant($this->tenantB);

        $this->assertCount(0, RegistrationQuestionnaire::all());
        $this->assertCount(0, ApplicabilityAssessment::all());
        $this->assertCount(0, AssessmentResult::all());
        $this->assertCount(0, ObligationInstance::all());
    }
}
