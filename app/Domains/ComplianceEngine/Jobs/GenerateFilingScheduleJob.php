<?php

namespace App\Domains\ComplianceEngine\Jobs;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\ComplianceEngine\Services\FilingScheduleGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFilingScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public string $organisationId,
        public ?string $startFromDate = null,
        public int $horizonMonths = FilingScheduleGenerator::DEFAULT_HORIZON_MONTHS,
        public ?string $triggeredByUserId = null
    ) {}

    public function handle(FilingScheduleGenerator $generator): void
    {
        $organisation = Organisation::withoutGlobalScopes()->find($this->organisationId);
        if (!$organisation) {
            return;
        }

        $generator->generateForOrganisation(
            organisation: $organisation,
            startFromDate: $this->startFromDate,
            horizonMonths: $this->horizonMonths,
            triggeredByUserId: $this->triggeredByUserId
        );
    }
}
