<?php

namespace App\Domains\RegistrationEngine\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Models\User;
use App\Shared\Ai\Contracts\AiOrchestrationServiceInterface;
use App\Shared\Ai\Contracts\AiUsageMonitorInterface;
use App\Shared\Ai\Services\DataPrivacySanitizer;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceDocumentService
{
    public const SUPPORTED_CATEGORIES = [
        'CERTIFICATE_OF_INCORPORATION' => 'Certificate of Incorporation (CIPZ)',
        'CR6' => 'Form CR6 (Registered Office Notice)',
        'CR14' => 'Form CR14 / CR5 (Directors & Secretaries)',
        'ITF263' => 'ZIMRA Tax Clearance Certificate (ITF263)',
        'NSSA_REGISTRATION' => 'NSSA Employer Registration / Clearance',
        'MUNICIPAL_LICENCE' => 'Municipal Business / Operating Licence',
        'OTHER' => 'Other Supporting Statutory Evidence',
    ];

    public const SUPPORTED_MIME_TYPES = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public const MAX_FILE_SIZE_BYTES = 10485760; // 10 MB

    public function __construct(
        protected TenantContext $tenantContext,
        protected ?AiOrchestrationServiceInterface $aiOrchestration = null,
        protected ?AiUsageMonitorInterface $usageMonitor = null,
        protected ?DataPrivacySanitizer $sanitizer = null
    ) {
        $this->aiOrchestration = $aiOrchestration ?? app(AiOrchestrationServiceInterface::class);
        $this->usageMonitor = $usageMonitor ?? app(AiUsageMonitorInterface::class);
        $this->sanitizer = $sanitizer ?? app(DataPrivacySanitizer::class);
    }

    /**
     * Upload and store a compliance evidence document in private, tenant-isolated storage.
     */
    public function upload(
        UploadedFile $file,
        array $attributes,
        User $uploader,
        Organisation $organisation
    ): ComplianceDocument {
        $tenantId = $this->tenantContext->getTenantId();

        if (empty($tenantId) || $organisation->tenant_id !== $tenantId) {
            throw new AuthorizationException('Cross-tenant document upload strictly prohibited.');
        }

        // 1. Validate File Size
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('File exceeds maximum allowed size of 10 MB.');
        }

        // 2. Validate MIME & Extension Matching
        $mimeType = $file->getMimeType() ?? $file->getClientMimeType();
        $clientExt = strtolower($file->getClientOriginalExtension());

        if (!array_key_exists($mimeType, self::SUPPORTED_MIME_TYPES)) {
            throw new InvalidArgumentException("Unsupported MIME type [{$mimeType}]. Allowed: PDF, JPEG, PNG.");
        }

        $allowedExtensions = self::SUPPORTED_MIME_TYPES[$mimeType];
        if (!in_array($clientExt, $allowedExtensions, true)) {
            throw new InvalidArgumentException("File extension [{$clientExt}] does not match MIME type [{$mimeType}]. Possible file spoofing detected.");
        }

        // 3. Inspect Magic Bytes / File Header
        $this->validateMagicBytes($file, $mimeType);

        // 4. Validate Category
        $category = $attributes['document_type'] ?? 'OTHER';
        if (!array_key_exists($category, self::SUPPORTED_CATEGORIES)) {
            throw new InvalidArgumentException("Unsupported document category [{$category}].");
        }

        // 5. Calculate SHA-256 Hash
        $fileContents = file_get_contents($file->getRealPath());
        if ($fileContents === false) {
            throw new RuntimeException('Unable to read uploaded file contents.');
        }
        $sha256Hash = hash('sha256', $fileContents);

        // 6. Generate Randomized Internal Storage Path (never trust client filename)
        $storageId = Str::uuid()->toString() . '.' . $clientExt;
        $privatePath = "tenants/{$tenantId}/documents/{$storageId}";

        // 7. Store on Private Disk
        $disk = Storage::disk('private');
        $stored = $disk->put($privatePath, $fileContents);

        if (!$stored) {
            throw new RuntimeException('Failed to persist document to private storage.');
        }

        // 8. Validate Optional Obligation Instance
        $obligationInstanceId = null;
        if (!empty($attributes['obligation_instance_id'])) {
            $obligation = ObligationInstance::where('id', $attributes['obligation_instance_id'])
                ->where('tenant_id', $tenantId)
                ->where('organisation_id', $organisation->id)
                ->first();

            if (!$obligation) {
                throw new AuthorizationException('Target obligation instance does not belong to active organisation.');
            }
            $obligationInstanceId = $obligation->id;
        }

        // 9. Persist Record with safe transaction
        $document = DB::transaction(function () use (
            $tenantId,
            $organisation,
            $uploader,
            $category,
            $attributes,
            $privatePath,
            $file,
            $mimeType,
            $sha256Hash,
            $obligationInstanceId
        ) {
            $doc = ComplianceDocument::create([
                'tenant_id' => $tenantId,
                'organisation_id' => $organisation->id,
                'uploaded_by_user_id' => $uploader->id,
                'obligation_instance_id' => $obligationInstanceId,
                'document_type' => $category,
                'title' => trim($attributes['title'] ?? $file->getClientOriginalName()),
                'file_path' => $privatePath,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size_bytes' => $file->getSize(),
                'sha256_hash' => $sha256Hash,
                'storage_disk' => 'private',
                'verification_status' => 'PENDING',
                'processing_status' => 'PENDING',
                'expiry_date' => !empty($attributes['expiry_date']) ? Carbon::parse($attributes['expiry_date']) : null,
                'issue_date' => !empty($attributes['issue_date']) ? Carbon::parse($attributes['issue_date']) : null,
                'issuing_authority' => $attributes['issuing_authority'] ?? null,
            ]);

            AuditEvent::create([
                'tenant_id' => $tenantId,
                'user_id' => $uploader->id,
                'event_type' => 'DOCUMENT_UPLOADED',
                'auditable_type' => ComplianceDocument::class,
                'auditable_id' => $doc->id,
                'payload' => [
                    'document_type' => $category,
                    'file_name' => $file->getClientOriginalName(),
                    'sha256_hash' => $sha256Hash,
                    'file_size' => $file->getSize(),
                    'obligation_instance_id' => $obligationInstanceId,
                ],
            ]);

            if ($obligationInstanceId) {
                $ob = ObligationInstance::find($obligationInstanceId);
                if ($ob && $ob->evidence_status !== 'VERIFIED_BY_ISSUER') {
                    $ob->update([
                        'evidence_status' => 'DOCUMENT_SUPPORTED',
                    ]);
                }
            }

            return $doc;
        });

        // 10. Automatically trigger AI extraction if requested & configured
        if (!empty($attributes['auto_extract']) && $attributes['auto_extract'] === true) {
            try {
                $this->processExtraction($document, $uploader);
            } catch (\Throwable $e) {
                Log::warning('Automatic document extraction deferred: ' . $e->getMessage());
            }
        }

        return $document;
    }

    /**
     * Download or inline preview a document with strict tenant and path security.
     */
    public function download(ComplianceDocument $document, User $user, bool $inline = false): StreamedResponse
    {
        $this->authorizeDocumentAccess($document, $user);

        $disk = Storage::disk($document->storage_disk ?: 'private');

        if (!$disk->exists($document->file_path)) {
            throw new RuntimeException('Underlying evidence file does not exist in private storage vault.');
        }

        $disposition = $inline ? 'inline' : 'attachment';
        $fallbackName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $document->file_name);

        return response()->stream(
            function () use ($disk, $document) {
                $stream = $disk->readStream($document->file_path);
                if ($stream) {
                    fpassthru($stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => "{$disposition}; filename=\"{$fallbackName}\"",
                'Content-Length' => $document->file_size_bytes,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }

    /**
     * Link document to an obligation instance.
     */
    public function linkToObligation(
        ComplianceDocument $document,
        ObligationInstance $obligation,
        User $user
    ): ComplianceDocument {
        $tenantId = $this->tenantContext->getTenantId();

        if ($document->tenant_id !== $tenantId || $obligation->tenant_id !== $tenantId) {
            throw new AuthorizationException('Cross-tenant obligation linking strictly prohibited.');
        }

        if ($document->organisation_id !== $obligation->organisation_id) {
            throw new AuthorizationException('Document and obligation must belong to the same organisation.');
        }

        DB::transaction(function () use ($document, $obligation, $user, $tenantId) {
            $document->update([
                'obligation_instance_id' => $obligation->id,
            ]);

            // Update obligation evidence status (never set status to COMPLIANT)
            $newEvidenceStatus = match ($document->verification_status) {
                'VERIFIED' => 'DOCUMENT_SUPPORTED',
                'REJECTED' => 'REJECTED_EVIDENCE',
                default => 'DOCUMENT_SUPPORTED',
            };

            if ($obligation->evidence_status !== 'VERIFIED_BY_ISSUER') {
                $obligation->update([
                    'evidence_status' => $newEvidenceStatus,
                ]);
            }

            AuditEvent::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'event_type' => 'DOCUMENT_LINKED_TO_OBLIGATION',
                'auditable_type' => ComplianceDocument::class,
                'auditable_id' => $document->id,
                'payload' => [
                    'obligation_instance_id' => $obligation->id,
                    'definition_id' => $obligation->definition_id,
                ],
            ]);
        });

        return $document->fresh(['obligationInstance.definition.authority']);
    }

    /**
     * Unlink document from an obligation instance.
     */
    public function unlinkFromObligation(ComplianceDocument $document, User $user): ComplianceDocument
    {
        $this->authorizeDocumentAccess($document, $user);

        DB::transaction(function () use ($document, $user) {
            $oldObligationId = $document->obligation_instance_id;

            $document->update([
                'obligation_instance_id' => null,
            ]);

            if ($oldObligationId) {
                $obligation = ObligationInstance::find($oldObligationId);
                if ($obligation) {
                    $hasOtherDocs = ComplianceDocument::where('obligation_instance_id', $obligation->id)
                        ->where('id', '!=', $document->id)
                        ->exists();

                    if (!$hasOtherDocs && $obligation->evidence_status !== 'VERIFIED_BY_ISSUER') {
                        $obligation->update([
                            'evidence_status' => $obligation->declared_registration_number ? 'USER_DECLARED' : 'UNKNOWN',
                        ]);
                    }
                }
            }

            AuditEvent::create([
                'tenant_id' => $document->tenant_id,
                'user_id' => $user->id,
                'event_type' => 'DOCUMENT_UNLINKED_FROM_OBLIGATION',
                'auditable_type' => ComplianceDocument::class,
                'auditable_id' => $document->id,
                'payload' => [
                    'unlinked_obligation_id' => $oldObligationId,
                ],
            ]);
        });

        return $document->fresh();
    }

    /**
     * Process AI extraction for a stored document.
     */
    public function processExtraction(ComplianceDocument $document, ?User $triggeredBy = null): ComplianceDocument
    {
        $tenantId = $document->tenant_id;
        $this->tenantContext->setTenant($document->organisation->tenant);

        // 1. Check AI Provider and Quota
        if (!$this->usageMonitor->hasSufficientQuota($tenantId, 2000)) {
            $document->update([
                'processing_status' => 'UNAVAILABLE',
            ]);
            throw new RuntimeException("AI extraction unavailable: monthly token quota exhausted for tenant [{$tenantId}].");
        }

        // 2. Read Document Contents Safely
        $disk = Storage::disk($document->storage_disk ?: 'private');
        if (!$disk->exists($document->file_path)) {
            $document->update(['processing_status' => 'FAILED']);
            throw new RuntimeException('Cannot extract document: file missing from private storage.');
        }

        $document->update(['processing_status' => 'PROCESSING']);

        try {
            $fileRaw = $disk->get($document->file_path);
            $extractableText = $this->deriveExtractableText($document, $fileRaw);

            // 3. Invoke Governed AI Extractor via Laravel AI SDK
            $extracted = $this->aiOrchestration->extractRegistrationDocument(
                documentText: $extractableText,
                documentType: $document->document_type,
                entityId: $document->organisation_id
            );

            // 4. Update Document Metadata
            $metadata = $extracted->toArray();

            $expiry = null;
            if (!empty($metadata['expiry_date']) && strtotime($metadata['expiry_date'])) {
                $expiry = Carbon::parse($metadata['expiry_date'])->toDateString();
            }

            $issue = null;
            if (!empty($metadata['issue_date']) && strtotime($metadata['issue_date'])) {
                $issue = Carbon::parse($metadata['issue_date'])->toDateString();
            } elseif (!empty($metadata['incorporation_date']) && strtotime($metadata['incorporation_date'])) {
                $issue = Carbon::parse($metadata['incorporation_date'])->toDateString();
            }

            $document->update([
                'ai_verification_item_id' => $extracted->verificationItemId,
                'processing_status' => 'EXTRACTED',
                'extracted_metadata' => $metadata,
                'issuing_authority' => $metadata['issuing_authority'] ?? $this->getAuthorityNameForCategory($document->document_type),
                'issue_date' => $issue,
                'expiry_date' => $expiry,
            ]);

            AuditEvent::create([
                'tenant_id' => $tenantId,
                'user_id' => $triggeredBy?->id,
                'event_type' => 'DOCUMENT_AI_EXTRACTED',
                'auditable_type' => ComplianceDocument::class,
                'auditable_id' => $document->id,
                'payload' => [
                    'verification_item_id' => $extracted->verificationItemId,
                    'confidence_score' => $extracted->confidenceScore,
                ],
            ]);

            return $document->fresh(['aiVerificationItem']);
        } catch (\Throwable $e) {
            $document->update(['processing_status' => 'FAILED']);
            // Log without logging confidential document content
            Log::error('AI document extraction failed', [
                'document_id' => $document->id,
                'tenant_id' => $document->tenant_id,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Human verification and approval of evidence and extracted payload.
     */
    public function verifyDocument(
        ComplianceDocument $document,
        User $verifier,
        ?array $confirmedPayload = null
    ): ComplianceDocument {
        $this->authorizeReviewer($document, $verifier);

        DB::transaction(function () use ($document, $verifier, $confirmedPayload) {
            // 1. Verify linked AI Verification Item if exists
            if ($document->ai_verification_item_id) {
                $this->aiOrchestration->verifyItem(
                    itemId: $document->ai_verification_item_id,
                    verifierUserId: $verifier->id,
                    confirmedPayload: $confirmedPayload
                );
            }

            // 2. Update Document
            $payload = $confirmedPayload ?? $document->extracted_metadata ?? [];

            $expiry = $document->expiry_date;
            if (!empty($payload['expiry_date']) && strtotime($payload['expiry_date'])) {
                $expiry = Carbon::parse($payload['expiry_date'])->toDateString();
            }

            $issue = $document->issue_date;
            if (!empty($payload['issue_date']) && strtotime($payload['issue_date'])) {
                $issue = Carbon::parse($payload['issue_date'])->toDateString();
            }

            $authority = $payload['issuing_authority'] ?? $document->issuing_authority;

            $document->update([
                'verification_status' => 'VERIFIED',
                'verified_by_user_id' => $verifier->id,
                'verified_at' => Carbon::now(),
                'rejection_reason' => null,
                'extracted_metadata' => $payload,
                'expiry_date' => $expiry,
                'issue_date' => $issue,
                'issuing_authority' => $authority,
            ]);

            // 3. Update Linked Obligation (Never set COMPLIANT)
            if ($document->obligation_instance_id) {
                $obligation = ObligationInstance::find($document->obligation_instance_id);
                if ($obligation && $obligation->evidence_status !== 'VERIFIED_BY_ISSUER') {
                    $obligation->update([
                        'evidence_status' => 'DOCUMENT_SUPPORTED',
                    ]);
                }
            }

            AuditEvent::create([
                'tenant_id' => $document->tenant_id,
                'user_id' => $verifier->id,
                'event_type' => 'DOCUMENT_VERIFIED',
                'auditable_type' => ComplianceDocument::class,
                'auditable_id' => $document->id,
                'payload' => [
                    'had_corrections' => $confirmedPayload !== null,
                    'verified_at' => Carbon::now()->toIso8601String(),
                ],
            ]);
        });

        return $document->fresh(['aiVerificationItem', 'obligationInstance']);
    }

    /**
     * Human rejection of evidence with mandatory justification.
     */
    public function rejectDocument(
        ComplianceDocument $document,
        User $verifier,
        string $reason
    ): ComplianceDocument {
        $this->authorizeReviewer($document, $verifier);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is mandatory.');
        }

        DB::transaction(function () use ($document, $verifier, $reason) {
            // 1. Reject linked AI Verification Item
            if ($document->ai_verification_item_id) {
                $this->aiOrchestration->rejectItem(
                    itemId: $document->ai_verification_item_id,
                    verifierUserId: $verifier->id,
                    reason: $reason
                );
            }

            // 2. Update Document
            $document->update([
                'verification_status' => 'REJECTED',
                'verified_by_user_id' => $verifier->id,
                'verified_at' => Carbon::now(),
                'rejection_reason' => $reason,
            ]);

            // 3. Update Linked Obligation
            if ($document->obligation_instance_id) {
                $obligation = ObligationInstance::find($document->obligation_instance_id);
                if ($obligation && $obligation->evidence_status !== 'VERIFIED_BY_ISSUER') {
                    $obligation->update([
                        'evidence_status' => 'REJECTED_EVIDENCE',
                    ]);
                }
            }

            AuditEvent::create([
                'tenant_id' => $document->tenant_id,
                'user_id' => $verifier->id,
                'event_type' => 'DOCUMENT_REJECTED',
                'auditable_type' => ComplianceDocument::class,
                'auditable_id' => $document->id,
                'payload' => [
                    'rejection_reason' => $reason,
                ],
            ]);
        });

        return $document->fresh(['aiVerificationItem', 'obligationInstance']);
    }

    /**
     * Authorize user access to a specific document.
     */
    public function authorizeDocumentAccess(ComplianceDocument $document, User $user): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (empty($tenantId) || $document->tenant_id !== $tenantId) {
            throw new AuthorizationException('Cross-tenant document access strictly prohibited.');
        }

        $membership = Membership::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('organisation_id', $document->organisation_id)
            ->first();

        if (!$membership && !in_array($user->role ?? '', ['SUPERADMIN', 'ADMIN'])) {
            throw new AuthorizationException('User does not have access to this organisation.');
        }
    }

    /**
     * Authorize that user has reviewer permissions.
     */
    public function authorizeReviewer(ComplianceDocument $document, User $verifier): void
    {
        $this->authorizeDocumentAccess($document, $verifier);

        $membership = Membership::where('tenant_id', $document->tenant_id)
            ->where('user_id', $verifier->id)
            ->where('organisation_id', $document->organisation_id)
            ->first();

        $authorizedRoles = ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER', 'AUDITOR'];

        if (!$membership || !in_array($membership->role, $authorizedRoles, true)) {
            throw new AuthorizationException('Reviewer authorization required. Only Compliance Officers, Admins or Owners may verify documents.');
        }
    }

    /**
     * Validate magic bytes for supported formats to prevent file extension spoofing.
     */
    protected function validateMagicBytes(UploadedFile $file, string $mimeType): void
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return;
        }

        $header = fread($handle, 8);
        fclose($handle);

        if ($mimeType === 'application/pdf') {
            if (!str_starts_with($header, '%PDF-')) {
                throw new InvalidArgumentException('Invalid PDF file content: Missing %PDF- signature.');
            }
        } elseif ($mimeType === 'image/jpeg') {
            if (!str_starts_with($header, "\xFF\xD8\xFF")) {
                throw new InvalidArgumentException('Invalid JPEG file content: Missing JPEG magic bytes.');
            }
        } elseif ($mimeType === 'image/png') {
            if (!str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
                throw new InvalidArgumentException('Invalid PNG file content: Missing PNG magic bytes.');
            }
        }
    }

    /**
     * Extract human-readable text from uploaded document.
     */
    protected function deriveExtractableText(ComplianceDocument $document, string $rawBytes): string
    {
        // For plain text / mock inputs:
        if (mb_check_encoding($rawBytes, 'UTF-8') && !str_contains($rawBytes, "\0")) {
            return $rawBytes;
        }

        // For PDF documents: Attempt extractable text stream scan
        if ($document->mime_type === 'application/pdf') {
            $extractedText = '';
            if (preg_match_all('/\((.*?)\)\s*Tj/s', $rawBytes, $matches)) {
                $extractedText = implode(' ', $matches[1]);
            }

            if (empty($extractedText)) {
                // If stream text is compressed or scanned, provide clean structured context for the AI agent
                $extractedText = "Statutory Document: {$document->document_type}\nFile Name: {$document->file_name}\nTitle: {$document->title}\nOrganisation: {$document->organisation->legal_name}";
            }

            return $extractedText;
        }

        // For images or binary scans:
        return "Scanned Statutory Document: {$document->document_type}\nFile Name: {$document->file_name}\nOrganisation: {$document->organisation->legal_name}";
    }

    protected function getAuthorityNameForCategory(string $category): string
    {
        return match ($category) {
            'CERTIFICATE_OF_INCORPORATION', 'CR6', 'CR14' => 'Companies and Intellectual Property Zimbabwe (CIPZ)',
            'ITF263' => 'Zimbabwe Revenue Authority (ZIMRA)',
            'NSSA_REGISTRATION' => 'National Social Security Authority (NSSA)',
            'MUNICIPAL_LICENCE' => 'Local Municipality Authority',
            default => 'Statutory Regulatory Authority',
        };
    }
}
