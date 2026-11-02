<?php

namespace Tests\Feature\Compliance;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Services\ComplianceLifecycleStateMachine;
use App\Domains\ComplianceEngine\Services\ComplianceObligationRegisterService;
use App\Domains\RegistrationEngine\Models\AssessmentResult;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\Jurisdiction;
use App\Domains\RegistrationEngine\Models\ObligationActivityLog;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
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

class ComplianceObligationRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Organisation $orgA1;
    protected Organisation $orgA2;
    protected User $adminUser;
    protected User $employeeUser;
    protected User $otherOrgUser;

    protected Tenant $tenantB;
    protected Organisation $orgB;
    protected User $tenantBUser;

    protected RegulatoryAuthority $zimra;
    protected RegulatoryAuthority $nssa;
    protected RegulatorySource $taxAct;
    protected RulePack $payePack;
    protected RuleVersion $approvedRuleVersion;
    protected RuleVersion $unverifiedRuleVersion;
    protected ObligationDefinition $payeDefinition;
    protected ObligationDefinition $nssaDefinition;
    protected ObligationInstance $payeInstance;
    protected ObligationInstance $nssaInstance;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tenant A: Harare Manufacturing
        $this->tenantA = Tenant::create([
            'name' => 'Harare Engineering Group',
            'slug' => 'harare-engineering',
            'status' => 'ACTIVE',
        ]);

        $this->orgA1 = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Harare Engineering (Pvt) Ltd',
            'trading_name' => 'Harare Steel Works',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->orgA2 = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Harare Logistics (Pvt) Ltd',
            'trading_name' => 'Harare Express',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        // Users in Tenant A
        $this->adminUser = User::create([
            'name' => 'Farai Matsika (Admin)',
            'email' => 'farai.admin@hararesteel.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->employeeUser = User::create([
            'name' => 'Tendai Moyo (Staff)',
            'email' => 'tendai.staff@hararesteel.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->otherOrgUser = User::create([
            'name' => 'Chipo Mutasa (Logistics)',
            'email' => 'chipo@hararelogistics.co.zw',
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
            'user_id' => $this->employeeUser->id,
            'organisation_id' => $this->orgA1->id,
            'role' => 'MEMBER',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->otherOrgUser->id,
            'organisation_id' => $this->orgA2->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        // 2. Tenant B: Bulawayo Textiles
        $this->tenantB = Tenant::create([
            'name' => 'Bulawayo Textiles Group',
            'slug' => 'bulawayo-textiles',
            'status' => 'ACTIVE',
        ]);

        $this->orgB = Organisation::create([
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Bulawayo Textiles (Pvt) Ltd',
            'trading_name' => 'Bulawayo Textiles',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->tenantBUser = User::create([
            'name' => 'Sipho Ndlovu',
            'email' => 'sipho@bulawayotextiles.co.zw',
            'password' => Hash::make('Secret123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->tenantBUser->id,
            'organisation_id' => $this->orgB->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

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

        $this->taxAct = RegulatorySource::create([
            'authority_id' => $this->zimra->id,
            'title' => 'Income Tax Act',
            'act_chapter' => 'Chapter 23:06',
            'gazette_date' => '1967-01-01',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'citation' => 'Income Tax Act [Chapter 23:06], Section 73',
        ]);

        $this->payePack = RulePack::create([
            'code' => 'ZIMRA_PAYE_REMITTANCE',
            'name' => 'PAYE Monthly Remittance Pack',
            'authority_id' => $this->zimra->id,
            'category' => 'REVENUE_TAX',
        ]);

        // Approved and Verified Rule Version
        $this->approvedRuleVersion = RuleVersion::create([
            'rule_pack_id' => $this->payePack->id,
            'version_code' => 'ZIMRA_PAYE_2026_V1',
            'source_id' => $this->taxAct->id,
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => ['remittance_day' => 10],
            'approved_at' => Carbon::now(),
        ]);

        // Unverified / Draft Rule Version
        $this->unverifiedRuleVersion = RuleVersion::create([
            'rule_pack_id' => $this->payePack->id,
            'version_code' => 'ZIMRA_PAYE_DRAFT_V2',
            'source_id' => $this->taxAct->id,
            'effective_from' => '2026-07-01',
            'status' => 'DRAFT',
            'verification_status' => 'REQUIRES_REVIEW',
            'parameters' => ['remittance_day' => 15],
        ]);

        // Definitions
        $this->payeDefinition = ObligationDefinition::create([
            'code' => 'ZIMRA_PAYE_MONTHLY',
            'authority_id' => $this->zimra->id,
            'source_id' => $this->taxAct->id,
            'rule_pack_id' => $this->payePack->id,
            'title' => 'PAYE Monthly Tax Remittance (Form P2)',
            'obligation_type' => 'PERIODIC_RETURN',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'statutory_citation' => 'Income Tax Act [Chapter 23:06], 13th Schedule',
            'renewal_frequency' => 'MONTHLY',
            'required_documents' => ['P2_RETURN_RECEIPT', 'PAYROLL_SCHEDULE'],
            'applicability_criteria' => ['has_employees' => true],
            'is_active' => true,
        ]);

        $this->nssaDefinition = ObligationDefinition::create([
            'code' => 'NSSA_POBS_MONTHLY',
            'authority_id' => $this->nssa->id,
            'title' => 'NSSA POBS Pension Remittance (Form P4)',
            'obligation_type' => 'PERIODIC_RETURN',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'statutory_citation' => 'NSSA Act [Chapter 17:04]',
            'renewal_frequency' => 'MONTHLY',
            'required_documents' => ['NSSA_P4_RECEIPT'],
            'applicability_criteria' => ['has_employees' => true],
            'is_active' => true,
        ]);

        $questionnaire = \App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'answers' => [
                'primary_sector' => 'ENGINEERING',
                'employee_count' => 12,
                'has_employees' => true,
            ],
            'completed_at' => Carbon::now(),
            'completed_by_user_id' => $this->adminUser->id,
        ]);

        $assessment = \App\Domains\RegistrationEngine\Models\ApplicabilityAssessment::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'INITIAL_REGISTRATION',
            'evaluation_inputs' => ['has_employees' => true],
            'assessed_by_user_id' => $this->adminUser->id,
            'generated_at' => Carbon::now(),
        ]);

        // Assessment Results linking approved rule version
        $payeResult = AssessmentResult::create([
            'tenant_id' => $this->tenantA->id,
            'assessment_id' => $assessment->id,
            'obligation_definition_id' => $this->payeDefinition->id,
            'rule_version_id' => $this->approvedRuleVersion->id,
            'decision' => 'APPLICABLE',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'trigger_reasons' => ['Active business with registered employees subject to PAYE deduction.'],
            'rule_source_citation' => 'Income Tax Act [Chapter 23:06]',
            'evaluated_at' => Carbon::now(),
        ]);

        $nssaResult = AssessmentResult::create([
            'tenant_id' => $this->tenantA->id,
            'assessment_id' => $assessment->id,
            'obligation_definition_id' => $this->nssaDefinition->id,
            'rule_version_id' => null,
            'decision' => 'UNVERIFIED_HOLD',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'trigger_reasons' => ['Statutory rule version is unverified or awaiting gazetted review.'],
            'rule_source_citation' => 'NSSA Act [Chapter 17:04]',
            'evaluated_at' => Carbon::now(),
        ]);

        // Obligation Instances
        $this->payeInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->payeDefinition->id,
            'assessment_result_id' => $payeResult->id,
            'status' => 'NOT_STARTED',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'MONTHLY',
            'next_due_date' => '2026-11-10',
            'lock_version' => 1,
        ]);

        $this->nssaInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->nssaDefinition->id,
            'assessment_result_id' => $nssaResult->id,
            'status' => 'NOT_STARTED',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'MONTHLY',
            'next_due_date' => '2026-11-10',
            'lock_version' => 1,
        ]);
    }

    /**
     * Test: Compliance register displays real obligations with complete metadata.
     */
    public function test_compliance_register_lists_applicable_approved_obligations_for_organisation(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/comply');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Comply/Index')
            ->has('obligations', 2)
            ->where('obligations.0.code', 'ZIMRA_PAYE_MONTHLY')
            ->where('obligations.0.authority.code', 'ZIMRA')
            ->where('obligations.0.category', 'MANDATORY_STATUTORY')
            ->where('obligations.0.approved_source.rule_version_number', 'ZIMRA_PAYE_2026_V1')
            ->where('obligations.0.lifecycle_status', 'NOT_STARTED')
            ->where('metrics.total', 2)
            ->where('can_manage', true)
        );
    }

    /**
     * Test: Register filters correctly by authority, category, lifecycle status, and responsible user.
     */
    public function test_compliance_register_filters_by_authority_category_status_and_assigned_user(): void
    {
        // Assign PAYE obligation to employee user and set in progress
        $this->payeInstance->update([
            'assigned_to_user_id' => $this->employeeUser->id,
            'status' => 'IN_PROGRESS',
        ]);

        // Filter by Authority = ZIMRA
        $response = $this->actingAs($this->adminUser)->get('/comply?authority=ZIMRA');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('obligations', 1)
            ->where('obligations.0.code', 'ZIMRA_PAYE_MONTHLY')
        );

        // Filter by status = IN_PROGRESS
        $response = $this->actingAs($this->adminUser)->get('/comply?lifecycle_status=IN_PROGRESS');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('obligations', 1)
            ->where('obligations.0.lifecycle_status', 'IN_PROGRESS')
        );

        // Filter by assigned user
        $response = $this->actingAs($this->adminUser)->get("/comply?responsible_user={$this->employeeUser->id}");
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('obligations', 1)
            ->where('obligations.0.responsible_user.id', $this->employeeUser->id)
        );
    }

    /**
     * Test: Obligations governed by unverified rules clearly maintain UNVERIFIED_HOLD decision.
     */
    public function test_unverified_rule_versions_maintain_unverified_hold_decision(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/comply');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('obligations.1.code', 'NSSA_POBS_MONTHLY')
            ->where('obligations.1.applicability_decision.is_unverified_hold', true)
            ->where('obligations.1.applicability_decision.decision', 'UNVERIFIED_HOLD')
        );
    }

    /**
     * Test: Authorised organisation administrator can assign obligation to organisation member.
     */
    public function test_authorised_organisation_admin_can_assign_obligation_to_organisation_member(): void
    {
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/assign", [
            'assigned_to_user_id' => $this->employeeUser->id,
            'lock_version' => 1,
            'notes' => 'Assigned PAYE monthly computation and filing responsibility to Tendai.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->payeInstance->refresh();
        $this->assertEquals($this->employeeUser->id, $this->payeInstance->assigned_to_user_id);
        $this->assertEquals(2, $this->payeInstance->lock_version);

        // Verify activity log was recorded
        $log = ObligationActivityLog::where('obligation_instance_id', $this->payeInstance->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('ASSIGNMENT_CHANGED', $log->activity_type);
        $this->assertEquals($this->employeeUser->id, $log->to_user_id);
        $this->assertEquals($this->adminUser->id, $log->user_id);

        // Verify enterprise AuditEvent was recorded
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminUser->id,
            'event_type' => 'OBLIGATION_ASSIGNMENT_CHANGED',
            'auditable_id' => $this->payeInstance->id,
        ]);
    }

    /**
     * Test: Non-admin organisation member cannot assign obligations.
     */
    public function test_non_admin_cannot_assign_obligations(): void
    {
        $response = $this->actingAs($this->employeeUser)->post("/compliance/obligations/{$this->payeInstance->id}/assign", [
            'assigned_to_user_id' => $this->employeeUser->id,
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors(['authorization']);
        $this->payeInstance->refresh();
        $this->assertNull($this->payeInstance->assigned_to_user_id);
    }

    /**
     * Test: Cannot assign obligation to a user from a different organisation (even within same tenant).
     */
    public function test_cannot_assign_obligation_to_user_from_different_organisation(): void
    {
        // Chipo Mutasa belongs to orgA2, not orgA1
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/assign", [
            'assigned_to_user_id' => $this->otherOrgUser->id,
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors(['authorization']);
        $this->payeInstance->refresh();
        $this->assertNull($this->payeInstance->assigned_to_user_id);
    }

    /**
     * Test: Cross-tenant isolation prevents accessing or modifying obligations.
     */
    public function test_cross_tenant_isolation_prevents_viewing_or_updating_obligations(): void
    {
        // Tenant B user attempts to transition Tenant A obligation
        $response = $this->actingAs($this->tenantBUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'IN_PROGRESS',
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors(['authorization']);
        $this->payeInstance->refresh();
        $this->assertEquals('NOT_STARTED', $this->payeInstance->status);
    }

    /**
     * Test: Same-tenant cross-organisation isolation prevents accessing other organisation's obligations.
     */
    public function test_same_tenant_cross_organisation_isolation_prevents_access(): void
    {
        // Chipo is Admin of orgA2, but payeInstance belongs to orgA1
        $response = $this->actingAs($this->otherOrgUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'IN_PROGRESS',
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors(['authorization']);
        $this->payeInstance->refresh();
        $this->assertEquals('NOT_STARTED', $this->payeInstance->status);
    }

    /**
     * Test: Valid lifecycle status transitions.
     */
    public function test_valid_lifecycle_status_transitions(): void
    {
        // 1. NOT_STARTED -> IN_PROGRESS
        $response = $this->actingAs($this->employeeUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'IN_PROGRESS',
            'lock_version' => 1,
            'notes' => 'Staff initiated payroll calculations for October 2026.',
        ]);
        $response->assertRedirect();
        $this->payeInstance->refresh();
        $this->assertEquals('IN_PROGRESS', $this->payeInstance->status);

        // 2. IN_PROGRESS -> AWAITING_EVIDENCE
        $response = $this->actingAs($this->employeeUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'AWAITING_EVIDENCE',
            'lock_version' => 2,
            'notes' => 'Payroll finalized. Awaiting official P2 return receipt.',
        ]);
        $response->assertRedirect();
        $this->payeInstance->refresh();
        $this->assertEquals('AWAITING_EVIDENCE', $this->payeInstance->status);

        // 3. AWAITING_EVIDENCE -> UNDER_REVIEW
        $response = $this->actingAs($this->employeeUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'UNDER_REVIEW',
            'lock_version' => 3,
            'notes' => 'Evidence uploaded. Ready for supervisor review.',
        ]);
        $response->assertRedirect();
        $this->payeInstance->refresh();
        $this->assertEquals('UNDER_REVIEW', $this->payeInstance->status);

        // 4. UNDER_REVIEW -> COMPLETED_INTERNAL (Internal completion separate from statutory compliance)
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'COMPLETED_INTERNAL',
            'lock_version' => 4,
            'notes' => 'Internal payroll team completed return preparation.',
        ]);
        $response->assertRedirect();
        $this->payeInstance->refresh();
        $this->assertEquals('COMPLETED_INTERNAL', $this->payeInstance->status);
    }

    /**
     * Test: CRITICAL SAFEGUARD - Cannot mark statutory obligation as VERIFIED_COMPLETE without verified evidence.
     */
    public function test_cannot_mark_statutory_obligation_verified_complete_without_verified_evidence(): void
    {
        // Transition to UNDER_REVIEW first
        $this->payeInstance->update(['status' => 'UNDER_REVIEW', 'lock_version' => 1]);

        // Attempt transition to VERIFIED_COMPLETE when no verified document exists
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'VERIFIED_COMPLETE',
            'lock_version' => 1,
            'notes' => 'Attempting to mark complete without official proof.',
        ]);

        $response->assertSessionHasErrors(['transition']);
        $this->payeInstance->refresh();
        $this->assertEquals('UNDER_REVIEW', $this->payeInstance->status);

        // Now link an unverified / pending document
        $pendingDoc = ComplianceDocument::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'obligation_instance_id' => $this->payeInstance->id,
            'uploaded_by_user_id' => $this->employeeUser->id,
            'document_type' => 'P2_RETURN_RECEIPT',
            'title' => 'P2 Return Receipt',
            'file_name' => 'p2_receipt.pdf',
            'file_path' => 'documents/test.pdf',
            'file_size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash('sha256', 'sample'),
            'verification_status' => 'PENDING',
        ]);

        // Still rejected because document is PENDING human verification
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'VERIFIED_COMPLETE',
            'lock_version' => 1,
        ]);
        $response->assertSessionHasErrors(['transition']);

        // Now mark document as VERIFIED
        $pendingDoc->update([
            'verification_status' => 'VERIFIED',
            'verified_by_user_id' => $this->adminUser->id,
            'verified_at' => Carbon::now(),
            'expiry_date' => Carbon::now()->addYear(),
        ]);
        $this->payeInstance->update(['evidence_status' => 'DOCUMENT_SUPPORTED']);

        // Now transition to VERIFIED_COMPLETE succeeds!
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'VERIFIED_COMPLETE',
            'lock_version' => 1,
            'notes' => 'Certified official statutory compliance with verified ZIMRA receipt.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->payeInstance->refresh();
        $this->assertEquals('VERIFIED_COMPLETE', $this->payeInstance->status);
    }

    /**
     * Test: Regular user cannot declare VERIFIED_COMPLETE even if valid evidence exists (Dual Control).
     */
    public function test_regular_user_cannot_mark_verified_complete_even_with_evidence(): void
    {
        $this->payeInstance->update([
            'status' => 'UNDER_REVIEW',
            'evidence_status' => 'DOCUMENT_SUPPORTED',
            'lock_version' => 1,
        ]);

        ComplianceDocument::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'obligation_instance_id' => $this->payeInstance->id,
            'uploaded_by_user_id' => $this->employeeUser->id,
            'document_type' => 'P2_RETURN_RECEIPT',
            'title' => 'P2 Return Receipt',
            'file_name' => 'p2_receipt.pdf',
            'file_path' => 'documents/test.pdf',
            'file_size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash('sha256', 'sample'),
            'verification_status' => 'VERIFIED',
            'expiry_date' => Carbon::now()->addYear(),
        ]);

        $response = $this->actingAs($this->employeeUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'VERIFIED_COMPLETE',
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors(['authorization']);
        $this->payeInstance->refresh();
        $this->assertEquals('UNDER_REVIEW', $this->payeInstance->status);
    }

    /**
     * Test: Transitioning to BLOCKED requires a mandatory justification note.
     */
    public function test_transition_to_blocked_requires_mandatory_justification_notes(): void
    {
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'BLOCKED',
            'lock_version' => 1,
            'notes' => '', // Empty note
        ]);

        $response->assertSessionHasErrors(['transition']);
        $this->payeInstance->refresh();
        $this->assertEquals('NOT_STARTED', $this->payeInstance->status);

        // With valid note: succeeds
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'BLOCKED',
            'lock_version' => 1,
            'notes' => 'ZIMRA TaRMS portal is currently unresponsive; payment submission blocked.',
        ]);

        $response->assertRedirect();
        $this->payeInstance->refresh();
        $this->assertEquals('BLOCKED', $this->payeInstance->status);
    }

    /**
     * Test: Concurrent updates are detected and safeguarded via lock_version.
     */
    public function test_concurrent_updates_are_safeguarded_via_optimistic_locking(): void
    {
        // First user updates status, bumping lock_version to 2
        $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'IN_PROGRESS',
            'lock_version' => 1,
        ]);

        $this->payeInstance->refresh();
        $this->assertEquals(2, $this->payeInstance->lock_version);

        // Second user concurrently attempts update using stale lock_version = 1
        $response = $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'AWAITING_EVIDENCE',
            'lock_version' => 1, // Stale!
        ]);

        $response->assertSessionHasErrors(['concurrency']);
        $this->payeInstance->refresh();
        $this->assertEquals('IN_PROGRESS', $this->payeInstance->status);
    }

    /**
     * Test: Historical activity logs and audit trail are preserved across all transitions.
     */
    public function test_historical_audit_trail_and_activity_logs_are_preserved(): void
    {
        // Execute assignment
        $this->actingAs($this->adminUser)->post("/compliance/obligations/{$this->payeInstance->id}/assign", [
            'assigned_to_user_id' => $this->employeeUser->id,
            'lock_version' => 1,
            'notes' => 'Initial assignment.',
        ]);

        // Execute transition
        $this->actingAs($this->employeeUser)->post("/compliance/obligations/{$this->payeInstance->id}/transition", [
            'status' => 'IN_PROGRESS',
            'lock_version' => 2,
            'notes' => 'Work started.',
        ]);

        $logs = ObligationActivityLog::where('obligation_instance_id', $this->payeInstance->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertEquals('ASSIGNMENT_CHANGED', $logs[0]->activity_type);
        $this->assertEquals('STATUS_TRANSITION', $logs[1]->activity_type);
        $this->assertEquals('NOT_STARTED', $logs[1]->from_status);
        $this->assertEquals('IN_PROGRESS', $logs[1]->to_status);
        $this->assertEquals($this->employeeUser->id, $logs[1]->user_id);
    }

    /**
     * Test: Show endpoint returns comprehensive obligation detail with linked documents and timeline.
     */
    public function test_obligation_detail_endpoint_returns_comprehensive_data(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson("/compliance/obligations/{$this->payeInstance->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'obligation' => [
                'id',
                'name',
                'code',
                'authority',
                'category',
                'approved_source',
                'lifecycle_status',
                'evidence_summary',
                'relevant_dates',
                'lock_version',
            ],
            'documents',
            'available_transitions',
            'timeline',
        ]);
    }
}
