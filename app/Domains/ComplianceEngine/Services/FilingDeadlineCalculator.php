<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FilingDeadlineCalculator
{
    public const TIMEZONE = 'Africa/Harare';

    public const SUPPORTED_FREQUENCIES = [
        'MONTHLY',
        'QUARTERLY',
        'ANNUAL',
        'FIXED_DATE',
        'EVENT_DRIVEN',
    ];

    /**
     * Deterministically calculate the statutory filing deadline for a given obligation,
     * rule version, and filing period.
     *
     * Every calculation uses an approved effective-dated schedule definition from RuleVersion.
     * If the rule version or official due-date rule is missing or unverified, returns
     * DEADLINE_UNVERIFIED / UNVERIFIED_HOLD without inventing a due date.
     *
     * @param ObligationDefinition $definition
     * @param RuleVersion|null $ruleVersion
     * @param CarbonInterface|string $periodStart
     * @param CarbonInterface|string $periodEnd
     * @param array<string, mixed> $context Optional context (e.g. event_date, schedule_identity, public_holidays)
     * @return array{
     *     is_verified: bool,
     *     verification_status: string,
     *     filing_frequency: string,
     *     schedule_identity: string,
     *     filing_period_start: string,
     *     filing_period_end: string,
     *     filing_period_label: string,
     *     original_statutory_due_date: string|null,
     *     current_due_date: string|null,
     *     payment_due_date: string|null,
     *     timezone: string,
     *     source_citation: string|null,
     *     rule_version_id: string|null,
     *     rule_version_code: string|null,
     *     deadline_calculation_basis: array<string, mixed>
     * }
     */
    public function calculate(
        ObligationDefinition $definition,
        ?RuleVersion $ruleVersion,
        CarbonInterface|string $periodStart,
        CarbonInterface|string $periodEnd,
        array $context = []
    ): array {
        $start = Carbon::parse($periodStart, self::TIMEZONE)->startOfDay();
        $end = Carbon::parse($periodEnd, self::TIMEZONE)->startOfDay();
        $scheduleIdentity = (string) ($context['schedule_identity'] ?? 'RETURN');

        $citation = $ruleVersion?->source?->citation
            ?? $definition->statutory_citation
            ?? $definition->source?->citation;

        // 1. Governance & Verification Check:
        // Must have an active, verified rule version and verified source
        if (
            !$ruleVersion
            || !$ruleVersion->isVerified()
            || $ruleVersion->status !== 'ACTIVE'
            || ($ruleVersion->source && !$ruleVersion->source->isVerified())
        ) {
            $frequency = (string) ($definition->renewal_frequency ?? 'PERIODIC');
            return $this->buildUnverifiedResult(
                definition: $definition,
                ruleVersion: $ruleVersion,
                start: $start,
                end: $end,
                frequency: $frequency,
                scheduleIdentity: $scheduleIdentity,
                citation: $citation,
                verificationStatus: 'UNVERIFIED_HOLD',
                reason: 'No active, officially verified rule version is approved for this obligation period.'
            );
        }

        $params = (array) ($ruleVersion->parameters ?? []);
        $scheduleRule = $this->extractScheduleRule($definition, $params, $scheduleIdentity);

        if ($scheduleRule === null) {
            $frequency = (string) ($params['filing_frequency'] ?? $definition->renewal_frequency ?? 'PERIODIC');
            return $this->buildUnverifiedResult(
                definition: $definition,
                ruleVersion: $ruleVersion,
                start: $start,
                end: $end,
                frequency: $frequency,
                scheduleIdentity: $scheduleIdentity,
                citation: $citation,
                verificationStatus: 'DEADLINE_UNVERIFIED',
                reason: 'Rule version is verified for applicability, but official statutory due-date schedule parameters are missing or unverified.'
            );
        }

        $frequency = strtoupper((string) ($scheduleRule['frequency'] ?? $definition->renewal_frequency ?? 'MONTHLY'));
        $periodLabel = $this->formatPeriodLabel($start, $end, $frequency);

        // 2. Calculate base statutory due date according to schedule pattern
        $calcDetails = $this->computeDueDateByPattern(
            frequency: $frequency,
            start: $start,
            end: $end,
            scheduleRule: $scheduleRule,
            context: $context
        );

        if (!$calcDetails['resolved'] || $calcDetails['base_due_date'] === null) {
            return $this->buildUnverifiedResult(
                definition: $definition,
                ruleVersion: $ruleVersion,
                start: $start,
                end: $end,
                frequency: $frequency,
                scheduleIdentity: $scheduleIdentity,
                citation: $citation,
                verificationStatus: 'DEADLINE_UNVERIFIED',
                reason: $calcDetails['explanation'] ?? 'Unable to resolve statutory due date from schedule parameters.'
            );
        }

        /** @var Carbon $baseDueDate */
        $baseDueDate = $calcDetails['base_due_date'];

        // 3. Apply weekend / public holiday adjustment ONLY if explicitly permitted by the approved rule
        $adjustmentPolicy = strtoupper((string) ($scheduleRule['weekend_adjustment'] ?? 'NONE'));
        $publicHolidays = (array) ($context['public_holidays'] ?? $scheduleRule['public_holidays'] ?? []);
        $adjustedDueDate = $this->applyBusinessDayAdjustment($baseDueDate->copy(), $adjustmentPolicy, $publicHolidays);

        // 4. Separate payment due date if rule specifies a distinct payment schedule
        $paymentDueDate = $adjustedDueDate->copy();
        $paymentExplanation = null;
        if (isset($scheduleRule['payment_due_day']) || isset($scheduleRule['payment_offset_days'])) {
            $paymentCalc = $this->computePaymentDueDate($start, $end, $scheduleRule, $adjustmentPolicy, $publicHolidays);
            if ($paymentCalc !== null) {
                $paymentDueDate = $paymentCalc['date'];
                $paymentExplanation = $paymentCalc['explanation'];
            }
        }

        $wasAdjustedForWeekend = !$baseDueDate->isSameDay($adjustedDueDate);

        $explanation = $calcDetails['explanation'];
        if ($wasAdjustedForWeekend) {
            $explanation .= sprintf(
                ' Adjusted from %s (%s) to %s under explicit rule policy [%s].',
                $baseDueDate->toDateString(),
                $baseDueDate->format('l'),
                $adjustedDueDate->toDateString(),
                $adjustmentPolicy
            );
        }

        return [
            'is_verified' => true,
            'verification_status' => 'VERIFIED_OFFICIAL',
            'filing_frequency' => $frequency,
            'schedule_identity' => $scheduleIdentity,
            'filing_period_start' => $start->toDateString(),
            'filing_period_end' => $end->toDateString(),
            'filing_period_label' => $periodLabel,
            'original_statutory_due_date' => $baseDueDate->toDateString(),
            'current_due_date' => $adjustedDueDate->toDateString(),
            'payment_due_date' => $paymentDueDate->toDateString(),
            'timezone' => self::TIMEZONE,
            'source_citation' => $citation,
            'rule_version_id' => $ruleVersion->id,
            'rule_version_code' => $ruleVersion->version_code,
            'deadline_calculation_basis' => [
                'pattern' => $frequency,
                'schedule_identity' => $scheduleIdentity,
                'rule_version_id' => $ruleVersion->id,
                'rule_version_code' => $ruleVersion->version_code,
                'effective_from' => $ruleVersion->effective_from?->toDateString(),
                'effective_to' => $ruleVersion->effective_to?->toDateString(),
                'authority_code' => $definition->authority?->code,
                'obligation_code' => $definition->code,
                'source_citation' => $citation,
                'schedule_parameters' => $scheduleRule,
                'weekend_adjustment_policy' => $adjustmentPolicy,
                'weekend_adjusted' => $wasAdjustedForWeekend,
                'original_unadjusted_date' => $baseDueDate->toDateString(),
                'payment_due_date' => $paymentDueDate->toDateString(),
                'payment_explanation' => $paymentExplanation,
                'explanation' => $explanation,
                'timezone' => self::TIMEZONE,
            ],
        ];
    }

    /**
     * Extract structured schedule rule parameters from RuleVersion parameters.
     * Supports both explicit `filing_schedule` object and existing seeded RuleVersion parameter keys.
     *
     * @param ObligationDefinition $definition
     * @param array<string, mixed> $params
     * @param string $scheduleIdentity
     * @return array<string, mixed>|null
     */
    public function extractScheduleRule(
        ObligationDefinition $definition,
        array $params,
        string $scheduleIdentity = 'RETURN'
    ): ?array {
        // If explicit unverified flag is set in parameters
        if (isset($params['deadline_verified']) && $params['deadline_verified'] === false) {
            return null;
        }

        // 1. Check if structured `filing_schedule` or `schedules` is defined in RuleVersion parameters
        if (isset($params['schedules'][$scheduleIdentity]) && is_array($params['schedules'][$scheduleIdentity])) {
            return $params['schedules'][$scheduleIdentity];
        }

        if (isset($params['filing_schedule']) && is_array($params['filing_schedule'])) {
            $sched = $params['filing_schedule'];
            if (isset($sched['deadline_verified']) && $sched['deadline_verified'] === false) {
                return null;
            }
            return $sched;
        }

        $freq = strtoupper((string) ($params['filing_frequency'] ?? $definition->renewal_frequency ?? ''));

        // 2. Support flat parameter keys already present in approved RuleVersion records
        if ($freq === 'MONTHLY' || isset($params['return_due_day']) || isset($params['due_day_of_month']) || isset($params['remittance_day'])) {
            $dueDay = $params['return_due_day']
                ?? $params['due_day_of_month']
                ?? $params['remittance_day']
                ?? $params['due_day']
                ?? null;

            if ($dueDay === null) {
                return null;
            }

            $result = [
                'frequency' => 'MONTHLY',
                'due_day' => (int) $dueDay,
                'month_offset' => (int) ($params['month_offset'] ?? 1),
                'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
            ];

            if (isset($params['payment_due_day'])) {
                $result['payment_due_day'] = (int) $params['payment_due_day'];
                $result['payment_month_offset'] = (int) ($params['payment_month_offset'] ?? $result['month_offset']);
            }

            return $result;
        }

        if ($freq === 'QUARTERLY') {
            if (isset($params['quarter_due_dates']) && is_array($params['quarter_due_dates'])) {
                return [
                    'frequency' => 'QUARTERLY',
                    'quarter_due_dates' => $params['quarter_due_dates'],
                    'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
                ];
            }

            if (isset($params['due_day']) || isset($params['days_after_quarter_end'])) {
                return [
                    'frequency' => 'QUARTERLY',
                    'due_day' => isset($params['due_day']) ? (int) $params['due_day'] : null,
                    'month_offset' => (int) ($params['month_offset'] ?? 1),
                    'days_after_quarter_end' => isset($params['days_after_quarter_end']) ? (int) $params['days_after_quarter_end'] : null,
                    'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
                ];
            }

            return null;
        }

        if ($freq === 'ANNUAL') {
            if (isset($params['due_month']) && isset($params['due_day'])) {
                return [
                    'frequency' => 'ANNUAL',
                    'due_month' => (int) $params['due_month'],
                    'due_day' => (int) $params['due_day'],
                    'year_offset' => (int) ($params['year_offset'] ?? 0),
                    'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
                ];
            }

            if (isset($params['days_after_period_end']) || isset($params['months_after_period_end'])) {
                return [
                    'frequency' => 'ANNUAL',
                    'days_after_period_end' => isset($params['days_after_period_end']) ? (int) $params['days_after_period_end'] : null,
                    'months_after_period_end' => isset($params['months_after_period_end']) ? (int) $params['months_after_period_end'] : null,
                    'due_day' => isset($params['due_day']) ? (int) $params['due_day'] : null,
                    'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
                ];
            }

            if (!empty($params['validity_calendar_year']) && isset($params['annual_due_month'], $params['annual_due_day'])) {
                return [
                    'frequency' => 'ANNUAL',
                    'due_month' => (int) $params['annual_due_month'],
                    'due_day' => (int) $params['annual_due_day'],
                    'year_offset' => (int) ($params['year_offset'] ?? 0),
                    'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
                ];
            }

            return null;
        }

        if ($freq === 'FIXED_DATE' || isset($params['fixed_due_date'])) {
            if (empty($params['fixed_due_date'])) {
                return null;
            }
            return [
                'frequency' => 'FIXED_DATE',
                'fixed_due_date' => (string) $params['fixed_due_date'],
                'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
            ];
        }

        if ($freq === 'EVENT_DRIVEN' || isset($params['days_after_event'])) {
            $days = $params['days_after_event'] ?? $params['registration_deadline_days'] ?? $params['mandatory_within_days'] ?? null;
            if ($days === null) {
                return null;
            }
            return [
                'frequency' => 'EVENT_DRIVEN',
                'days_after_event' => (int) $days,
                'event_name' => (string) ($params['event_name'] ?? 'STATUTORY_TRIGGER_EVENT'),
                'weekend_adjustment' => $params['weekend_adjustment'] ?? 'NONE',
            ];
        }

        return null;
    }

    /**
     * Compute the unadjusted statutory due date based on the schedule pattern.
     *
     * @param string $frequency
     * @param Carbon $start
     * @param Carbon $end
     * @param array<string, mixed> $scheduleRule
     * @param array<string, mixed> $context
     * @return array{resolved: bool, base_due_date: Carbon|null, explanation: string}
     */
    protected function computeDueDateByPattern(
        string $frequency,
        Carbon $start,
        Carbon $end,
        array $scheduleRule,
        array $context = []
    ): array {
        switch ($frequency) {
            case 'MONTHLY':
                $dueDay = isset($scheduleRule['due_day']) ? (int) $scheduleRule['due_day'] : null;
                if ($dueDay === null || $dueDay < 1 || $dueDay > 31) {
                    return [
                        'resolved' => false,
                        'base_due_date' => null,
                        'explanation' => 'Invalid or missing monthly due_day in schedule definition.',
                    ];
                }

                $monthOffset = (int) ($scheduleRule['month_offset'] ?? 1);
                // Anchor to start of the filing period's month, add monthOffset without overflow, then clamp day to end of target month
                $targetMonth = $start->copy()->startOfMonth()->addMonthsNoOverflow($monthOffset);
                $daysInTargetMonth = $targetMonth->daysInMonth;
                $resolvedDay = min($dueDay, $daysInTargetMonth);
                $dueDate = $targetMonth->copy()->day($resolvedDay)->startOfDay();

                return [
                    'resolved' => true,
                    'base_due_date' => $dueDate,
                    'explanation' => sprintf(
                        'Monthly filing for period %s to %s: due on day %d of month +%d (%s).',
                        $start->toDateString(),
                        $end->toDateString(),
                        $resolvedDay,
                        $monthOffset,
                        $dueDate->toDateString()
                    ),
                ];

            case 'QUARTERLY':
                // Option A: Explicit quarter due dates map (e.g. {'Q1': '03-25', 'Q2': '06-25', 'Q3': '09-25', 'Q4': '12-20'})
                if (!empty($scheduleRule['quarter_due_dates']) && is_array($scheduleRule['quarter_due_dates'])) {
                    $quarterNum = (int) ceil($end->month / 3);
                    $qKey = "Q{$quarterNum}";
                    $mmdd = $scheduleRule['quarter_due_dates'][$qKey] ?? $scheduleRule['quarter_due_dates'][$quarterNum] ?? null;

                    if (!$mmdd || !preg_match('/^(\d{2})-(\d{2})$/', (string) $mmdd, $m)) {
                        return [
                            'resolved' => false,
                            'base_due_date' => null,
                            'explanation' => "Missing quarterly due date mapping for {$qKey}.",
                        ];
                    }

                    $dueYear = $end->year + (int) ($scheduleRule['year_offset'] ?? 0);
                    $dueMonth = (int) $m[1];
                    $dueDay = (int) $m[2];

                    $targetMonth = Carbon::create($dueYear, $dueMonth, 1, 0, 0, 0, self::TIMEZONE);
                    $resolvedDay = min($dueDay, $targetMonth->daysInMonth);
                    $dueDate = $targetMonth->day($resolvedDay)->startOfDay();

                    return [
                        'resolved' => true,
                        'base_due_date' => $dueDate,
                        'explanation' => sprintf(
                            'Quarterly schedule (%s) for period %s to %s: statutory due date %s.',
                            $qKey,
                            $start->toDateString(),
                            $end->toDateString(),
                            $dueDate->toDateString()
                        ),
                    ];
                }

                // Option B: Days after quarter end
                if (isset($scheduleRule['days_after_quarter_end'])) {
                    $daysAfter = (int) $scheduleRule['days_after_quarter_end'];
                    $dueDate = $end->copy()->addDays($daysAfter)->startOfDay();

                    return [
                        'resolved' => true,
                        'base_due_date' => $dueDate,
                        'explanation' => sprintf(
                            'Quarterly filing for period %s to %s: due %d days after quarter end (%s).',
                            $start->toDateString(),
                            $end->toDateString(),
                            $daysAfter,
                            $dueDate->toDateString()
                        ),
                    ];
                }

                // Option C: Day of month following quarter end
                if (isset($scheduleRule['due_day'])) {
                    $dueDay = (int) $scheduleRule['due_day'];
                    $monthOffset = (int) ($scheduleRule['month_offset'] ?? 1);
                    $targetMonth = $end->copy()->startOfMonth()->addMonthsNoOverflow($monthOffset);
                    $resolvedDay = min($dueDay, $targetMonth->daysInMonth);
                    $dueDate = $targetMonth->day($resolvedDay)->startOfDay();

                    return [
                        'resolved' => true,
                        'base_due_date' => $dueDate,
                        'explanation' => sprintf(
                            'Quarterly filing for period %s to %s: due on day %d of month +%d after quarter end (%s).',
                            $start->toDateString(),
                            $end->toDateString(),
                            $resolvedDay,
                            $monthOffset,
                            $dueDate->toDateString()
                        ),
                    ];
                }

                return [
                    'resolved' => false,
                    'base_due_date' => null,
                    'explanation' => 'Incomplete quarterly schedule definition.',
                ];

            case 'ANNUAL':
                // Option A: Specific month and day (with optional year_offset relative to period end year)
                if (isset($scheduleRule['due_month'], $scheduleRule['due_day'])) {
                    $dueMonth = (int) $scheduleRule['due_month'];
                    $dueDay = (int) $scheduleRule['due_day'];
                    $yearOffset = (int) ($scheduleRule['year_offset'] ?? 0);
                    $targetYear = $end->year + $yearOffset;

                    $targetMonth = Carbon::create($targetYear, $dueMonth, 1, 0, 0, 0, self::TIMEZONE);
                    $resolvedDay = min($dueDay, $targetMonth->daysInMonth);
                    $dueDate = $targetMonth->day($resolvedDay)->startOfDay();

                    return [
                        'resolved' => true,
                        'base_due_date' => $dueDate,
                        'explanation' => sprintf(
                            'Annual filing for period %s to %s: due on %04d-%02d-%02d.',
                            $start->toDateString(),
                            $end->toDateString(),
                            $targetYear,
                            $dueMonth,
                            $resolvedDay
                        ),
                    ];
                }

                // Option B: Months after period end (and optional day of month)
                if (isset($scheduleRule['months_after_period_end'])) {
                    $monthsAfter = (int) $scheduleRule['months_after_period_end'];
                    $targetMonth = $end->copy()->startOfMonth()->addMonthsNoOverflow($monthsAfter);
                    $dueDay = isset($scheduleRule['due_day']) ? (int) $scheduleRule['due_day'] : $targetMonth->daysInMonth;
                    $resolvedDay = min($dueDay, $targetMonth->daysInMonth);
                    $dueDate = $targetMonth->day($resolvedDay)->startOfDay();

                    return [
                        'resolved' => true,
                        'base_due_date' => $dueDate,
                        'explanation' => sprintf(
                            'Annual filing for period %s to %s: due %d months after period end (%s).',
                            $start->toDateString(),
                            $end->toDateString(),
                            $monthsAfter,
                            $dueDate->toDateString()
                        ),
                    ];
                }

                // Option C: Days after period end
                if (isset($scheduleRule['days_after_period_end'])) {
                    $daysAfter = (int) $scheduleRule['days_after_period_end'];
                    $dueDate = $end->copy()->addDays($daysAfter)->startOfDay();

                    return [
                        'resolved' => true,
                        'base_due_date' => $dueDate,
                        'explanation' => sprintf(
                            'Annual filing for period %s to %s: due %d days after period end (%s).',
                            $start->toDateString(),
                            $end->toDateString(),
                            $daysAfter,
                            $dueDate->toDateString()
                        ),
                    ];
                }

                return [
                    'resolved' => false,
                    'base_due_date' => null,
                    'explanation' => 'Incomplete annual schedule definition.',
                ];

            case 'FIXED_DATE':
                if (empty($scheduleRule['fixed_due_date'])) {
                    return [
                        'resolved' => false,
                        'base_due_date' => null,
                        'explanation' => 'Missing fixed_due_date parameter.',
                    ];
                }

                $dueDate = Carbon::parse((string) $scheduleRule['fixed_due_date'], self::TIMEZONE)->startOfDay();
                return [
                    'resolved' => true,
                    'base_due_date' => $dueDate,
                    'explanation' => sprintf(
                        'Fixed-date statutory filing for period %s to %s: due on %s.',
                        $start->toDateString(),
                        $end->toDateString(),
                        $dueDate->toDateString()
                    ),
                ];

            case 'EVENT_DRIVEN':
                $eventDateRaw = $context['event_date'] ?? $scheduleRule['event_date'] ?? null;
                if (empty($eventDateRaw)) {
                    return [
                        'resolved' => false,
                        'base_due_date' => null,
                        'explanation' => 'Event-driven obligation requires a known triggering event_date.',
                    ];
                }

                $daysAfterEvent = isset($scheduleRule['days_after_event']) ? (int) $scheduleRule['days_after_event'] : null;
                if ($daysAfterEvent === null) {
                    return [
                        'resolved' => false,
                        'base_due_date' => null,
                        'explanation' => 'Missing days_after_event in event-driven schedule rule.',
                    ];
                }

                $eventDate = Carbon::parse((string) $eventDateRaw, self::TIMEZONE)->startOfDay();
                $dueDate = $eventDate->copy()->addDays($daysAfterEvent)->startOfDay();
                $eventName = (string) ($scheduleRule['event_name'] ?? 'Triggering Event');

                return [
                    'resolved' => true,
                    'base_due_date' => $dueDate,
                    'explanation' => sprintf(
                        'Event-driven filing triggered by %s on %s: due %d days after event (%s).',
                        $eventName,
                        $eventDate->toDateString(),
                        $daysAfterEvent,
                        $dueDate->toDateString()
                    ),
                ];

            default:
                return [
                    'resolved' => false,
                    'base_due_date' => null,
                    'explanation' => "Unsupported filing frequency pattern [{$frequency}].",
                ];
        }
    }

    /**
     * Compute distinct payment due date where filing period vs payment period differs.
     *
     * @param Carbon $start
     * @param Carbon $end
     * @param array<string, mixed> $scheduleRule
     * @param string $adjustmentPolicy
     * @param array<int, string> $publicHolidays
     * @return array{date: Carbon, explanation: string}|null
     */
    protected function computePaymentDueDate(
        Carbon $start,
        Carbon $end,
        array $scheduleRule,
        string $adjustmentPolicy,
        array $publicHolidays
    ): ?array {
        if (isset($scheduleRule['payment_due_day'])) {
            $paymentDay = (int) $scheduleRule['payment_due_day'];
            $monthOffset = (int) ($scheduleRule['payment_month_offset'] ?? $scheduleRule['month_offset'] ?? 1);
            $targetMonth = $start->copy()->startOfMonth()->addMonthsNoOverflow($monthOffset);
            $resolvedDay = min($paymentDay, $targetMonth->daysInMonth);
            $basePaymentDate = $targetMonth->day($resolvedDay)->startOfDay();
            $adjustedPaymentDate = $this->applyBusinessDayAdjustment($basePaymentDate, $adjustmentPolicy, $publicHolidays);

            return [
                'date' => $adjustedPaymentDate,
                'explanation' => sprintf(
                    'Payment due on day %d of month +%d (%s).',
                    $resolvedDay,
                    $monthOffset,
                    $adjustedPaymentDate->toDateString()
                ),
            ];
        }

        if (isset($scheduleRule['payment_offset_days'])) {
            $offset = (int) $scheduleRule['payment_offset_days'];
            $basePaymentDate = $end->copy()->addDays($offset)->startOfDay();
            $adjustedPaymentDate = $this->applyBusinessDayAdjustment($basePaymentDate, $adjustmentPolicy, $publicHolidays);

            return [
                'date' => $adjustedPaymentDate,
                'explanation' => sprintf(
                    'Payment due %d days after period end (%s).',
                    $offset,
                    $adjustedPaymentDate->toDateString()
                ),
            ];
        }

        return null;
    }

    /**
     * Adjust due date for weekends and public holidays ONLY when the approved rule explicitly permits it.
     *
     * Supported policies:
     * - NONE (default): No adjustment; exact calendar day applies.
     * - NEXT_BUSINESS_DAY: Moves forward to the next working day.
     * - PREVIOUS_BUSINESS_DAY: Moves backward to the preceding working day.
     *
     * @param Carbon $date
     * @param string $policy
     * @param array<int, string> $publicHolidays List of 'YYYY-MM-DD' holiday dates
     * @return Carbon
     */
    public function applyBusinessDayAdjustment(Carbon $date, string $policy, array $publicHolidays = []): Carbon
    {
        $normalizedPolicy = strtoupper(trim($policy));
        if ($normalizedPolicy === 'NONE' || $normalizedPolicy === '') {
            return $date;
        }

        $holidaySet = array_flip($publicHolidays);

        $isNonBusinessDay = function (Carbon $d) use ($holidaySet): bool {
            return $d->isWeekend() || isset($holidaySet[$d->toDateString()]);
        };

        $adjusted = $date->copy();
        $safetyCounter = 0;

        if ($normalizedPolicy === 'NEXT_BUSINESS_DAY') {
            while ($isNonBusinessDay($adjusted) && $safetyCounter < 15) {
                $adjusted->addDay();
                $safetyCounter++;
            }
        } elseif ($normalizedPolicy === 'PREVIOUS_BUSINESS_DAY') {
            while ($isNonBusinessDay($adjusted) && $safetyCounter < 15) {
                $adjusted->subDay();
                $safetyCounter++;
            }
        }

        return $adjusted;
    }

    /**
     * Format human-readable period label (e.g., "Nov 2026", "Q1 2026", "FY 2026").
     */
    public function formatPeriodLabel(Carbon $start, Carbon $end, string $frequency): string
    {
        return match (strtoupper($frequency)) {
            'MONTHLY' => $start->format('M Y'),
            'QUARTERLY' => 'Q' . (int) ceil($end->month / 3) . ' ' . $end->year,
            'ANNUAL' => 'FY ' . $end->year,
            'FIXED_DATE' => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
            'EVENT_DRIVEN' => 'Event (' . $start->format('d M Y') . ')',
            default => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
        };
    }

    /**
     * Build a safe unverified result structure without inventing any due date.
     *
     * @return array<string, mixed>
     */
    protected function buildUnverifiedResult(
        ObligationDefinition $definition,
        ?RuleVersion $ruleVersion,
        Carbon $start,
        Carbon $end,
        string $frequency,
        string $scheduleIdentity,
        ?string $citation,
        string $verificationStatus,
        string $reason
    ): array {
        return [
            'is_verified' => false,
            'verification_status' => $verificationStatus,
            'filing_frequency' => strtoupper($frequency),
            'schedule_identity' => $scheduleIdentity,
            'filing_period_start' => $start->toDateString(),
            'filing_period_end' => $end->toDateString(),
            'filing_period_label' => $this->formatPeriodLabel($start, $end, $frequency),
            'original_statutory_due_date' => null,
            'current_due_date' => null,
            'payment_due_date' => null,
            'timezone' => self::TIMEZONE,
            'source_citation' => $citation,
            'rule_version_id' => $ruleVersion?->id,
            'rule_version_code' => $ruleVersion?->version_code,
            'deadline_calculation_basis' => [
                'pattern' => strtoupper($frequency),
                'schedule_identity' => $scheduleIdentity,
                'rule_version_id' => $ruleVersion?->id,
                'rule_version_code' => $ruleVersion?->version_code,
                'authority_code' => $definition->authority?->code,
                'obligation_code' => $definition->code,
                'source_citation' => $citation,
                'verification_status' => $verificationStatus,
                'explanation' => $reason,
                'timezone' => self::TIMEZONE,
            ],
        ];
    }
}

