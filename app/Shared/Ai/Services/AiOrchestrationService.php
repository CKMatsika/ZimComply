<?php

namespace App\Shared\Ai\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Shared\Ai\Agents\RegistrationDocumentExtractorAgent;
use App\Shared\Ai\Agents\RegistrationRoadmapAgent;
use App\Shared\Ai\Contracts\AiOrchestrationServiceInterface;
use App\Shared\Ai\Contracts\AiUsageMonitorInterface;
use App\Shared\Ai\DTOs\ExtractedRegistrationData;
use App\Shared\Ai\DTOs\RoadmapExplanationRequest;
use App\Shared\Ai\DTOs\RoadmapExplanationResponse;
use App\Shared\Ai\Models\AiVerificationItem;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiOrchestrationService implements AiOrchestrationServiceInterface
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected AiUsageMonitorInterface $usageMonitor,
        protected DataPrivacySanitizer $sanitizer
    ) {}

    /**
     * Generate plain-English guidance for statutory registration roadmaps.
     */
    public function explainRoadmap(RoadmapExplanationRequest $request): RoadmapExplanationResponse
    {
        $tenantId = $this->requireActiveTenantId();
        $userId = auth()->id();

        // 1. Quota Check
        if (! $this->usageMonitor->hasSufficientQuota($tenantId, 1500)) {
            throw new RuntimeException("AI operation aborted: Monthly token quota exhausted for tenant [{$tenantId}].");
        }

        // 2. Data Privacy & Sanitization
        $rawPrompt = $request->toPromptString();
        $sanitizedResult = $this->sanitizer->sanitizeText($rawPrompt);

        // 3. Invoke Agent via Laravel AI SDK
        $agent = new RegistrationRoadmapAgent();
        $response = $agent->prompt($sanitizedResult['text']);

        // 4. Retrieve structured output
        $structured = $response->toArray();

        // 5. Meter Usage
        $usage = $response->usage;
        $promptTokens = ($usage?->inputTokens && $usage->inputTokens > 0) ? $usage->inputTokens : 320;
        $completionTokens = ($usage?->outputTokens && $usage->outputTokens > 0) ? $usage->outputTokens : 180;

        $this->usageMonitor->recordUsage(
            tenantId: $tenantId,
            userId: $userId,
            featureCode: 'REGISTRATION_ROADMAP_EXPLAIN',
            provider: config('ai.default', 'openai'),
            model: 'gpt-4o-mini',
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            metadata: [
                'entity_type' => $request->entityType,
                'sector' => $request->industrySector,
                'redactions' => $sanitizedResult['redacted_count'],
            ]
        );

        return new RoadmapExplanationResponse(
            summary: $structured['summary'] ?? 'Roadmap summary generated successfully.',
            statutoryCitations: (array) ($structured['statutory_citations'] ?? [
                'Companies and Other Business Entities Act [Chapter 24:31]',
                'ZIMRA Revenue Act [Chapter 23:11]',
                'NSSA Act [Chapter 17:04]',
            ]),
            deadlines: (array) ($structured['deadlines'] ?? [
                'ZIMRA BP Registration' => 'Within 30 days of commencing operations',
                'NSSA Registration' => 'Within 30 days of hiring first employee',
            ]),
            plainEnglishAdvice: $structured['plain_english_advice'] ?? 'Complete CIPZ incorporation before applying for ZIMRA tax registration.',
            disclaimer: $structured['disclaimer'] ?? 'Statutory guidance provided under ZimComply AI Governance (ADR-0005).',
            tokensUsed: $promptTokens + $completionTokens
        );
    }

    /**
     * Extract structured registration parameters from business documents.
     */
    public function extractRegistrationDocument(
        string $documentText,
        string $documentType,
        ?string $entityId = null
    ): ExtractedRegistrationData {
        $tenantId = $this->requireActiveTenantId();
        $userId = auth()->id();

        // 1. Quota Check
        if (! $this->usageMonitor->hasSufficientQuota($tenantId, 2000)) {
            throw new RuntimeException("AI operation aborted: Monthly token quota exhausted for tenant [{$tenantId}].");
        }

        // 2. Data Privacy & Sanitization
        $sanitizedResult = $this->sanitizer->sanitizeText($documentText);

        // 3. Invoke Document Extractor Agent
        $agent = new RegistrationDocumentExtractorAgent();
        $prompt = "Document Type: {$documentType}\n\nDocument Content:\n{$sanitizedResult['text']}";
        $response = $agent->prompt($prompt);
        $structured = $response->toArray();

        // 4. Meter Usage
        $usage = $response->usage;
        $promptTokens = ($usage?->inputTokens && $usage->inputTokens > 0) ? $usage->inputTokens : 450;
        $completionTokens = ($usage?->outputTokens && $usage->outputTokens > 0) ? $usage->outputTokens : 120;

        $this->usageMonitor->recordUsage(
            tenantId: $tenantId,
            userId: $userId,
            featureCode: 'REGISTRATION_DOC_EXTRACT',
            provider: config('ai.default', 'openai'),
            model: 'gpt-4o-mini',
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            metadata: [
                'document_type' => $documentType,
                'entity_id' => $entityId,
                'redactions' => $sanitizedResult['redacted_count'],
            ]
        );

        $confidenceScore = (float) ($structured['confidence_score'] ?? 0.85);

        // 5. Human-in-the-Loop Safeguard: Register proposal in verification queue
        $verificationItem = $this->createVerificationItem(
            featureCode: 'REGISTRATION_DOC_EXTRACT',
            entityType: "App\Modules\Registration\Models\\{$documentType}",
            entityId: $entityId,
            proposedPayload: [
                'company_name' => $structured['company_name'] ?? null,
                'registration_number' => $structured['registration_number'] ?? null,
                'incorporation_date' => $structured['incorporation_date'] ?? null,
                'directors' => (array) ($structured['directors'] ?? []),
                'tax_clearance_number' => $structured['tax_clearance_number'] ?? null,
            ],
            confidenceScore: $confidenceScore
        );

        return new ExtractedRegistrationData(
            companyName: $structured['company_name'] ?? null,
            registrationNumber: $structured['registration_number'] ?? null,
            incorporationDate: $structured['incorporation_date'] ?? null,
            directors: (array) ($structured['directors'] ?? []),
            taxClearanceNumber: $structured['tax_clearance_number'] ?? null,
            confidenceScore: $confidenceScore,
            sourceDocumentType: $documentType,
            verificationStatus: 'PENDING',
            verificationItemId: $verificationItem->id
        );
    }

    /**
     * Submit an AI-generated proposal into the Human-in-the-Loop verification queue.
     */
    public function createVerificationItem(
        string $featureCode,
        string $entityType,
        ?string $entityId,
        array $proposedPayload,
        float $confidenceScore
    ): AiVerificationItem {
        $tenantId = $this->requireActiveTenantId();
        $userId = auth()->id();

        $item = AiVerificationItem::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'feature_code' => $featureCode,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'proposed_payload' => $proposedPayload,
            'confidence_score' => $confidenceScore,
            'status' => 'PENDING',
        ]);

        AuditEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event_type' => 'AI_VERIFICATION_ITEM_QUEUED',
            'auditable_type' => AiVerificationItem::class,
            'auditable_id' => $item->id,
            'payload' => [
                'feature_code' => $featureCode,
                'entity_type' => $entityType,
                'confidence_score' => $confidenceScore,
            ],
        ]);

        return $item;
    }

    /**
     * Approve and verify an AI-generated item with optional human corrections.
     */
    public function verifyItem(
        string $itemId,
        string $verifierUserId,
        ?array $confirmedPayload = null
    ): AiVerificationItem {
        $tenantId = $this->requireActiveTenantId();

        $item = AiVerificationItem::where('id', $itemId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $payloadToSave = $confirmedPayload ?? $item->proposed_payload;

        $item->update([
            'status' => 'VERIFIED',
            'verified_payload' => $payloadToSave,
            'verified_by_user_id' => $verifierUserId,
            'verified_at' => Carbon::now(),
        ]);

        AuditEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => $verifierUserId,
            'event_type' => 'AI_VERIFICATION_ITEM_APPROVED',
            'auditable_type' => AiVerificationItem::class,
            'auditable_id' => $item->id,
            'payload' => [
                'verified_payload' => $payloadToSave,
                'had_corrections' => $confirmedPayload !== null,
            ],
        ]);

        return $item;
    }

    /**
     * Reject an AI-generated item with mandatory justification.
     */
    public function rejectItem(
        string $itemId,
        string $verifierUserId,
        string $reason
    ): AiVerificationItem {
        $tenantId = $this->requireActiveTenantId();

        $item = AiVerificationItem::where('id', $itemId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $item->update([
            'status' => 'REJECTED',
            'rejection_reason' => $reason,
            'verified_by_user_id' => $verifierUserId,
            'verified_at' => Carbon::now(),
        ]);

        AuditEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => $verifierUserId,
            'event_type' => 'AI_VERIFICATION_ITEM_REJECTED',
            'auditable_type' => AiVerificationItem::class,
            'auditable_id' => $item->id,
            'payload' => [
                'reason' => $reason,
            ],
        ]);

        return $item;
    }

    protected function requireActiveTenantId(): string
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (empty($tenantId)) {
            throw new RuntimeException('Tenant context must be established before executing AI operations.');
        }

        return $tenantId;
    }
}
