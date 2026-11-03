<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class FilingLifecycleStateMachine
{
    public const NOT_STARTED = 'NOT_STARTED';
    public const PREPARING = 'PREPARING';
    public const READY_FOR_REVIEW = 'READY_FOR_REVIEW';
    public const INTERNALLY_APPROVED = 'INTERNALLY_APPROVED';
    public const SUBMITTED = 'SUBMITTED';
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const REJECTED = 'REJECTED';
    public const CLOSED = 'CLOSED';

    public const OFFICIAL_NOT_SUBMITTED = 'NOT_SUBMITTED';
    public const OFFICIAL_DECLARED_SUBMITTED = 'DECLARED_SUBMITTED';
    public const OFFICIAL_ACKNOWLEDGED = 'OFFICIALLY_ACKNOWLEDGED';
    public const OFFICIAL_REJECTED = 'OFFICIALLY_REJECTED';

    /**
     * Map of valid internal filing workflow transitions.
     *
     * @var array<string, array<int, string>>
     */
    protected array $transitionMatrix = [
        self::NOT_STARTED => [
            self::PREPARING,
            self::CLOSED,
        ],
        self::PREPARING => [
            self::NOT_STARTED,
            self::READY_FOR_REVIEW,
            self::CLOSED,
        ],
        self::READY_FOR_REVIEW => [
            self::PREPARING,
            self::INTERNALLY_APPROVED,
            self::REJECTED,
        ],
        self::INTERNALLY_APPROVED => [
            self::PREPARING,
            self::READY_FOR_REVIEW,
            self::SUBMITTED,
            self::REJECTED,
        ],
        self::SUBMITTED => [
            self::PREPARING,
            self::ACKNOWLEDGED,
            self::REJECTED,
            self::CLOSED,
        ],
        self::ACKNOWLEDGED => [
            self::CLOSED,
            self::REJECTED,
        ],
        self::REJECTED => [
            self::PREPARING,
            self::READY_FOR_REVIEW,
            self::CLOSED,
        ],
        self::CLOSED => [
            self::PREPARING,
        ],
    ];

    /**
     * Validate whether a filing occurrence lifecycle transition is permissible.
     *
     * Distinguishes internal submission declaration (`SUBMITTED` / `DECLARED_SUBMITTED`)
     * from independently verified authority acknowledgement (`ACKNOWLEDGED` / `OFFICIALLY_ACKNOWLEDGED`).
     *
     * @throws InvalidLifecycleTransitionException
     * @throws AuthorizationException
     */
    public function validateTransition(
        FilingOccurrence $occurrence,
        string $targetStatus,
        User $actor,
        ?string $notes = null,
        ?string $submissionReference = null,
        ?string $officialReceiptNumber = null
    ): void {
        $fromStatus = $occurrence->workflow_status;
        $targetStatus = strtoupper(trim($targetStatus));

        // 1. Enforce active membership in the occurrence's organisation
        $membership = Membership::where('organisation_id', $occurrence->organisation_id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership) {
            throw new AuthorizationException(
                'Actor is not an active member of the organisation responsible for this filing occurrence.'
            );
        }

        // 2. Reject self-transitions
        if ($fromStatus === $targetStatus) {
            throw new InvalidLifecycleTransitionException(
                "Filing occurrence is already in status [{$targetStatus}]."
            );
        }

        // 3. Verify transition is in the allowed matrix
        $allowedTargets = $this->transitionMatrix[$fromStatus] ?? [];
        if (!in_array($targetStatus, $allowedTargets, true)) {
            throw new InvalidLifecycleTransitionException(
                "Invalid filing lifecycle transition from [{$fromStatus}] to [{$targetStatus}]."
            );
        }

        $isSupervisor = in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER', 'ACCOUNTANT'], true);
        $isAdminOrCompliance = in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'], true);

        // 4. Preconditions for INTERNALLY_APPROVED
        if ($targetStatus === self::INTERNALLY_APPROVED) {
            if (!$isSupervisor) {
                throw new AuthorizationException(
                    'Only organisation administrators, compliance officers, or accountants can internally approve a filing.'
                );
            }
        }

        // 5. Preconditions for SUBMITTED (Internal Submission Declaration)
        // Note: Entering a submission reference records an internal declaration of submission;
        // it does NOT automatically prove official regulatory acceptance (ACKNOWLEDGED).
        if ($targetStatus === self::SUBMITTED) {
            $hasSubRef = !empty(trim((string) ($submissionReference ?? $occurrence->submission_reference)));
            $hasUploadedDoc = ComplianceDocument::withoutGlobalScopes()
                ->where('organisation_id', $occurrence->organisation_id)
                ->where('filing_occurrence_id', $occurrence->id)
                ->whereNotIn('verification_status', ['REJECTED', 'REVOKED'])
                ->exists();

            if (!$hasSubRef && !$hasUploadedDoc && empty(trim((string) $notes))) {
                throw new InvalidLifecycleTransitionException(
                    'Internal submission declaration requires a submission reference, linked filing document, or declaration note.'
                );
            }
        }

        // 6. Preconditions for ACKNOWLEDGED (Official Regulatory Acceptance)
        // Crucial safeguard: A user-entered submission reference must NOT automatically prove official acceptance.
        // Requires both:
        // a) Authorised reviewer role (Owner / Admin / Compliance Officer)
        // b) Period-specific, human-VERIFIED non-expired supporting evidence linked to THIS filing occurrence.
        if ($targetStatus === self::ACKNOWLEDGED) {
            if (!$isAdminOrCompliance) {
                throw new AuthorizationException(
                    'Dual-control requirement: Only organisation administrators or compliance officers can certify official authority acknowledgement.'
                );
            }

            $now = Carbon::now(FilingDeadlineCalculator::TIMEZONE)->toDateString();
            $hasVerifiedPeriodEvidence = ComplianceDocument::withoutGlobalScopes()
                ->where('organisation_id', $occurrence->organisation_id)
                ->where('filing_occurrence_id', $occurrence->id)
                ->where('verification_status', 'VERIFIED')
                ->where(function ($q) use ($now) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>=', $now);
                })
                ->exists();

            if (!$hasVerifiedPeriodEvidence) {
                throw new InvalidLifecycleTransitionException(
                    'Statutory safeguard: Cannot mark a filing as ACKNOWLEDGED without period-specific, human-verified supporting evidence (e.g. verified official receipt). A submission reference alone does not prove official regulatory acceptance.'
                );
            }
        }

        // 7. Preconditions for REJECTED
        if ($targetStatus === self::REJECTED) {
            if (empty(trim((string) $notes))) {
                throw new InvalidLifecycleTransitionException(
                    'Audit requirement: A detailed justification note is required when marking a filing occurrence as REJECTED.'
                );
            }
        }

        // 8. Preconditions for CLOSED
        if ($targetStatus === self::CLOSED) {
            if (!$isAdminOrCompliance) {
                throw new AuthorizationException(
                    'Only organisation administrators or compliance officers can close a filing occurrence.'
                );
            }

            // If closing without official acknowledgement (e.g. obligation became inapplicable or nil/exempt period), require justification
            if ($fromStatus !== self::ACKNOWLEDGED && empty(trim((string) $notes))) {
                throw new InvalidLifecycleTransitionException(
                    'Audit requirement: Closing an unacknowledged filing occurrence requires a documented justification note.'
                );
            }
        }
    }

    /**
     * Get allowed next transitions for a filing occurrence given an actor.
     *
     * @return array<string, array{status: string, label: string, allowed: bool, reason: string|null}>
     */
    public function getAvailableTransitions(FilingOccurrence $occurrence, User $actor): array
    {
        $current = $occurrence->workflow_status;
        $possible = $this->transitionMatrix[$current] ?? [];

        $results = [];
        foreach ($possible as $candidate) {
            try {
                $this->validateTransition(
                    $occurrence,
                    $candidate,
                    $actor,
                    'Preview check',
                    'PREVIEW-REF',
                    'PREVIEW-RECEIPT'
                );
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
            self::PREPARING => 'Preparing Return',
            self::READY_FOR_REVIEW => 'Ready for Review',
            self::INTERNALLY_APPROVED => 'Internally Approved',
            self::SUBMITTED => 'Submitted (Internal Declaration)',
            self::ACKNOWLEDGED => 'Acknowledged (Official Receipt Verified)',
            self::REJECTED => 'Rejected / Returned',
            self::CLOSED => 'Closed',
            default => $status,
        };
    }

    public function getOfficialStatusLabel(string $officialStatus): string
    {
        return match ($officialStatus) {
            self::OFFICIAL_NOT_SUBMITTED => 'Not Submitted',
            self::OFFICIAL_DECLARED_SUBMITTED => 'Declared Submitted (Unverified by Authority)',
            self::OFFICIAL_ACKNOWLEDGED => 'Officially Acknowledged (Verified Receipt)',
            self::OFFICIAL_REJECTED => 'Rejected by Authority / Reviewer',
            default => $officialStatus,
        };
    }
}
