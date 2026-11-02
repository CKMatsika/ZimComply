<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class ComplianceLifecycleStateMachine
{
    public const NOT_STARTED = 'NOT_STARTED';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const AWAITING_EVIDENCE = 'AWAITING_EVIDENCE';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const COMPLETED_INTERNAL = 'COMPLETED_INTERNAL';
    public const VERIFIED_COMPLETE = 'VERIFIED_COMPLETE';
    public const BLOCKED = 'BLOCKED';
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';

    /**
     * Map of valid lifecycle transitions.
     *
     * @var array<string, array<string>>
     */
    protected array $transitionMatrix = [
        self::NOT_STARTED => [
            self::IN_PROGRESS,
            self::BLOCKED,
            self::NOT_APPLICABLE,
        ],
        self::IN_PROGRESS => [
            self::NOT_STARTED,
            self::AWAITING_EVIDENCE,
            self::UNDER_REVIEW,
            self::COMPLETED_INTERNAL,
            self::BLOCKED,
            self::NOT_APPLICABLE,
        ],
        self::AWAITING_EVIDENCE => [
            self::IN_PROGRESS,
            self::UNDER_REVIEW,
            self::COMPLETED_INTERNAL,
            self::BLOCKED,
            self::NOT_APPLICABLE,
        ],
        self::UNDER_REVIEW => [
            self::IN_PROGRESS,
            self::AWAITING_EVIDENCE,
            self::COMPLETED_INTERNAL,
            self::VERIFIED_COMPLETE,
            self::BLOCKED,
            self::NOT_APPLICABLE,
        ],
        self::COMPLETED_INTERNAL => [
            self::IN_PROGRESS,
            self::UNDER_REVIEW,
            self::VERIFIED_COMPLETE,
            self::BLOCKED,
            self::NOT_APPLICABLE,
        ],
        self::VERIFIED_COMPLETE => [
            self::UNDER_REVIEW,
            self::IN_PROGRESS,
            self::BLOCKED,
        ],
        self::BLOCKED => [
            self::NOT_STARTED,
            self::IN_PROGRESS,
            self::AWAITING_EVIDENCE,
            self::UNDER_REVIEW,
            self::COMPLETED_INTERNAL,
            self::NOT_APPLICABLE,
        ],
        self::NOT_APPLICABLE => [
            self::NOT_STARTED,
            self::IN_PROGRESS,
        ],
    ];

    /**
     * Normalize legacy Phase 2 statuses to standard Phase 3 lifecycle statuses.
     */
    public function normalizeStatus(string $status): string
    {
        return match ($status) {
            'COMPLIANT' => self::VERIFIED_COMPLETE,
            'EXEMPT' => self::NOT_APPLICABLE,
            'AWAITING_VERIFICATION' => self::UNDER_REVIEW,
            'DISPUTED' => self::BLOCKED,
            default => $status,
        };
    }

    /**
     * Validate whether a transition is permissible, considering actor permissions and evidence preconditions.
     *
     * @throws InvalidLifecycleTransitionException
     * @throws AuthorizationException
     */
    public function validateTransition(
        ObligationInstance $obligation,
        string $targetStatus,
        User $actor,
        ?string $notes = null
    ): void {
        $fromStatus = $this->normalizeStatus($obligation->status);
        $targetStatus = $this->normalizeStatus($targetStatus);

        // 1. Enforce actor organisation membership
        $membership = Membership::where('organisation_id', $obligation->organisation_id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership) {
            throw new AuthorizationException('Actor is not an active member of the organisation responsible for this obligation.');
        }

        // 2. Reject trivial self-transitions
        if ($fromStatus === $targetStatus) {
            throw new InvalidLifecycleTransitionException("Obligation is already in status [{$targetStatus}].");
        }

        // 3. Verify target status exists in lifecycle
        $allowedTargets = $this->transitionMatrix[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedTargets, true)) {
            throw new InvalidLifecycleTransitionException(
                "Invalid lifecycle transition from [{$fromStatus}] to [{$targetStatus}]."
            );
        }

        // 4. Preconditions for VERIFIED_COMPLETE
        if ($targetStatus === self::VERIFIED_COMPLETE) {
            // Role precondition: Only Admin or Owner can sign off on official statutory verification
            if (!$membership->isOwnerOrAdmin()) {
                throw new AuthorizationException(
                    'Dual-control requirement: Only organisation administrators or compliance officers can certify statutory compliance.'
                );
            }

            // Evidence precondition: Must have valid verified evidence
            $hasIssuerVerification = ($obligation->evidence_status === 'VERIFIED_BY_ISSUER');

            $now = Carbon::now()->toDateString();
            $hasValidDocument = ComplianceDocument::withoutGlobalScopes()
                ->where('organisation_id', $obligation->organisation_id)
                ->where('obligation_instance_id', $obligation->id)
                ->where('verification_status', 'VERIFIED')
                ->where(function ($q) use ($now) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>=', $now);
                })
                ->exists();

            if (!$hasIssuerVerification && !$hasValidDocument) {
                throw new InvalidLifecycleTransitionException(
                    'Statutory compliance safeguard: Cannot declare an obligation VERIFIED_COMPLETE without independently verified, valid supporting evidence. Mark internal task completion as COMPLETED_INTERNAL instead.'
                );
            }
        }

        // 5. Preconditions for BLOCKED
        if ($targetStatus === self::BLOCKED) {
            if (empty(trim((string) $notes))) {
                throw new InvalidLifecycleTransitionException(
                    'Audit requirement: A detailed justification note is required when marking an obligation as BLOCKED.'
                );
            }
        }

        // 6. Preconditions for NOT_APPLICABLE
        if ($targetStatus === self::NOT_APPLICABLE) {
            $isStatutorilyExempt = ($obligation->assessmentResult?->decision === 'EXEMPT'
                || $obligation->assessmentResult?->decision === 'NOT_APPLICABLE');

            if (!$isStatutorilyExempt) {
                // Must be an admin/owner with an explicit legal justification
                if (!$membership->isOwnerOrAdmin()) {
                    throw new AuthorizationException(
                        'Only organisation administrators can declare an applicable statutory obligation as NOT_APPLICABLE.'
                    );
                }

                if (empty(trim((string) $notes))) {
                    throw new InvalidLifecycleTransitionException(
                        'Legal audit requirement: A statutory justification note is required to mark an assessed obligation as NOT_APPLICABLE.'
                    );
                }
            }
        }
    }

    /**
     * Get allowed next transitions for an obligation given an actor.
     *
     * @return array<string, array{status: string, label: string, allowed: bool, reason: string|null}>
     */
    public function getAvailableTransitions(ObligationInstance $obligation, User $actor): array
    {
        $current = $this->normalizeStatus($obligation->status);
        $possible = $this->transitionMatrix[$current] ?? [];

        $results = [];
        foreach ($possible as $candidate) {
            try {
                $this->validateTransition($obligation, $candidate, $actor, 'Preview check');
                $results[$candidate] = [
                    'status' => $candidate,
                    'label' => $this->getStatusLabel($candidate),
                    'allowed' => true,
                    'reason' => null,
                ];
            } catch (\Exception $e) {
                $results[$candidate] = [
                    'status' => $candidate,
                    'label' => $this->getStatusLabel($candidate),
                    'allowed' => false,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            self::NOT_STARTED => 'Not Started',
            self::IN_PROGRESS => 'In Progress',
            self::AWAITING_EVIDENCE => 'Awaiting Evidence',
            self::UNDER_REVIEW => 'Under Review',
            self::COMPLETED_INTERNAL => 'Completed (Internal)',
            self::VERIFIED_COMPLETE => 'Verified Complete (Statutory)',
            self::BLOCKED => 'Blocked',
            self::NOT_APPLICABLE => 'Not Applicable / Exempt',
            default => $status,
        };
    }
}
