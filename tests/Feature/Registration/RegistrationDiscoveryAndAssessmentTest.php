<?php

namespace Tests\Feature\Registration;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\RegistrationEngine\Models\ApplicabilityAssessment;
use App\Domains\RegistrationEngine\Models\AssessmentResult;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire;
use App\Models\User;
use Database\Seeders\RegulatoryCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RegistrationDiscoveryAndAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Organisation $orgA;
    protected Organisation $orgB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegulatoryCatalogSeeder::class);

        // Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'Apex Engineering Group',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $this->userA = User::create([
            'name' => 'Tinashe Moyo',
            'email' => 'tinashe@apex.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->orgA = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Apex Industrial Solutions (Pvt) Ltd',
            'trading_name' => 'Apex Engineering',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'organisation_id' => $this->orgA->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        // Tenant B (for cross-tenant security verification)
        $this->tenantB = Tenant::create([
            'name' => 'Kalahari Minerals Ltd',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $this->userB = User::create([
            'name' => 'Chipo Mutasa',
            'email' => 'chipo@kalahari.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->orgB = Organisation::create([
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Kalahari Minerals (Pvt) Ltd',
            'trading_name' => 'Kalahari Mining',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'organisation_id' => $this->orgB->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_authenticated_user_can_view_registration_workspace(): void
    {
        $response = $this->actingAs($this->userA)->get('/registration');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Register/Index')
            ->has('sectors')
            ->has('jurisdictions')
        );
    }

    public function test_save_draft_persists_incomplete_questionnaire_progress(): void
    {
        $this->actingAs($this->userA);

        $payload = [
            'current_step' => 2,
            'answers' => [
                'business_mode' => 'EXISTING',
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'is_already_incorporated' => true,
                'company_registration_number' => 'COBE-2023-9941',
                'industry_sector' => 'ENGINEERING',
                'annual_turnover_usd' => 50000,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/save', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'questionnaire' => [
                'current_step' => 2,
                'status' => 'DRAFT',
            ],
        ]);

        $this->assertDatabaseHas('registration_questionnaires', [
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'current_step' => 2,
            'status' => 'DRAFT',
        ]);
    }

    public function test_user_can_resume_saved_questionnaire_draft(): void
    {
        RegistrationQuestionnaire::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'status' => 'DRAFT',
            'current_step' => 3,
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'is_already_incorporated' => true,
                'company_registration_number' => 'COBE-2023-9941',
                'employee_count' => 8,
            ],
        ]);

        $response = $this->actingAs($this->userA)->get('/registration');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Register/Index')
            ->where('questionnaire.current_step', 3)
            ->where('questionnaire.status', 'DRAFT')
            ->where('questionnaire.answers.employee_count', 8)
        );
    }

    public function test_submission_validates_required_business_profile_fields(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postJson('/registration/questionnaire/submit', [
            'answers' => [
                'entity_type' => '',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'answers.entity_type',
            'answers.is_already_incorporated',
            'answers.industry_sector',
            'answers.confirmation_accurate',
        ]);
    }

    public function test_submission_executes_deterministic_engine_and_generates_roadmap(): void
    {
        $this->actingAs($this->userA);

        $payload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'business_mode' => 'NEW',
                'is_already_incorporated' => false,
                'industry_sector' => 'ENGINEERING',
                'annual_turnover_usd' => 45000,
                'employee_count' => 6,
                'has_physical_premises' => true,
                'jurisdiction_code' => 'ZW-HARARE',
                'bids_on_government_tenders' => true,
                'confirmation_accurate' => true,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/submit', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $assessmentId = $response->json('assessment.assessment_id');
        $this->assertNotNull($assessmentId);

        // Verify topological sequencing: CIPZ_INCORP must be #1
        $roadmap = $response->json('assessment.roadmap');
        $this->assertNotEmpty($roadmap);
        $this->assertSame('CIPZ_INCORP', $roadmap[0]['obligation_code']);
        $this->assertSame(1, $roadmap[0]['sequence_number']);

        // Verify database persistence
        $this->assertDatabaseHas('applicability_assessments', [
            'id' => $assessmentId,
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'assessment_type' => 'INITIAL_REGISTRATION',
        ]);

        $this->assertDatabaseHas('registration_questionnaires', [
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'status' => 'COMPLETED',
        ]);
    }

    public function test_user_declared_registration_does_not_confer_compliant_status(): void
    {
        $this->actingAs($this->userA);

        $payload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'business_mode' => 'EXISTING',
                'is_already_incorporated' => true,
                'company_registration_number' => 'COBE-2023-48192',
                'bp_number' => 'BP-020038914',
                'industry_sector' => 'ENGINEERING',
                'annual_turnover_usd' => 30000,
                'employee_count' => 5,
                'nssa_employer_number' => 'NSSA-ER-89412',
                'has_physical_premises' => true,
                'jurisdiction_code' => 'ZW-HARARE',
                'confirmation_accurate' => true,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/submit', $payload);
        $response->assertStatus(200);

        // Verify obligation instances were NOT marked COMPLIANT
        $instances = ObligationInstance::where('organisation_id', $this->orgA->id)->get();
        $this->assertNotEmpty($instances);

        foreach ($instances as $inst) {
            $this->assertNotSame('COMPLIANT', $inst->status, "Instance [{$inst->definition_id}] was marked compliant without verification!");
            if (!empty($inst->declared_registration_number)) {
                $this->assertSame('USER_DECLARED', $inst->evidence_status);
                $this->assertSame('AWAITING_VERIFICATION', $inst->status);
            }
        }
    }

    public function test_reassessment_creates_new_assessment_snapshot_and_preserves_history(): void
    {
        $this->actingAs($this->userA);

        // 1. Initial submission
        $initialPayload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'is_already_incorporated' => false,
                'industry_sector' => 'ENGINEERING',
                'annual_turnover_usd' => 15000, // Below $25k VAT threshold
                'employee_count' => 2,
                'has_physical_premises' => true,
                'jurisdiction_code' => 'ZW-HARARE',
                'bids_on_government_tenders' => false,
                'confirmation_accurate' => true,
            ],
        ];

        $res1 = $this->postJson('/registration/questionnaire/submit', $initialPayload);
        $res1->assertStatus(200);
        $initialId = $res1->json('assessment.assessment_id');

        // 2. Business expands: turnover increases to $60,000 (compulsory VAT triggered) and begins public tenders
        $updatedPayload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'is_already_incorporated' => true,
                'company_registration_number' => 'COBE-2023-8821',
                'industry_sector' => 'ENGINEERING',
                'annual_turnover_usd' => 60000, // Compulsory VAT!
                'employee_count' => 10,
                'has_physical_premises' => true,
                'jurisdiction_code' => 'ZW-HARARE',
                'bids_on_government_tenders' => true, // PRAZ triggered!
                'confirmation_accurate' => true,
            ],
        ];

        $res2 = $this->postJson('/registration/reassess', $updatedPayload);
        $res2->assertStatus(200);
        $reassessmentId = $res2->json('assessment.assessment_id');

        $this->assertNotSame($initialId, $reassessmentId);

        // Historical preservation: Both assessment records exist in database
        $this->assertDatabaseHas('applicability_assessments', [
            'id' => $initialId,
            'assessment_type' => 'INITIAL_REGISTRATION',
        ]);

        $this->assertDatabaseHas('applicability_assessments', [
            'id' => $reassessmentId,
            'assessment_type' => 'REASSESSMENT',
        ]);

        $count = ApplicabilityAssessment::where('organisation_id', $this->orgA->id)->count();
        $this->assertSame(2, $count);
    }

    public function test_concurrent_submissions_are_safeguarded(): void
    {
        $this->actingAs($this->userA);

        // Artificially acquire lock
        $lockKey = "assessment_lock_{$this->tenantA->id}_{$this->orgA->id}";
        $lock = Cache::lock($lockKey, 30);
        $lock->get();

        $payload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Apex Industrial Solutions (Pvt) Ltd',
                'is_already_incorporated' => false,
                'industry_sector' => 'ENGINEERING',
                'annual_turnover_usd' => 30000,
                'employee_count' => 3,
                'has_physical_premises' => true,
                'jurisdiction_code' => 'ZW-HARARE',
                'confirmation_accurate' => true,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/submit', $payload);
        $response->assertStatus(429);
        $response->assertJsonStructure(['error']);

        $lock->release();
    }

    public function test_cross_tenant_access_is_strictly_prevented(): void
    {
        // Create an assessment under Tenant B
        $qB = RegistrationQuestionnaire::create([
            'tenant_id' => $this->tenantB->id,
            'organisation_id' => $this->orgB->id,
            'status' => 'COMPLETED',
            'answers' => ['entity_type' => 'PBC'],
            'current_step' => 5,
        ]);

        $assessmentB = ApplicabilityAssessment::create([
            'tenant_id' => $this->tenantB->id,
            'organisation_id' => $this->orgB->id,
            'questionnaire_id' => $qB->id,
            'assessment_type' => 'INITIAL_REGISTRATION',
            'evaluation_inputs' => ['entity_type' => 'PBC'],
            'generated_at' => now(),
        ]);

        // Tenant A user attempts to view Tenant B's assessment
        $this->actingAs($this->userA);
        $response = $this->getJson("/registration/assessments/{$assessmentB->id}");

        // Must fail with 404/403 (model not found within Tenant A's scope)
        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Three Business Scenarios (Phase G)
    // -------------------------------------------------------------------------

    public function test_scenario_1_harare_clothing_retailer(): void
    {
        $this->actingAs($this->userA);

        // Scenario 1: Harare clothing retailer
        // Commercial retail, physical shop in Harare, 4 staff, $35k USD turnover (compulsory VAT), no government tenders
        $payload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Chic Attire Zimbabwe (Pvt) Ltd',
                'trading_name' => 'Chic Attire',
                'business_mode' => 'NEW',
                'is_already_incorporated' => false,
                'industry_sector' => 'RETAIL',
                'goods_and_services' => 'Retail apparel, footwear, and accessories',
                'annual_turnover_usd' => 35000.00, // Exceeds $25k statutory threshold -> Compulsory VAT
                'employee_count' => 4,
                'pays_wages' => true,
                'nec_industry' => 'NEC_COMM',
                'has_physical_premises' => true,
                'premises_type' => 'RETAIL_SHOP',
                'jurisdiction_code' => 'ZW-HARARE',
                'physical_address' => 'Shop 4, First Street Mall, Harare',
                'bids_on_government_tenders' => false,
                'confirmation_accurate' => true,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/submit', $payload);
        $response->assertStatus(200);

        $results = AssessmentResult::where('assessment_id', $response->json('assessment.assessment_id'))
            ->with('obligationDefinition')
            ->get()
            ->keyBy(fn ($r) => $r->obligationDefinition->code);

        // Foundational incorporation is MANDATORY
        $this->assertSame('MANDATORY', $results['CIPZ_INCORP']->decision);

        // Compulsory VAT is triggered because turnover ($35,000) exceeds $25,000 statutory threshold
        $this->assertSame('CONDITIONAL', $results['ZIMRA_VAT_REG']->decision);

        // NSSA and ZIMDEF are MANDATORY (4 employees, pays wages)
        $this->assertSame('MANDATORY', $results['NSSA_EMPLOYER_REG']->decision);
        $this->assertSame('MANDATORY', $results['ZIMDEF_LEVY_REG']->decision);

        // City of Harare Municipal Operating Licence is held on UNVERIFIED_HOLD pending gazetted rule pack approval
        $this->assertSame('UNVERIFIED_HOLD', $results['COH_BUSINESS_LICENCE']->decision);

        // Commercial NEC registration is held on UNVERIFIED_HOLD pending gazetted rule pack approval
        $this->assertSame('UNVERIFIED_HOLD', $results['NEC_COMM_REG']->decision);

        // PRAZ Supplier Registration is NOT_APPLICABLE (does not bid on tenders)
        $this->assertSame('NOT_APPLICABLE', $results['PRAZ_SUPPLIER_REG']->decision);

        // Engineering Council is held on UNVERIFIED_HOLD pending gazetted rule pack approval
        $this->assertSame('UNVERIFIED_HOLD', $results['ECZ_FIRM_REG']->decision);
    }

    public function test_scenario_2_engineering_and_fabrication_company(): void
    {
        $this->actingAs($this->userA);

        // Scenario 2: Engineering and fabrication company
        // Heavy steel fabrication, workshop in Harare, 12 staff, $85k USD turnover, bids on government tenders, ECZ professional council
        $payload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'Highlands Heavy Fabrication (Pvt) Ltd',
                'trading_name' => 'Highlands Engineering',
                'business_mode' => 'NEW',
                'is_already_incorporated' => false,
                'industry_sector' => 'ENGINEERING',
                'goods_and_services' => 'Structural steel, mining hoppers, pressure tanks, consulting engineering',
                'annual_turnover_usd' => 85000.00,
                'licensed_profession_involved' => true,
                'employee_count' => 12,
                'pays_wages' => true,
                'gross_monthly_wage_bill_usd' => 7500.00,
                'nec_industry' => 'NEC_ENG',
                'has_physical_premises' => true,
                'premises_type' => 'INDUSTRIAL_WORKSHOP',
                'jurisdiction_code' => 'ZW-HARARE',
                'physical_address' => '22 Simon Mazorodze Road, Workington, Harare',
                'bids_on_government_tenders' => true,
                'tender_categories' => ['WORKS', 'GOODS'],
                'confirmation_accurate' => true,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/submit', $payload);
        $response->assertStatus(200);

        $results = AssessmentResult::where('assessment_id', $response->json('assessment.assessment_id'))
            ->with('obligationDefinition')
            ->get()
            ->keyBy(fn ($r) => $r->obligationDefinition->code);

        // Statutory incorporation & Tax
        $this->assertSame('MANDATORY', $results['CIPZ_INCORP']->decision);
        $this->assertSame('MANDATORY', $results['ZIMRA_TIN_REG']->decision);
        $this->assertSame('CONDITIONAL', $results['ZIMRA_VAT_REG']->decision);

        // Social security & Levies
        $this->assertSame('MANDATORY', $results['NSSA_EMPLOYER_REG']->decision);
        $this->assertSame('MANDATORY', $results['ZIMDEF_LEVY_REG']->decision);

        // Engineering NEC is held on UNVERIFIED_HOLD pending gazetted rule pack approval
        $this->assertSame('UNVERIFIED_HOLD', $results['NEC_ENG_REG']->decision);

        // Commercial NEC is held on UNVERIFIED_HOLD pending gazetted rule pack approval
        $this->assertSame('UNVERIFIED_HOLD', $results['NEC_COMM_REG']->decision);

        // City of Harare workshop licence is held on UNVERIFIED_HOLD
        $this->assertSame('UNVERIFIED_HOLD', $results['COH_BUSINESS_LICENCE']->decision);

        // Public procurement tender registrations
        $this->assertSame('CONDITIONAL', $results['ITF263_CLEARANCE']->decision);
        $this->assertSame('CONDITIONAL', $results['PRAZ_SUPPLIER_REG']->decision);

        // ECZ Firm Registration is held on UNVERIFIED_HOLD pending gazetted rule pack approval
        $this->assertSame('UNVERIFIED_HOLD', $results['ECZ_FIRM_REG']->decision);
    }

    public function test_scenario_3_multi_branch_computer_accessories_retailer(): void
    {
        $this->actingAs($this->userA);

        // Scenario 3: Multi-branch computer accessories retailer
        // Commercial IT retail, existing incorporation, HQ in Harare + Branch in Bulawayo, 8 staff, $60k turnover, bids on tenders
        $payload = [
            'answers' => [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'registered_name' => 'DataCore Systems Zimbabwe (Pvt) Ltd',
                'trading_name' => 'DataCore IT',
                'business_mode' => 'EXISTING',
                'is_already_incorporated' => true,
                'company_registration_number' => 'COBE-2022-7718',
                'bp_number' => 'BP-010049281',
                'industry_sector' => 'IT_SERVICES',
                'goods_and_services' => 'Computer accessories, networking gear, laptop distribution',
                'annual_turnover_usd' => 60000.00,
                'employee_count' => 8,
                'pays_wages' => true,
                'nec_industry' => 'NEC_COMM',
                'has_physical_premises' => true,
                'premises_type' => 'COMMERCIAL_OFFICE',
                'jurisdiction_code' => 'ZW-HARARE',
                'physical_address' => '102 Kwame Nkrumah Avenue, Harare',
                'branches' => [
                    [
                        'name' => 'DataCore Bulawayo Branch',
                        'jurisdiction_code' => 'ZW-BULAWAYO',
                        'city' => 'Bulawayo',
                        'physical_address' => '74 Main Street, Bulawayo',
                        'activities' => 'Retail IT Distribution',
                    ],
                ],
                'bids_on_government_tenders' => true,
                'confirmation_accurate' => true,
            ],
        ];

        $response = $this->postJson('/registration/questionnaire/submit', $payload);
        $response->assertStatus(200);

        $results = AssessmentResult::where('assessment_id', $response->json('assessment.assessment_id'))
            ->with('obligationDefinition')
            ->get()
            ->keyBy(fn ($r) => $r->obligationDefinition->code);

        // Since entity is already incorporated, CIPZ is NOT a new formation requirement
        $this->assertSame('NOT_APPLICABLE', $results['CIPZ_INCORP']->decision);

        // ZIMRA TIN is mandatory
        $this->assertSame('MANDATORY', $results['ZIMRA_TIN_REG']->decision);

        // VAT is triggered
        $this->assertSame('CONDITIONAL', $results['ZIMRA_VAT_REG']->decision);

        // NSSA, ZIMDEF, PRAZ are applicable
        $this->assertSame('MANDATORY', $results['NSSA_EMPLOYER_REG']->decision);
        $this->assertSame('MANDATORY', $results['ZIMDEF_LEVY_REG']->decision);
        $this->assertSame('CONDITIONAL', $results['PRAZ_SUPPLIER_REG']->decision);

        // Branches were synchronized to branches table
        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'name' => 'DataCore Bulawayo Branch',
            'city' => 'Bulawayo',
        ]);
    }
}
