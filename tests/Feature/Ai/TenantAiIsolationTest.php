<?php

namespace Tests\Feature\Ai;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Subscription;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use App\Shared\Ai\Agents\RegistrationRoadmapAgent;
use App\Shared\Ai\Contracts\AiOrchestrationServiceInterface;
use App\Shared\Ai\Contracts\AiUsageMonitorInterface;
use App\Shared\Ai\DTOs\RoadmapExplanationRequest;
use App\Shared\Ai\Models\AiUsageLedger;
use App\Shared\Ai\Models\AiVerificationItem;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use RuntimeException;
use Tests\TestCase;

class TenantAiIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant A: Harare Industrial
        $this->tenantA = Tenant::create([
            'name' => 'Harare Industrial Ltd',
            'slug' => 'harare-industrial',
            'status' => 'ACTIVE',
        ]);

        $this->userA = User::create([
            'name' => 'Tendai Mugabe',
            'email' => 'tendai@harareindustrial.co.zw',
            'password' => bcrypt('Password123!'),
        ]);

        // Tenant B: Bulawayo Logistics
        $this->tenantB = Tenant::create([
            'name' => 'Bulawayo Logistics Ltd',
            'slug' => 'bulawayo-logistics',
            'status' => 'ACTIVE',
        ]);

        $this->userB = User::create([
            'name' => 'Sipho Ndebele',
            'email' => 'sipho@bulawayologistics.co.zw',
            'password' => bcrypt('Password123!'),
        ]);
    }

    public function test_tenant_cannot_see_another_tenants_ai_usage_ledgers(): void
    {
        // 1. Establish Tenant A context and record AI usage
        $context = app(TenantContext::class);
        $context->setTenant($this->tenantA);

        AiUsageLedger::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'feature_code' => 'REGISTRATION_ROADMAP_EXPLAIN',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 500,
            'completion_tokens' => 200,
            'total_tokens' => 700,
            'estimated_cost_usd' => 0.000195,
        ]);

        // Tenant A sees exactly 1 usage ledger
        $this->assertCount(1, AiUsageLedger::all());

        // 2. Switch to Tenant B context
        $context->setTenant($this->tenantB);

        // Tenant B cannot see Tenant A's usage ledger (TenantScope in effect)
        $this->assertCount(0, AiUsageLedger::all());
    }

    public function test_tenant_cannot_approve_another_tenants_verification_item(): void
    {
        $context = app(TenantContext::class);
        $service = app(AiOrchestrationServiceInterface::class);

        // 1. Establish Tenant A context and create verification item
        $context->setTenant($this->tenantA);

        $itemA = AiVerificationItem::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'feature_code' => 'REGISTRATION_DOC_EXTRACT',
            'entity_type' => 'App\Modules\Registration\Models\CR6',
            'proposed_payload' => ['company_name' => 'Harare Industrial Ltd'],
            'status' => 'PENDING',
        ]);

        // 2. Switch to Tenant B context
        $context->setTenant($this->tenantB);

        // Tenant B attempts to verify Tenant A's item -> Must throw ModelNotFoundException
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->verifyItem($itemA->id, $this->userB->id, ['company_name' => 'Tampered Name']);
    }

    public function test_ai_request_aborts_when_tenant_exceeds_monthly_quota(): void
    {
        $context = app(TenantContext::class);
        $context->setTenant($this->tenantA);
        $this->actingAs($this->userA);

        // Create an active FREE subscription for Tenant A (Free tier limit: 25,000 tokens)
        Subscription::create([
            'tenant_id' => $this->tenantA->id,
            'plan_code' => 'FREE',
            'status' => 'ACTIVE',
            'starts_at' => Carbon::now()->startOfMonth(),
            'expires_at' => Carbon::now()->addYear(),
        ]);

        Ai::fakeAgent(RegistrationRoadmapAgent::class, []);

        // Simulate that Tenant A has already used 24,500 tokens this month
        AiUsageLedger::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'feature_code' => 'HISTORIC_BATCH',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 15000,
            'completion_tokens' => 9500,
            'total_tokens' => 24500,
            'estimated_cost_usd' => 0.00795,
        ]);

        $service = app(AiOrchestrationServiceInterface::class);

        // A roadmap request requires at least 1500 tokens, which would exceed the 25,000 quota
        $request = new RoadmapExplanationRequest(
            entityType: 'Private Limited Company',
            industrySector: 'Manufacturing',
            mandatorySteps: ['CIPZ Incorporation', 'ZIMRA Registration']
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Monthly token quota exhausted for tenant');

        $service->explainRoadmap($request);
    }
}
