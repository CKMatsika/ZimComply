<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FilingDisplayClassifier
{
    public const UPCOMING = 'UPCOMING';
    public const DUE_SOON = 'DUE_SOON';
    public const DUE_TODAY = 'DUE_TODAY';
    public const OVERDUE = 'OVERDUE';
    public const COMPLETED = 'COMPLETED';
    public const DEADLINE_UNVERIFIED = 'DEADLINE_UNVERIFIED';

    public const DEFAULT_DUE_SOON_DAYS = 14;

    /**
     * Dynamically calculate the operational display classification of a filing occurrence.
     *
     * Rules:
     * 1. Completed occurrences (SUBMITTED, ACKNOWLEDGED, CLOSED) -> COMPLETED
     *    (whilst separately displaying whether official acceptance is confirmed or pending).
     * 2. Unverified due dates (DEADLINE_UNVERIFIED, UNVERIFIED_HOLD, or null current_due_date) -> DEADLINE_UNVERIFIED.
     *    Crucial safeguard: NEVER classify an occurrence as OVERDUE if its statutory due date is unverified.
     * 3. current_due_date < referenceDate -> OVERDUE.
     * 4. current_due_date == referenceDate -> DUE_TODAY.
     * 5. 0 < daysUntilDue <= dueSoonDays -> DUE_SOON.
     * 6. daysUntilDue > dueSoonDays -> UPCOMING.
     *
     * @param FilingOccurrence $occurrence
     * @param CarbonInterface|string|null $referenceDate
     * @param int|null $dueSoonWindowDays
     * @return array{
     *     classification: string,
     *     label: string,
     *     days_delta: int|null,
     *     due_soon_window_days: int
     * }
     */
    public function classify(
        FilingOccurrence $occurrence,
        CarbonInterface|string|null $referenceDate = null,
        ?int $dueSoonWindowDays = null
    ): array {
        $windowDays = $dueSoonWindowDays ?? (int) config('compliance.due_soon_days', self::DEFAULT_DUE_SOON_DAYS);
        $today = $referenceDate
            ? Carbon::parse($referenceDate, FilingDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(FilingDeadlineCalculator::TIMEZONE)->startOfDay();

        // 1. Check if internal / official filing workflow is completed
        if ($occurrence->isCompleted()) {
            return [
                'classification' => self::COMPLETED,
                'label' => $occurrence->isOfficiallyAcknowledged()
                    ? 'Completed (Verified)'
                    : 'Submitted (Internal)',
                'days_delta' => null,
                'due_soon_window_days' => $windowDays,
            ];
        }

        // 2. Check if statutory deadline is unverified or on hold
        if ($occurrence->isDeadlineUnverified() || $occurrence->current_due_date === null) {
            return [
                'classification' => self::DEADLINE_UNVERIFIED,
                'label' => 'Deadline Unverified (Hold)',
                'days_delta' => null,
                'due_soon_window_days' => $windowDays,
            ];
        }

        $dueDate = Carbon::parse($occurrence->current_due_date->toDateString(), FilingDeadlineCalculator::TIMEZONE)->startOfDay();
        $daysUntilDue = (int) $today->diffInDays($dueDate, false);

        if ($daysUntilDue < 0) {
            $overdueDays = abs($daysUntilDue);
            return [
                'classification' => self::OVERDUE,
                'label' => $overdueDays === 1 ? 'Overdue by 1 day' : "Overdue by {$overdueDays} days",
                'days_delta' => $daysUntilDue,
                'due_soon_window_days' => $windowDays,
            ];
        }

        if ($daysUntilDue === 0) {
            return [
                'classification' => self::DUE_TODAY,
                'label' => 'Due Today',
                'days_delta' => 0,
                'due_soon_window_days' => $windowDays,
            ];
        }

        if ($daysUntilDue <= $windowDays) {
            return [
                'classification' => self::DUE_SOON,
                'label' => $daysUntilDue === 1 ? 'Due in 1 day' : "Due in {$daysUntilDue} days",
                'days_delta' => $daysUntilDue,
                'due_soon_window_days' => $windowDays,
            ];
        }

        return [
            'classification' => self::UPCOMING,
            'label' => "Due in {$daysUntilDue} days",
            'days_delta' => $daysUntilDue,
            'due_soon_window_days' => $windowDays,
        ];
    }
}

