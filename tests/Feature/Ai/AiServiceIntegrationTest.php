<?php

namespace Tests\Feature\Ai;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use App\Shared\Ai\Agents\RegistrationRoadmapAgent;
use App\Shared\Ai\Contracts\AiOrchestrationServiceInterface;
use App\Shared\Ai\DTOs\RoadmapExplanationRequest;
use App\Shared\Ai\Models\AiUsageLedger;
use App\Shared\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

class AiServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Kudakwashe Mining Enterprises',
            'slug' => 'kudakwashe-mining',
            'status' => 'ACTIVE',
        ]);

        $this->user = User::create([
            'name' => 'Tatenda Chiweshe',
            'email' => 'compliance@kudakwashe.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->organisation = Organisation::create([
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Kudakwashe Mining (Pvt) Ltd',
            'trading_name' => 'Kudakwashe Mining',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        $this->actingAs($this->user);
    }

    public function test_ai_roadmap_agent_generates_structured_explanation_with_metered_usage(): void
    {
        // 1. Mock the Laravel AI Agent using Ai::fakeAgent
        Ai::fakeAgent(RegistrationRoadmapAgent::class, [
            [
                'summary' => 'Comprehensive Zimbabwean corporate registration roadmap for Private Limited mining company.',
                'statutory_citations' => [
                    'Companies and Other Business Entities Act [Chapter 24:31]',
                    'ZIMRA Revenue Act [Chapter 23:11]',
                    'NSSA Act [Chapter 17:04]',
                    'Mines and Minerals Act [Chapter 21:05]',
                ],
                'deadlines' => [
                    'ZIMRA BP Registration' => '30 days after CIPZ incorporation',
                    'NSSA Employer Registration' => '30 days from employing first worker',
                ],
                'plain_english_advice' => 'Submit CIPZ name reservation first, then execute Memorandum of Association before opening corporate bank accounts.',
                'disclaimer' => 'Advisory guidance under ZimComply AI Governance (ADR-0005).',
            ],
        ]);

        // 2. Request AI Explanation via Orchestration Service
        $service = app(AiOrchestrationServiceInterface::class);

        $request = new RoadmapExplanationRequest(
            entityType: 'Private Limited Company (Pvt Ltd)',
            industrySector: 'Mining & Beneficiation',
            mandatorySteps: [
                'CIPZ Name Search',
                'ZIMRA BP & Tax Registration',
                'NSSA Employer Registration',
                'Mining Commissioner Registration',
            ],
            userQuestion: 'What are the required compliance milestones?'
        );

        $response = $service->explainRoadmap($request);

        // 3. Assert Structured Response
        $this->assertStringContainsString('Comprehensive Zimbabwean corporate registration roadmap', $response->summary);
        $this->assertContains('Companies and Other Business Entities Act [Chapter 24:31]', $response->statutoryCitations);
        $this->assertArrayHasKey('ZIMRA BP Registration', $response->deadlines);
        $this->assertStringContainsString('CIPZ incorporation', $response->deadlines['ZIMRA BP Registration']);
        $this->assertStringContainsString('ADR-0005', $response->disclaimer);

        // 4. Assert Usage Metering Recorded in Database
        $this->assertDatabaseHas('ai_usage_ledgers', [
            'tenant_id' => $this->tenant->id,
            'feature_code' => 'REGISTRATION_ROADMAP_EXPLAIN',
            'status' => 'SUCCESS',
        ]);

        // 5. Assert Audit Event Recorded (ADR-0005)
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'AI_USAGE_METERED',
        ]);

        // 6. Assert Agent Was Prompted via Laravel AI Assertion
        Ai::assertAgentWasPromptedTimes(RegistrationRoadmapAgent::class, 1);
    }
}
