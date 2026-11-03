<?php

namespace App\Http\Controllers;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Models\RenewalAttempt;
use App\Domains\ComplianceEngine\Models\RenewalEvidenceLink;
use App\Domains\ComplianceEngine\Models\StatutoryInstrument;
use App\Domains\ComplianceEngine\Services\RenewalDeadlineCalculator;
use App\Domains\ComplianceEngine\Services\RenewalManagementService;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Models\User;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class RenewalManagementController extends Controller
{
    public function __construct(
        protected RenewalManagementService $renewalService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Render the Statutory Licence, Certificate & Registration Renewal Workspace.
     */
    public function index(Request $request): Response
    {
        $org = $this->tenantContext->getOrganisation();
        $user = $request->user();

        if (!$org) {
            return Inertia::render('Renew/Index', [
                'instruments' => [],
                'metrics' => [
                    'total' => 0,
                    'active_instruments' => 0,
                    'expiring_soon' => 0,
                    'expired_instruments' => 0,
                    'renewals_in_progress' => 0,
                    'awaiting_authority_decision' => 0,
                    'completed_renewals' => 0,
                    'suspended_or_cancelled' => 0,
                    'deadline_unverified' => 0,
                ],
                'members' => [],
                'filters' => [],
                'authorities' => [],
                'obligations' => [],
                'vault_documents' => [],
                'supported_categories' => RenewalManagementService::SUPPORTED_CATEGORIES,
                'supported_purposes' => RenewalEvidenceLink::SUPPORTED_PURPOSES,
                'can_manage' => false,
                'reference_date' => Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->toDateString(),
                'timezone' => RenewalDeadlineCalculator::TIMEZONE,
            ]);
        }

        $membership = Membership::where('organisation_id', $org->id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership || $membership->tenant_id !== $org->tenant_id) {
            abort(403, 'Unauthorized access: You do not have active membership in the selected organisation.');
        }

        $filters = $request->only([
            'category',
            'authority',
            'validity_status',
            'workflow_status',
            'responsible_user',
            'tab',
            'search',
        ]);

        $registerData = $this->renewalService->getRegisterForOrganisation($org, $filters);

        $authorities = RegulatoryAuthority::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $obligations = ObligationInstance::withoutGlobalScopes()
            ->where('tenant_id', $org->tenant_id)
            ->where('organisation_id', $org->id)
            ->with(['definition.authority'])
            ->get()
            ->map(fn (ObligationInstance $ob) => [
                'id' => $ob->id,
                'code' => $ob->definition?->code ?? 'OBLIGATION',
                'title' => $ob->definition?->title ?? 'Statutory Obligation',
                'authority_name' => $ob->definition?->authority?->name ?? 'Statutory Authority',
                'authority_id' => $ob->definition?->authority_id,
            ])
            ->values();

        $vaultDocs = ComplianceDocument::withoutGlobalScopes()
            ->where('tenant_id', $org->tenant_id)
            ->where('organisation_id', $org->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ComplianceDocument $doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'file_name' => $doc->file_name,
                'document_type' => $doc->document_type,
                'verification_status' => $doc->verification_status,
                'issue_date' => $doc->issue_date?->toDateString(),
                'expiry_date' => $doc->expiry_date?->toDateString(),
            ])
            ->values();

        $canManage = in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'], true);

        return Inertia::render('Renew/Index', array_merge($registerData, [
            'authorities' => $authorities,
            'obligations' => $obligations,
            'vault_documents' => $vaultDocs,
            'supported_categories' => RenewalManagementService::SUPPORTED_CATEGORIES,
            'supported_purposes' => RenewalEvidenceLink::SUPPORTED_PURPOSES,
            'can_manage' => $canManage,
        ]));
    }

    /**
     * Get detailed JSON payload for a single StatutoryInstrument (attempts, evidence links, available transitions, audit trail).
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        $detail = $this->renewalService->getInstrumentDetail($instrument, $request->user());

        return response()->json($detail);
    }

    /**
     * Register an existing or new statutory instrument in the organisation's renewal register.
     */
    public function store(Request $request): RedirectResponse
    {
        $org = $this->tenantContext->getOrganisation();
        if (!$org) {
            return back()->withErrors(['organisation' => 'No active organisation selected.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instrument_category' => ['required', 'string', 'max:32'],
            'issuing_authority_name' => ['required', 'string', 'max:255'],
            'regulatory_authority_id' => ['nullable', 'uuid'],
            'obligation_instance_id' => ['nullable', 'uuid'],
            'obligation_definition_id' => ['nullable', 'uuid'],
            'rule_version_id' => ['nullable', 'uuid'],
            'reference_number' => ['nullable', 'string', 'max:128'],
            'premises_or_scope' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'current_document_id' => ['nullable', 'uuid'],
            'assigned_to_user_id' => ['nullable', 'uuid'],
            'source_citation' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $instrument = $this->renewalService->registerInstrument(
                organisation: $org,
                data: $validated,
                actor: $request->user()
            );

            return back()->with(
                'success',
                "Statutory instrument [{$instrument->title}] registered in the renewal register."
            );
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['registration' => $e->getMessage()]);
        }
    }

    /**
     * Perform a versioned, audited correction to instrument metadata.
     */
    public function updateMetadata(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:128'],
            'premises_or_scope' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'source_citation' => ['nullable', 'string', 'max:255'],
            'rule_version_id' => ['nullable', 'uuid'],
            'correction_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $this->renewalService->updateInstrumentMetadata(
                instrument: $instrument,
                data: $validated,
                actor: $request->user(),
                lockVersion: $validated['lock_version'] ?? null
            );

            return back()->with('success', 'Statutory instrument metadata updated and recorded in audit trail.');
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['metadata' => $e->getMessage()]);
        }
    }

    /**
     * Assign or reassign a responsible owner for an instrument and its active renewal attempt.
     */
    public function assign(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'uuid'],
            'lock_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        $assignee = null;
        if (!empty($validated['assigned_to_user_id'])) {
            $assignee = User::findOrFail($validated['assigned_to_user_id']);
        }

        try {
            $this->renewalService->assignOwner(
                instrument: $instrument,
                assignee: $assignee,
                actor: $request->user(),
                lockVersion: $validated['lock_version'] ?? null,
                notes: $validated['notes'] ?? null
            );

            return back()->with(
                'success',
                $assignee
                    ? "Renewal responsibility assigned to {$assignee->name}."
                    : 'Renewal responsibility unassigned.'
            );
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        }
    }

    /**
     * Start a new renewal application attempt for a StatutoryInstrument.
     */
    public function startAttempt(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'initial_status' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $attempt = $this->renewalService->startRenewalAttempt(
                instrument: $instrument,
                actor: $request->user(),
                data: $validated,
                lockVersion: $validated['lock_version'] ?? null
            );

            return back()->with(
                'success',
                "Started renewal application attempt #{$attempt->attempt_number} for [{$instrument->title}]."
            );
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        }
    }

    /**
     * Transition a RenewalAttempt through the renewal workflow state machine.
     */
    public function transitionAttempt(string $attemptId, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:40'],
            'submission_reference' => ['nullable', 'string', 'max:128'],
            'authority_receipt_number' => ['nullable', 'string', 'max:128'],
            'replacement_reference_number' => ['nullable', 'string', 'max:128'],
            'replacement_issue_date' => ['nullable', 'date'],
            'replacement_expiry_date' => ['nullable', 'date'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $attempt = RenewalAttempt::withoutGlobalScopes()->where('id', $attemptId)->firstOrFail();

        try {
            $this->renewalService->transitionRenewalAttempt(
                attempt: $attempt,
                targetStatus: $validated['status'],
                actor: $request->user(),
                data: $validated,
                lockVersion: $validated['lock_version'] ?? null
            );

            return back()->with('success', "Renewal application transitioned to [{$validated['status']}].");
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        }
    }

    /**
     * Link a vault document to a StatutoryInstrument / RenewalAttempt with explicit purpose.
     */
    public function linkEvidence(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_id' => ['required', 'uuid'],
            'document_purpose' => ['required', 'string', 'max:48'],
            'renewal_attempt_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();
        $document = ComplianceDocument::withoutGlobalScopes()->where('id', $validated['document_id'])->firstOrFail();
        $attempt = !empty($validated['renewal_attempt_id'])
            ? RenewalAttempt::withoutGlobalScopes()->where('id', $validated['renewal_attempt_id'])->firstOrFail()
            : null;

        try {
            $this->renewalService->linkEvidence(
                instrument: $instrument,
                document: $document,
                purpose: $validated['document_purpose'],
                actor: $request->user(),
                attempt: $attempt,
                notes: $validated['notes'] ?? null
            );

            return back()->with('success', "Document [{$document->file_name}] linked as [{$validated['document_purpose']}].");
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }
    }

    /**
     * Unlink a non-superseded evidence link from a StatutoryInstrument.
     */
    public function unlinkEvidence(string $id, string $linkId, Request $request): RedirectResponse
    {
        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();
        $link = RenewalEvidenceLink::withoutGlobalScopes()->where('id', $linkId)->firstOrFail();

        try {
            $this->renewalService->unlinkEvidence($instrument, $link, $request->user());
            return back()->with('success', 'Evidence document unlinked.');
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }
    }

    /**
     * Suspend, cancel, or restore a StatutoryInstrument's validity status without deleting history.
     */
    public function changeValidity(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'validity_status' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $instrument = StatutoryInstrument::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $this->renewalService->changeInstrumentValidityStatus(
                instrument: $instrument,
                targetValidity: $validated['validity_status'],
                actor: $request->user(),
                reason: $validated['reason'],
                lockVersion: $validated['lock_version'] ?? null
            );

            return back()->with('success', "Instrument validity status updated to [{$validated['validity_status']}].");
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['validity' => $e->getMessage()]);
        }
    }
}

