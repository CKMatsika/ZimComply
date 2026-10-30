<?php

namespace Tests\Feature\Ai;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use App\Shared\Ai\Agents\RegistrationDocumentExtractorAgent;
use App\Shared\Ai\Contracts\AiOrchestrationServiceInterface;
use App\Shared\Ai\Models\AiVerificationItem;
use App\Shared\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

class StructuredDocumentExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected User $verifier;
    protected Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Chivhu Agribusiness Consortium',
            'slug' => 'chivhu-agribusiness',
            'status' => 'ACTIVE',
        ]);

        $this->user = User::create([
            'name' => 'Farai Mutasa',
            'email' => 'farai@chivhu.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->verifier = User::create([
            'name' => 'Nyasha Moyo',
            'email' => 'auditor@chivhu.co.zw',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->organisation = Organisation::create([
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Chivhu Agro Enterprises (Pvt) Ltd',
            'trading_name' => 'Chivhu Agro',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        $this->actingAs($this->user);
    }

    public function test_document_extraction_creates_pending_verification_item_and_human_approves(): void
    {
        // 1. Mock Document Extractor Agent
        Ai::fakeAgent(RegistrationDocumentExtractorAgent::class, [
            [
                'company_name' => 'Chivhu Agro Enterprises (Pvt) Ltd',
                'registration_number' => '10482/2026',
                'incorporation_date' => '2026-04-15',
                'directors' => ['Farai Mutasa', 'Nyasha Moyo'],
                'tax_clearance_number' => 'ITF263-2026-98124',
                'confidence_score' => 0.94,
            ],
        ]);

        $service = app(AiOrchestrationServiceInterface::class);

        $documentSample = <<<'DOC'
ZIMBABWE COMPANIES ACT [CHAPTER 24:31]
CERTIFICATE OF INCORPORATION
This is to certify that CHIVHU AGRO ENTERPRISES (PVT) LTD
is this day incorporated under the Companies and Other Business Entities Act.
Company Registration Number: 10482/2026
Given under my hand at Harare this 15th day of April 2026.
DOC;

        // 2. Perform Extraction
        $extracted = $service->extractRegistrationDocument(
            documentText: $documentSample,
            documentType: 'CERTIFICATE_OF_INCORPORATION',
            entityId: $this->organisation->id
        );

        // 3. Verify DTO values
        $this->assertEquals('Chivhu Agro Enterprises (Pvt) Ltd', $extracted->companyName);
        $this->assertEquals('10482/2026', $extracted->registrationNumber);
        $this->assertEquals('2026-04-15', $extracted->incorporationDate);
        $this->assertEquals(['Farai Mutasa', 'Nyasha Moyo'], $extracted->directors);
        $this->assertEquals('ITF263-2026-98124', $extracted->taxClearanceNumber);
        $this->assertEquals(0.94, $extracted->confidenceScore);
        $this->assertEquals('PENDING', $extracted->verificationStatus);
        $this->assertNotNull($extracted->verificationItemId);

        // 4. Assert Record in Verification Queue is PENDING (Human-in-the-Loop)
        $this->assertDatabaseHas('ai_verification_items', [
            'id' => $extracted->verificationItemId,
            'tenant_id' => $this->tenant->id,
            'status' => 'PENDING',
            'feature_code' => 'REGISTRATION_DOC_EXTRACT',
        ]);

        // 5. Human Verifier Reviews and Approves Item
        $verifiedItem = $service->verifyItem(
            itemId: $extracted->verificationItemId,
            verifierUserId: $this->verifier->id,
            confirmedPayload: [
                'company_name' => 'Chivhu Agro Enterprises (Pvt) Ltd',
                'registration_number' => '10482/2026',
                'incorporation_date' => '2026-04-15',
                'directors' => ['Farai Mutasa', 'Nyasha Moyo'],
                'tax_clearance_number' => 'ITF263-2026-98124',
                'verified_by_human' => true,
            ]
        );

        $this->assertTrue($verifiedItem->isVerified());
        $this->assertEquals($this->verifier->id, $verifiedItem->verified_by_user_id);
        $this->assertNotNull($verifiedItem->verified_at);

        // 6. Assert Audit Trail for Human Approval
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->verifier->id,
            'event_type' => 'AI_VERIFICATION_ITEM_APPROVED',
        ]);
    }

    public function test_document_extraction_can_be_rejected_by_human_verifier(): void
    {
        Ai::fakeAgent(RegistrationDocumentExtractorAgent::class, [
            [
                'company_name' => 'Illegible Corporation',
                'registration_number' => 'UNKNOWN',
                'incorporation_date' => '1900-01-01',
                'directors' => [],
                'tax_clearance_number' => '',
                'confidence_score' => 0.35,
            ],
        ]);

        $service = app(AiOrchestrationServiceInterface::class);

        $extracted = $service->extractRegistrationDocument(
            documentText: 'Blurred document scan with illegible stamps',
            documentType: 'CR6',
            entityId: $this->organisation->id
        );

        $this->assertEquals(0.35, $extracted->confidenceScore);

        // Human verifier rejects item due to poor scan quality
        $rejectedItem = $service->rejectItem(
            itemId: $extracted->verificationItemId,
            verifierUserId: $this->verifier->id,
            reason: 'Stamp and registration number are illegible. Please re-upload a 300 DPI scanned copy.'
        );

        $this->assertTrue($rejectedItem->isRejected());
        $this->assertEquals('Stamp and registration number are illegible. Please re-upload a 300 DPI scanned copy.', $rejectedItem->rejection_reason);
        $this->assertEquals($this->verifier->id, $rejectedItem->verified_by_user_id);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->verifier->id,
            'event_type' => 'AI_VERIFICATION_ITEM_REJECTED',
        ]);
    }
}
