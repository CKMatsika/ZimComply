<?php

namespace App\Console\Commands;

use App\Domains\ComplianceEngine\Services\FilingScheduleGenerator;
use Illuminate\Console\Command;

class GenerateFilingSchedulesCommand extends Command
{
    protected $signature = 'compliance:generate-filing-schedules
                            {--tenant= : Optional tenant ID to scope generation}
                            {--from= : Start anchor date (YYYY-MM-DD)}
                            {--horizon=12 : Future horizon in months}
                            {--chunk=50 : Organisation chunk size}';

    protected $description = 'Idempotently generate statutory filing occurrences across organisations for a configurable future horizon.';

    public function handle(FilingScheduleGenerator $generator): int
    {
        $tenantId = $this->option('tenant') ?: null;
        $fromDate = $this->option('from') ?: null;
        $horizon = (int) ($this->option('horizon') ?: FilingScheduleGenerator::DEFAULT_HORIZON_MONTHS);
        $chunk = (int) ($this->option('chunk') ?: 50);

        $this->info("Generating statutory filing occurrences (Horizon: {$horizon} months)...");

        $stats = $generator->generateForAllOrganisations(
            tenantId: $tenantId,
            startFromDate: $fromDate,
            horizonMonths: $horizon,
            chunkSize: $chunk
        );

        $this->info(sprintf(
            'Completed schedule generation across %d organisations: %d created, %d updated, %d skipped, %d unverified holds.',
            $stats['organisations_processed'],
            $stats['generated_count'],
            $stats['updated_count'],
            $stats['skipped_existing_count'],
            $stats['unverified_count']
        ));

        return self::SUCCESS;
    }
}

