<?php

namespace Tests\Feature\Registration;

use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RegulatorySource;
use App\Domains\RegistrationEngine\Models\RulePack;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Domains\RegistrationEngine\Services\ApplicabilityEvaluator;
use Carbon\Carbon;
use Database\Seeders\RegulatoryCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

class ApplicabilityEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicabilityEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegulatoryCatalogSeeder::class);
        $this->evaluator = new ApplicabilityEvaluator();
    }

    public function test_evaluates_mandatory_statutory_obligations_for_new_business(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'employee_count' => 3,
            'pays_wages' => true,
            'annual_turnover_usd' => 10000.00,
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
            'wants_voluntary_vat' => false,
        ];

        $decisions = $this->evaluator->evaluate($profile, Carbon::parse('2026-03-01'));

        $cipz = $decisions->firstWhere('obligationDefinition.code', 'CIPZ_INCORP');
        $this->assertNotNull($cipz);
        $this->assertSame('MANDATORY', $cipz->decision);
        $this->assertStringContainsString('Companies and Other Business Entities Act [Chapter 24:31]', (string) $cipz->sourceCitation);

        $tin = $decisions->firstWhere('obligationDefinition.code', 'ZIMRA_TIN_REG');
        $this->assertNotNull($tin);
        $this->assertSame('MANDATORY', $tin->decision);

        $nssa = $decisions->firstWhere('obligationDefinition.code', 'NSSA_EMPLOYER_REG');
        $this->assertNotNull($nssa);
        $this->assertSame('MANDATORY', $nssa->decision);

        $zimdef = $decisions->firstWhere('obligationDefinition.code', 'ZIMDEF_LEVY_REG');
        $this->assertNotNull($zimdef);
        $this->assertSame('MANDATORY', $zimdef->decision);

        // VAT is NOT_APPLICABLE because turnover 10,000 < statutory threshold 25,000 and voluntary = false
        $vat = $decisions->firstWhere('obligationDefinition.code', 'ZIMRA_VAT_REG');
        $this->assertNotNull($vat);
        $this->assertSame('NOT_APPLICABLE', $vat->decision);
    }

    public function test_evaluates_compulsory_vat_when_turnover_exceeds_statutory_threshold(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => true,
            'employee_count' => 5,
            'pays_wages' => true,
            'annual_turnover_usd' => 75000.00, // Exceeds USD 25,000 threshold
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
            'wants_voluntary_vat' => false,
        ];

        $decisions = $this->evaluator->evaluate($profile, Carbon::parse('2026-03-01'));

        $vat = $decisions->firstWhere('obligationDefinition.code', 'ZIMRA_VAT_REG');
        $this->assertNotNull($vat);
        $this->assertSame('CONDITIONAL', $vat->decision);
        $this->assertSame('CONDITIONAL_STATUTORY', $vat->legalClassification);
        $this->assertNotEmpty($vat->triggerReasons);
    }

    public function test_evaluates_voluntary_vat_when_declared_under_threshold(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => true,
            'employee_count' => 2,
            'pays_wages' => true,
            'annual_turnover_usd' => 8000.00, // Under threshold
            'wants_voluntary_vat' => true, // Declared voluntary registration
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
        ];

        $decisions = $this->evaluator->evaluate($profile, Carbon::parse('2026-03-01'));

        $vat = $decisions->firstWhere('obligationDefinition.code', 'ZIMRA_VAT_REG');
        $this->assertSame('CONDITIONAL', $vat->decision);
    }

    public function test_evaluates_statutory_exemption_diplomatic_or_religious(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => true,
            'employee_count' => 5,
            'pays_wages' => true,
            'annual_turnover_usd' => 10000.00,
            'is_religious_or_charity' => true, // Statutory exemption for ZIMDEF
            'bids_on_government_tenders' => false,
            'has_physical_premises' => false,
            'wants_voluntary_vat' => false,
        ];

        $decisions = $this->evaluator->evaluate($profile, Carbon::parse('2026-03-01'));

        $zimdef = $decisions->firstWhere('obligationDefinition.code', 'ZIMDEF_LEVY_REG');
        $this->assertNotNull($zimdef);
        $this->assertSame('EXEMPT', $zimdef->decision);
        $this->assertStringContainsString('Religious and registered charitable institutions', $zimdef->triggerReasons[0]);
    }

    public function test_unverified_or_draft_rule_versions_yield_unverified_hold(): void
    {
        $auth = RegulatoryAuthority::where('code', 'CIPZ')->first();
        $source = RegulatorySource::first();

        $pack = RulePack::create([
            'authority_id' => $auth->id,
            'code' => 'DRAFT_EXPERIMENT_PACK',
            'name' => 'Draft Rules Test',
            'category' => 'CUSTOM',
            'description' => 'Unapproved draft rule pack',
            'is_active' => true,
        ]);

        RuleVersion::create([
            'rule_pack_id' => $pack->id,
            'source_id' => $source->id,
            'version_code' => '0.1-DRAFT',
            'effective_from' => '2026-01-01',
            'status' => 'DRAFT', // Unapproved / draft status
            'verification_status' => 'REQUIRES_REVIEW',
            'parameters' => [],
        ]);

        $def = ObligationDefinition::create([
            'code' => 'EXPERIMENTAL_PERMIT',
            'authority_id' => $auth->id,
            'source_id' => $source->id,
            'rule_pack_id' => $pack->id,
            'title' => 'Experimental Permit',
            'obligation_type' => 'REGISTRATION',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'verification_status' => 'REQUIRES_REVIEW',
            'applicability_criteria' => ['is_new_business' => true],
            'prerequisites' => [],
            'is_active' => true,
        ]);

        $decisions = $this->evaluator->evaluate([
            'is_already_incorporated' => false,
        ], Carbon::parse('2026-03-01'), collect([$def]));

        $res = $decisions->first();
        $this->assertSame('UNVERIFIED_HOLD', $res->decision);
        $this->assertNull($res->ruleVersion);
    }

    public function test_historical_effective_date_selection(): void
    {
        $cipz = ObligationDefinition::where('code', 'CIPZ_INCORP')->first();

        // Seeded rule version is effective from 2024-01-01.
        // If assessment date is in 2020 (before effective date):
        $historicalEarly = $this->evaluator->evaluateObligation(
            $cipz,
            ['is_already_incorporated' => false],
            Carbon::parse('2020-01-01')
        );
        $this->assertSame('UNVERIFIED_HOLD', $historicalEarly->decision);

        // When assessed in 2025 (within effective window):
        $historicalValid = $this->evaluator->evaluateObligation(
            $cipz,
            ['is_already_incorporated' => false, 'entity_type' => 'PRIVATE_LIMITED_COMPANY'],
            Carbon::parse('2025-06-01')
        );
        $this->assertSame('MANDATORY', $historicalValid->decision);
    }

    public function test_missing_required_inputs_yield_requires_information(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            // 'employee_count' is omitted!
            'annual_turnover_usd' => 10000.00,
        ];

        $nssa = ObligationDefinition::where('code', 'NSSA_EMPLOYER_REG')->first();
        $decision = $this->evaluator->evaluateObligation($nssa, $profile, Carbon::parse('2026-03-01'));

        $this->assertSame('REQUIRES_INFORMATION', $decision->decision);
        $this->assertContains('employee_count', $decision->unresolvedInformation);
    }

    public function test_contradictory_inputs_yield_requires_information(): void
    {
        $contradictoryProfile = [
            'employee_count' => 0,
            'pays_wages' => true, // Contradiction
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ];

        $nssa = ObligationDefinition::where('code', 'NSSA_EMPLOYER_REG')->first();
        $decision = $this->evaluator->evaluateObligation($nssa, $contradictoryProfile, Carbon::parse('2026-03-01'));

        $this->assertSame('REQUIRES_INFORMATION', $decision->decision);
        $this->assertNotEmpty($decision->unresolvedInformation);
    }

    public function test_declared_existing_registration_does_not_label_compliant(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'existing_registrations' => [
                'CIPZ_INCORP' => [
                    'registration_number' => '12345/2024',
                    'registered_at' => '2024-03-15',
                ],
            ],
        ];

        $cipz = ObligationDefinition::where('code', 'CIPZ_INCORP')->first();
        $decision = $this->evaluator->evaluateObligation($cipz, $profile, Carbon::parse('2026-03-01'));

        // Requirement 7: Statutory decision remains MANDATORY, NOT compliant!
        $this->assertSame('MANDATORY', $decision->decision);
        $this->assertNotNull($decision->declaredRegistration);
        $this->assertSame('12345/2024', $decision->declaredRegistration['declared_number']);
        $this->assertSame('USER_DECLARED_UNVERIFIED', $decision->declaredRegistration['verification_state']);
    }

    public function test_zero_ai_api_calls_made_during_rule_evaluation(): void
    {
        Ai::spy();

        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'is_already_incorporated' => false,
            'employee_count' => 10,
            'pays_wages' => true,
            'annual_turnover_usd' => 50000.00,
            'bids_on_government_tenders' => true,
            'has_physical_premises' => true,
            'wants_voluntary_vat' => false,
        ];

        $this->evaluator->evaluate($profile, Carbon::parse('2026-03-01'));

        // Verify that NO AI calls were made by the Laravel AI SDK
        Ai::shouldNotHaveReceived('chat');
    }
}
