<?php

namespace App\Domains\RegistrationEngine\Jobs;

use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Services\ComplianceDocumentService;
use App\Models\User;
use App\Shared\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly string $documentId,
        public readonly ?string $userId = null
    ) {}

    public function handle(ComplianceDocumentService $service, TenantContext $tenantContext): void
    {
        $document = ComplianceDocument::withoutGlobalScopes()->find($this->documentId);

        if (!$document) {
            Log::warning("ExtractDocumentJob aborted: Document [{$this->documentId}] not found.");
            return;
        }

        // Restore Tenant Context
        $tenantContext->setTenant($document->organisation->tenant);

        $user = $this->userId ? User::find($this->userId) : null;

        try {
            $service->processExtraction($document, $user);
        } catch (\Throwable $e) {
            Log::error("ExtractDocumentJob execution failed for document [{$this->documentId}]: " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                $document->update([
                    'processing_status' => 'FAILED',
                ]);
            }

            throw $e;
        }
    }
}
