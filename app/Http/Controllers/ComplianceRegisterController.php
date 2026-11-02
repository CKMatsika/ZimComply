<?php

namespace App\Http\Controllers;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Services\ComplianceObligationRegisterService;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Models\User;
use App\Shared\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceRegisterController extends Controller
{
    public function __construct(
        protected ComplianceObligationRegisterService $registerService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Render the Compliance Register workspace.
     */
    public function index(Request $request): Response
    {
        $org = $this->tenantContext->getOrganisation();
        $user = $request->user();

        if (!$org) {
            return Inertia::render('Comply/Index', [
                'obligations' => [],
                'metrics' => [
                    'total' => 0,
                    'not_started' => 0,
                    'in_progress' => 0,
                    'awaiting_evidence' => 0,
                    'under_review' => 0,
                    'completed_internal' => 0,
                    'verified_complete' => 0,
                    'blocked' => 0,
                    'not_applicable' => 0,
                    'overdue' => 0,
                ],
                'members' => [],
                'filters' => [],
                'authorities' => [],
                'categories' => [],
                'can_manage' => false,
            ]);
        }

        $filters = $request->only([
            'authority',
            'category',
            'lifecycle_status',
            'responsible_user',
            'search',
        ]);

        $registerData = $this->registerService->getRegisterForOrganisation($org, $filters);

        // Fetch authorities for filtering
        $authorities = RegulatoryAuthority::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $categories = [
            ['code' => 'MANDATORY_STATUTORY', 'name' => 'Mandatory Statutory'],
            ['code' => 'CONDITIONAL_STATUTORY', 'name' => 'Conditional Statutory'],
            ['code' => 'CBA_COLLECTIVE_AGREEMENT', 'name' => 'Collective Bargaining (CBA)'],
            ['code' => 'PROFESSIONAL_REGULATION', 'name' => 'Professional Regulation'],
            ['code' => 'TENDER_SPECIFIC', 'name' => 'Tender Specific'],
            ['code' => 'OPTIONAL_ASSOCIATION', 'name' => 'Voluntary Association'],
        ];

        // Check if current user is admin/owner
        $membership = Membership::where('organisation_id', $org->id)
            ->where('user_id', $user->id)
            ->first();
        $canManage = $membership?->isOwnerOrAdmin() ?? false;

        return Inertia::render('Comply/Index', array_merge($registerData, [
            'authorities' => $authorities,
            'categories' => $categories,
            'can_manage' => $canManage,
        ]));
    }

    /**
     * Get single obligation detail with timeline and linked documents.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $obligation = ObligationInstance::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        $detail = $this->registerService->getObligationDetail($obligation, $request->user());

        return response()->json($detail);
    }

    /**
     * Assign or unassign obligation responsibility.
     */
    public function assign(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'uuid'],
            'lock_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $obligation = ObligationInstance::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        $targetUser = null;
        if (!empty($validated['assigned_to_user_id'])) {
            $targetUser = User::findOrFail($validated['assigned_to_user_id']);
        }

        try {
            $this->registerService->assignObligation(
                $obligation,
                $targetUser,
                $request->user(),
                $validated['lock_version'] ?? null,
                $validated['notes'] ?? null
            );

            return back()->with('success', $targetUser
                ? "Obligation assigned to {$targetUser->name}."
                : "Obligation responsibility unassigned.");
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        }
    }

    /**
     * Transition lifecycle status.
     */
    public function transition(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $obligation = ObligationInstance::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $this->registerService->transitionStatus(
                $obligation,
                $validated['status'],
                $request->user(),
                $validated['lock_version'] ?? null,
                $validated['notes'] ?? null
            );

            return back()->with('success', "Obligation status successfully transitioned to [{$validated['status']}].");
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        }
    }

    /**
     * Update compliance schedule (due date and frequency).
     */
    public function updateSchedule(string $id, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'next_due_date' => ['nullable', 'date'],
            'compliance_frequency' => ['nullable', 'string', 'max:32'],
            'lock_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $obligation = ObligationInstance::withoutGlobalScopes()->where('id', $id)->firstOrFail();

        try {
            $this->registerService->updateSchedule(
                $obligation,
                $validated['next_due_date'] ?? null,
                $validated['compliance_frequency'] ?? null,
                $request->user(),
                $validated['lock_version'] ?? null,
                $validated['notes'] ?? null
            );

            return back()->with('success', 'Compliance schedule updated successfully.');
        } catch (ConcurrentUpdateException $e) {
            return back()->withErrors(['concurrency' => $e->getMessage()]);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['authorization' => $e->getMessage()]);
        }
    }
}
