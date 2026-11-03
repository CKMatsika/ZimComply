<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Models\RenewalAttempt;
use App\Domains\ComplianceEngine\Models\RenewalEvidenceLink;
use App\Domains\ComplianceEngine\Models\StatutoryInstrument;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class RenewalLifecycleStateMachine
{
    // 1. Underlying Instrument Legal Validity Classifications (Independent of Application Workflow)
    public const VALIDITY_ACTIVE = 'ACTIVE';
    public const VALIDITY_EXPIRED = 'EXPIRED';
    public const VALIDITY_SUSPENDED = 'SUSPENDED';
    public const VALIDITY_CANCELLED = 'CANCELLED';
    public const VALIDITY_SUPERSEDED = 'SUPERSEDED';
    public const VALIDITY_UNKNOWN = 'UNKNOWN';

    public const VALIDITY_STATUSES = [
        self::VALIDITY_ACTIVE => 'Active (Valid)',
        self::VALIDITY_EXPIRED => 'Expired',
        self::VALIDITY_SUSPENDED => 'Suspended by Authority',
        self::VALIDITY_CANCELLED => 'Cancelled / Revoked',
        self::VALIDITY_SUPERSEDED => 'Superseded by Replacement',
        self::VALIDITY_UNKNOWN => 'Validity Date Unknown',
    ];

    // 2. Renewal Application Workflow States (Does NOT imply legal validity or automatic extension)
    public const WORKFLOW_NONE = 'NONE';
    public const WORKFLOW_ACTIVE = 'ACTIVE';
    public const WORKFLOW_RENEWAL_UPCOMING = 'RENEWAL_UPCOMING';
    public const WORKFLOW_RENEWAL_IN_PROGRESS = 'RENEWAL_IN_PROGRESS';
    public const WORKFLOW_AWAITING_DOCUMENTS = 'AWAITING_DOCUMENTS';
    public const WORKFLOW_READY_FOR_SUBMISSION = 'READY_FOR_SUBMISSION';
    public const WORKFLOW_DECLARED_SUBMITTED = 'DECLARED_SUBMITTED';
    public const WORKFLOW_AWAITING_AUTHORITY_DECISION = 'AWAITING_AUTHORITY_DECISION';
    public const WORKFLOW_RENEWED = 'RENEWED';
    public const WORKFLOW_REJECTED = 'REJECTED';
    public const WORKFLOW_WITHDRAWN = 'WITHDRAWN';
    public const WORKFLOW_ABANDONED = 'ABANDONED';
    public const WORKFLOW_CANCELLED = 'CANCELLED';

    public const WORKFLOW_STATUSES = [
        self::WORKFLOW_NONE => 'No Active Renewal Application',
        self::WORKFLOW_ACTIVE => 'Active Standing',
        self::WORKFLOW_RENEWAL_UPCOMING => 'Renewal Upcoming',
        self::WORKFLOW_RENEWAL_IN_PROGRESS => 'Renewal in Progress',
        self::WORKFLOW_AWAITING_DOCUMENTS => 'Awaiting Supporting Documents',
        self::WORKFLOW_READY_FOR_SUBMISSION => 'Ready for Authority Submission',
        self::WORKFLOW_DECLARED_SUBMITTED => 'Declared Submitted (Internal)',
        self::WORKFLOW_AWAITING_AUTHORITY_DECISION => 'Awaiting Authority Decision',
        self::WORKFLOW_RENEWED => 'Officially Renewed (Verified)',
        self::WORKFLOW_REJECTED => 'Application Rejected',
        self::WORKFLOW_WITHDRAWN => 'Application Withdrawn',
        self::WORKFLOW_ABANDONED => 'Application Abandoned',
        self::WORKFLOW_CANCELLED => 'Application Cancelled',
    ];

    public const TERMINAL_ATTEMPT_STATUSES = [
        self::WORKFLOW_RENEWED,
        self::WORKFLOW_REJECTED,
        self::WORKFLOW_WITHDRAWN,
        self::WORKFLOW_ABANDONED,
        self::WORKFLOW_CANCELLED,
    ];

    /**
     * Allowed workflow transitions for a RenewalAttempt.
     *
     * @var array<string, array<int, string>>
     */
    protected const TRANSITIONS = [
        self::WORKFLOW_NONE => [
            self::WORKFLOW_RENEWAL_UPCOMING,
            self::WORKFLOW_RENEWAL_IN_PROGRESS,
            self::WORKFLOW_AWAITING_DOCUMENTS,
        ],
        self::WORKFLOW_ACTIVE => [
            self::WORKFLOW_RENEWAL_UPCOMING,
            self::WORKFLOW_RENEWAL_IN_PROGRESS,
            self::WORKFLOW_AWAITING_DOCUMENTS,
        ],
        self::WORKFLOW_RENEWAL_UPCOMING => [
            self::WORKFLOW_RENEWAL_IN_PROGRESS,
            self::WORKFLOW_AWAITING_DOCUMENTS,
            self::WORKFLOW_READY_FOR_SUBMISSION,
            self::WORKFLOW_WITHDRAWN,
            self::WORKFLOW_CANCELLED,
        ],
        self::WORKFLOW_RENEWAL_IN_PROGRESS => [
            self::WORKFLOW_AWAITING_DOCUMENTS,
            self::WORKFLOW_READY_FOR_SUBMISSION,
            self::WORKFLOW_DECLARED_SUBMITTED,
            self::WORKFLOW_WITHDRAWN,
            self::WORKFLOW_ABANDONED,
            self::WORKFLOW_CANCELLED,
        ],
        self::WORKFLOW_AWAITING_DOCUMENTS => [
            self::WORKFLOW_RENEWAL_IN_PROGRESS,
            self::WORKFLOW_READY_FOR_SUBMISSION,
            self::WORKFLOW_WITHDRAWN,
            self::WORKFLOW_ABANDONED,
            self::WORKFLOW_CANCELLED,
        ],
        self::WORKFLOW_READY_FOR_SUBMISSION => [
            self::WORKFLOW_RENEWAL_IN_PROGRESS,
            self::WORKFLOW_AWAITING_DOCUMENTS,
            self::WORKFLOW_DECLARED_SUBMITTED,
            self::WORKFLOW_WITHDRAWN,
            self::WORKFLOW_CANCELLED,
        ],
        self::WORKFLOW_DECLARED_SUBMITTED => [
            self::WORKFLOW_AWAITING_AUTHORITY_DECISION,
            self::WORKFLOW_RENEWED,
            self::WORKFLOW_REJECTED,
            self::WORKFLOW_AWAITING_DOCUMENTS,
            self::WORKFLOW_WITHDRAWN,
        ],
        self::WORKFLOW_AWAITING_AUTHORITY_DECISION => [
            self::WORKFLOW_RENEWED,
            self::WORKFLOW_REJECTED,
            self::WORKFLOW_AWAITING_DOCUMENTS,
            self::WORKFLOW_WITHDRAWN,
        ],
        self::WORKFLOW_REJECTED => [],
        self::WORKFLOW_RENEWED => [],
        self::WORKFLOW_WITHDRAWN => [],
        self::WORKFLOW_ABANDONED => [],
        self::WORKFLOW_CANCELLED => [],
    ];

    /**
     * Evaluate the underlying legal validity status of a StatutoryInstrument.
     *
     * CRITICAL GOVERNANCE SAFEGUARD:
     * A pending or submitted renewal application NEVER extends the legal validity of an expired instrument.
     */
    public function evaluateInstrumentValidity(
        StatutoryInstrument $instrument,
        CarbonInterface|string|null $referenceDate = null
    ): string {
        if (in_array($instrument->validity_status, [
            self::VALIDITY_SUSPENDED,
            self::VALIDITY_CANCELLED,
            self::VALIDITY_SUPERSEDED,
        ], true)) {
            return $instrument->validity_status;
        }

        if ($instrument->expiry_date === null) {
            return self::VALIDITY_UNKNOWN;
        }

        $ref = $referenceDate
            ? Carbon::parse($referenceDate, FilingDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(FilingDeadlineCalculator::TIMEZONE)->startOfDay();

        $expiry = Carbon::parse($instrument->expiry_date, FilingDeadlineCalculator::TIMEZONE)->startOfDay();

        if ($expiry->lt($ref)) {
            return self::VALIDITY_EXPIRED;
        }

        return self::VALIDITY_ACTIVE;
    }

    /**
     * Validate and authorize a workflow transition on a RenewalAttempt.
     *
     * @param RenewalAttempt $attempt
     * @param string $targetStatus
     * @param bool $isReviewerOrAdmin
     * @param array<string, mixed> $context
     * @throws InvalidLifecycleTransitionException
     */
    public function validateTransition(
        RenewalAttempt $attempt,
        string $targetStatus,
        bool $isReviewerOrAdmin,
        array $context = []
    ): void {
        $currentStatus = $attempt->workflow_status ?: self::WORKFLOW_RENEWAL_IN_PROGRESS;
        $targetStatus = strtoupper(trim($targetStatus));

        if (!array_key_exists($targetStatus, self::WORKFLOW_STATUSES)) {
            throw new InvalidLifecycleTransitionException("Unknown renewal workflow status [{$targetStatus}].");
        }

        if (!$attempt->is_active_attempt || in_array($currentStatus, self::TERMINAL_ATTEMPT_STATUSES, true)) {
            throw new InvalidLifecycleTransitionException(
                "Renewal attempt #{$attempt->attempt_number} is already in terminal state [{$currentStatus}] and cannot be transitioned. Start a new renewal attempt if required."
            );
        }

        if ($currentStatus === $targetStatus) {
            return;
        }

        $allowedTargets = self::TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($targetStatus, $allowedTargets, true)) {
            throw new InvalidLifecycleTransitionException(
                "Invalid renewal workflow transition from [{$currentStatus}] to [{$targetStatus}]."
            );
        }

        // Precondition 1: READY_FOR_SUBMISSION requires reviewer/admin or supporting preparation
        if ($targetStatus === self::WORKFLOW_DECLARED_SUBMITTED) {
            $submissionRef = trim((string) ($context['submission_reference'] ?? $attempt->submission_reference ?? ''));
            $notes = trim((string) ($context['notes'] ?? ''));
            if ($submissionRef === '' && $notes === '') {
                throw new InvalidLifecycleTransitionException(
                    'Recording a declared renewal submission requires either a submission reference or submission notes.'
                );
            }
        }

        // Precondition 2: RENEWED requires reviewer/admin AND human-VERIFIED non-expired replacement certificate
        if ($targetStatus === self::WORKFLOW_RENEWED) {
            if (!$isReviewerOrAdmin) {
                throw new InvalidLifecycleTransitionException(
                    'Only an authorised Administrator, Owner, or Compliance Officer can confirm an official renewal outcome (RENEWED).'
                );
            }

            $hasVerifiedReplacement = $this->hasVerifiedReplacementDocument($attempt, $context['reference_date'] ?? null);
            if (!$hasVerifiedReplacement) {
                throw new InvalidLifecycleTransitionException(
                    'Cannot mark renewal attempt as RENEWED without a human-VERIFIED, non-expired replacement certificate linked to this renewal attempt.'
                );
            }
        }

        // Precondition 3: REJECTED requires reviewer/admin and mandatory rejection reason
        if ($targetStatus === self::WORKFLOW_REJECTED) {
            if (!$isReviewerOrAdmin) {
                throw new InvalidLifecycleTransitionException(
                    'Only an authorised Administrator, Owner, or Compliance Officer can record an official authority rejection (REJECTED).'
                );
            }

            $rejectionReason = trim((string) ($context['rejection_reason'] ?? $context['notes'] ?? $attempt->rejection_reason ?? ''));
            if ($rejectionReason === '') {
                throw new InvalidLifecycleTransitionException(
                    'Recording a rejected renewal application requires a mandatory rejection reason.'
                );
            }
        }

        // Precondition 4: CANCELLED / WITHDRAWN / ABANDONED requires notes/reason
        if (in_array($targetStatus, [self::WORKFLOW_WITHDRAWN, self::WORKFLOW_ABANDONED, self::WORKFLOW_CANCELLED], true)) {
            $notes = trim((string) ($context['notes'] ?? ''));
            if ($notes === '') {
                throw new InvalidLifecycleTransitionException(
                    "Transitioning a renewal attempt to [{$targetStatus}] requires a justification note."
                );
            }
        }
    }

    /**
     * Check whether the renewal attempt has a human-VERIFIED, non-expired replacement certificate.
     */
    public function hasVerifiedReplacementDocument(
        RenewalAttempt $attempt,
        CarbonInterface|string|null $referenceDate = null
    ): bool {
        $refDate = $referenceDate
            ? Carbon::parse($referenceDate, FilingDeadlineCalculator::TIMEZONE)->toDateString()
            : Carbon::now(FilingDeadlineCalculator::TIMEZONE)->toDateString();

        $replacementLinks = RenewalEvidenceLink::withoutGlobalScopes()
            ->where('tenant_id', $attempt->tenant_id)
            ->where('organisation_id', $attempt->organisation_id)
            ->where('statutory_instrument_id', $attempt->statutory_instrument_id)
            ->where(function ($q) use ($attempt) {
                $q->where('renewal_attempt_id', $attempt->id)
                  ->orWhereNull('renewal_attempt_id');
            })
            ->where('document_purpose', RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT)
            ->where('is_superseded', false)
            ->with('document')
            ->get();

        foreach ($replacementLinks as $link) {
            $doc = $link->document;
            if (!$doc) {
                continue;
            }
            if ($doc->tenant_id !== $attempt->tenant_id || $doc->organisation_id !== $attempt->organisation_id) {
                continue;
            }
            if ($doc->verification_status !== 'VERIFIED') {
                continue;
            }
            if ($doc->expiry_date !== null && $doc->expiry_date->toDateString() < $refDate) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * Get available workflow transitions and precondition feedback for UI rendering.
     *
     * @return array<string, array{status: string, label: string, allowed: bool, reason: string|null}>
     */
    public function getAvailableTransitions(
        RenewalAttempt $attempt,
        bool $isReviewerOrAdmin,
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $currentStatus = $attempt->workflow_status ?: self::WORKFLOW_RENEWAL_IN_PROGRESS;
        $targets = self::TRANSITIONS[$currentStatus] ?? [];
        $hasVerifiedReplacement = $this->hasVerifiedReplacementDocument($attempt, $referenceDate);

        $result = [];
        foreach ($targets as $target) {
            $allowed = true;
            $reason = null;

            if ($target === self::WORKFLOW_RENEWED) {
                if (!$isReviewerOrAdmin) {
                    $allowed = false;
                    $reason = 'Requires Owner, Admin, or Compliance Officer role.';
                } elseif (!$hasVerifiedReplacement) {
                    $allowed = false;
                    $reason = 'Requires a human-VERIFIED replacement certificate linked to this renewal attempt.';
                }
            } elseif ($target === self::WORKFLOW_REJECTED && !$isReviewerOrAdmin) {
                $allowed = false;
                $reason = 'Requires Owner, Admin, or Compliance Officer role.';
            }

            $result[$target] = [
                'status' => $target,
                'label' => self::WORKFLOW_STATUSES[$target] ?? $target,
                'allowed' => $allowed,
                'reason' => $reason,
            ];
        }

        return $result;
    }

    public function isTerminalAttemptStatus(string $status): bool
    {
        return in_array(strtoupper(trim($status)), self::TERMINAL_ATTEMPT_STATUSES, true);
    }
}

