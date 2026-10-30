<?php

namespace App\Shared\Ai\Contracts;

use App\Shared\Ai\DTOs\ExtractedRegistrationData;
use App\Shared\Ai\DTOs\RoadmapExplanationRequest;
use App\Shared\Ai\DTOs\RoadmapExplanationResponse;
use App\Shared\Ai\Models\AiVerificationItem;

interface AiOrchestrationServiceInterface
{
    /**
     * Generate plain-English guidance for statutory registration roadmaps,
     * grounded strictly in approved Zimbabwean statutory frameworks (ADR-0005).
     */
    public function explainRoadmap(RoadmapExplanationRequest $request): RoadmapExplanationResponse;

    /**
     * Extract structured registration parameters from uploaded business documents (CR6, CR14, ITF263).
     * Automatically registers an entry in the Human-in-the-Loop verification queue.
     */
    public function extractRegistrationDocument(
        string $documentText,
        string $documentType,
        ?string $entityId = null
    ): ExtractedRegistrationData;

    /**
     * Submit an AI-generated proposal into the Human-in-the-Loop verification queue.
     */
    public function createVerificationItem(
        string $featureCode,
        string $entityType,
        ?string $entityId,
        array $proposedPayload,
        float $confidenceScore
    ): AiVerificationItem;

    /**
     * Approve and verify an AI-generated item with optional human corrections.
     */
    public function verifyItem(
        string $itemId,
        string $verifierUserId,
        ?array $confirmedPayload = null
    ): AiVerificationItem;

    /**
     * Reject an AI-generated item with mandatory justification.
     */
    public function rejectItem(
        string $itemId,
        string $verifierUserId,
        string $reason
    ): AiVerificationItem;
}
