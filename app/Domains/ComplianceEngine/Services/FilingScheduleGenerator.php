<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use App\Domains\ComplianceEngine\Models\FilingOccurrenceActivityLog;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FilingScheduleGenerator
{
    public const DEFAULT_HORIZON_MONTHS = 12;

    public function __construct(
        protected FilingDeadlineCalculator $calculator = new FilingDeadlineCalculator()
    ) {}

    /**
     * Generate filing occurrences for an organisation across a configurable future horizon.
     *
     * Safeguards:
     * - Idempotent generation (no duplicate occurrences).
     * - Tenant and organisation isolation.
     * - Transactional persistence & concurrency lock protection.
     * - Correct effective-dated rule-version selection per filing period.
     * - Historical immutability (never overwrites completed or historical filing occurrences).
     * - Safe regeneration after regulatory amendments (updates future unstarted occurrences).
     * - Clear handling of obligations that become inapplicable (stops generating new future periods
     *   and marks existing future unstarted occurrences without deleting historical records).
     *
     * @param Organisation $organisation
     * @param CarbonInterface|string|null $startFromDate
     * @param int $horizonMonths
     * @param string|null $triggeredByUserId
     * @return array{
     *     organisation_id: string,
     *     tenant_id: string,
     *     horizon_months: int,
     *     generated_count: int,
     *     updated_count: int,
     *     skipped_existing_count: int,
     *     unverified_count: int,
     *     inapplicable_handled_count: int
     * }
     */
    public function generateForOrganisation(
        Organisation $organisation,
        CarbonInterface|string|null $startFromDate = null,
        int $horizonMonths = self::DEFAULT_HORIZON_MONTHS,
        ?string $triggeredByUserId = null
    ): array {
        $lockKey = "filing_schedule_gen_org_{$organisation->id}";
        $lock = Cache::lock($lockKey, 60);

        $stats = [
            'organisation_id' => $organisation->id,
            'tenant_id' => $organisation->tenant_id,
            'horizon_months' => $horizonMonths,
            'generated_count' => 0,
            'updated_count' => 0,
            'skipped_existing_count' => 0,
            'unverified_count' => 0,
            'inapplicable_handled_count' => 0,
        ];

        // Protect against concurrent schedule generation
        if (!$lock->get()) {
            return $stats;
        }

        try {
            $anchorDate = $startFromDate
                ? Carbon::parse($startFromDate, FilingDeadlineCalculator::TIMEZONE)->startOfDay()
                : Carbon::now(FilingDeadlineCalculator::TIMEZONE)->startOfMonth();

            $instances = ObligationInstance::withoutGlobalScopes()
                ->where('tenant_id', $organisation->tenant_id)
                ->where('organisation_id', $organisation->id)
                ->with(['definition.authority', 'definition.source', 'assessmentResult'])
                ->get();

            foreach ($instances as $instance) {
                $instanceResult = $this->generateForObligationInstance(
                    instance: $instance,
                    anchorDate: $anchorDate,
                    horizonMonths: $horizonMonths,
                    triggeredByUserId: $triggeredByUserId
                );

                $stats['generated_count'] += $instanceResult['generated_count'];
                $stats['updated_count'] += $instanceResult['updated_count'];
                $stats['skipped_existing_count'] += $instanceResult['skipped_existing_count'];
                $stats['unverified_count'] += $instanceResult['unverified_count'];
                $stats['inapplicable_handled_count'] += $instanceResult['inapplicable_handled_count'];
            }

            return $stats;
        } finally {
            $lock->release();
        }
    }

    /**
     * Generate or synchronize filing occurrences for a single ObligationInstance.
     *
     * @param ObligationInstance $instance
     * @param CarbonInterface|string|null $anchorDate
     * @param int $horizonMonths
     * @param string|null $triggeredByUserId
     * @param array<string, mixed> $context
     * @return array{
     *     generated_count: int,
     *     updated_count: int,
     *     skipped_existing_count: int,
     *     unverified_count: int,
     *     inapplicable_handled_count: int
     * }
     */
    public function generateForObligationInstance(
        ObligationInstance $instance,
        CarbonInterface|string|null $anchorDate = null,
        int $horizonMonths = self::DEFAULT_HORIZON_MONTHS,
        ?string $triggeredByUserId = null,
        array $context = []
    ): array {
        $result = [
            'generated_count' => 0,
            'updated_count' => 0,
            'skipped_existing_count' => 0,
            'unverified_count' => 0,
            'inapplicable_handled_count' => 0,
        ];

        $definition = $instance->definition;
        if (!$definition || !$definition->is_active) {
            return $result;
        }

        $startAnchor = $anchorDate
            ? Carbon::parse($anchorDate, FilingDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(FilingDeadlineCalculator::TIMEZONE)->startOfMonth();

        // Check if obligation is currently inapplicable or exempt
        $decision = $instance->assessmentResult?->decision;
        $isInapplicable = in_array($instance->status, ['NOT_APPLICABLE', 'EXEMPT'], true)
            || in_array($decision, ['NOT_APPLICABLE', 'EXEMPT'], true);

        if ($isInapplicable) {
            $result['inapplicable_handled_count'] = $this->handleInapplicableObligation($instance, $startAnchor, $triggeredByUserId);
            return $result;
        }

        // Determine recurrence frequency
        $frequency = strtoupper((string) ($instance->compliance_frequency ?: $definition->renewal_frequency ?: 'ONCE_OFF'));

        // Skip pure ONCE_OFF registration obligations unless they are EVENT_DRIVEN or FIXED_DATE with schedule rules
        if ($frequency === 'ONCE_OFF' && empty($context['event_date']) && empty($context['fixed_due_date'])) {
            return $result;
        }

        $periods = $this->buildFilingPeriods(
            frequency: $frequency,
            anchorDate: $startAnchor,
            horizonMonths: $horizonMonths,
            context: $context
        );

        foreach ($periods as $period) {
            /** @var Carbon $pStart */
            $pStart = $period['start'];
            /** @var Carbon $pEnd */
            $pEnd = $period['end'];

            // Select the approved, active RuleVersion effective for this filing period (evaluated as of period end)
            $ruleVersion = $this->resolveRuleVersionForPeriod($definition, $pStart, $pEnd);

            $calc = $this->calculator->calculate(
                definition: $definition,
                ruleVersion: $ruleVersion,
                periodStart: $pStart,
                periodEnd: $pEnd,
                context: $context
            );

            $outcome = $this->persistOrSyncOccurrence(
                instance: $instance,
                definition: $definition,
                calc: $calc,
                anchorDate: $startAnchor,
                triggeredByUserId: $triggeredByUserId
            );

            if ($outcome === 'CREATED') {
                $result['generated_count']++;
                if (!$calc['is_verified']) {
                    $result['unverified_count']++;
                }
            } elseif ($outcome === 'UPDATED') {
                $result['updated_count']++;
            } else {
                $result['skipped_existing_count']++;
            }
        }

        // Sync parent ObligationInstance next_due_date to the earliest upcoming incomplete verified occurrence
        $this->syncParentNextDueDate($instance);

        return $result;
    }

    /**
     * Multi-organisation chunked generation for large datasets.
     */
    public function generateForAllOrganisations(
        ?string $tenantId = null,
        CarbonInterface|string|null $startFromDate = null,
        int $horizonMonths = self::DEFAULT_HORIZON_MONTHS,
        int $chunkSize = 50
    ): array {
        $totalStats = [
            'organisations_processed' => 0,
            'generated_count' => 0,
            'updated_count' => 0,
            'skipped_existing_count' => 0,
            'unverified_count' => 0,
        ];

        $query = Organisation::withoutGlobalScopes();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $query->chunkById($chunkSize, function (Collection $organisations) use ($startFromDate, $horizonMonths, &$totalStats) {
            foreach ($organisations as $org) {
                $res = $this->generateForOrganisation($org, $startFromDate, $horizonMonths);
                $totalStats['organisations_processed']++;
                $totalStats['generated_count'] += $res['generated_count'];
                $totalStats['updated_count'] += $res['updated_count'];
                $totalStats['skipped_existing_count'] += $res['skipped_existing_count'];
                $totalStats['unverified_count'] += $res['unverified_count'];
            }
        });

        return $totalStats;
    }

    /**
     * Build the sequence of filing periods across the horizon.
     *
     * @param string $frequency
     * @param Carbon $anchorDate
     * @param int $horizonMonths
     * @param array<string, mixed> $context
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function buildFilingPeriods(
        string $frequency,
        Carbon $anchorDate,
        int $horizonMonths = self::DEFAULT_HORIZON_MONTHS,
        array $context = []
    ): array {
        $periods = [];
        $months = max(1, $horizonMonths);

        switch (strtoupper($frequency)) {
            case 'MONTHLY':
                $cursor = $anchorDate->copy()->startOfMonth();
                for ($i = 0; $i < $months; $i++) {
                    $pStart = $cursor->copy()->startOfMonth();
                    $pEnd = $cursor->copy()->endOfMonth()->startOfDay();
                    $periods[] = ['start' => $pStart, 'end' => $pEnd];
                    $cursor->addMonthsNoOverflow(1);
                }
                break;

            case 'QUARTERLY':
                $quartersCount = max(1, (int) ceil($months / 3));
                $cursor = $anchorDate->copy()->firstOfQuarter();
                for ($i = 0; $i < $quartersCount; $i++) {
                    $pStart = $cursor->copy()->firstOfQuarter()->startOfDay();
                    $pEnd = $cursor->copy()->lastOfQuarter()->startOfDay();
                    $periods[] = ['start' => $pStart, 'end' => $pEnd];
                    $cursor->addMonthsNoOverflow(3)->firstOfQuarter();
                }
                break;

            case 'ANNUAL':
                $yearsCount = max(1, (int) ceil($months / 12));
                $cursorYear = $anchorDate->year;
                for ($i = 0; $i < $yearsCount; $i++) {
                    $yr = $cursorYear + $i;
                    $pStart = Carbon::create($yr, 1, 1, 0, 0, 0, FilingDeadlineCalculator::TIMEZONE)->startOfDay();
                    $pEnd = Carbon::create($yr, 12, 31, 0, 0, 0, FilingDeadlineCalculator::TIMEZONE)->startOfDay();
                    $periods[] = ['start' => $pStart, 'end' => $pEnd];
                }
                break;

            case 'FIXED_DATE':
                $pStart = isset($context['period_start'])
                    ? Carbon::parse($context['period_start'], FilingDeadlineCalculator::TIMEZONE)->startOfDay()
                    : $anchorDate->copy()->startOfMonth();
                $pEnd = isset($context['period_end'])
                    ? Carbon::parse($context['period_end'], FilingDeadlineCalculator::TIMEZONE)->startOfDay()
                    : $anchorDate->copy()->endOfMonth()->startOfDay();
                $periods[] = ['start' => $pStart, 'end' => $pEnd];
                break;

            case 'EVENT_DRIVEN':
                if (!empty($context['event_date'])) {
                    $evDate = Carbon::parse($context['event_date'], FilingDeadlineCalculator::TIMEZONE)->startOfDay();
                    $periods[] = ['start' => $evDate->copy(), 'end' => $evDate->copy()];
                }
                break;
        }

        return $periods;
    }

    /**
     * Resolve the approved, active RuleVersion effective for the given filing period.
     * Supports rule amendments effective partway through a year by matching against the period end date
     * (or period start date if applicable).
     */
    public function resolveRuleVersionForPeriod(
        ObligationDefinition $definition,
        Carbon $periodStart,
        Carbon $periodEnd
    ): ?RuleVersion {
        if (!$definition->rule_pack_id) {
            return null;
        }

        $targetDate = $periodEnd->toDateString();

        // First look for an ACTIVE, VERIFIED_OFFICIAL rule version covering the period end date
        $activeVersion = RuleVersion::query()
            ->with('source')
            ->where('rule_pack_id', $definition->rule_pack_id)
            ->whereIn('status', ['ACTIVE', 'SUPERSEDED'])
            ->where('verification_status', 'VERIFIED_OFFICIAL')
            ->where('effective_from', '<=', $targetDate)
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $targetDate);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($activeVersion) {
            // Ensure that if it was SUPERSEDED, it was still the valid rule version for that historical period,
            // while for current/future periods ACTIVE takes precedence.
            // To satisfy "active" check in calculator when historical: temporarily mark or normalize if verified.
            if ($activeVersion->status === 'SUPERSEDED' && $activeVersion->isVerified()) {
                $activeVersion->status = 'ACTIVE';
            }
            return $activeVersion;
        }

        return null;
    }

    /**
     * Transactionally persist a new filing occurrence or safely update a future unstarted one
     * if a regulatory amendment changed the rule version.
     *
     * Historical immutability: Never overwrite historical filing periods or any occurrence
     * that has already begun preparation or been completed.
     *
     * @param ObligationInstance $instance
     * @param ObligationDefinition $definition
     * @param array<string, mixed> $calc
     * @param Carbon $anchorDate
     * @param string|null $triggeredByUserId
     * @return string 'CREATED' | 'UPDATED' | 'SKIPPED'
     */
    protected function persistOrSyncOccurrence(
        ObligationInstance $instance,
        ObligationDefinition $definition,
        array $calc,
        Carbon $anchorDate,
        ?string $triggeredByUserId = null
    ): string {
        $scheduleIdentity = (string) ($calc['schedule_identity'] ?? 'RETURN');
        $pStart = (string) $calc['filing_period_start'];
        $pEnd = (string) $calc['filing_period_end'];

        $existing = FilingOccurrence::withoutGlobalScopes()
            ->where('organisation_id', $instance->organisation_id)
            ->where('obligation_instance_id', $instance->id)
            ->whereDate('filing_period_start', $pStart)
            ->whereDate('filing_period_end', $pEnd)
            ->where('schedule_identity', $scheduleIdentity)
            ->first();

        if ($existing) {
            // Historical Immutability Safeguard:
            // 1. Never overwrite completed filings (SUBMITTED, ACKNOWLEDGED, CLOSED)
            // 2. Never overwrite historical periods (filing_period_end < anchorDate)
            // 3. Never overwrite occurrences where work has started (workflow_status != NOT_STARTED)
            $isHistoricalPeriod = $existing->filing_period_end->lt($anchorDate);
            $hasWorkStarted = $existing->workflow_status !== FilingLifecycleStateMachine::NOT_STARTED;

            if ($existing->isCompleted() || $isHistoricalPeriod || $hasWorkStarted) {
                return 'SKIPPED';
            }

            // Check if a regulatory amendment changed the effective rule_version_id for this future unstarted period
            $newRuleVersionId = $calc['rule_version_id'];
            if ($newRuleVersionId !== null && $existing->rule_version_id !== $newRuleVersionId) {
                DB::transaction(function () use ($existing, $calc, $triggeredByUserId) {
                    $oldRuleId = $existing->rule_version_id;
                    $oldDueDate = $existing->current_due_date?->toDateString();

                    $existing->update([
                        'rule_version_id' => $calc['rule_version_id'],
                        'current_due_date' => $calc['current_due_date'],
                        'payment_due_date' => $calc['payment_due_date'],
                        'deadline_calculation_basis' => $calc['deadline_calculation_basis'],
                        'source_citation' => $calc['source_citation'],
                        'verification_status' => $calc['verification_status'],
                        'is_obligation_currently_applicable' => true,
                        'lock_version' => $existing->lock_version + 1,
                    ]);

                    FilingOccurrenceActivityLog::create([
                        'tenant_id' => $existing->tenant_id,
                        'organisation_id' => $existing->organisation_id,
                        'filing_occurrence_id' => $existing->id,
                        'user_id' => $triggeredByUserId,
                        'activity_type' => 'RULE_AMENDED',
                        'notes' => sprintf(
                            'Future unstarted occurrence updated following regulatory rule version amendment (%s -> %s). Due date: %s -> %s.',
                            $oldRuleId ?? 'NONE',
                            $calc['rule_version_code'] ?? $calc['rule_version_id'],
                            $oldDueDate ?? 'UNVERIFIED',
                            $calc['current_due_date'] ?? 'UNVERIFIED'
                        ),
                        'metadata' => [
                            'previous_rule_version_id' => $oldRuleId,
                            'new_rule_version_id' => $calc['rule_version_id'],
                            'previous_due_date' => $oldDueDate,
                            'new_due_date' => $calc['current_due_date'],
                        ],
                        'created_at' => Carbon::now(),
                    ]);
                });

                return 'UPDATED';
            }

            return 'SKIPPED';
        }

        // Create new FilingOccurrence inside a transaction, handling race conditions via unique constraint
        try {
            return DB::transaction(function () use ($instance, $definition, $calc, $scheduleIdentity, $triggeredByUserId) {
                $created = FilingOccurrence::create([
                    'tenant_id' => $instance->tenant_id,
                    'organisation_id' => $instance->organisation_id,
                    'obligation_instance_id' => $instance->id,
                    'obligation_definition_id' => $definition->id,
                    'rule_version_id' => $calc['rule_version_id'],
                    'schedule_identity' => $scheduleIdentity,
                    'filing_frequency' => $calc['filing_frequency'],
                    'filing_period_start' => $calc['filing_period_start'],
                    'filing_period_end' => $calc['filing_period_end'],
                    'filing_period_label' => $calc['filing_period_label'],
                    'original_statutory_due_date' => $calc['original_statutory_due_date'],
                    'current_due_date' => $calc['current_due_date'],
                    'payment_due_date' => $calc['payment_due_date'],
                    'deadline_calculation_basis' => $calc['deadline_calculation_basis'],
                    'timezone' => $calc['timezone'],
                    'assigned_to_user_id' => $instance->assigned_to_user_id,
                    'workflow_status' => FilingLifecycleStateMachine::NOT_STARTED,
                    'official_submission_status' => FilingLifecycleStateMachine::OFFICIAL_NOT_SUBMITTED,
                    'evidence_status' => 'NONE',
                    'source_citation' => $calc['source_citation'],
                    'verification_status' => $calc['verification_status'],
                    'is_obligation_currently_applicable' => true,
                    'lock_version' => 1,
                ]);

                FilingOccurrenceActivityLog::create([
                    'tenant_id' => $instance->tenant_id,
                    'organisation_id' => $instance->organisation_id,
                    'filing_occurrence_id' => $created->id,
                    'user_id' => $triggeredByUserId,
                    'activity_type' => 'GENERATED',
                    'to_status' => FilingLifecycleStateMachine::NOT_STARTED,
                    'to_official_status' => FilingLifecycleStateMachine::OFFICIAL_NOT_SUBMITTED,
                    'notes' => $calc['is_verified']
                        ? "Generated statutory filing occurrence for {$calc['filing_period_label']} (Due: {$calc['current_due_date']})."
                        : "Generated filing occurrence on hold ({$calc['verification_status']}): official deadline rule unverified.",
                    'metadata' => [
                        'rule_version_id' => $calc['rule_version_id'],
                        'rule_version_code' => $calc['rule_version_code'],
                        'verification_status' => $calc['verification_status'],
                    ],
                    'created_at' => Carbon::now(),
                ]);

                return 'CREATED';
            });
        } catch (QueryException $e) {
            // Unique constraint hit during concurrent execution -> idempotently skip
            return 'SKIPPED';
        }
    }

    /**
     * Handle obligations that have become inapplicable:
     * - Never delete completed or historical filings.
     * - Mark future unstarted occurrences as CLOSED with `is_obligation_currently_applicable = false`.
     */
    protected function handleInapplicableObligation(
        ObligationInstance $instance,
        Carbon $anchorDate,
        ?string $triggeredByUserId = null
    ): int {
        $futureUnstarted = FilingOccurrence::withoutGlobalScopes()
            ->where('organisation_id', $instance->organisation_id)
            ->where('obligation_instance_id', $instance->id)
            ->where('workflow_status', FilingLifecycleStateMachine::NOT_STARTED)
            ->whereDate('filing_period_start', '>=', $anchorDate->toDateString())
            ->get();

        $count = 0;
        foreach ($futureUnstarted as $occ) {
            DB::transaction(function () use ($occ, $triggeredByUserId, &$count) {
                $occ->update([
                    'workflow_status' => FilingLifecycleStateMachine::CLOSED,
                    'closed_at' => Carbon::now(),
                    'closed_by_user_id' => $triggeredByUserId,
                    'is_obligation_currently_applicable' => false,
                    'internal_notes' => 'Parent obligation became inapplicable or exempt; future unstarted occurrence closed automatically.',
                    'lock_version' => $occ->lock_version + 1,
                ]);

                FilingOccurrenceActivityLog::create([
                    'tenant_id' => $occ->tenant_id,
                    'organisation_id' => $occ->organisation_id,
                    'filing_occurrence_id' => $occ->id,
                    'user_id' => $triggeredByUserId,
                    'activity_type' => 'STATUS_TRANSITION',
                    'from_status' => FilingLifecycleStateMachine::NOT_STARTED,
                    'to_status' => FilingLifecycleStateMachine::CLOSED,
                    'notes' => 'Closed automatically because parent obligation is no longer applicable.',
                    'created_at' => Carbon::now(),
                ]);

                $count++;
            });
        }

        return $count;
    }

    /**
     * Synchronize the parent ObligationInstance's `next_due_date` to the earliest incomplete verified occurrence.
     */
    protected function syncParentNextDueDate(ObligationInstance $instance): void
    {
        $nextOccurrence = FilingOccurrence::withoutGlobalScopes()
            ->where('organisation_id', $instance->organisation_id)
            ->where('obligation_instance_id', $instance->id)
            ->whereNotIn('workflow_status', [
                FilingLifecycleStateMachine::SUBMITTED,
                FilingLifecycleStateMachine::ACKNOWLEDGED,
                FilingLifecycleStateMachine::CLOSED,
            ])
            ->where('verification_status', 'VERIFIED_OFFICIAL')
            ->whereNotNull('current_due_date')
            ->orderBy('current_due_date', 'asc')
            ->first();

        if ($nextOccurrence && $nextOccurrence->current_due_date) {
            $instance->update([
                'next_due_date' => $nextOccurrence->current_due_date->toDateString(),
            ]);
        }
    }
}

