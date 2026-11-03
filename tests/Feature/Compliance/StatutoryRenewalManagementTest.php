<?php

namespace Tests\Feature\Compliance;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use App\Domains\ComplianceEngine\Models\RenewalAttempt;
use App\Domains\ComplianceEngine\Models\RenewalEvidenceLink;
use App\Domains\ComplianceEngine\Models\StatutoryInstrument;
use App\Domains\ComplianceEngine\Services\FilingCalendarService;
use App\Domains\ComplianceEngine\Services\RenewalDeadlineCalculator;
use App\Domains\ComplianceEngine\Services\RenewalLifecycleStateMachine;
use App\Domains\ComplianceEngine\Services\RenewalManagementService;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RegulatorySource;
use App\Domains\RegistrationEngine\Models\RulePack;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StatutoryRenewalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Organisation $orgA1;
    protected Organisation $orgA2;
    protected User $adminUser;
    protected User $staffUser;
    protected User $orgA2Admin;

    protected Tenant $tenantB;
    protected Organisation $orgB1;
    protected User $tenantBUser;

    protected RegulatoryAuthority $authorityCoh;
    protected RegulatorySource $verifiedSource;
    protected ObligationDefinition $licenceDefinition;
    protected ObligationInstance $licenceObligationInstance;
    protected RulePack $renewalPack;
    protected RuleVersion $verifiedCalendarYearRenewalRule;

    protected RenewalManagementService $renewalService;
    protected RenewalDeadlineCalculator $deadlineCalculator;
    protected FilingCalendarService $calendarService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RegulatoryCatalogSeeder::class);

        $this->renewalService = app(RenewalManagementService::class);
        $this->deadlineCalculator = app(RenewalDeadlineCalculator::class);
        $this->calendarService = app(FilingCalendarService::class);

        // Tenant A & Organisations A1, A2
        $this->tenantA = Tenant::create([
            'name' => 'Renewal Test Tenant A',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $this->orgA1 = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Harare Precision Engineering (Pvt) Ltd',
            'trading_name' => 'Harare Precision',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
            'fiscal_year_end_month' => 12,
        ]);

        $this->orgA2 = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Gweru Foundry Works (Pvt) Ltd',
            'trading_name' => 'Gweru Foundry',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
            'fiscal_year_end_month' => 12,
        ]);

        $this->adminUser = User::create([
            'name' => 'Rudo Moyo (Owner)',
            'email' => 'rudo@harareprecision.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $this->staffUser = User::create([
            'name' => 'Tendai Ncube (Staff)',
            'email' => 'tendai@harareprecision.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $this->orgA2Admin = User::create([
            'name' => 'Simba Dube (Org A2 Admin)',
            'email' => 'simba@gwerufoundry.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'user_id' => $this->adminUser->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'user_id' => $this->staffUser->id,
            'role' => 'STAFF',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA2->id,
            'user_id' => $this->orgA2Admin->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        // Tenant B
        $this->tenantB = Tenant::create([
            'name' => 'Renewal Test Tenant B',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $this->orgB1 = Organisation::create([
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Mutare Timber Mills (Pvt) Ltd',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
        ]);

        $this->tenantBUser = User::create([
            'name' => 'Tenant B Admin',
            'email' => 'admin@mutaretimber.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantB->id,
            'organisation_id' => $this->orgB1->id,
            'user_id' => $this->tenantBUser->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        // Synthetic Approved Renewal Rule Fixture (clearly labelled SYNTHETIC_FIXTURE)
        $this->authorityCoh = RegulatoryAuthority::where('code', 'COH')->firstOrFail();

        $this->verifiedSource = RegulatorySource::create([
            'authority_id' => $this->authorityCoh->id,
            'title' => 'SYNTHETIC_FIXTURE: Shop Licences Renewal Schedule Test Source',
            'act_chapter' => '14:17',
            'gazette_date' => '2025-01-01',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'citation' => 'SYNTHETIC_FIXTURE: Shop Licences Act [Chapter 14:17], Section 24',
        ]);

        $this->licenceDefinition = ObligationDefinition::where('code', 'COH_BUSINESS_LICENCE')->firstOrFail();

        $this->renewalPack = RulePack::create([
            'authority_id' => $this->authorityCoh->id,
            'code' => 'SYNTHETIC_COH_RENEWAL_PACK',
            'name' => 'SYNTHETIC_FIXTURE: City of Harare Renewal Schedule Pack',
            'category' => 'MUNICIPAL_LICENSING',
            'is_active' => true,
        ]);

        $this->verifiedCalendarYearRenewalRule = RuleVersion::create([
            'rule_pack_id' => $this->renewalPack->id,
            'source_id' => $this->verifiedSource->id,
            'version_code' => 'SYNTHETIC_COH_RENEWAL_2026_V1',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'renewal_schedule' => [
                    'cadence_type' => 'CALENDAR_YEAR',
                    'calendar_expiry_month' => 12,
                    'calendar_expiry_day' => 31,
                    'window_days_before_expiry' => 60,
                    'deadline_days_before_expiry' => 15,
                    'expiring_soon_days' => 30,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $this->licenceObligationInstance = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'definition_id' => $this->licenceDefinition->id,
            'status' => 'IN_PROGRESS',
            'evidence_status' => 'UNKNOWN',
            'compliance_frequency' => 'ANNUAL',
        ]);

        app(TenantContext::class)->setTenant($this->tenantA);
        app(TenantContext::class)->setOrganisation($this->orgA1);
    }

    protected function createVaultDocument(
        Organisation $org,
        User $uploader,
        string $fileName,
        string $verificationStatus = 'VERIFIED',
        ?string $issueDate = '2026-01-01',
        ?string $expiryDate = '2026-12-31'
    ): ComplianceDocument {
        $path = "tenants/{$org->tenant_id}/documents/" . uniqid() . '_' . $fileName;
        \Illuminate\Support\Facades\Storage::disk('private')->put($path, '%PDF-1.4 synthetic certificate content ' . $fileName);

        return ComplianceDocument::create([
            'tenant_id' => $org->tenant_id,
            'organisation_id' => $org->id,
            'obligation_instance_id' => $org->id === $this->orgA1->id ? $this->licenceObligationInstance->id : null,
            'uploaded_by_user_id' => $uploader->id,
            'document_type' => 'MUNICIPAL_LICENCE',
            'title' => $fileName,
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'sha256_hash' => hash('sha256', 'content-' . $fileName . '-' . uniqid()),
            'storage_disk' => 'private',
            'verification_status' => $verificationStatus,
            'verified_by_user_id' => $verificationStatus === 'VERIFIED' ? $uploader->id : null,
            'verified_at' => $verificationStatus === 'VERIFIED' ? Carbon::now() : null,
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
        ]);
    }

    /**
     * 1. Instrument registration and initial current document linking without duplicating vault records.
     */
    public function test_01_instrument_registration_and_initial_evidence_linking(): void
    {
        $initialDoc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'COH_Licence_2026.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');
        $vaultCountBefore = ComplianceDocument::withoutGlobalScopes()->count();

        $res = $this->actingAs($this->adminUser)->post('/renew/instruments', [
            'title' => 'City of Harare Shop & Operating Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'obligation_instance_id' => $this->licenceObligationInstance->id,
            'rule_version_id' => $this->verifiedCalendarYearRenewalRule->id,
            'reference_number' => 'COH-2026-8841',
            'premises_or_scope' => 'Workington Workshop',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
            'current_document_id' => $initialDoc->id,
            'assigned_to_user_id' => $this->staffUser->id,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Confirm no duplicate ComplianceDocument record was created in the vault
        $this->assertEquals($vaultCountBefore, ComplianceDocument::withoutGlobalScopes()->count());

        $instrument = StatutoryInstrument::where('organisation_id', $this->orgA1->id)->firstOrFail();
        $this->assertEquals('COH-2026-8841', $instrument->reference_number);
        $this->assertEquals('ACTIVE', $instrument->validity_status);
        $this->assertEquals('NONE', $instrument->current_workflow_status);
        $this->assertEquals('2026-12-16', $instrument->statutory_renewal_deadline->toDateString());
        $this->assertEquals('VERIFIED_OFFICIAL', $instrument->deadline_verification_status);

        $this->assertDatabaseHas('renewal_evidence_links', [
            'statutory_instrument_id' => $instrument->id,
            'compliance_document_id' => $initialDoc->id,
            'document_purpose' => RenewalEvidenceLink::PURPOSE_CURRENT_INSTRUMENT,
            'is_superseded' => false,
        ]);
    }

    /**
     * 2. Same-tenant cross-organisation isolation (Safeguard E.1).
     */
    public function test_02_same_tenant_cross_organisation_isolation(): void
    {
        $instA1 = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Org A1 Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        // orgA2Admin belongs to Tenant A, but only has membership in orgA2
        $resShow = $this->actingAs($this->orgA2Admin)->getJson("/renew/instruments/{$instA1->id}");
        $resShow->assertStatus(403);

        $resStart = $this->actingAs($this->orgA2Admin)->post("/renew/instruments/{$instA1->id}/attempts/start");
        $resStart->assertSessionHasErrors(['authorization']);

        // Cannot assign Org A1 instrument to Org A2 user
        $resAssign = $this->actingAs($this->adminUser)->post("/renew/instruments/{$instA1->id}/assign", [
            'assigned_to_user_id' => $this->orgA2Admin->id,
        ]);
        $resAssign->assertSessionHasErrors(['authorization']);
    }

    /**
     * 3. Cross-tenant isolation.
     */
    public function test_03_cross_tenant_isolation(): void
    {
        $instA1 = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Tenant A Confidential Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        app(TenantContext::class)->setTenant($this->tenantB);
        app(TenantContext::class)->setOrganisation($this->orgB1);

        $resShow = $this->actingAs($this->tenantBUser)->getJson("/renew/instruments/{$instA1->id}");
        $resShow->assertStatus(403);

        $resAssign = $this->actingAs($this->tenantBUser)->post("/renew/instruments/{$instA1->id}/assign", [
            'assigned_to_user_id' => $this->tenantBUser->id,
        ]);
        $resAssign->assertSessionHasErrors(['authorization']);
    }

    /**
     * 4. Valid renewal workflow transitions and verified completion.
     */
    public function test_04_valid_renewal_workflow_transitions_and_completion(): void
    {
        $priorDoc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Licence_2026.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');

        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Harare Operating Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'reference_number' => 'COH-2026-100',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
            'current_document_id' => $priorDoc->id,
        ], $this->adminUser);

        // 1. Start renewal attempt
        $this->actingAs($this->staffUser)->post("/renew/instruments/{$inst->id}/attempts/start", [
            'notes' => 'Starting 2027 renewal preparation.',
        ])->assertRedirect();

        $attempt = RenewalAttempt::where('statutory_instrument_id', $inst->id)->firstOrFail();
        $this->assertEquals('RENEWAL_IN_PROGRESS', $attempt->workflow_status);
        $this->assertTrue($attempt->is_active_attempt);

        // 2. Transition RENEWAL_IN_PROGRESS -> READY_FOR_SUBMISSION
        $this->actingAs($this->staffUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'READY_FOR_SUBMISSION',
            'notes' => 'All inspection forms ready.',
        ])->assertRedirect();

        // 3. Transition READY_FOR_SUBMISSION -> DECLARED_SUBMITTED
        $this->actingAs($this->staffUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'DECLARED_SUBMITTED',
            'submission_reference' => 'COH-SUB-2026-991',
            'notes' => 'Submitted at Rowan Martin Building.',
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertEquals('DECLARED_SUBMITTED', $attempt->workflow_status);
        $this->assertEquals('COH-SUB-2026-991', $attempt->submission_reference);

        // 4. Transition DECLARED_SUBMITTED -> AWAITING_AUTHORITY_DECISION
        $this->actingAs($this->staffUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'AWAITING_AUTHORITY_DECISION',
            'authority_receipt_number' => 'COH-REC-5541',
        ])->assertRedirect();

        // 5. Link human-VERIFIED replacement certificate & transition to RENEWED by admin
        $replacementDoc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Licence_2027.pdf', 'VERIFIED', '2027-01-01', '2027-12-31');
        $this->actingAs($this->adminUser)->post("/renew/instruments/{$inst->id}/evidence/link", [
            'document_id' => $replacementDoc->id,
            'document_purpose' => RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT,
            'renewal_attempt_id' => $attempt->id,
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'RENEWED',
            'replacement_reference_number' => 'COH-2027-200',
            'replacement_issue_date' => '2027-01-01',
            'replacement_expiry_date' => '2027-12-31',
            'notes' => '2027 licence verified and issued.',
        ])->assertRedirect();

        $attempt->refresh();
        $inst->refresh();

        $this->assertFalse($attempt->is_active_attempt);
        $this->assertEquals('RENEWED', $attempt->workflow_status);
        $this->assertEquals('COH-2027-200', $inst->reference_number);
        $this->assertEquals('2027-12-31', $inst->expiry_date->toDateString());
        $this->assertEquals($replacementDoc->id, $inst->current_document_id);
        $this->assertEquals('ACTIVE', $inst->validity_status);
        $this->assertEquals('RENEWED', $inst->current_workflow_status);
    }

    /**
     * 5. Invalid renewal transitions blocked.
     */
    public function test_05_invalid_renewal_transitions_blocked(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Invalid Transition Test Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);

        // Cannot jump directly from RENEWAL_IN_PROGRESS to RENEWED without submission
        $res = $this->actingAs($this->adminUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'RENEWED',
        ]);
        $res->assertSessionHasErrors(['transition']);

        // Cannot transition to DECLARED_SUBMITTED without submission_reference or notes
        $resSub = $this->actingAs($this->adminUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'DECLARED_SUBMITTED',
        ]);
        $resSub->assertSessionHasErrors(['transition']);
    }

    /**
     * 6. Role-based permissions enforce reviewer/admin for sensitive transitions & assignments.
     */
    public function test_06_role_based_permissions_enforce_reviewer_admin_for_sensitive_actions(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'RBAC Test Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        // Regular staff cannot assign owner
        $this->actingAs($this->staffUser)->post("/renew/instruments/{$inst->id}/assign", [
            'assigned_to_user_id' => $this->staffUser->id,
        ])->assertSessionHasErrors(['authorization']);

        // Regular staff cannot suspend or cancel an instrument
        $this->actingAs($this->staffUser)->post("/renew/instruments/{$inst->id}/validity", [
            'validity_status' => 'SUSPENDED',
            'reason' => 'Attempted by staff.',
        ])->assertSessionHasErrors(['authorization']);

        // Regular staff cannot approve RENEWED or record REJECTED
        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->staffUser);
        $attempt->update(['workflow_status' => 'DECLARED_SUBMITTED', 'submission_reference' => 'REF-1']);

        $replacementDoc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Rep.pdf', 'VERIFIED', '2027-01-01', '2027-12-31');
        $this->renewalService->linkEvidence($inst, $replacementDoc, RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT, $this->staffUser, $attempt);

        $this->actingAs($this->staffUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'RENEWED',
        ])->assertSessionHasErrors(['transition']);

        $this->actingAs($this->staffUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'REJECTED',
            'rejection_reason' => 'Staff trying to reject.',
        ])->assertSessionHasErrors(['transition']);
    }

    /**
     * 7. Expiry and renewal window calculations across cadence types (Calendar-Year, Anniversary-Based, Fixed-Duration, Event-Driven).
     */
    public function test_07_expiry_and_renewal_window_calculations_across_cadence_types(): void
    {
        // A. Calendar-Year rule (expires Dec 31, window 60 days prior, deadline 15 days prior = Dec 16)
        $calRes = $this->deadlineCalculator->calculate(
            instrumentDates: ['issue_date' => '2026-03-10'],
            ruleVersion: $this->verifiedCalendarYearRenewalRule,
            referenceDate: '2026-11-15'
        );
        $this->assertEquals('2026-12-31', $calRes['expiry_date']);
        $this->assertEquals('2026-11-01', $calRes['renewal_window_opens_at']);
        $this->assertEquals('2026-12-16', $calRes['statutory_renewal_deadline']);
        $this->assertEquals('VERIFIED_OFFICIAL', $calRes['deadline_verification_status']);
        $this->assertEquals(46, $calRes['days_remaining']);
        $this->assertTrue($calRes['is_renewal_window_open']);

        // B. Anniversary-Based rule (12 months from issue_date 2026-04-01 -> expires 2027-03-31, deadline 30 days before)
        $anniversaryRule = RuleVersion::create([
            'rule_pack_id' => $this->renewalPack->id,
            'source_id' => $this->verifiedSource->id,
            'version_code' => 'SYNTHETIC_ANNIVERSARY_RULE',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'renewal_schedule' => [
                    'cadence_type' => 'ANNIVERSARY_BASED',
                    'validity_months' => 12,
                    'window_days_before_expiry' => 45,
                    'deadline_days_before_expiry' => 30,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $annRes = $this->deadlineCalculator->calculate(
            instrumentDates: ['issue_date' => '2026-04-01'],
            ruleVersion: $anniversaryRule,
            referenceDate: '2027-03-01'
        );
        $this->assertEquals('2027-03-31', $annRes['expiry_date']);
        $this->assertEquals('2027-03-01', $annRes['statutory_renewal_deadline']);

        // C. Fixed-Duration rule (90 days validity)
        $fixedDurationRule = RuleVersion::create([
            'rule_pack_id' => $this->renewalPack->id,
            'source_id' => $this->verifiedSource->id,
            'version_code' => 'SYNTHETIC_FIXED_DURATION_90D',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'renewal_schedule' => [
                    'cadence_type' => 'FIXED_DURATION',
                    'validity_days' => 90,
                    'deadline_days_before_expiry' => 7,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $fixedRes = $this->deadlineCalculator->calculate(
            instrumentDates: ['issue_date' => '2026-01-01'],
            ruleVersion: $fixedDurationRule,
            referenceDate: '2026-03-20'
        );
        $this->assertEquals('2026-04-01', $fixedRes['expiry_date']);
        $this->assertEquals('2026-03-25', $fixedRes['statutory_renewal_deadline']);

        // D. Event-Driven renewal rule (30 days after triggering event)
        $eventRule = RuleVersion::create([
            'rule_pack_id' => $this->renewalPack->id,
            'source_id' => $this->verifiedSource->id,
            'version_code' => 'SYNTHETIC_EVENT_RENEWAL_30D',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'renewal_schedule' => [
                    'cadence_type' => 'EVENT_DRIVEN',
                    'days_after_event' => 30,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $evRes = $this->deadlineCalculator->calculate(
            instrumentDates: ['trigger_event_date' => '2026-06-01'],
            ruleVersion: $eventRule,
            referenceDate: '2026-06-15'
        );
        $this->assertEquals('2026-07-01', $evRes['statutory_renewal_deadline']);
        $this->assertEquals('VERIFIED_OFFICIAL', $evRes['deadline_verification_status']);
    }

    /**
     * 8. Unknown expiry date and unknown statutory renewal deadline are never invented (Section 10.8, 10.18 & Safeguard E.6).
     */
    public function test_08_unknown_expiry_date_and_unknown_statutory_renewal_deadline_never_invented(): void
    {
        // Case A: Known expiry date, but NO approved renewal rule -> displays expiry date, statutory_renewal_deadline = null, DEADLINE_UNVERIFIED
        $instWithExpiryOnly = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Municipal Permit Without Verified Renewal Rule',
            'instrument_category' => 'PERMIT',
            'issuing_authority_name' => 'City of Harare',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $this->assertEquals('2026-12-31', $instWithExpiryOnly->expiry_date->toDateString());
        $this->assertNull($instWithExpiryOnly->statutory_renewal_deadline);
        $this->assertEquals('DEADLINE_UNVERIFIED', $instWithExpiryOnly->deadline_verification_status);
        $this->assertEquals('ACTIVE', $instWithExpiryOnly->validity_status);

        // Case B: Unknown expiry date AND no approved renewal rule -> validity = UNKNOWN, expiry_date = null, statutory_renewal_deadline = null
        $instUnknownBoth = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Historical Certificate With Unknown Expiry',
            'instrument_category' => 'CERTIFICATE',
            'issuing_authority_name' => 'Department of Works',
        ], $this->adminUser);

        $this->assertNull($instUnknownBoth->expiry_date);
        $this->assertNull($instUnknownBoth->statutory_renewal_deadline);
        $this->assertEquals('DEADLINE_UNVERIFIED', $instUnknownBoth->deadline_verification_status);
        $this->assertEquals('UNKNOWN', $instUnknownBoth->validity_status);
    }

    /**
     * 9. Calendar integration and event deduplication without suppressing distinct legal deadlines (Section 10.9 & Safeguard E.9).
     */
    public function test_09_calendar_integration_and_event_deduplication_without_suppressing_distinct_legal_deadlines(): void
    {
        // Create a FilingOccurrence due on 2026-12-31 for the same obligation instance
        FilingOccurrence::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'obligation_instance_id' => $this->licenceObligationInstance->id,
            'obligation_definition_id' => $this->licenceDefinition->id,
            'rule_version_id' => $this->verifiedCalendarYearRenewalRule->id,
            'schedule_identity' => 'RETURN',
            'filing_frequency' => 'ANNUAL',
            'filing_period_start' => '2026-01-01',
            'filing_period_end' => '2026-12-31',
            'filing_period_label' => 'FY 2026',
            'original_statutory_due_date' => '2026-12-31',
            'current_due_date' => '2026-12-31',
            'payment_due_date' => '2026-12-31',
            'timezone' => 'Africa/Harare',
            'workflow_status' => 'NOT_STARTED',
            'official_submission_status' => 'NOT_SUBMITTED',
            'verification_status' => 'VERIFIED_OFFICIAL',
        ]);

        // Register a StatutoryInstrument for the SAME obligation instance expiring on 2026-12-31 with verified renewal deadline 2026-12-16
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'City of Harare Shop Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'obligation_instance_id' => $this->licenceObligationInstance->id,
            'rule_version_id' => $this->verifiedCalendarYearRenewalRule->id,
            'reference_number' => 'COH-2026-777',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $calData = $this->calendarService->getCalendarForOrganisation($this->orgA1, ['month' => '2026-12'], '2026-12-01');
        $events = collect($calData['calendar_events']);

        // Confirm all 4 distinct legal event types exist without suppressing each other even when sharing 2026-12-31 and obligation_instance_id:
        $this->assertTrue($events->contains('event_type', 'FILING_DUE_DATE'));
        $this->assertTrue($events->contains('event_type', 'PAYMENT_DUE_DATE'));
        $this->assertTrue($events->contains('event_type', 'INSTRUMENT_EXPIRY_DATE'));
        $this->assertTrue($events->contains('event_type', 'RENEWAL_SUBMISSION_DEADLINE'));

        // Confirm no duplicate event_ids exist
        $this->assertEquals($events->count(), $events->pluck('event_id')->unique()->count());

        // Confirm no placeholder FilingOccurrence was created for the renewal
        $this->assertEquals(1, FilingOccurrence::where('organisation_id', $this->orgA1->id)->count());
    }

    /**
     * 10. Document version preservation and replacement certificate supersession history (Section 10.10, 10.12 & Safeguard E.10).
     */
    public function test_10_document_version_preservation_and_replacement_certificate_supersession_history(): void
    {
        $doc2026 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Cert_2026.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');
        $hash2026 = $doc2026->sha256_hash;

        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'ITF263 Tax Clearance',
            'instrument_category' => 'CLEARANCE',
            'issuing_authority_name' => 'ZIMRA',
            'reference_number' => 'ITF-2026-01',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
            'current_document_id' => $doc2026->id,
        ], $this->adminUser);

        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);
        $this->renewalService->transitionRenewalAttempt($attempt, 'DECLARED_SUBMITTED', $this->adminUser, [
            'submission_reference' => 'TARMS-REN-2027',
        ]);

        // Upload & verify 2027 replacement certificate
        $doc2027 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Cert_2027.pdf', 'VERIFIED', '2027-01-01', '2027-12-31');
        $this->renewalService->linkEvidence($inst, $doc2027, RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT, $this->adminUser, $attempt);

        $this->renewalService->transitionRenewalAttempt($attempt, 'RENEWED', $this->adminUser, [
            'replacement_reference_number' => 'ITF-2027-02',
            'replacement_issue_date' => '2027-01-01',
            'replacement_expiry_date' => '2027-12-31',
        ]);

        // Prior 2026 certificate still exists in vault with intact SHA-256 hash and verification_status = SUPERSEDED
        $doc2026->refresh();
        $this->assertEquals($hash2026, $doc2026->sha256_hash);
        $this->assertEquals('SUPERSEDED', $doc2026->verification_status);

        // Prior link is marked PRIOR_INSTRUMENT, is_superseded = true, superseded_by_document_id = doc2027->id
        $this->assertDatabaseHas('renewal_evidence_links', [
            'statutory_instrument_id' => $inst->id,
            'compliance_document_id' => $doc2026->id,
            'document_purpose' => RenewalEvidenceLink::PURPOSE_PRIOR_INSTRUMENT,
            'is_superseded' => true,
            'superseded_by_document_id' => $doc2027->id,
        ]);

        // Superseded historical link cannot be unlinked/deleted
        $supersededLink = RenewalEvidenceLink::where('compliance_document_id', $doc2026->id)->firstOrFail();
        $this->expectException(\InvalidArgumentException::class);
        $this->renewalService->unlinkEvidence($inst, $supersededLink, $this->adminUser);
    }

    /**
     * 11. Rejected renewal followed by a new authorised attempt (Section 10.11 & Safeguard E.3).
     */
    public function test_11_rejected_renewal_followed_by_new_authorised_attempt(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Fire Clearance Certificate',
            'instrument_category' => 'CERTIFICATE',
            'issuing_authority_name' => 'Harare Fire Brigade',
            'reference_number' => 'FIRE-2026-01',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        // Attempt #1 is submitted and then REJECTED
        $attempt1 = $this->renewalService->startRenewalAttempt($inst, $this->staffUser);
        $this->assertEquals(1, $attempt1->attempt_number);

        $this->renewalService->transitionRenewalAttempt($attempt1, 'DECLARED_SUBMITTED', $this->staffUser, [
            'submission_reference' => 'FIRE-SUB-01',
        ]);

        $rejectionLetter = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Fire_Inspection_Query.pdf', 'VERIFIED', null, null);
        $this->renewalService->linkEvidence($inst, $rejectionLetter, RenewalEvidenceLink::PURPOSE_REJECTION_CORRESPONDENCE, $this->adminUser, $attempt1);

        $this->renewalService->transitionRenewalAttempt($attempt1, 'REJECTED', $this->adminUser, [
            'rejection_reason' => 'Extinguisher pressure gauges in Bay 2 required recalibration.',
        ]);

        $attempt1->refresh();
        $inst->refresh();
        $this->assertFalse($attempt1->is_active_attempt);
        $this->assertEquals('REJECTED', $attempt1->workflow_status);
        $this->assertEquals('REJECTED', $inst->current_workflow_status);

        // Now start Attempt #2 while preserving Attempt #1 in history
        $attempt2 = $this->renewalService->startRenewalAttempt($inst, $this->staffUser, [
            'notes' => 'Re-applying after servicing Bay 2 extinguishers.',
        ]);

        $this->assertEquals(2, $attempt2->attempt_number);
        $this->assertTrue($attempt2->is_active_attempt);
        $this->assertEquals(2, RenewalAttempt::where('statutory_instrument_id', $inst->id)->count());
    }

    /**
     * 12. Expired evidence recalculation & expiry during a pending renewal application (Section 10.13 & Safeguard E.5).
     */
    public function test_12_expired_evidence_recalculation_and_expiry_during_pending_renewal_application(): void
    {
        $doc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Licence_Expiring_Oct2026.pdf', 'VERIFIED', '2025-11-01', '2026-10-31');

        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Permit Expiring Oct 2026',
            'instrument_category' => 'PERMIT',
            'issuing_authority_name' => 'City of Harare',
            'obligation_instance_id' => $this->licenceObligationInstance->id,
            'issue_date' => '2025-11-01',
            'expiry_date' => '2026-10-31',
            'current_document_id' => $doc->id,
            'reference_date' => '2026-10-15',
        ], $this->adminUser);

        $this->assertEquals('ACTIVE', $inst->validity_status);

        // Start renewal and submit BEFORE expiry (on 2026-10-20)
        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->staffUser);
        $this->renewalService->transitionRenewalAttempt($attempt, 'DECLARED_SUBMITTED', $this->staffUser, [
            'submission_reference' => 'PENDING-SUB-2026',
            'reference_date' => '2026-10-20',
        ]);
        $this->renewalService->transitionRenewalAttempt($attempt, 'AWAITING_AUTHORITY_DECISION', $this->staffUser, [
            'authority_receipt_number' => 'REC-2026-10',
            'reference_date' => '2026-10-25',
        ]);

        // Now advance date to 2026-11-03 (past 2026-10-31 expiry) while renewal is STILL AWAITING_AUTHORITY_DECISION
        $expiredCount = $this->renewalService->recalculateExpirationsForOrganisation($this->orgA1, '2026-11-03');
        $this->assertEquals(1, $expiredCount);

        $inst->refresh();
        $attempt->refresh();
        $doc->refresh();
        $this->licenceObligationInstance->refresh();

        // Pending renewal application MUST NOT extend validity of the expired licence!
        $this->assertEquals('EXPIRED', $inst->validity_status);
        $this->assertEquals('AWAITING_AUTHORITY_DECISION', $inst->current_workflow_status);
        $this->assertEquals('EXPIRED', $doc->verification_status);
        $this->assertEquals('EXPIRED_EVIDENCE', $this->licenceObligationInstance->evidence_status);
    }

    /**
     * 13. Prevention of simultaneous duplicate active renewal attempts (Section 10.14 & Safeguard E.8).
     */
    public function test_13_prevention_of_simultaneous_duplicate_active_renewal_attempts(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Duplicate Prevention Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $attempt1 = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);
        $this->assertTrue($attempt1->is_active_attempt);

        // Service-level check blocks starting a second active attempt
        $this->actingAs($this->adminUser)->post("/renew/instruments/{$inst->id}/attempts/start")
            ->assertSessionHasErrors(['transition']);

        $this->assertEquals(1, RenewalAttempt::where('statutory_instrument_id', $inst->id)->count());

        // Database-level unique constraint (statutory_instrument_id, active_attempt_guard) also blocks raw duplicate active insert
        $this->expectException(QueryException::class);
        RenewalAttempt::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA1->id,
            'statutory_instrument_id' => $inst->id,
            'attempt_number' => 2,
            'is_active_attempt' => true,
            'active_attempt_guard' => 'ACTIVE',
            'workflow_status' => 'RENEWAL_IN_PROGRESS',
        ]);
    }

    /**
     * 14. Multiple successive renewals of a single instrument (Safeguard E.2).
     */
    public function test_14_multiple_successive_renewals_of_single_instrument(): void
    {
        $doc2025 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'PRAZ_2025.pdf', 'VERIFIED', '2025-01-01', '2025-12-31');

        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'PRAZ Annual Supplier Registration',
            'instrument_category' => 'REGISTRATION',
            'issuing_authority_name' => 'PRAZ',
            'reference_number' => 'PRAZ-2025-01',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2025-12-31',
            'current_document_id' => $doc2025->id,
        ], $this->adminUser);

        // Cycle 1: Renew for 2026
        $attempt1 = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);
        $this->renewalService->transitionRenewalAttempt($attempt1, 'DECLARED_SUBMITTED', $this->adminUser, ['submission_reference' => 'PRAZ-SUB-2026']);
        $doc2026 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'PRAZ_2026.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');
        $this->renewalService->linkEvidence($inst, $doc2026, RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT, $this->adminUser, $attempt1);
        $this->renewalService->transitionRenewalAttempt($attempt1, 'RENEWED', $this->adminUser, [
            'replacement_reference_number' => 'PRAZ-2026-02',
            'replacement_issue_date' => '2026-01-01',
            'replacement_expiry_date' => '2026-12-31',
        ]);

        // Cycle 2: Renew for 2027 on the SAME instrument
        $inst->refresh();
        $attempt2 = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);
        $this->assertEquals(2, $attempt2->attempt_number);
        $this->assertEquals('PRAZ-2026-02', $attempt2->prior_reference_number);

        $this->renewalService->transitionRenewalAttempt($attempt2, 'DECLARED_SUBMITTED', $this->adminUser, ['submission_reference' => 'PRAZ-SUB-2027']);
        $doc2027 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'PRAZ_2027.pdf', 'VERIFIED', '2027-01-01', '2027-12-31');
        $this->renewalService->linkEvidence($inst, $doc2027, RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT, $this->adminUser, $attempt2);
        $this->renewalService->transitionRenewalAttempt($attempt2, 'RENEWED', $this->adminUser, [
            'replacement_reference_number' => 'PRAZ-2027-03',
            'replacement_issue_date' => '2027-01-01',
            'replacement_expiry_date' => '2027-12-31',
        ]);

        $inst->refresh();
        $this->assertEquals('PRAZ-2027-03', $inst->reference_number);
        $this->assertEquals('2027-12-31', $inst->expiry_date->toDateString());
        $this->assertEquals($doc2027->id, $inst->current_document_id);
        $this->assertCount(2, $inst->renewalAttempts);
    }

    /**
     * 15. Suspension or cancellation without deleting history (Safeguard E.4).
     */
    public function test_15_suspension_and_cancellation_without_deleting_history(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Environmental Discharge Permit',
            'instrument_category' => 'PERMIT',
            'issuing_authority_name' => 'EMA',
            'reference_number' => 'EMA-2026-55',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);

        // Suspend instrument
        $this->actingAs($this->adminUser)->post("/renew/instruments/{$inst->id}/validity", [
            'validity_status' => 'SUSPENDED',
            'reason' => 'Temporary suspension notice pending effluent filter inspection.',
        ])->assertRedirect();

        $inst->refresh();
        $this->assertEquals('SUSPENDED', $inst->validity_status);

        // Cancel instrument -> closes active renewal attempt without deleting history
        $this->actingAs($this->adminUser)->post("/renew/instruments/{$inst->id}/validity", [
            'validity_status' => 'CANCELLED',
            'reason' => 'Decommissioned discharge point permanently.',
        ])->assertRedirect();

        $inst->refresh();
        $attempt->refresh();
        $this->assertEquals('CANCELLED', $inst->validity_status);
        $this->assertFalse($attempt->is_active_attempt);
        $this->assertEquals('CANCELLED', $attempt->workflow_status);
        $this->assertGreaterThanOrEqual(3, $inst->activityLogs()->count());
    }

    /**
     * 16. Multiple supporting evidence documents for one renewal (Safeguard E.7).
     */
    public function test_16_multiple_supporting_evidence_documents_for_one_renewal(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Multi-Document Renewal Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->staffUser);

        $appForm = $this->createVaultDocument($this->orgA1, $this->staffUser, 'Application_Form.pdf', 'VERIFIED');
        $fireClearance = $this->createVaultDocument($this->orgA1, $this->staffUser, 'Fire_Clearance.pdf', 'VERIFIED');
        $receiptDoc = $this->createVaultDocument($this->orgA1, $this->staffUser, 'Payment_Receipt.pdf', 'VERIFIED');

        $this->renewalService->linkEvidence($inst, $appForm, RenewalEvidenceLink::PURPOSE_APPLICATION_SUPPORTING, $this->staffUser, $attempt);
        $this->renewalService->linkEvidence($inst, $fireClearance, RenewalEvidenceLink::PURPOSE_APPLICATION_SUPPORTING, $this->staffUser, $attempt);
        $this->renewalService->linkEvidence($inst, $receiptDoc, RenewalEvidenceLink::PURPOSE_AUTHORITY_RECEIPT, $this->staffUser, $attempt);

        $this->assertEquals(3, RenewalEvidenceLink::where('renewal_attempt_id', $attempt->id)->count());
    }

    /**
     * 17. No automatic renewal confirmation solely because a user uploaded or linked a replacement document (Section 10.17).
     */
    public function test_17_no_automatic_renewal_confirmation_when_replacement_document_uploaded(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'No Auto-Renewal Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2025-12-31',
            'reference_date' => '2026-11-03',
        ], $this->adminUser);

        $this->assertEquals('EXPIRED', $inst->validity_status);

        $attempt = $this->renewalService->startRenewalAttempt($inst, $this->staffUser);
        $this->renewalService->transitionRenewalAttempt($attempt, 'DECLARED_SUBMITTED', $this->staffUser, [
            'submission_reference' => 'SUB-REF-100',
        ]);

        // Upload and link a replacement document (even a VERIFIED one)
        $repDoc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Uploaded_Replacement_2026.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');
        $this->renewalService->linkEvidence($inst, $repDoc, RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT, $this->staffUser, $attempt);

        $inst->refresh();
        $attempt->refresh();

        // Neither the attempt nor the instrument becomes RENEWED or ACTIVE automatically!
        $this->assertEquals('DECLARED_SUBMITTED', $attempt->workflow_status);
        $this->assertEquals('EXPIRED', $inst->validity_status);

        // Furthermore, if the replacement document is still PENDING human review, even an admin cannot transition to RENEWED
        $repDoc->update(['verification_status' => 'PENDING']);
        $this->actingAs($this->adminUser)->post("/renew/attempts/{$attempt->id}/transition", [
            'status' => 'RENEWED',
        ])->assertSessionHasErrors(['transition']);
    }

    /**
     * 18. Historical audit integrity and versioned metadata corrections (Section 10.15).
     */
    public function test_18_historical_audit_integrity_and_versioned_metadata_corrections(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Audited Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'reference_number' => 'TYPO-REF-01',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $this->assertEquals(1, $inst->metadata_version);

        $this->actingAs($this->adminUser)->post("/renew/instruments/{$inst->id}/metadata", [
            'reference_number' => 'CORRECTED-REF-01',
            'correction_reason' => 'Corrected reference number transcription error from original physical licence.',
            'lock_version' => $inst->lock_version,
        ])->assertRedirect();

        $inst->refresh();
        $this->assertEquals('CORRECTED-REF-01', $inst->reference_number);
        $this->assertEquals(2, $inst->metadata_version);

        $this->assertDatabaseHas('renewal_activity_logs', [
            'statutory_instrument_id' => $inst->id,
            'activity_type' => 'METADATA_CORRECTED',
        ]);
    }

    /**
     * 19. Concurrent update protection via optimistic locking (Section 10.16).
     */
    public function test_19_concurrent_update_protection_via_optimistic_locking(): void
    {
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Concurrency Test Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $staleLockVersion = $inst->lock_version;

        // First update increments lock_version
        $this->renewalService->assignOwner($inst, $this->staffUser, $this->adminUser, $staleLockVersion);

        // Second update with stale lock_version fails with concurrency error
        $this->actingAs($this->adminUser)->post("/renew/instruments/{$inst->id}/assign", [
            'assigned_to_user_id' => $this->adminUser->id,
            'lock_version' => $staleLockVersion,
        ])->assertSessionHasErrors(['concurrency']);
    }

    /**
     * 20. Revoked access prevents evidence preview and workflow actions (Safeguard E.11).
     */
    public function test_20_revoked_access_prevents_evidence_preview_and_workflow_actions(): void
    {
        $doc = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Restricted_Cert.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Revoked Access Test Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'current_document_id' => $doc->id,
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        // Revoke staffUser's membership in orgA1
        Membership::where('organisation_id', $this->orgA1->id)
            ->where('user_id', $this->staffUser->id)
            ->update(['status' => 'REVOKED']);

        // 1. Cannot view instrument detail
        $this->actingAs($this->staffUser)->getJson("/renew/instruments/{$inst->id}")
            ->assertStatus(403);

        // 2. Cannot start renewal workflow
        $this->actingAs($this->staffUser)->post("/renew/instruments/{$inst->id}/attempts/start")
            ->assertSessionHasErrors(['authorization']);

        // 3. Cannot preview or download linked vault document
        $this->actingAs($this->staffUser)->get("/documents/{$doc->id}/preview")
            ->assertStatus(403);
    }

    /**
     * 21. Regulatory rule amendments update future deadlines without historical date corruption (Safeguard E.12).
     */
    public function test_21_regulatory_rule_amendments_update_future_deadlines_without_historical_corruption(): void
    {
        $doc2025 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Hist_2025.pdf', 'VERIFIED', '2025-01-01', '2025-12-31');

        // Historical completed renewal from 2025 -> 2026 under V1 rule (deadline_days_before_expiry = 15 -> 2026-12-16)
        $inst = $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Amended Rule Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'rule_version_id' => $this->verifiedCalendarYearRenewalRule->id,
            'issue_date' => '2025-01-01',
            'expiry_date' => '2025-12-31',
            'current_document_id' => $doc2025->id,
        ], $this->adminUser);

        $attempt2025 = $this->renewalService->startRenewalAttempt($inst, $this->adminUser);
        $this->renewalService->transitionRenewalAttempt($attempt2025, 'DECLARED_SUBMITTED', $this->adminUser, ['submission_reference' => 'SUB-2025']);
        $doc2026 = $this->createVaultDocument($this->orgA1, $this->adminUser, 'Hist_2026.pdf', 'VERIFIED', '2026-01-01', '2026-12-31');
        $this->renewalService->linkEvidence($inst, $doc2026, RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT, $this->adminUser, $attempt2025);
        $this->renewalService->transitionRenewalAttempt($attempt2025, 'RENEWED', $this->adminUser, [
            'replacement_reference_number' => 'COH-2026-AMEND',
            'replacement_issue_date' => '2026-01-01',
            'replacement_expiry_date' => '2026-12-31',
        ]);

        $attempt2025->refresh();
        $historicalAttemptDeadline = $attempt2025->statutory_renewal_deadline->toDateString();

        // Now enact a new RuleVersion effective 2026-06-01 changing deadline_days_before_expiry from 15 to 30 (2026-12-01)
        $amendedRule = RuleVersion::create([
            'rule_pack_id' => $this->renewalPack->id,
            'source_id' => $this->verifiedSource->id,
            'version_code' => 'SYNTHETIC_COH_RENEWAL_2026_V2_AMENDED',
            'effective_from' => '2026-06-01',
            'status' => 'ACTIVE',
            'verification_status' => 'VERIFIED_OFFICIAL',
            'parameters' => [
                'renewal_schedule' => [
                    'cadence_type' => 'CALENDAR_YEAR',
                    'calendar_expiry_month' => 12,
                    'calendar_expiry_day' => 31,
                    'window_days_before_expiry' => 60,
                    'deadline_days_before_expiry' => 30,
                ],
            ],
            'approved_at' => Carbon::now(),
        ]);

        $this->renewalService->synchronizeRuleAmendmentForOrganisation($this->orgA1, $amendedRule, '2026-06-01');

        $inst->refresh();
        $attempt2025->refresh();

        // Instrument's future renewal deadline updates to 2026-12-01 while preserving its issue_date and expiry_date
        $this->assertEquals('2026-12-01', $inst->statutory_renewal_deadline->toDateString());
        $this->assertEquals('2026-12-31', $inst->expiry_date->toDateString());

        // Historical completed attempt2025 retains its original deadline snapshot and rule_version_id without corruption
        $this->assertEquals($historicalAttemptDeadline, $attempt2025->statutory_renewal_deadline->toDateString());
        $this->assertEquals($this->verifiedCalendarYearRenewalRule->id, $attempt2025->rule_version_id);
    }

    /**
     * 22. Renew Workspace Inertia page rendering and filtering.
     */
    public function test_22_renew_workspace_inertia_rendering_and_filtering(): void
    {
        $this->renewalService->registerInstrument($this->orgA1, [
            'title' => 'Harare Shop Licence',
            'instrument_category' => 'LICENCE',
            'issuing_authority_name' => 'City of Harare',
            'expiry_date' => '2026-12-31',
        ], $this->adminUser);

        $res = $this->actingAs($this->adminUser)->get('/renew?category=LICENCE');
        $res->assertStatus(200);
        $res->assertInertia(fn ($page) => $page
            ->component('Renew/Index')
            ->has('instruments', 1)
            ->has('metrics')
            ->has('authorities')
            ->has('obligations')
            ->has('vault_documents')
            ->where('filters.category', 'LICENCE')
        );
    }
}
