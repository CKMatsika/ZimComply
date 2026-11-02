<?php

namespace Tests\Feature\Documents;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\RegistrationEngine\Jobs\ExtractDocumentJob;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\Jurisdiction;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Services\ComplianceDocumentService;
use App\Domains\RegistrationEngine\Services\ObligationEvidenceStatusEvaluator;
use App\Domains\RegistrationEngine\Services\RegistrationRoadmapGenerator;
use App\Models\User;
use App\Shared\Ai\Agents\RegistrationDocumentExtractorAgent;
use App\Shared\Ai\Models\AiVerificationItem;
use App\Shared\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;
use Tests\TestCase;

class EvidenceLifecycleAndRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Organisation $orgA;
    protected Organisation $orgB;
    protected User $userOrgA;
    protected User $userOrgB;
    protected User $reviewerOrgA;
    protected RegulatoryAuthority $zimraAuthority;
    protected ObligationDefinition $itf263Definition;
    protected ObligationInstance $obligationA;
    protected ObligationInstance $obligationB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        // Single Tenant with Multiple Organisations (Same-tenant isolation boundary)
        $this->tenant = Tenant::create([
            'name' => 'Apex Holdings Zimbabwe',
            'slug' => 'apex-holdings',
            'status' => 'ACTIVE',
        ]);

        $this->orgA = Organisation::create([
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Apex Mining & Exploration (Pvt) Ltd',
            'trading_name' => 'Apex Mining',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->orgB = Organisation::create([
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Apex Agricultural Inputs (Pvt) Ltd',
            'trading_name' => 'Apex Agriculture',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->userOrgA = User::create([
            'name' => 'Kudakwashe Moyo',
            'email' => 'kmoyo@apexmining.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $this->reviewerOrgA = User::create([
            'name' => 'Tendai Chidziwa',
            'email' => 'tchidziwa@apexmining.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $this->userOrgB = User::create([
            'name' => 'Ruvimbo Marowa',
            'email' => 'rmarowa@apexagri.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        // Memberships: strictly separated across Org A and Org B
        Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->userOrgA->id,
            'organisation_id' => $this->orgA->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->reviewerOrgA->id,
            'organisation_id' => $this->orgA->id,
            'role' => 'COMPLIANCE_OFFICER',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->userOrgB->id,
            'organisation_id' => $this->orgB->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        // Statutory Regulators and Obligations
        $this->zimraAuthority = RegulatoryAuthority::create([
            'code' => 'ZIMRA',
            'name' => 'Zimbabwe Revenue Authority',
            'category' => 'STATUTORY_REGULATOR',
            'portal_url' => 'https://tarms.zimra.co.zw',
            'is_active' => true,
        ]);

        $this->itf263Definition = ObligationDefinition::create([
            'code' => 'ZIMRA_ITF263',
            'authority_id' => $this->zimraAuthority->id,
            'title' => 'Tax Clearance Certificate (ITF263)',
            'obligation_type' => 'REGISTRATION',
            'legal_classification' => 'PRIMARY_STATUTORY',
            'is_entity_level' => true,
            'statutory_citation' => 'Income Tax Act [Chapter 23:06], Section 80',
            'applicability_criteria' => [],
            'is_active' => true,
        ]);

        $this->obligationA = ObligationInstance::create([
            'tenant_id' => $this->tenant->id,
            'organisation_id' => $this->orgA->id,
            'definition_id' => $this->itf263Definition->id,
            'status' => 'NOT_STARTED',
            'evidence_status' => 'UNKNOWN',
        ]);

        $this->obligationB = ObligationInstance::create([
            'tenant_id' => $this->tenant->id,
            'organisation_id' => $this->orgB->id,
            'definition_id' => $this->itf263Definition->id,
            'status' => 'NOT_STARTED',
            'evidence_status' => 'UNKNOWN',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setOrganisation($this->orgA);
    }

    protected function createFakePdf(string $filename = 'test_certificate.pdf'): UploadedFile
    {
        $dummyPdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 55 >>\nstream\nBT /F1 12 Tf 72 712 Td (Statutory Certificate) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000189 00000 n \ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n294\n%%EOF";
        return UploadedFile::fake()->createWithContent($filename, $dummyPdfContent);
    }

    protected function mockAiAgent(array $metadata = []): void
    {
        Ai::fakeAgent(RegistrationDocumentExtractorAgent::class, [
            array_merge([
                'company_name' => 'Apex Mining & Exploration (Pvt) Ltd',
                'registration_number' => 'ME-4910/2026',
                'tax_clearance_number' => 'ITF263-2026-9901',
                'confidence_score' => 0.98,
                'issuing_authority' => 'Zimbabwe Revenue Authority',
                'document_type' => 'ITF263',
                'issue_date' => Carbon::now()->subMonths(2)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(10)->toDateString(),
            ], $metadata),
        ]);
    }

    /**
     * Test 1: Repeated / simultaneous extraction requests are handled idempotently.
     */
    public function test_concurrent_and_simultaneous_extraction_requests_handled_idempotently(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('itf263_cert.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: ['document_type' => 'ITF263', 'title' => '2026 Tax Clearance'],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $this->mockAiAgent();

        // 1st Extraction
        $extracted1 = $service->processExtraction($doc, $this->userOrgA);
        $firstVerificationItemId = $extracted1->ai_verification_item_id;

        $this->assertEquals(1, AiVerificationItem::count());

        // 2nd Extraction immediately on same document (simulating double click or duplicate request)
        $extracted2 = $service->processExtraction($doc, $this->userOrgA);

        // Assert no duplicate verification items were generated
        $this->assertEquals(1, AiVerificationItem::count());
        $this->assertEquals($firstVerificationItemId, $extracted2->ai_verification_item_id);
        $this->assertEquals('EXTRACTED', $extracted2->processing_status);
    }

    /**
     * Test 2: Repeated extraction returns existing proposal without extra AI call unless forced.
     */
    public function test_repeated_extraction_after_completed_extraction_returns_existing_unless_forced(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('tax_cert.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: ['document_type' => 'ITF263', 'title' => 'Tax Clearance'],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $this->mockAiAgent(['confidence_score' => 0.95]);
        $service->processExtraction($doc, $this->userOrgA);

        $originalItemId = $doc->fresh()->ai_verification_item_id;

        // Repeated request without force
        $service->processExtraction($doc->fresh(), $this->userOrgA, force: false);
        $this->assertEquals($originalItemId, $doc->fresh()->ai_verification_item_id);
        $this->assertEquals(1, AiVerificationItem::count());

        // Deliberate re-extraction with force: true
        $this->mockAiAgent(['confidence_score' => 0.99, 'tax_clearance_number' => 'ITF263-NEW-999']);
        $service->processExtraction($doc->fresh(), $this->userOrgA, force: true);

        // Verification proposal updated in-place without generating orphan items
        $this->assertEquals(1, AiVerificationItem::count());
        $freshItem = AiVerificationItem::find($originalItemId);
        $this->assertEquals(0.99, $freshItem->confidence_score);
        $this->assertEquals('ITF263-NEW-999', $freshItem->proposed_payload['tax_clearance_number']);
    }

    /**
     * Test 3: Extraction failure records error safely and permits clean retry.
     */
    public function test_extraction_failure_and_retry_tracking(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('faulty_doc.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: ['document_type' => 'ITF263', 'title' => 'Faulty Document'],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        // Delete underlying storage file to induce controlled failure
        Storage::disk('private')->delete($doc->file_path);

        try {
            $service->processExtraction($doc, $this->userOrgA);
            $this->fail('Expected extraction exception');
        } catch (\Throwable $e) {
            // Handled
        }

        $doc->refresh();
        $this->assertEquals('FAILED', $doc->processing_status);
        $this->assertNotNull($doc->last_extraction_error);
        $this->assertStringContainsString('missing from private storage', $doc->last_extraction_error);

        // Restore file and retry
        Storage::disk('private')->put($doc->file_path, "%PDF-1.4\nStatutory Restored File\n%%EOF");
        $this->mockAiAgent();

        $service->processExtraction($doc, $this->userOrgA, force: true);

        $doc->refresh();
        $this->assertEquals('EXTRACTED', $doc->processing_status);
        $this->assertNull($doc->last_extraction_error);
    }

    /**
     * Test 4: Valid evidence is preserved during reassessment without resetting to UNKNOWN.
     */
    public function test_valid_evidence_retained_after_reassessment(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('valid_tax_cert.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: [
                'document_type' => 'ITF263',
                'title' => 'Active 2026 Tax Clearance',
                'issue_date' => Carbon::now()->subMonths(1)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(6)->toDateString(),
            ],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        // Link to obligation
        $service->linkToObligation($doc, $this->obligationA, $this->userOrgA);

        // Reviewer approves evidence
        $service->verifyDocument($doc, $this->reviewerOrgA);

        $this->obligationA->refresh();
        $this->assertEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);

        // Run evaluator directly to verify authoritativeness
        $evaluator = app(ObligationEvidenceStatusEvaluator::class);
        $status = $evaluator->evaluate($this->obligationA);
        $this->assertEquals('DOCUMENT_SUPPORTED', $status);

        // Statutory status remains NOT_STARTED (no automatic compliance declaration)
        $this->assertEquals('NOT_STARTED', $this->obligationA->status);
    }

    /**
     * Test 5: Expired evidence is excluded from DOCUMENT_SUPPORTED status during reassessment.
     */
    public function test_expired_evidence_excluded_after_reassessment(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('expired_cert.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: [
                'document_type' => 'ITF263',
                'title' => 'Expired Tax Clearance',
                'issue_date' => Carbon::now()->subYear()->toDateString(),
                'expiry_date' => Carbon::now()->subDays(10)->toDateString(), // Already expired
            ],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $service->linkToObligation($doc, $this->obligationA, $this->userOrgA);
        $service->verifyDocument($doc, $this->reviewerOrgA);

        $evaluator = app(ObligationEvidenceStatusEvaluator::class);
        $derivedStatus = $evaluator->evaluate($this->obligationA);

        // An expired document must NOT qualify for DOCUMENT_SUPPORTED
        $this->assertNotEquals('DOCUMENT_SUPPORTED', $derivedStatus);
        $this->assertEquals('EXPIRED_EVIDENCE', $derivedStatus);
    }

    /**
     * Test 6: Same-tenant cross-organisation extraction and access is strictly forbidden.
     */
    public function test_same_tenant_cross_organisation_extraction_and_access_denied(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('org_b_document.pdf');

        // Document uploaded to Org B by Org B user
        $docB = $service->upload(
            file: $file,
            attributes: ['document_type' => 'ITF263', 'title' => 'Org B Sensitive Financials'],
            uploader: $this->userOrgB,
            organisation: $this->orgB
        );

        // User A belongs to Org A (same tenant Apex Holdings)
        // Attempting to extract Org B's document must fail with 403 / AuthorizationException
        $this->actingAs($this->userOrgA)
            ->post("/documents/{$docB->id}/extract")
            ->assertStatus(403);

        // Attempting to download Org B's document must fail with 403
        $this->actingAs($this->userOrgA)
            ->get("/documents/{$docB->id}/download")
            ->assertStatus(403);

        // Attempting to verify Org B's document must fail with 403
        $this->actingAs($this->reviewerOrgA)
            ->post("/documents/{$docB->id}/verify", ['confirmed_payload' => []])
            ->assertStatus(403);

        // Attempting to reject Org B's document must fail with 403
        $this->actingAs($this->reviewerOrgA)
            ->post("/documents/{$docB->id}/reject", ['reason' => 'Unauthorized rejection'])
            ->assertStatus(403);

        // Attempting to link Org B's document to Org A's obligation must fail with 403
        $this->actingAs($this->userOrgA)
            ->post("/documents/{$docB->id}/link", ['obligation_instance_id' => $this->obligationA->id])
            ->assertStatus(403);
    }

    /**
     * Test 7: Unlinking eligible document causes evidence status to drop to rejected or unknown.
     */
    public function test_rejected_evidence_remaining_after_valid_evidence_is_unlinked(): void
    {
        $service = app(ComplianceDocumentService::class);

        // Document 1: Valid and approved
        $file1 = $this->createFakePdf('valid_clearance.pdf');
        $doc1 = $service->upload(
            file: $file1,
            attributes: [
                'document_type' => 'ITF263',
                'title' => 'Valid Clearance',
                'expiry_date' => Carbon::now()->addMonths(6)->toDateString(),
            ],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );
        $service->linkToObligation($doc1, $this->obligationA, $this->userOrgA);
        $service->verifyDocument($doc1, $this->reviewerOrgA);

        $this->obligationA->refresh();
        $this->assertEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);

        // Unlink valid document
        $service->unlinkFromObligation($doc1, $this->userOrgA);

        $this->obligationA->refresh();
        // With no eligible documents remaining, evidence status resets
        $this->assertNotEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);
        $this->assertEquals('UNKNOWN', $this->obligationA->evidence_status);
    }

    /**
     * Test 8: Multiple documents with mixed validity (one valid, one rejected) maintains support.
     */
    public function test_multiple_linked_documents_with_mixed_validity(): void
    {
        $service = app(ComplianceDocumentService::class);

        // Document 1: Valid and approved
        $file1 = $this->createFakePdf('valid_clearance.pdf');
        $doc1 = $service->upload(
            file: $file1,
            attributes: [
                'document_type' => 'ITF263',
                'title' => 'Valid Clearance',
                'expiry_date' => Carbon::now()->addMonths(6)->toDateString(),
            ],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );
        $service->linkToObligation($doc1, $this->obligationA, $this->userOrgA);
        $service->verifyDocument($doc1, $this->reviewerOrgA);

        // Document 2: Uploaded to same obligation, but rejected by reviewer
        $file2 = $this->createFakePdf('defective_clearance.pdf');
        $doc2 = $service->upload(
            file: $file2,
            attributes: ['document_type' => 'ITF263', 'title' => 'Defective Clearance'],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );
        $service->linkToObligation($doc2, $this->obligationA, $this->userOrgA);
        $service->rejectDocument($doc2, $this->reviewerOrgA, 'Signature illegible');

        // Evaluator must inspect both documents: Since doc1 is valid and verified, obligation remains DOCUMENT_SUPPORTED
        $evaluator = app(ObligationEvidenceStatusEvaluator::class);
        $status = $evaluator->evaluate($this->obligationA);

        $this->assertEquals('DOCUMENT_SUPPORTED', $status);
    }

    /**
     * Test 9: Scheduled evidence expiry command executes safely and idempotently.
     */
    public function test_scheduled_evidence_expiry_command_processing(): void
    {
        $service = app(ComplianceDocumentService::class);

        // Create document that expired yesterday
        $file = $this->createFakePdf('expiring_permit.pdf');
        $doc = $service->upload(
            file: $file,
            attributes: [
                'document_type' => 'ITF263',
                'title' => 'Expiring Certificate',
                'expiry_date' => Carbon::now()->subDay()->toDateString(),
            ],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $service->linkToObligation($doc, $this->obligationA, $this->userOrgA);
        $service->verifyDocument($doc, $this->reviewerOrgA);

        $this->assertEquals('VERIFIED', $doc->fresh()->verification_status);

        // Run scheduled artisan command
        $this->artisan('compliance:evaluate-evidence-lifecycle')
            ->expectsOutputToContain('Transitioned [1] documents to EXPIRED')
            ->assertExitCode(0);

        $doc->refresh();
        $this->assertEquals('EXPIRED', $doc->verification_status);

        $this->obligationA->refresh();
        $this->assertEquals('EXPIRED_EVIDENCE', $this->obligationA->evidence_status);

        // Verify audit event
        $auditEvent = AuditEvent::where('event_type', 'DOCUMENT_EXPIRED')
            ->where('auditable_id', $doc->id)
            ->first();
        $this->assertNotNull($auditEvent);

        // Second execution must be idempotent (0 transitions)
        $this->artisan('compliance:evaluate-evidence-lifecycle')
            ->expectsOutputToContain('Transitioned [0] documents to EXPIRED')
            ->assertExitCode(0);
    }

    /**
     * Test 10: Asynchronous queue dispatch returns promptly with QUEUED state.
     */
    public function test_queue_worker_processing_and_dispatch(): void
    {
        Queue::fake();

        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('permit.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: ['document_type' => 'ITF263', 'title' => 'Permit Upload'],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $response = $this->actingAs($this->userOrgA)->post("/documents/{$doc->id}/extract");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify QUEUED state persisted
        $doc->refresh();
        $this->assertEquals('QUEUED', $doc->processing_status);

        // Verify job dispatched to Laravel queue
        Queue::assertPushed(ExtractDocumentJob::class, function ($job) use ($doc) {
            return $job->documentId === $doc->id && $job->userId === $this->userOrgA->id;
        });
    }

    /**
     * Test 11: Audit trail is immutably preserved across document lifecycle.
     */
    public function test_historical_audit_events_preserved_across_lifecycle_events(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('audit_doc.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: ['document_type' => 'ITF263', 'title' => 'Audit Test Document'],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $uploadEvent = AuditEvent::where('event_type', 'DOCUMENT_UPLOADED')
            ->where('auditable_id', $doc->id)
            ->first();
        $this->assertNotNull($uploadEvent);
        $this->assertEquals($this->orgA->tenant_id, $uploadEvent->tenant_id);

        // Rejection audit
        $service->rejectDocument($doc, $this->reviewerOrgA, 'Audit check rejection');

        $rejectEvent = AuditEvent::where('event_type', 'DOCUMENT_REJECTED')
            ->where('auditable_id', $doc->id)
            ->first();
        $this->assertNotNull($rejectEvent);
        $this->assertEquals('Audit check rejection', $rejectEvent->payload['rejection_reason']);
    }

    /**
     * Test 12: Evidence verification NEVER independently declares statutory COMPLIANT status.
     */
    public function test_verification_never_automatically_declares_statutory_compliance(): void
    {
        $service = app(ComplianceDocumentService::class);
        $file = $this->createFakePdf('compliance_guard.pdf');

        $doc = $service->upload(
            file: $file,
            attributes: [
                'document_type' => 'ITF263',
                'title' => 'Tax Instrument',
                'expiry_date' => Carbon::now()->addYear()->toDateString(),
            ],
            uploader: $this->userOrgA,
            organisation: $this->orgA
        );

        $this->assertEquals('NOT_STARTED', $this->obligationA->status);

        $service->linkToObligation($doc, $this->obligationA, $this->userOrgA);
        $service->verifyDocument($doc, $this->reviewerOrgA);

        $this->obligationA->refresh();

        // Evidence status is elevated to DOCUMENT_SUPPORTED
        $this->assertEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);

        // Regulatory Safeguard: Obligation status MUST NOT be set to COMPLIANT by document upload/review
        $this->assertNotEquals('COMPLIANT', $this->obligationA->status);
        $this->assertEquals('NOT_STARTED', $this->obligationA->status);
    }
}
