<?php

namespace App\Http\Controllers;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use App\Domains\ComplianceEngine\Services\FilingCalendarService;
use App\Domains\ComplianceEngine\Services\FilingDeadlineCalculator;
use App\Domains\ComplianceEngine\Services\FilingScheduleGenerator;
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

class ComplianceCalendarController extends Controller
{
    public function __construct(
        protected FilingCalendarService $calendarService,
        protected FilingScheduleGenerator $scheduleGenerator,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Render the Statutory Filing Calendar & Deadline Engine workspace.
     */
    public function index(Request $request): Response
    {
        $org = $this->tenantContext->getOrganisation();
        $user = $request->user();

        if (!$org) {
            return Inertia::render('Comply/Calendar', [
                'occurrences' => [],
                'metrics' => [
                    'total' => 0,
                    'upcoming' => 0,
                    'due_soon' => 0,
                    'due_today' => 0,
                    'overdue' => 0,
                    'completed' => 0,
                    'deadline_unverified' => 0,
                    'submitted_internal' => 0,
                    'officially_acknowledged' => 0,
                ],
                'members' => [],
                'filters' => [],
                'authorities' => [],
                'obligations' => [],
                'can_manage' => false,
                'reference_date' => Carbon::now(FilingDeadlineCalculator::TIMEZONE)->toDateString(),
                'timezone' => FilingDeadlineCalculator::TIMEZONE,
            ]);
        }

        // Verify organisation membership
        $membership = Membership::where('organisation_id', $org->id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership) {
            abort(403, 'Unauthorized access: You do not have active membership in the selected organisation.');
        }

        $filters = $request->only([
            'month',
            'authority',
            'obligation',
            'responsible_user',
            'workflow_status',
            'classification',
            'search',
            'view',
        ]);

        $calendarData = $this->calendarService->getCalendarForOrganisation($org, $filters);

        $authorities = RegulatoryAuthority::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $obligations = ObligationInstance::withoutGlobalScopes()
            ->where('organisation_id', $org->id)
            ->with('definition')
            ->get()
            ->map(fn (ObligationInstance $inst) => [
                'id' => $inst->id,
                'code' => $inst->definition?->code ?? 'OBLIGATION',
                'title' => $inst->definition?->title ?? 'Statutory Obligation',
            ]);

        $canManage = in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'], true);

        return Inertia::render('Comply/Calendar', array_merge($calendarData, [
            'authorities' => $authorities,
            'obligations' => $obligations,
            'can_manage' => $canManage,
        ]));
    }

    /**
     * Get single filing occurrence detail with period documents, calculation basis, and audit history.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $occurrence = FilingOccurrence::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        $detail = $this->calendarService->getOccurrenceDetail($occurrence, $request->user());

        return response()->json($detail);
    }

    /**
     * Trigger idempotent filing schedule generation for the active organisation.
     */
    public function generate(Request $request): RedirectResponse
    {
        $org = $this->tenantContext->getOrganisation();
        $user = $request->user();

        if (!$org) {
            return back()->withErrors(['organisation' => 'No active organisation selected.']);
        }

        $membership = Membership::where('organisation_id', $org->id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership || !in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER', 'ACCOUNTANT'], true)) {
            return back()->withErrors(['authorization' => 'Insufficient privileges to generate statutory schedules.']);
        }

        $validated = $request->validate([
            'start_from_date' => ['nullable', 'date'],
            'horizon_months' => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);

        $horizon = (int) ($validated['horizon_months'] ?? FilingScheduleGenerator::DEFAULT_HORIZON_MONTHS);
        $startFrom = $validated['start_from_date'] ?? null;

        $stats = $this->scheduleGenerator->generateForOrganisation(
            organisation: $org,
            startFromDate: $startFrom,
            horizonMonths: $horizon,
            triggeredByUserId: $user->id
        );

        return back()->with(
            'success',
            "Statutory schedule synchronized for {$horizon}-month horizon ({$stats['generated_count']} new occurrences created, {$stats['updated_count']} updated)."
        );
    }

    /**
     * Assign or reassign a filing occurrence to an organisation member.
     */
    public function assign(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'uuid'],
            'lock_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $occurrence = FilingOccurrence::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        $targetUser = null;
        if (!empty($validated['assigned_to_user_id'])) {
            $targetUser = User::findOrFail($validated['assigned_to_user_id']);
        }

        try {
            $this->calendarService->assignOccurrence(
                occurrence: $occurrence,
                assignee: $targetUser,
                actor: $request->user(),
                lockVersion: $validated['lock_version'] ?? null,
                notes: $validated['notes'] ?? null
            );

            return back()->with(
                'success',
                $targetUser
                    ? "Filing occurrence assigned to {$targetUser->name}."
                    : 'Filing occurrence responsibility unassigned.'
            );
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        }
    }

    /**
     * Transition a filing occurrence's internal workflow status.
     */
    public function transition(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:32'],
            'lock_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_reference' => ['nullable', 'string', 'max:128'],
            'official_receipt_number' => ['nullable', 'string', 'max:128'],
        ]);

