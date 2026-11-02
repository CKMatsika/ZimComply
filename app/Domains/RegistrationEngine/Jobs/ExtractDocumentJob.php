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
use Illuminate\Support\Str;

class ExtractDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 60;

    public function __construct(
        public readonly string $documentId,
        public readonly ?string $userId = null,
        public readonly bool $force = false
    ) {}

    public function handle(ComplianceDocumentService $service, TenantContext $tenantContext): void
    {
        $document = ComplianceDocument::withoutGlobalScopes()
            ->with(['organisation.tenant'])
            ->find($this->documentId);

        if (!$document) {
            Log::warning("ExtractDocumentJob aborted: Document [{$this->documentId}] not found.");
            return;
        }

        // Restore Tenant and Organisation Context
        if ($document->organisation && $document->organisation->tenant) {
            $tenantContext->setTenant($document->organisation->tenant);
            $tenantContext->setOrganisation($document->organisation);
        }

        $user = $this->userId ? User::find($this->userId) : null;

        try {
            $service->processExtraction($document, $user, $this->force);
        } catch (\Throwable $e) {
            Log::error("ExtractDocumentJob execution failed for document [{$this->documentId}]: " . $e->getMessage(), [
                'document_id' => $this->documentId,
                'attempt' => $this->attempts(),
            ]);

            $document->update([
                'last_extraction_error' => Str::limit($e->getMessage(), 500),
                'extraction_attempts' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $document->update([
                    'processing_status' => 'FAILED',
                ]);
            }

            throw $e;
        }
    }
}
