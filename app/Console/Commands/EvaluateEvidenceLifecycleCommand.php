<?php

namespace App\Console\Commands;

use App\Domains\RegistrationEngine\Services\ComplianceDocumentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EvaluateEvidenceLifecycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:evaluate-evidence-lifecycle {--date= : Effective evaluation date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate statutory compliance document expirations and update linked obligation evidence status.';

    /**
     * Execute the console command.
     */
    public function handle(ComplianceDocumentService $documentService): int
    {
        $dateOption = $this->option('date');
        $asOfDate = null;

        if ($dateOption) {
            try {
                $asOfDate = Carbon::parse($dateOption)->startOfDay();
            } catch (\Throwable $e) {
                $this->error("Invalid date format [{$dateOption}]. Expected YYYY-MM-DD.");
                return self::FAILURE;
            }
        }

        $effectiveDate = $asOfDate ?? Carbon::now()->startOfDay();
        $this->info("Evaluating evidence lifecycle as of [{$effectiveDate->toDateString()}]...");

        $expiredCount = $documentService->evaluateExpirations($effectiveDate);

        $this->info("Completed evidence lifecycle evaluation. Transitioned [{$expiredCount}] documents to EXPIRED.");

        return self::SUCCESS;
    }
}