        $occurrence = FilingOccurrence::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $this->calendarService->transitionOccurrenceStatus(
                occurrence: $occurrence,
                targetStatus: $validated['status'],
                actor: $request->user(),
                lockVersion: $validated['lock_version'] ?? null,
                notes: $validated['notes'] ?? null,
                submissionReference: $validated['submission_reference'] ?? null,
                officialReceiptNumber: $validated['official_receipt_number'] ?? null
            );

            return back()->with('success', "Filing occurrence status transitioned to [{$validated['status']}].");
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        }
    }

    /**
     * Link a vault document to a specific filing occurrence period.
     */
    public function linkEvidence(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_id' => ['required', 'uuid'],
        ]);

        $occurrence = FilingOccurrence::withoutGlobalScopes()->where('id', $id)->firstOrFail();
        $document = ComplianceDocument::withoutGlobalScopes()->where('id', $validated['document_id'])->firstOrFail();

        try {
            $this->calendarService->linkEvidenceToOccurrence($occurrence, $document, $request->user());
            return back()->with('success', "Document [{$document->file_name}] linked to filing period {$occurrence->filing_period_label}.");
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }
    }

    /**
     * Unlink a vault document from a filing occurrence.
     */
    public function unlinkEvidence(string $id, string $documentId, Request $request): RedirectResponse
    {
        $occurrence = FilingOccurrence::withoutGlobalScopes()->where('id', $id)->firstOrFail();
        $document = ComplianceDocument::withoutGlobalScopes()->where('id', $documentId)->firstOrFail();

        try {
            $this->calendarService->unlinkEvidenceFromOccurrence($occurrence, $document, $request->user());
            return back()->with('success', "Document unlinked from filing period {$occurrence->filing_period_label}.");
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }
    }

    /**
     * Authorised statutory due-date correction with mandatory legal basis and audit trail.
     */
    public function amendDueDate(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'new_due_date' => ['required', 'date'],
            'legal_basis_citation' => ['required', 'string', 'min:5', 'max:255'],
            'justification_notes' => ['required', 'string', 'min:5', 'max:1000'],
            'rule_version_id' => ['nullable', 'uuid'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $occurrence = FilingOccurrence::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $this->calendarService->recordAuthorisedDueDateCorrection(
                occurrence: $occurrence,
                newDueDate: $validated['new_due_date'],
                legalBasisCitation: $validated['legal_basis_citation'],
                justificationNotes: $validated['justification_notes'],
                actor: $request->user(),
                ruleVersionId: $validated['rule_version_id'] ?? null,
                lockVersion: $validated['lock_version'] ?? null
            );

            return back()->with('success', 'Authorised statutory due date correction recorded with legal basis.');
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amendment' => $e->getMessage()]);
        }
    }
}

