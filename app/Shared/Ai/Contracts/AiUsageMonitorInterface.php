<?php

namespace App\Shared\Ai\Contracts;

use App\Shared\Ai\Models\AiUsageLedger;

interface AiUsageMonitorInterface
{
    /**
     * Determine if the tenant has sufficient remaining quota for the operation.
     */
    public function hasSufficientQuota(string $tenantId, int $estimatedTokens = 1000): bool;

    /**
     * Record an AI operation's token usage and estimated cost into the audit ledger.
     */
    public function recordUsage(
        string $tenantId,
        ?string $userId,
        string $featureCode,
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        array $metadata = []
    ): AiUsageLedger;

    /**
     * Get monthly token usage summary for the tenant.
     *
     * @return array{
     *     tenant_id: string,
     *     month: string,
     *     total_tokens: int,
     *     quota_tokens: int,
     *     remaining_tokens: int,
     *     estimated_cost_usd: float
     * }
     */
    public function getMonthlyUsage(string $tenantId): array;
}
