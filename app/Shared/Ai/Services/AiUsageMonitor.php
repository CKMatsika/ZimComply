<?php

namespace App\Shared\Ai\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Subscription;
use App\Shared\Ai\Contracts\AiUsageMonitorInterface;
use App\Shared\Ai\Models\AiUsageLedger;
use Carbon\Carbon;

class AiUsageMonitor implements AiUsageMonitorInterface
{
    /**
     * Approximate cost per 1M tokens in USD by provider/model.
     */
    protected array $pricingRates = [
        'openai' => [
            'gpt-4o' => ['prompt' => 2.50, 'completion' => 10.00],
            'gpt-4o-mini' => ['prompt' => 0.15, 'completion' => 0.60],
            'default' => ['prompt' => 0.50, 'completion' => 1.50],
        ],
        'gemini' => [
            'gemini-1.5-pro' => ['prompt' => 1.25, 'completion' => 5.00],
            'gemini-1.5-flash' => ['prompt' => 0.075, 'completion' => 0.30],
            'default' => ['prompt' => 0.10, 'completion' => 0.40],
        ],
        'anthropic' => [
            'claude-3-5-sonnet' => ['prompt' => 3.00, 'completion' => 15.00],
            'claude-3-5-haiku' => ['prompt' => 0.80, 'completion' => 4.00],
            'default' => ['prompt' => 1.00, 'completion' => 5.00],
        ],
        'default' => ['prompt' => 0.50, 'completion' => 1.50],
    ];

    /**
     * Tier token limits.
     */
    protected array $tierLimits = [
        'FREE' => 25000,
        'STANDARD' => 150000,
        'ENTERPRISE' => 1000000,
    ];

    /**
     * Determine if tenant has sufficient remaining quota for this operation.
     */
    public function hasSufficientQuota(string $tenantId, int $estimatedTokens = 1000): bool
    {
        $usage = $this->getMonthlyUsage($tenantId);

        return ($usage['remaining_tokens'] >= $estimatedTokens);
    }

    /**
     * Record an AI operation's token usage and cost.
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
    ): AiUsageLedger {
        $totalTokens = $promptTokens + $completionTokens;
        $costUsd = $this->calculateEstimatedCost($provider, $model, $promptTokens, $completionTokens);

        $ledger = AiUsageLedger::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'feature_code' => $featureCode,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost_usd' => $costUsd,
            'status' => 'SUCCESS',
            'metadata' => $metadata,
        ]);

        // Audit Trail (ADR-0005 AI Governance)
        AuditEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event_type' => 'AI_USAGE_METERED',
            'auditable_type' => AiUsageLedger::class,
            'auditable_id' => $ledger->id,
            'payload' => [
                'feature_code' => $featureCode,
                'provider' => $provider,
                'model' => $model,
                'tokens' => $totalTokens,
                'cost_usd' => $costUsd,
            ],
        ]);

        return $ledger;
    }

    /**
     * Get monthly token usage summary for the tenant.
     */
    public function getMonthlyUsage(string $tenantId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $totalTokensUsed = (int) AiUsageLedger::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_tokens');

        $totalCostUsd = (float) AiUsageLedger::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('estimated_cost_usd');

        $quotaTokens = $this->resolveTenantQuota($tenantId);
        $remainingTokens = max(0, $quotaTokens - $totalTokensUsed);

        return [
            'tenant_id' => $tenantId,
            'month' => $startOfMonth->format('Y-m'),
            'total_tokens' => $totalTokensUsed,
            'quota_tokens' => $quotaTokens,
            'remaining_tokens' => $remainingTokens,
            'estimated_cost_usd' => round($totalCostUsd, 4),
        ];
    }

    /**
     * Resolve quota limit based on tenant subscription plan.
     */
    protected function resolveTenantQuota(string $tenantId): int
    {
        $subscription = Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->first();

        $plan = $subscription ? strtoupper($subscription->plan_code ?? 'FREE') : 'FREE';

        return $this->tierLimits[$plan] ?? config('ai.zimcomply.default_monthly_quota_tokens', 100000);
    }

    /**
     * Calculate cost in USD given token usage.
     */
    protected function calculateEstimatedCost(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        $providerKey = strtolower($provider);
        $modelKey = strtolower($model);

        $rates = $this->pricingRates[$providerKey][$modelKey]
            ?? $this->pricingRates[$providerKey]['default']
            ?? $this->pricingRates['default'];

        $promptCost = ($promptTokens / 1000000) * $rates['prompt'];
        $completionCost = ($completionTokens / 1000000) * $rates['completion'];

        return round($promptCost + $completionCost, 6);
    }
}
