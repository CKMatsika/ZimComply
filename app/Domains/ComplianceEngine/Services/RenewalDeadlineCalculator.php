<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class RenewalDeadlineCalculator
{
    public const TIMEZONE = FilingDeadlineCalculator::TIMEZONE; // Africa/Harare
    public const DEFAULT_EXPIRING_SOON_DAYS = 30;
    public const DEFAULT_WINDOW_OPEN_DAYS = 90;

    /**
     * Deterministically calculate instrument expiry, renewal preparation window, and statutory renewal submission deadline.
     *
     * CRITICAL GOVERNANCE SAFEGUARDS:
     * 1. Never invent an expiry date or statutory renewal deadline.
     * 2. Keep verified instrument expiry dates distinct from verified statutory renewal submission deadlines.
     * 3. Never automatically apply filing_schedule rules to licence renewals unless renewal_schedule is explicitly defined.
     *
     * @param array{
     *     issue_date?: CarbonInterface|string|null,
     *     expiry_date?: CarbonInterface|string|null,
     *     trigger_event_date?: CarbonInterface|string|null,
     *     source_citation?: string|null
     * } $instrumentDates
     * @param RuleVersion|null $ruleVersion
     * @param ObligationDefinition|null $definition
     * @param CarbonInterface|string|null $referenceDate
     * @return array{
     *     issue_date: string|null,
     *     expiry_date: string|null,
     *     renewal_window_opens_at: string|null,
     *     statutory_renewal_deadline: string|null,
     *     deadline_verification_status: string,
     *     days_remaining: int|null,
     *     days_until_renewal_deadline: int|null,
     *     is_expired: bool,
     *     is_expiring_soon: bool,
     *     is_renewal_window_open: bool,
     *     is_renewal_deadline_overdue: bool,
     *     cadence_type: string,
     *     source_citation: string|null,
     *     rule_version_id: string|null,
     *     rule_version_code: string|null,
     *     deadline_calculation_basis: array<string, mixed>
     * }
     */
    public function calculate(
        array $instrumentDates,
        ?RuleVersion $ruleVersion = null,
        ?ObligationDefinition $definition = null,
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $ref = $referenceDate
            ? Carbon::parse($referenceDate, self::TIMEZONE)->startOfDay()
            : Carbon::now(self::TIMEZONE)->startOfDay();

        $issueDate = !empty($instrumentDates['issue_date'])
            ? Carbon::parse($instrumentDates['issue_date'], self::TIMEZONE)->startOfDay()
            : null;

        $explicitExpiry = !empty($instrumentDates['expiry_date'])
            ? Carbon::parse($instrumentDates['expiry_date'], self::TIMEZONE)->startOfDay()
            : null;

        $triggerEventDate = !empty($instrumentDates['trigger_event_date'])
            ? Carbon::parse($instrumentDates['trigger_event_date'], self::TIMEZONE)->startOfDay()
            : null;

        $citation = $instrumentDates['source_citation']
            ?? $ruleVersion?->source?->citation
            ?? $definition?->statutory_citation
            ?? $definition?->source?->citation;

        $isRuleVerified = $ruleVersion !== null
            && $ruleVersion->status === 'ACTIVE'
            && $ruleVersion->isVerified()
            && ($ruleVersion->source === null || $ruleVersion->source->isVerified());

        $renewalRule = $isRuleVerified
            ? $this->extractExplicitRenewalRule($ruleVersion)
            : null;

        // 1. Resolve Instrument Expiry Date
        // Explicit certificate expiry date takes precedence; if absent, derive ONLY if an approved renewal rule defines deterministic validity from issue_date
        $resolvedExpiry = $explicitExpiry;
        $expiryDerivedFromRule = false;
        $cadenceType = $renewalRule['cadence_type'] ?? 'ESTABLISHED_CERTIFICATE_DATE';

        if ($resolvedExpiry === null && $renewalRule !== null) {
            $derived = $this->deriveExpiryFromApprovedRule($renewalRule, $issueDate, $triggerEventDate);
            if ($derived !== null) {
                $resolvedExpiry = $derived;
                $expiryDerivedFromRule = true;
            }
        }

        // 2. Resolve Statutory Renewal Submission Deadline & Preparation Window (ONLY from approved renewal rule)
        $renewalWindowOpensAt = null;
        $statutoryRenewalDeadline = null;
        $deadlineVerificationStatus = 'DEADLINE_UNVERIFIED';
        $explanationParts = [];

        if ($resolvedExpiry !== null) {
            $explanationParts[] = $expiryDerivedFromRule
                ? sprintf(
                    'Instrument expiry date (%s) calculated deterministically from approved %s rule (%s).',
                    $resolvedExpiry->toDateString(),
                    $cadenceType,
                    $ruleVersion?->version_code ?? 'RULE'
                )
                : sprintf(
                    'Instrument expiry date (%s) established from registered certificate metadata.',
                    $resolvedExpiry->toDateString()
                );
        } else {
            $explanationParts[] = 'Instrument expiry date is currently unknown (no expiry date established on certificate and no deterministic validity rule applicable).';
        }

        if ($renewalRule !== null && $resolvedExpiry !== null) {
            $windowAndDeadline = $this->computeVerifiedRenewalWindowAndDeadline(
                $resolvedExpiry,
                $renewalRule,
                $triggerEventDate
            );

            $renewalWindowOpensAt = $windowAndDeadline['window_opens_at'];
            $statutoryRenewalDeadline = $windowAndDeadline['statutory_deadline'];

            if ($statutoryRenewalDeadline !== null) {
                $deadlineVerificationStatus = 'VERIFIED_OFFICIAL';
                $explanationParts[] = $windowAndDeadline['explanation'];
            } else {
                $deadlineVerificationStatus = 'DEADLINE_UNVERIFIED';
                $explanationParts[] = 'Expiry date is established, but no verified statutory renewal submission deadline is defined in the approved rule.';
            }
        } elseif ($renewalRule !== null && $cadenceType === 'EVENT_DRIVEN' && $triggerEventDate !== null) {
            $windowAndDeadline = $this->computeEventDrivenRenewalDeadline($triggerEventDate, $renewalRule);
            $statutoryRenewalDeadline = $windowAndDeadline['statutory_deadline'];
            $renewalWindowOpensAt = $windowAndDeadline['window_opens_at'];
            if ($statutoryRenewalDeadline !== null) {
                $deadlineVerificationStatus = 'VERIFIED_OFFICIAL';
                $explanationParts[] = $windowAndDeadline['explanation'];
            }
        } else {
            // No verified renewal rule exists -> keep statutory_renewal_deadline null and mark DEADLINE_UNVERIFIED
            $deadlineVerificationStatus = ($ruleVersion && !$isRuleVerified)
                ? 'UNVERIFIED_HOLD'
                : 'DEADLINE_UNVERIFIED';

            if ($resolvedExpiry !== null) {
                $explanationParts[] = 'Statutory renewal submission window/deadline is unverified (DEADLINE_UNVERIFIED). Display relies solely on the established instrument expiry date without inventing a statutory submission deadline.';
            } else {
                $explanationParts[] = 'Both instrument expiry date and statutory renewal submission deadline are unverified.';
            }
        }

        // 3. Compute Days Remaining & Operational Classifications
        $daysRemaining = $resolvedExpiry !== null
            ? (int) $ref->diffInDays($resolvedExpiry, false)
            : null;

        $daysUntilRenewalDeadline = $statutoryRenewalDeadline !== null
            ? (int) $ref->diffInDays($statutoryRenewalDeadline, false)
            : null;

        $isExpired = $daysRemaining !== null && $daysRemaining < 0;
        $expiringSoonWindow = (int) ($renewalRule['expiring_soon_days'] ?? self::DEFAULT_EXPIRING_SOON_DAYS);
        $isExpiringSoon = $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= $expiringSoonWindow;

        $isRenewalWindowOpen = false;
        if ($renewalWindowOpensAt !== null && $resolvedExpiry !== null) {
            $isRenewalWindowOpen = $ref->gte($renewalWindowOpensAt) && !$isExpired;
        } elseif ($daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= self::DEFAULT_WINDOW_OPEN_DAYS) {
            $isRenewalWindowOpen = true;
        }

        // Overdue renewal deadline ONLY when statutory_renewal_deadline is VERIFIED_OFFICIAL
        $isRenewalDeadlineOverdue = $deadlineVerificationStatus === 'VERIFIED_OFFICIAL'
            && $daysUntilRenewalDeadline !== null
            && $daysUntilRenewalDeadline < 0;

        $basis = [
            'cadence_type' => $cadenceType,
            'timezone' => self::TIMEZONE,
            'reference_date' => $ref->toDateString(),
            'issue_date' => $issueDate?->toDateString(),
            'expiry_date' => $resolvedExpiry?->toDateString(),
            'expiry_derived_from_rule' => $expiryDerivedFromRule,
            'renewal_window_opens_at' => $renewalWindowOpensAt?->toDateString(),
            'statutory_renewal_deadline' => $statutoryRenewalDeadline?->toDateString(),
            'deadline_verification_status' => $deadlineVerificationStatus,
            'rule_version_id' => $ruleVersion?->id,
            'rule_version_code' => $ruleVersion?->version_code,
            'source_citation' => $citation,
            'explanation' => implode(' ', $explanationParts),
        ];

        return [
            'issue_date' => $issueDate?->toDateString(),
            'expiry_date' => $resolvedExpiry?->toDateString(),
            'renewal_window_opens_at' => $renewalWindowOpensAt?->toDateString(),
            'statutory_renewal_deadline' => $statutoryRenewalDeadline?->toDateString(),
            'deadline_verification_status' => $deadlineVerificationStatus,
            'days_remaining' => $daysRemaining,
            'days_until_renewal_deadline' => $daysUntilRenewalDeadline,
            'is_expired' => $isExpired,
            'is_expiring_soon' => $isExpiringSoon,
            'is_renewal_window_open' => $isRenewalWindowOpen,
            'is_renewal_deadline_overdue' => $isRenewalDeadlineOverdue,
            'cadence_type' => $cadenceType,
            'source_citation' => $citation,
            'rule_version_id' => $ruleVersion?->id,
            'rule_version_code' => $ruleVersion?->version_code,
            'deadline_calculation_basis' => $basis,
        ];
    }

    /**
     * Extract explicit renewal rule parameters from an approved RuleVersion.
     *
     * Does NOT read `filing_schedule` — tax return filing schedules are never conflated with licence renewal windows.
     *
     * @return array<string, mixed>|null
     */
    protected function extractExplicitRenewalRule(RuleVersion $ruleVersion): ?array
    {
        $params = (array) ($ruleVersion->parameters ?? []);

        if (isset($params['renewal_schedule']) && is_array($params['renewal_schedule'])) {
            $sched = $params['renewal_schedule'];
            return [
                'cadence_type' => strtoupper((string) ($sched['cadence_type'] ?? 'ANNIVERSARY_BASED')),
                'validity_months' => isset($sched['validity_months']) ? (int) $sched['validity_months'] : null,
                'validity_days' => isset($sched['validity_days']) ? (int) $sched['validity_days'] : null,
                'calendar_expiry_month' => isset($sched['calendar_expiry_month']) ? (int) $sched['calendar_expiry_month'] : 12,
                'calendar_expiry_day' => isset($sched['calendar_expiry_day']) ? (int) $sched['calendar_expiry_day'] : 31,
                'window_days_before_expiry' => isset($sched['window_days_before_expiry']) ? (int) $sched['window_days_before_expiry'] : null,
                'deadline_days_before_expiry' => isset($sched['deadline_days_before_expiry']) ? (int) $sched['deadline_days_before_expiry'] : null,
                'grace_days_after_expiry' => isset($sched['grace_days_after_expiry']) ? (int) $sched['grace_days_after_expiry'] : null,
                'fixed_deadline_month' => isset($sched['fixed_deadline_month']) ? (int) $sched['fixed_deadline_month'] : null,
                'fixed_deadline_day' => isset($sched['fixed_deadline_day']) ? (int) $sched['fixed_deadline_day'] : null,
                'days_after_event' => isset($sched['days_after_event']) ? (int) $sched['days_after_event'] : null,
                'expiring_soon_days' => isset($sched['expiring_soon_days']) ? (int) $sched['expiring_soon_days'] : self::DEFAULT_EXPIRING_SOON_DAYS,
            ];
        }

        return null;
    }

    /**
     * Derive expiry date from an approved rule when issue_date or trigger_event_date is known.
     *
     * @param array<string, mixed> $renewalRule
     */
    protected function deriveExpiryFromApprovedRule(
        array $renewalRule,
        ?Carbon $issueDate,
        ?Carbon $triggerEventDate
    ): ?Carbon {
        $cadence = $renewalRule['cadence_type'] ?? '';

        if ($cadence === 'CALENDAR_YEAR' && $issueDate !== null) {
            $month = (int) ($renewalRule['calendar_expiry_month'] ?? 12);
            $day = (int) ($renewalRule['calendar_expiry_day'] ?? 31);
            $targetMonth = Carbon::create($issueDate->year, $month, 1, 0, 0, 0, self::TIMEZONE);
            $resolvedDay = min($day, $targetMonth->daysInMonth);
            return $targetMonth->day($resolvedDay)->startOfDay();
        }

        if ($cadence === 'ANNIVERSARY_BASED' && $issueDate !== null) {
            $months = (int) ($renewalRule['validity_months'] ?? 12);
            return $issueDate->copy()->addMonthsNoOverflow($months)->subDay()->startOfDay();
        }

        if ($cadence === 'FIXED_DURATION' && $issueDate !== null) {
            if (!empty($renewalRule['validity_days'])) {
                return $issueDate->copy()->addDays((int) $renewalRule['validity_days'])->startOfDay();
            }
            if (!empty($renewalRule['validity_months'])) {
                return $issueDate->copy()->addMonthsNoOverflow((int) $renewalRule['validity_months'])->startOfDay();
            }
        }

        if ($cadence === 'EVENT_DRIVEN' && $triggerEventDate !== null && !empty($renewalRule['validity_days'])) {
            return $triggerEventDate->copy()->addDays((int) $renewalRule['validity_days'])->startOfDay();
        }

        return null;
    }

    /**
     * Compute verified renewal preparation window and statutory renewal submission deadline.
     *
     * @param array<string, mixed> $renewalRule
     * @return array{window_opens_at: Carbon|null, statutory_deadline: Carbon|null, explanation: string}
     */
    protected function computeVerifiedRenewalWindowAndDeadline(
        Carbon $expiryDate,
        array $renewalRule,
        ?Carbon $triggerEventDate = null
    ): array {
        $windowOpensAt = null;
        if (isset($renewalRule['window_days_before_expiry'])) {
            $windowOpensAt = $expiryDate->copy()->subDays((int) $renewalRule['window_days_before_expiry'])->startOfDay();
        }

        $statutoryDeadline = null;
        $explanation = '';

        if (isset($renewalRule['deadline_days_before_expiry'])) {
            $daysBefore = (int) $renewalRule['deadline_days_before_expiry'];
            $statutoryDeadline = $expiryDate->copy()->subDays($daysBefore)->startOfDay();
            $explanation = sprintf(
                'Verified statutory renewal submission deadline is %d days prior to expiry (%s).',
                $daysBefore,
                $statutoryDeadline->toDateString()
            );
        } elseif (isset($renewalRule['grace_days_after_expiry'])) {
            $daysAfter = (int) $renewalRule['grace_days_after_expiry'];
            $statutoryDeadline = $expiryDate->copy()->addDays($daysAfter)->startOfDay();
            $explanation = sprintf(
                'Verified statutory renewal deadline permits submission up to %d days after expiry (%s).',
                $daysAfter,
                $statutoryDeadline->toDateString()
            );
        } elseif (isset($renewalRule['fixed_deadline_month'], $renewalRule['fixed_deadline_day'])) {
            $m = (int) $renewalRule['fixed_deadline_month'];
            $d = (int) $renewalRule['fixed_deadline_day'];
            $targetMonth = Carbon::create($expiryDate->year, $m, 1, 0, 0, 0, self::TIMEZONE);
            $resolvedDay = min($d, $targetMonth->daysInMonth);
            $statutoryDeadline = $targetMonth->day($resolvedDay)->startOfDay();
            $explanation = sprintf(
                'Verified fixed calendar renewal submission deadline is %s.',
                $statutoryDeadline->toDateString()
            );
        } elseif ($triggerEventDate !== null && isset($renewalRule['days_after_event'])) {
            $daysAfterEvent = (int) $renewalRule['days_after_event'];
            $statutoryDeadline = $triggerEventDate->copy()->addDays($daysAfterEvent)->startOfDay();
            $explanation = sprintf(
                'Verified event-driven renewal submission deadline is %d days after triggering event (%s).',
                $daysAfterEvent,
                $statutoryDeadline->toDateString()
            );
        }

        return [
            'window_opens_at' => $windowOpensAt,
            'statutory_deadline' => $statutoryDeadline,
            'explanation' => $explanation,
        ];
    }

    /**
     * Compute event-driven renewal deadline when expiry is not pre-established.
     *
     * @param array<string, mixed> $renewalRule
     * @return array{window_opens_at: Carbon|null, statutory_deadline: Carbon|null, explanation: string}
     */
    protected function computeEventDrivenRenewalDeadline(Carbon $triggerEventDate, array $renewalRule): array
    {
        $days = isset($renewalRule['days_after_event']) ? (int) $renewalRule['days_after_event'] : null;
        if ($days === null) {
            return [
                'window_opens_at' => null,
                'statutory_deadline' => null,
                'explanation' => 'Missing days_after_event in event-driven renewal rule.',
            ];
        }

        $deadline = $triggerEventDate->copy()->addDays($days)->startOfDay();

        return [
            'window_opens_at' => $triggerEventDate->copy()->startOfDay(),
            'statutory_deadline' => $deadline,
            'explanation' => sprintf(
                'Event-driven renewal triggered on %s: statutory submission deadline %d days after event (%s).',
                $triggerEventDate->toDateString(),
                $days,
                $deadline->toDateString()
            ),
        ];
    }
}

