<?php

namespace Tests\Feature\Compliance;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use App\Domains\ComplianceEngine\Models\FilingOccurrenceActivityLog;
use App\Domains\ComplianceEngine\Services\FilingCalendarService;
use App\Domains\ComplianceEngine\Services\FilingDeadlineCalculator;
use App\Domains\ComplianceEngine\Services\FilingDisplayClassifier;
use App\Domains\ComplianceEngine\Services\FilingLifecycleStateMachine;
use App\Domains\ComplianceEngine\Services\FilingScheduleGenerator;
use App\Domains\RegistrationEngine\Models\ApplicabilityAssessment;
use App\Domains\RegistrationEngine\Models\AssessmentResult;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RegulatorySource;
use App\Domains\RegistrationEngine\Models\RulePack;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatutoryFilingCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Organisation $orgA1;
    protected Organisation $orgA2;
    protected User $adminUser;
    protected User $staffUser;
    protected User $orgA2Admin;

    protected Tenant $tenantB;
    protected Organisation $orgB;
    protected User $tenantBUser;

    protected RegulatoryAuthority $zimra;
    protected RegulatoryAuthority $nssa;
    protected RegulatoryAuthority $praz;
    protected RegulatorySource $syntheticTaxSource;
    protected RegulatorySource $syntheticNssaSource;

    protected RulePack $syntheticMonthlyPack;
    protected RuleVersion $syntheticMonthlyRule;

    protected RulePack $syntheticQuarterlyPack;
    protected RuleVersion $syntheticQuarterlyRule;

    protected RulePack $syntheticAnnualPack;
    protected RuleVersion $syntheticAnnualRule;

    protected ObligationDefinition $monthlyDef;
    protected ObligationDefinition $quarterlyDef;
    protected ObligationDefinition $annualDef;
    protected ObligationDefinition $unverifiedDef;

    protected ObligationInstance $monthlyInstance;
    protected ObligationInstance $quarterlyInstance;
    protected ObligationInstance $annualInstance;
    protected ObligationInstance $unverifiedInstance;

    protected FilingDeadlineCalculator $calculator;
    protected FilingScheduleGenerator $generator;
    protected FilingDisplayClassifier $classifier;
    protected FilingCalendarService $calendarService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new FilingDeadlineCalculator();
        $this->generator = new FilingScheduleGenerator($this->calculator);
        $this->classifier = new FilingDisplayClassifier();
        $this->calendarService = new FilingCalendarService(
            new FilingLifecycleStateMachine(),
            $this->classifier,
            $this->generator,
            $this->calculator
        );

        // 1. Setup Tenant A with two Organisations (orgA1 and orgA2)
        $this->tenantA = Tenant::create([
            'name' => 'Mashonaland Industrial Holdings',
            'slug' => 'mashonaland-ind',
            'status' => 'ACTIVE',
        ]);

        $this->orgA1 = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Mashonaland Steelworks (Pvt) Ltd',
            'trading_name' => 'Mashonaland Steel',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->orgA2 = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Mashonaland Transport (Pvt) Ltd',
            'trading_name' => 'Mashonaland Transport',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->adminUser = User::create([
            'name' => 'Rudo Moyo (Compliance Lead)',
            'email' => 'rudo.admin@mashsteel.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->staffUser = User::create([
            'name' => 'Tafadzwa Ncube (Payroll Clerk)',
            'email' => 'tafadzwa.clerk@mashsteel.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->orgA2Admin = User::create([
            'name' => 'Blessing Dube (Transport Admin)',
            'email' => 'blessing@mashtransport.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminUser->id,
            'organisation_id' => $this->orgA1->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->staffUser->id,
            'organisation_id' => $this->orgA1->id,
            'role' => 'MEMBER',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->orgA2Admin->id,
            'organisation_id' => $this->orgA2->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        // 2. Setup Tenant B for Cross-Tenant Isolation
        $this->tenantB = Tenant::create([
            'name' => 'Matabeleland Mining Corp',
            'slug' => 'matabeleland-mining',
            'status' => 'ACTIVE',
        ]);

        $this->orgB = Organisation::create([
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Matabeleland Gold (Pvt) Ltd',
            'trading_name' => 'MatGold',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->tenantBUser = User::create([
            'name' => 'Nqobile Sibanda',
            'email' => 'nqobile@matgold.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->tenantBUser->id,
            'organisation_id' => $this->orgB->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        // 3. Setup Synthetic Approved Test Rules (Clearly labelled as synthetic test fixtures per Section 11)
        $this->zimra = RegulatoryAuthority::create([
            'code' => 'ZIMRA',
            'name' => 'Zimbabwe Revenue Authority',
            'category' => 'STATUTORY_REGULATOR',
            'is_active' => true,
        ]);

        $this->nssa = RegulatoryAuthority::create([
            'code' => 'NSSA',
            'name' => 'National Social Security Authority',
            'category' => 'STATUTORY_REGULATOR',
            'is_active' => true,
        ]);

        $this->praz = RegulatoryAuthority::create([
            'code' => 'PRAZ',
            'name' => 'Procurement Regulatory Authority of Zimbabwe',
            'category' => 'PROCUREMENT_PLATFORM',
            'is_active' => true,
        ]);

        $this->syntheticTaxSource = RegulatorySource::create([
            'authority_id' => $this->zimra->id,
            'title' => 'Synthetic Test Tax Schedule Fixture [Income Tax Act Chapter 23:06]',
            'act_chapter' => '23:06',
            'gazette_date' => '2024-01-01',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'citation' => 'SYNTHETIC_FIXTURE: Income Tax Act [Chapter 23:06], 13th Schedule',
        ]);

        $this->syntheticNssaSource = RegulatorySource::create([
            'authority_id' => $this->nssa->id,
            'title' => 'Synthetic Test Social Security Fixture [NSSA Act Chapter 17:04]',
            'act_chapter' => '17:04',
            'gazette_date' => '2024-01-01',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'citation' => 'SYNTHETIC_FIXTURE: NSSA Act [Chapter 17:04], SI 393/1993',
        ]);

        // Monthly Rule Pack & Approved Version (Due on 10th of following month)
        $this->syntheticMonthlyPack = RulePack::create([
            'authority_id' => $this->nssa->id,
            'code' => 'SYNTHETIC_MONTHLY_PACK',
            'name' => 'Synthetic Monthly Remittance Schedule Pack',
            'category' => 'SOCIAL_SECURITY',
            'is_active' => true,
        ]);

        $this->syntheticMonthlyRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticMonthlyPack->id,
            'source_id' => $this->syntheticNssaSource->id,
            'version_code' => 'SYNTHETIC_MONTHLY_2026_V1',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'MONTHLY',
                    'due_day' => 10,
                    'month_offset' => 1,
                    'payment_due_day' => 10,
                    'weekend_adjustment' => 'NONE',
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        // Quarterly Rule Pack & Approved Version (Due 20th of month following quarter end)
        $this->syntheticQuarterlyPack = RulePack::create([
            'authority_id' => $this->zimra->id,
            'code' => 'SYNTHETIC_QUARTERLY_PACK',
            'name' => 'Synthetic Quarterly Return Schedule Pack',
            'category' => 'TAXATION',
            'is_active' => true,
        ]);

        $this->syntheticQuarterlyRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticQuarterlyPack->id,
            'source_id' => $this->syntheticTaxSource->id,
            'version_code' => 'SYNTHETIC_QUARTERLY_2026_V1',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'QUARTERLY',
                    'due_day' => 20,
                    'month_offset' => 1,
                    'weekend_adjustment' => 'NONE',
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        // Annual Rule Pack & Approved Version (Due 30 April of following year: year_offset = 1)
        $this->syntheticAnnualPack = RulePack::create([
            'authority_id' => $this->zimra->id,
            'code' => 'SYNTHETIC_ANNUAL_PACK',
            'name' => 'Synthetic Annual Return Schedule Pack',
            'category' => 'TAXATION',
            'is_active' => true,
        ]);

        $this->syntheticAnnualRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticAnnualPack->id,
            'source_id' => $this->syntheticTaxSource->id,
            'version_code' => 'SYNTHETIC_ANNUAL_2026_V1',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'ANNUAL',
                    'due_month' => 4,
                    'due_day' => 30,
                    'year_offset' => 1,
                    'weekend_adjustment' => 'NONE',
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        // Obligation Definitions
        $this->monthlyDef = ObligationDefinition::create([
            'code' => 'SYNTH_NSSA_MONTHLY',
            'authority_id' => $this->nssa->id,
            'source_id' => $this->syntheticNssaSource->id,
            'rule_pack_id' => $this->syntheticMonthlyPack->id,
            'title' => 'Synthetic Monthly Social Security Return (Form P4)',
            'obligation_type' => 'PERIODIC_RETURN',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'statutory_citation' => 'SYNTHETIC_FIXTURE: NSSA Act [Chapter 17:04]',
            'renewal_frequency' => 'MONTHLY',
            'required_documents' => ['NSSA_P4_RECEIPT'],
            'applicability_criteria' => ['min_employees' => 1],
            'is_active' => true,
        ]);

        $this->quarterlyDef = ObligationDefinition::create([
            'code' => 'SYNTH_QPD_QUARTERLY',
            'authority_id' => $this->zimra->id,
            'source_id' => $this->syntheticTaxSource->id,
            'rule_pack_id' => $this->syntheticQuarterlyPack->id,
            'title' => 'Synthetic Quarterly Corporate Tax Return',
            'obligation_type' => 'PERIODIC_RETURN',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'statutory_citation' => 'SYNTHETIC_FIXTURE: Income Tax Act [Chapter 23:06]',
            'renewal_frequency' => 'QUARTERLY',
            'required_documents' => ['QPD_RECEIPT'],
            'applicability_criteria' => ['min_employees' => 1],
            'is_active' => true,
        ]);

        $this->annualDef = ObligationDefinition::create([
            'code' => 'SYNTH_ITF12C_ANNUAL',
            'authority_id' => $this->zimra->id,
            'source_id' => $this->syntheticTaxSource->id,
            'rule_pack_id' => $this->syntheticAnnualPack->id,
            'title' => 'Synthetic Annual Income Tax Self-Assessment Return (ITF12C)',
            'obligation_type' => 'PERIODIC_RETURN',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'statutory_citation' => 'SYNTHETIC_FIXTURE: Income Tax Act [Chapter 23:06]',
            'renewal_frequency' => 'ANNUAL',
            'required_documents' => ['ITF12C_RECEIPT'],
            'applicability_criteria' => ['min_employees' => 1],
            'is_active' => true,
        ]);

        $this->unverifiedDef = ObligationDefinition::create([
            'code' => 'SYNTH_UNVERIFIED_MUNICIPAL',
            'authority_id' => $this->praz->id,
            'source_id' => null,
            'rule_pack_id' => null,
            'title' => 'Synthetic Unverified Schedule Permit',
            'obligation_type' => 'LICENCE_RENEWAL',
            'legal_classification' => 'CONDITIONAL_STATUTORY',
            'verification_status' => 'REQUIRES_REVIEW',
            'statutory_citation' => 'SYNTHETIC_UNVERIFIED_INSTRUMENT',
            'renewal_frequency' => 'MONTHLY',
            'required_documents' => ['PERMIT_RECEIPT'],
            'applicability_criteria' => ['min_employees' => 1],
            'is_active' => true,
        ]);

        // Create Obligation Instances for orgA1
        $this->monthlyInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->monthlyDef->id,
            'status' => 'IN_PROGRESS',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'MONTHLY',
        ]);

        $this->quarterlyInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->quarterlyDef->id,
            'status' => 'IN_PROGRESS',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'QUARTERLY',
        ]);

        $this->annualInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->annualDef->id,
            'status' => 'IN_PROGRESS',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'ANNUAL',
        ]);

        $this->unverifiedInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->unverifiedDef->id,
            'status' => 'NOT_STARTED',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'MONTHLY',
        ]);
    }

    /**
     * 1. Monthly occurrence generation.
     */
    public function test_1_monthly_occurrence_generation(): void
    {
        $res = $this->generator->generateForObligationInstance(
            instance: $this->monthlyInstance,
            anchorDate: '2026-01-01',
            horizonMonths: 12
        );

        $this->assertEquals(12, $res['generated_count']);

        $occurrences = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->orderBy('filing_period_start')
            ->get();

        $this->assertCount(12, $occurrences);

        // Jan 2026 period (2026-01-01 to 2026-01-31) -> due 2026-02-10
        $jan = $occurrences->first();
        $this->assertEquals('2026-01-01', $jan->filing_period_start->toDateString());
        $this->assertEquals('2026-01-31', $jan->filing_period_end->toDateString());
        $this->assertEquals('2026-02-10', $jan->current_due_date->toDateString());
        $this->assertEquals('VERIFIED_OFFICIAL', $jan->verification_status);
        $this->assertEquals('Africa/Harare', $jan->timezone);
    }

    /**
     * 2. Quarterly occurrence generation.
     */
    public function test_2_quarterly_occurrence_generation(): void
    {
        $res = $this->generator->generateForObligationInstance(
            instance: $this->quarterlyInstance,
            anchorDate: '2026-01-01',
            horizonMonths: 12
        );

        $this->assertEquals(4, $res['generated_count']);

        $occurrences = FilingOccurrence::where('obligation_instance_id', $this->quarterlyInstance->id)
            ->orderBy('filing_period_start')
            ->get();

        $this->assertCount(4, $occurrences);

        // Q1 2026 (2026-01-01 to 2026-03-31) -> due 2026-04-20
        $this->assertEquals('2026-01-01', $occurrences[0]->filing_period_start->toDateString());
        $this->assertEquals('2026-03-31', $occurrences[0]->filing_period_end->toDateString());
        $this->assertEquals('2026-04-20', $occurrences[0]->current_due_date->toDateString());
        $this->assertEquals('Q1 2026', $occurrences[0]->filing_period_label);

        // Q4 2026 (2026-10-01 to 2026-12-31) -> due 2027-01-20
        $this->assertEquals('2026-10-01', $occurrences[3]->filing_period_start->toDateString());
        $this->assertEquals('2026-12-31', $occurrences[3]->filing_period_end->toDateString());
        $this->assertEquals('2027-01-20', $occurrences[3]->current_due_date->toDateString());
    }

    /**
     * 3. Annual occurrence generation.
     */
    public function test_3_annual_occurrence_generation(): void
    {
        $res = $this->generator->generateForObligationInstance(
            instance: $this->annualInstance,
            anchorDate: '2026-01-01',
            horizonMonths: 24
        );

        $this->assertEquals(2, $res['generated_count']);

        $occurrences = FilingOccurrence::where('obligation_instance_id', $this->annualInstance->id)
            ->orderBy('filing_period_start')
            ->get();

        $this->assertCount(2, $occurrences);
        // FY 2026 (2026-01-01 to 2026-12-31) -> due 2027-04-30
        $this->assertEquals('2026-01-01', $occurrences[0]->filing_period_start->toDateString());
        $this->assertEquals('2026-12-31', $occurrences[0]->filing_period_end->toDateString());
        $this->assertEquals('2027-04-30', $occurrences[0]->current_due_date->toDateString());
        $this->assertEquals('FY 2026', $occurrences[0]->filing_period_label);
    }

    /**
     * 4. Leap-year handling (e.g. February 2028 has 29 days vs February 2026 has 28 days).
     */
    public function test_4_leap_year_handling(): void
    {
        // Rule with due_day = 31 on monthly schedule -> clamps to 29 Feb in leap year 2028, 28 Feb in 2026
        $this->syntheticMonthlyRule->update([
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'MONTHLY',
                    'due_day' => 31,
                    'month_offset' => 1,
                    'weekend_adjustment' => 'NONE',
                ],
            ],
        ]);

        // Jan 2028 filing period -> due in Feb 2028 (Leap year: 2028-02-29)
        $calcLeap = $this->calculator->calculate(
            definition: $this->monthlyDef,
            ruleVersion: $this->syntheticMonthlyRule,
            periodStart: '2028-01-01',
            periodEnd: '2028-01-31'
        );
        $this->assertEquals('2028-02-29', $calcLeap['current_due_date']);

        // Feb 2028 period end boundary via generator
        $periods2028 = $this->generator->buildFilingPeriods('MONTHLY', Carbon::parse('2028-02-01'), 1);
        $this->assertEquals('2028-02-01', $periods2028[0]['start']->toDateString());
        $this->assertEquals('2028-02-29', $periods2028[0]['end']->toDateString());

        // Jan 2026 filing period -> due in Feb 2026 (Non-leap year: 2026-02-28)
        $calcNonLeap = $this->calculator->calculate(
            definition: $this->monthlyDef,
            ruleVersion: $this->syntheticMonthlyRule,
            periodStart: '2026-01-01',
            periodEnd: '2026-01-31'
        );
        $this->assertEquals('2026-02-28', $calcNonLeap['current_due_date']);
    }

    /**
     * 5. Year-end transitions (December 2026 period due in January 2027).
     */
    public function test_5_year_end_transitions(): void
    {
        $calcDec = $this->calculator->calculate(
            definition: $this->monthlyDef,
            ruleVersion: $this->syntheticMonthlyRule,
            periodStart: '2026-12-01',
            periodEnd: '2026-12-31'
        );

        $this->assertTrue($calcDec['is_verified']);
        $this->assertEquals('2026-12-01', $calcDec['filing_period_start']);
        $this->assertEquals('2026-12-31', $calcDec['filing_period_end']);
        $this->assertEquals('2027-01-10', $calcDec['current_due_date']);
    }

    /**
     * 6. Rule effective-date selection (including rule amendment partway through a year).
     */
    public function test_6_rule_effective_date_selection_mid_year(): void
    {
        // V1 effective 2026-01-01 to 2026-06-30 (due_day = 10)
        $this->syntheticMonthlyRule->update([
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);

        // V2 effective 2026-07-01 onwards (due_day = 15)
        $v2Rule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticMonthlyPack->id,
            'source_id' => $this->syntheticNssaSource->id,
            'version_code' => 'SYNTHETIC_MONTHLY_2026_V2',
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'MONTHLY',
                    'due_day' => 15,
                    'month_offset' => 1,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $this->generator->generateForObligationInstance(
            instance: $this->monthlyInstance,
            anchorDate: '2026-05-01',
            horizonMonths: 4
        );

        $mayOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-05-01')
            ->firstOrFail();
        $this->assertEquals($this->syntheticMonthlyRule->id, $mayOcc->rule_version_id);
        $this->assertEquals('2026-06-10', $mayOcc->current_due_date->toDateString());

        $julyOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-07-01')
            ->firstOrFail();
        $this->assertEquals($v2Rule->id, $julyOcc->rule_version_id);
        $this->assertEquals('2026-08-15', $julyOcc->current_due_date->toDateString());
    }

    /**
     * 7. Unverified deadline handling (no invented due dates; never classified as overdue).
     */
    public function test_7_unverified_deadline_handling(): void
    {
        $res = $this->generator->generateForObligationInstance(
            instance: $this->unverifiedInstance,
            anchorDate: '2026-01-01',
            horizonMonths: 3
        );

        $this->assertEquals(3, $res['generated_count']);
        $this->assertEquals(3, $res['unverified_count']);

        $occ = FilingOccurrence::where('obligation_instance_id', $this->unverifiedInstance->id)->firstOrFail();
        $this->assertNull($occ->current_due_date);
        $this->assertNull($occ->original_statutory_due_date);
        $this->assertEquals('UNVERIFIED_HOLD', $occ->verification_status);
        $this->assertTrue($occ->isDeadlineUnverified());

        // Even if evaluated far in the future (e.g. 2027), classification is DEADLINE_UNVERIFIED, never OVERDUE!
        $classified = $this->classifier->classify($occ, '2027-06-01');
        $this->assertEquals(FilingDisplayClassifier::DEADLINE_UNVERIFIED, $classified['classification']);
        $this->assertNotEquals(FilingDisplayClassifier::OVERDUE, $classified['classification']);
    }

    /**
     * 8. Idempotent generation (repeated runs do not create duplicates).
     */
    public function test_8_idempotent_generation(): void
    {
        $first = $this->generator->generateForOrganisation($this->orgA1, '2026-01-01', 6);
        $this->assertGreaterThan(0, $first['generated_count']);

        $totalCountAfterFirst = FilingOccurrence::where('organisation_id', $this->orgA1->id)->count();

        $second = $this->generator->generateForOrganisation($this->orgA1, '2026-01-01', 6);
        $this->assertEquals(0, $second['generated_count']);
        $this->assertEquals($totalCountAfterFirst, $second['skipped_existing_count']);

        $this->assertEquals($totalCountAfterFirst, FilingOccurrence::where('organisation_id', $this->orgA1->id)->count());
    }

    /**
     * 9. Concurrent generation & unique database constraint protection.
     */
    public function test_9_concurrent_generation_and_unique_constraint(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-01-01', 1);
        $existing = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        // Attempting to directly insert a duplicate row for the same org, obligation_instance, period start/end, and schedule_identity must fail at DB level
        $this->expectException(\Illuminate\Database\QueryException::class);

        FilingOccurrence::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'obligation_instance_id' => $this->monthlyInstance->id,
            'obligation_definition_id' => $this->monthlyDef->id,
            'schedule_identity' => $existing->schedule_identity,
            'filing_frequency' => 'MONTHLY',
            'filing_period_start' => $existing->filing_period_start->toDateString(),
            'filing_period_end' => $existing->filing_period_end->toDateString(),
        ]);
    }

    /**
     * 10. Cross-tenant isolation.
     */
    public function test_10_cross_tenant_isolation(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 2);
        $occA = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        // Tenant B user cannot view Tenant A's filing detail
        $resShow = $this->actingAs($this->tenantBUser)->getJson("/compliance/filings/{$occA->id}");
        $resShow->assertStatus(403);

        // Tenant B user cannot transition Tenant A's filing occurrence
        $resTrans = $this->actingAs($this->tenantBUser)->post("/compliance/filings/{$occA->id}/transition", [
            'status' => 'PREPARING',
        ]);
        $resTrans->assertSessionHasErrors(['authorization']);

        $occA->refresh();
        $this->assertEquals('NOT_STARTED', $occA->workflow_status);
    }

    /**
     * 11. Same-tenant cross-organisation isolation.
     */
    public function test_11_same_tenant_cross_organisation_isolation(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 2);
        $occA1 = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        // orgA2Admin belongs to Tenant A, but only has membership in orgA2 (not orgA1)
        $resShow = $this->actingAs($this->orgA2Admin)->getJson("/compliance/filings/{$occA1->id}");
        $resShow->assertStatus(403);

        $resAssign = $this->actingAs($this->orgA2Admin)->post("/compliance/filings/{$occA1->id}/assign", [
            'assigned_to_user_id' => $this->orgA2Admin->id,
        ]);
        $resAssign->assertSessionHasErrors(['authorization']);

        // Even orgA1 Admin cannot assign an orgA1 filing occurrence to orgA2Admin
        $resCrossAssign = $this->actingAs($this->adminUser)->post("/compliance/filings/{$occA1->id}/assign", [
            'assigned_to_user_id' => $this->orgA2Admin->id,
        ]);
        $resCrossAssign->assertSessionHasErrors(['authorization']);
    }

    /**
     * 12. Filing owner assignment.
     */
    public function test_12_filing_owner_assignment(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 1);
        $occ = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        $res = $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/assign", [
            'assigned_to_user_id' => $this->staffUser->id,
            'lock_version' => 1,
            'notes' => 'Assigned October 2026 P4 return preparation to Tafadzwa.',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $occ->refresh();
        $this->assertEquals($this->staffUser->id, $occ->assigned_to_user_id);
        $this->assertEquals(2, $occ->lock_version);

        $this->assertDatabaseHas('filing_occurrence_activity_logs', [
            'filing_occurrence_id' => $occ->id,
            'activity_type' => 'ASSIGNMENT_CHANGED',
            'to_user_id' => $this->staffUser->id,
        ]);
    }

    /**
     * 13. Filing status transitions & audit log recording.
     */
    public function test_13_filing_status_transitions(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 1);
        $occ = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        // 1. NOT_STARTED -> PREPARING (by staff)
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'PREPARING',
            'notes' => 'Compiling payroll schedule for Oct 2026.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('PREPARING', $occ->workflow_status);

        // 2. PREPARING -> READY_FOR_REVIEW (by staff)
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'READY_FOR_REVIEW',
            'notes' => 'Schedule reconciled; ready for compliance officer check.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('READY_FOR_REVIEW', $occ->workflow_status);

        // 3. READY_FOR_REVIEW -> INTERNALLY_APPROVED (by admin)
        $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'INTERNALLY_APPROVED',
            'notes' => 'Figures verified against October payroll.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('INTERNALLY_APPROVED', $occ->workflow_status);
        $this->assertNotNull($occ->internally_approved_at);

        // 4. INTERNALLY_APPROVED -> SUBMITTED (with submission reference)
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'SUBMITTED',
            'submission_reference' => 'NSSA-PORTAL-SUB-8821',
            'notes' => 'Uploaded return on NSSA self-service portal.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('SUBMITTED', $occ->workflow_status);
        $this->assertEquals('DECLARED_SUBMITTED', $occ->official_submission_status);
        $this->assertEquals('NSSA-PORTAL-SUB-8821', $occ->submission_reference);
    }

    /**
     * 14. Unauthorised transitions (regular member cannot approve internally, acknowledge officially, or close).
     */
    public function test_14_unauthorised_transitions(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 1);
        $occ = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        // Regular staff member cannot assign owners
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/assign", [
            'assigned_to_user_id' => $this->staffUser->id,
        ])->assertSessionHasErrors(['authorization']);

        // Move to READY_FOR_REVIEW
        $occ->update(['workflow_status' => 'READY_FOR_REVIEW']);

        // Regular staff member cannot transition to INTERNALLY_APPROVED
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'INTERNALLY_APPROVED',
        ])->assertSessionHasErrors(['authorization']);

        $occ->refresh();
        $this->assertEquals('READY_FOR_REVIEW', $occ->workflow_status);

        // Regular staff member cannot close an occurrence
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'CLOSED',
            'notes' => 'Trying to close without admin rights.',
        ])->assertSessionHasErrors(['transition']);
    }

    /**
     * 15. Dynamic overdue, due-today, due-soon, upcoming, and completed classification.
     */
    public function test_15_dynamic_overdue_and_display_classification(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 3);
        $occurrences = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->orderBy('filing_period_start')
            ->get();

        // Oct 2026 due date = 2026-11-10
        $octOcc = $occurrences[0];

        // 1. As of 2026-10-15 (26 days before due date) -> UPCOMING
        $c1 = $this->classifier->classify($octOcc, '2026-10-15', 14);
        $this->assertEquals(FilingDisplayClassifier::UPCOMING, $c1['classification']);

        // 2. As of 2026-11-03 (7 days before due date, within 14-day window) -> DUE_SOON
        $c2 = $this->classifier->classify($octOcc, '2026-11-03', 14);
        $this->assertEquals(FilingDisplayClassifier::DUE_SOON, $c2['classification']);

        // 3. As of 2026-11-10 (exact due date) -> DUE_TODAY
        $c3 = $this->classifier->classify($octOcc, '2026-11-10', 14);
        $this->assertEquals(FilingDisplayClassifier::DUE_TODAY, $c3['classification']);

        // 4. As of 2026-11-15 (5 days past due date) -> OVERDUE
        $c4 = $this->classifier->classify($octOcc, '2026-11-15', 14);
        $this->assertEquals(FilingDisplayClassifier::OVERDUE, $c4['classification']);
        $this->assertEquals(-5, $c4['days_delta']);

        // 5. Once marked SUBMITTED or ACKNOWLEDGED -> COMPLETED
        $octOcc->update(['workflow_status' => 'SUBMITTED']);
        $c5 = $this->classifier->classify($octOcc, '2026-11-15', 14);
        $this->assertEquals(FilingDisplayClassifier::COMPLETED, $c5['classification']);
    }

    /**
     * 16. Source citation preservation.
     */
    public function test_16_source_citation_preservation(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 1);
        $occ = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        $this->assertEquals('SYNTHETIC_FIXTURE: NSSA Act [Chapter 17:04], SI 393/1993', $occ->source_citation);
        $this->assertEquals($this->syntheticMonthlyRule->id, $occ->rule_version_id);
        $this->assertEquals(
            'SYNTHETIC_MONTHLY_2026_V1',
            $occ->deadline_calculation_basis['rule_version_code']
        );
    }

    /**
     * 17. Filing-specific evidence (evidence linked to Oct 2026 does NOT satisfy Nov 2026).
     */
    public function test_17_filing_specific_evidence_isolation_across_periods(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 2);
        $occurrences = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->orderBy('filing_period_start')
            ->get();

        $octOcc = $occurrences[0]; // Oct 2026
        $novOcc = $occurrences[1]; // Nov 2026

        // Upload & link a document to October 2026 occurrence
        $octDoc = ComplianceDocument::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'obligation_instance_id' => $this->monthlyInstance->id,
            'uploaded_by_user_id' => $this->staffUser->id,
            'document_type' => 'NSSA_REGISTRATION',
            'title' => 'Oct 2026 NSSA P4 Receipt',
            'file_name' => 'oct_2026_p4.pdf',
            'file_path' => 'tenants/test/oct_p4.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'sha256_hash' => hash('sha256', 'oct-2026-content'),
            'verification_status' => 'PENDING',
        ]);

        $this->actingAs($this->staffUser)->post("/compliance/filings/{$octOcc->id}/evidence/link", [
            'document_id' => $octDoc->id,
        ])->assertRedirect();

        $octOcc->refresh();
        $novOcc->refresh();

        // Oct 2026 has DOCUMENT_UPLOADED; Nov 2026 still has NONE
        $this->assertEquals('DOCUMENT_UPLOADED', $octOcc->evidence_status);
        $this->assertEquals('NONE', $novOcc->evidence_status);
        $this->assertCount(1, $octOcc->documents);
        $this->assertCount(0, $novOcc->documents);
    }

    /**
     * 18. Historical immutability (completed or historical periods are never overwritten when rules change).
     */
    public function test_18_historical_immutability_on_regeneration(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-01-01', 3);

        $janOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-01-01')
            ->firstOrFail();

        // Complete Jan 2026 filing
        $janOcc->update([
            'workflow_status' => 'SUBMITTED',
            'official_submission_status' => 'DECLARED_SUBMITTED',
            'submission_reference' => 'JAN-2026-REF',
        ]);

        // Even if the rule version parameters are modified and generator is re-run, Jan 2026 is untouched
        $newRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticMonthlyPack->id,
            'source_id' => $this->syntheticNssaSource->id,
            'version_code' => 'SYNTHETIC_MONTHLY_2026_AMENDED',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'MONTHLY',
                    'due_day' => 25,
                    'month_offset' => 1,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-02-01', 2);

        $janOcc->refresh();
        // Jan 2026 retains original due date (2026-02-10), original rule_version_id, and SUBMITTED status
        $this->assertEquals('2026-02-10', $janOcc->current_due_date->toDateString());
        $this->assertEquals($this->syntheticMonthlyRule->id, $janOcc->rule_version_id);
        $this->assertEquals('SUBMITTED', $janOcc->workflow_status);
    }

    /**
     * 19. Regulatory amendment handling (future unstarted occurrences safely update to new approved rule version).
     */
    public function test_19_regulatory_amendment_updates_future_unstarted_occurrences(): void
    {
        // Generate Jan - Jun 2026 under V1 (due_day = 10)
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-01-01', 6);

        // Complete Jan 2026
        $janOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-01-01')
            ->firstOrFail();
        $janOcc->update(['workflow_status' => 'SUBMITTED']);

        // Start preparing Feb 2026
        $febOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-02-01')
            ->firstOrFail();
        $febOcc->update(['workflow_status' => 'PREPARING']);

        // Now a regulatory amendment is enacted effective 2026-03-01 changing due_day from 10 to 18
        $this->syntheticMonthlyRule->update([
            'effective_to' => '2026-02-28',
        ]);

        $amendedRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticMonthlyPack->id,
            'source_id' => $this->syntheticNssaSource->id,
            'version_code' => 'SYNTHETIC_MONTHLY_2026_V2_AMENDED',
            'effective_from' => '2026-03-01',
            'effective_to' => null,
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'MONTHLY',
                    'due_day' => 18,
                    'month_offset' => 1,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        // Regenerate from March 2026 onwards
        $regen = $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-03-01', 4);
        $this->assertEquals(4, $regen['updated_count']);

        // Verify Jan & Feb were NOT overwritten
        $janOcc->refresh();
        $febOcc->refresh();
        $this->assertEquals('2026-02-10', $janOcc->current_due_date->toDateString());
        $this->assertEquals('2026-03-10', $febOcc->current_due_date->toDateString());

        // Verify March 2026 (future unstarted) was updated to amended rule (2026-04-18) while preserving original_statutory_due_date (2026-04-10)
        $marOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-03-01')
            ->firstOrFail();
        $this->assertEquals($amendedRule->id, $marOcc->rule_version_id);
        $this->assertEquals('2026-04-18', $marOcc->current_due_date->toDateString());
        $this->assertEquals('2026-04-10', $marOcc->original_statutory_due_date->toDateString());
    }

    /**
     * 20. No automatic official acknowledgement (user submission reference != official acceptance;
     *     ACKNOWLEDGED requires human-VERIFIED period-specific evidence).
     */
    public function test_20_no_automatic_official_acknowledgement_without_verified_period_evidence(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 1);
        $occ = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        $occ->update(['workflow_status' => 'INTERNALLY_APPROVED']);

        // 1. User submits with a reference number -> status becomes SUBMITTED and official status becomes DECLARED_SUBMITTED (NOT OFFICIALLY_ACKNOWLEDGED)
        $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'SUBMITTED',
            'submission_reference' => 'USER-ENTERED-REF-999',
            'notes' => 'Submitted via portal.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('SUBMITTED', $occ->workflow_status);
        $this->assertEquals('DECLARED_SUBMITTED', $occ->official_submission_status);
        $this->assertFalse($occ->isOfficiallyAcknowledged());

        // 2. Attempting to transition to ACKNOWLEDGED without verified period-specific evidence MUST fail
        $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'ACKNOWLEDGED',
            'official_receipt_number' => 'FAKE-RECEIPT-001',
        ])->assertSessionHasErrors(['transition']);

        $occ->refresh();
        $this->assertEquals('SUBMITTED', $occ->workflow_status);
        $this->assertFalse($occ->isOfficiallyAcknowledged());

        // 3. Upload a PENDING (unreviewed) document and link it -> still cannot transition to ACKNOWLEDGED
        $doc = ComplianceDocument::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'obligation_instance_id' => $this->monthlyInstance->id,
            'filing_occurrence_id' => $occ->id,
            'uploaded_by_user_id' => $this->staffUser->id,
            'document_type' => 'NSSA_REGISTRATION',
            'title' => 'Official NSSA Receipt Oct 2026',
            'file_name' => 'nssa_receipt_oct2026.pdf',
            'file_path' => 'tenants/test/nssa_receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'sha256_hash' => hash('sha256', 'nssa-oct-receipt'),
            'verification_status' => 'PENDING',
        ]);

        $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'ACKNOWLEDGED',
        ])->assertSessionHasErrors(['transition']);

        // 4. Human reviewer approves/verifies the period-specific document -> does NOT automatically mark ACKNOWLEDGED
        $doc->update([
            'verification_status' => 'VERIFIED',
            'verified_by_user_id' => $this->adminUser->id,
            'verified_at' => Carbon::now(),
        ]);

        $occ->refresh();
        $this->assertEquals('SUBMITTED', $occ->workflow_status);

        // 5. Now admin explicitly transitions to ACKNOWLEDGED -> succeeds!
        $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/transition", [
            'status' => 'ACKNOWLEDGED',
            'official_receipt_number' => 'NSSA-REC-2026-88412',
            'notes' => 'Official NSSA receipt verified against bank statement and portal acknowledgement.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('ACKNOWLEDGED', $occ->workflow_status);
        $this->assertEquals('OFFICIALLY_ACKNOWLEDGED', $occ->official_submission_status);
        $this->assertEquals('OFFICIALLY_ACKNOWLEDGED', $occ->evidence_status);
        $this->assertTrue($occ->isOfficiallyAcknowledged());
    }

    /**
     * 21. Fixed-date, Event-driven schedules, weekend adjustment policy, and filing vs payment period distinction.
     */
    public function test_21_fixed_date_event_driven_weekend_adjustment_and_payment_vs_filing_distinction(): void
    {
        // A. Fixed-date rule with weekend adjustment and distinct payment_due_day
        $fixedRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticAnnualPack->id,
            'source_id' => $this->syntheticNssaSource->id,
            'version_code' => 'SYNTHETIC_FIXED_DATE_2026',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'FIXED_DATE',
                    'fixed_due_date' => '2026-06-13', // Saturday
                    'weekend_adjustment' => 'NEXT_BUSINESS_DAY',
                    'payment_due_day' => 25,
                    'payment_month_offset' => 1,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $fixedResult = $this->calculator->calculate(
            $this->annualInstance->definition,
            $fixedRule,
            '2026-01-01',
            '2026-05-31'
        );
        $this->assertEquals('VERIFIED_OFFICIAL', $fixedResult['verification_status']);
        // 2026-06-13 is Saturday -> NEXT_BUSINESS_DAY shifts to Monday 2026-06-15
        $this->assertEquals('2026-06-13', $fixedResult['original_statutory_due_date']);
        $this->assertEquals('2026-06-15', $fixedResult['current_due_date']);
        $this->assertTrue($fixedResult['deadline_calculation_basis']['weekend_adjusted']);

        // B. Event-driven rule (e.g. 30 days after triggering event)
        $eventRule = RuleVersion::create([
            'rule_pack_id' => $this->syntheticAnnualPack->id,
            'source_id' => $this->syntheticNssaSource->id,
            'version_code' => 'SYNTHETIC_EVENT_DRIVEN_2026',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'filing_schedule' => [
                    'frequency' => 'EVENT_DRIVEN',
                    'days_after_event' => 30,
                    'weekend_adjustment' => 'NONE',
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        // Missing trigger date -> DEADLINE_UNVERIFIED
        $missingTrigger = $this->calculator->calculate(
            $this->annualInstance->definition,
            $eventRule,
            '2026-04-01',
            '2026-04-01'
        );
        $this->assertEquals('DEADLINE_UNVERIFIED', $missingTrigger['verification_status']);
        $this->assertNull($missingTrigger['current_due_date']);

        // With trigger event_date 2026-04-01 -> +30 days = 2026-05-01
        $withTrigger = $this->calculator->calculate(
            $this->annualInstance->definition,
            $eventRule,
            '2026-04-01',
            '2026-04-01',
            ['event_date' => '2026-04-01']
        );
        $this->assertEquals('VERIFIED_OFFICIAL', $withTrigger['verification_status']);
        $this->assertEquals('2026-05-01', $withTrigger['current_due_date']);
    }

    /**
     * 22. Inapplicable obligation handling (closes future unstarted occurrences without deleting completed historical ones).
     */
    public function test_22_inapplicable_obligation_closes_future_unstarted_and_preserves_historical(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-01-01', 4);

        $janOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-01-01')
            ->firstOrFail();
        $janOcc->update(['workflow_status' => 'SUBMITTED']);

        // Mark obligation instance NOT_APPLICABLE
        $this->monthlyInstance->update(['status' => 'NOT_APPLICABLE']);

        $result = $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-01-01', 4);
        $this->assertEquals(3, $result['inapplicable_handled_count']);

        $janOcc->refresh();
        $this->assertEquals('SUBMITTED', $janOcc->workflow_status);

        $febOcc = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)
            ->whereDate('filing_period_start', '2026-02-01')
            ->firstOrFail();
        $this->assertEquals('CLOSED', $febOcc->workflow_status);
    }

    /**
     * 23. Authorised due-date correction workflow (requires legal basis, preserves original_statutory_due_date, logs audit).
     */
    public function test_23_authorised_due_date_correction_preserves_original_due_date(): void
    {
        $this->generator->generateForObligationInstance($this->monthlyInstance, '2026-10-01', 1);
        $occ = FilingOccurrence::where('obligation_instance_id', $this->monthlyInstance->id)->firstOrFail();

        // Staff cannot override due date
        $this->actingAs($this->staffUser)->post("/compliance/filings/{$occ->id}/amend-due-date", [
            'new_due_date' => '2026-11-20',
            'legal_basis_citation' => 'SYNTHETIC_EXTENSION_NOTICE_2026',
            'justification_notes' => 'Extension attempted by staff.',
        ])->assertSessionHasErrors(['authorization']);

        // Admin can record an audited correction with legal basis
        $this->actingAs($this->adminUser)->post("/compliance/filings/{$occ->id}/amend-due-date", [
            'new_due_date' => '2026-11-20',
            'legal_basis_citation' => 'SYNTHETIC_FIXTURE: NSSA General Notice Extension 2026',
            'justification_notes' => 'Statutory extension granted by authority notice.',
        ])->assertRedirect();

        $occ->refresh();
        $this->assertEquals('2026-11-10', $occ->original_statutory_due_date->toDateString());
        $this->assertEquals('2026-11-20', $occ->current_due_date->toDateString());

        $this->assertDatabaseHas('filing_occurrence_activity_logs', [
            'filing_occurrence_id' => $occ->id,
            'activity_type' => 'DUE_DATE_AMENDED',
        ]);
    }

    /**
     * 24. Compliance Calendar UI Inertia page rendering and filters.
     */
    public function test_24_compliance_calendar_inertia_workspace_renders_with_filters(): void
    {
        $this->generator->generateForOrganisation($this->orgA1, '2026-10-01', 3);

        $res = $this->actingAs($this->adminUser)
            ->get("/comply/calendar?organisation_id={$this->orgA1->id}&month=2026-11");

        $res->assertStatus(200);
        $res->assertInertia(fn ($page) => $page
            ->component('Comply/Calendar')
            ->has('occurrences')
            ->has('metrics')
            ->has('authorities')
            ->has('obligations')
            ->has('members')
            ->where('filters.month', '2026-11')
        );
    }
}
