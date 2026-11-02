<?php

namespace Tests\Feature\Documents;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\Jurisdiction;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Services\ComplianceDocumentService;
use App\Models\User;
use App\Shared\Ai\Agents\RegistrationDocumentExtractorAgent;
use App\Shared\Ai\Contracts\AiUsageMonitorInterface;
use App\Shared\Ai\Models\AiVerificationItem;
use App\Shared\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;
use Tests\TestCase;

class DocumentVaultAndExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $reviewerA;
    protected User $memberA;
    protected User $userB;
    protected Organisation $orgA;
    protected Organisation $orgB;
    protected RegulatoryAuthority $zimraAuthority;
    protected ObligationDefinition $vatDefinition;
    protected ObligationInstance $obligationA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        // Tenant A: Harare Engineering & Fabrication
        $this->tenantA = Tenant::create([
            'name' => 'Highfield Engineering Ltd',
            'slug' => 'highfield-engineering',
            'status' => 'ACTIVE',
        ]);

        $this->orgA = Organisation::create([
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Highfield Engineering & Fabrication (Pvt) Ltd',
            'trading_name' => 'Highfield Engineering',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->userA = User::create([
            'name' => 'Tinashe Chikore',
            'email' => 'tinashe@highfield.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $this->reviewerA = User::create([
            'name' => 'Grace Marufu',
            'email' => 'compliance@highfield.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $this->memberA = User::create([
            'name' => 'Simba Zhou',
            'email' => 'simba@highfield.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'organisation_id' => $this->orgA->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->reviewerA->id,
            'organisation_id' => $this->orgA->id,
            'role' => 'COMPLIANCE_OFFICER',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->memberA->id,
            'organisation_id' => $this->orgA->id,
            'role' => 'MEMBER',
            'status' => 'ACTIVE',
        ]);

        // Tenant B: Bulawayo Textiles
        $this->tenantB = Tenant::create([
            'name' => 'Bulawayo Textiles Ltd',
            'slug' => 'bulawayo-textiles',
            'status' => 'ACTIVE',
        ]);

        $this->orgB = Organisation::create([
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Bulawayo Textiles (Pvt) Ltd',
            'trading_name' => 'Bulawayo Textiles',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ]);

        $this->userB = User::create([
            'name' => 'Nqobile Ndlovu',
            'email' => 'nqobile@textiles.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        Membership::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'organisation_id' => $this->orgB->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        // Authorities and Obligations
        $jurisdiction = Jurisdiction::create([
            'code' => 'ZW-HARARE',
            'name' => 'City of Harare',
            'province' => 'Harare',
            'council_type' => 'CITY_COUNCIL',
        ]);

        $this->zimraAuthority = RegulatoryAuthority::create([
            'code' => 'ZIMRA',
            'name' => 'Zimbabwe Revenue Authority',
            'category' => 'STATUTORY_REGULATOR',
            'portal_url' => 'https://tarms.zimra.co.zw',
            'is_active' => true,
        ]);

        $this->vatDefinition = ObligationDefinition::create([
            'code' => 'ZIMRA_VAT',
            'authority_id' => $this->zimraAuthority->id,
            'title' => 'Value Added Tax Registration',
            'obligation_type' => 'REGISTRATION',
            'legal_classification' => 'CONDITIONAL_STATUTORY',
            'is_entity_level' => true,
            'statutory_citation' => 'Value Added Tax Act [Chapter 23:12], Section 23',
            'applicability_criteria' => [],
            'is_active' => true,
        ]);

        $this->obligationA = ObligationInstance::create([
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'definition_id' => $this->vatDefinition->id,
            'status' => 'NOT_STARTED',
            'evidence_status' => 'UNKNOWN',
        ]);

        app(TenantContext::class)->setTenant($this->tenantA);
        app(TenantContext::class)->setOrganisation($this->orgA);
    }

    /**
     * Helper to create a genuine test PDF with %PDF- header.
     */
    protected function createFakePdf(string $name = 'certificate.pdf', int $sizeKb = 50): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj\n<< /Title (ZimComply Test Document) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        $padding = str_repeat("A", max(0, ($sizeKb * 1024) - strlen($content)));
        return UploadedFile::fake()->createWithContent($name, $content . $padding);
    }

    /**
     * Helper to create a genuine test JPEG with magic bytes.
     */
    protected function createFakeJpeg(string $name = 'licence.jpg'): UploadedFile
    {
        $content = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00\x60\x00\x60\x00\x00" . str_repeat("\x00", 200);
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * Helper to create a genuine test PNG with magic bytes.
     */
    protected function createFakePng(string $name = 'stamp.png'): UploadedFile
    {
        $content = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4" . str_repeat("\x00", 100);
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_successful_document_upload_pdf(): void
    {
        $file = $this->createFakePdf('incorporation_cert.pdf');

        $response = $this->actingAs($this->userA)->post('/documents/upload', [
            'file' => $file,
            'document_type' => 'CERTIFICATE_OF_INCORPORATION',
            'title' => 'Certificate of Incorporation (CIPZ)',
            'issuing_authority' => 'Companies Registry (COBE Act)',
            'issue_date' => '2026-02-10',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('compliance_documents', [
            'tenant_id' => $this->tenantA->id,
            'organisation_id' => $this->orgA->id,
            'uploaded_by_user_id' => $this->userA->id,
            'document_type' => 'CERTIFICATE_OF_INCORPORATION',
            'title' => 'Certificate of Incorporation (CIPZ)',
            'file_name' => 'incorporation_cert.pdf',
            'mime_type' => 'application/pdf',
            'verification_status' => 'PENDING',
            'processing_status' => 'PENDING',
            'storage_disk' => 'private',
        ]);

        $doc = ComplianceDocument::first();
        $this->assertNotNull($doc);
        $this->assertNotEmpty($doc->sha256_hash);
        $this->assertEquals(64, strlen($doc->sha256_hash));

        // Assert file exists on private storage and NOT on public disk
        Storage::disk('private')->assertExists($doc->file_path);
        Storage::disk('public')->assertMissing($doc->file_path);
    }

    public function test_successful_document_upload_jpeg_and_png(): void
    {
        $jpeg = $this->createFakeJpeg('shop_licence.jpg');
        $this->actingAs($this->userA)->post('/documents/upload', [
            'file' => $jpeg,
            'document_type' => 'MUNICIPAL_LICENCE',
            'title' => 'City of Harare Operating Licence',
        ])->assertSessionHas('success');

        $png = $this->createFakePng('nssa_clearance.png');
        $this->actingAs($this->userA)->post('/documents/upload', [
            'file' => $png,
            'document_type' => 'NSSA_REGISTRATION',
            'title' => 'NSSA Registration Evidence',
        ])->assertSessionHas('success');

        $this->assertEquals(2, ComplianceDocument::count());
    }

    public function test_invalid_mime_type_rejected(): void
    {
        $csvFile = UploadedFile::fake()->createWithContent('payroll.csv', "Name,Wage,NSSA\nJohn,500,22.50");

        $response = $this->actingAs($this->userA)->post('/documents/upload', [
            'file' => $csvFile,
            'document_type' => 'OTHER',
            'title' => 'Payroll Sheet',
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(0, ComplianceDocument::count());
    }

    public function test_spoofed_file_extension_rejected(): void
    {
        // Malicious script disguised with .pdf extension without PDF magic bytes
        $spoofed = UploadedFile::fake()->createWithContent('malware.pdf', "<?php echo 'malicious payload'; ?>");

        $service = app(ComplianceDocumentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PDF file content: Missing %PDF- signature');

        $service->upload(
            file: $spoofed,
            attributes: ['document_type' => 'OTHER', 'title' => 'Spoofed File'],
            uploader: $this->userA,
            organisation: $this->orgA
        );
    }

    public function test_oversized_file_rejected(): void
    {
        // > 10MB file (11 MB)
        $hugeFile = UploadedFile::fake()->create('huge_document.pdf', 11500, 'application/pdf');

        $response = $this->actingAs($this->userA)->post('/documents/upload', [
            'file' => $hugeFile,
            'document_type' => 'CERTIFICATE_OF_INCORPORATION',
        ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertEquals(0, ComplianceDocument::count());
    }

    public function test_sha256_integrity_calculated_and_stored(): void
    {
        $content = "%PDF-1.4\nTest integrity payload for hashing\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('hash_test.pdf', $content);
        $expectedHash = hash('sha256', $content);

        $this->actingAs($this->userA)->post('/documents/upload', [
            'file' => $file,
            'document_type' => 'CR6',
            'title' => 'Registered Office Form CR6',
        ]);

        $doc = ComplianceDocument::where('document_type', 'CR6')->first();
        $this->assertNotNull($doc);
        $this->assertEquals($expectedHash, $doc->sha256_hash);
    }

    public function test_authorized_user_can_download_and_preview(): void
    {
        $file = $this->createFakePdf('tax_clearance.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'ITF263', 'title' => 'ITF263 Tax Clearance'], $this->userA, $this->orgA);

        // Download (attachment)
        $downloadResponse = $this->actingAs($this->userA)->get("/documents/{$doc->id}/download");
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
        $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename="tax_clearance.pdf"');

        // Preview (inline)
        $previewResponse = $this->actingAs($this->userA)->get("/documents/{$doc->id}/preview");
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Disposition', 'inline; filename="tax_clearance.pdf"');
    }

    public function test_cross_tenant_access_strictly_rejected(): void
    {
        $file = $this->createFakePdf('confidential_orgA.pdf');
        $service = app(ComplianceDocumentService::class);
        $docA = $service->upload($file, ['document_type' => 'CR14', 'title' => 'Highfield Directors'], $this->userA, $this->orgA);

        // Switch to Tenant B context
        app(TenantContext::class)->setTenant($this->tenantB);
        app(TenantContext::class)->setOrganisation($this->orgB);

        // Tenant B user tries to download Tenant A document -> ModelNotFoundException via TenantScope / 404
        $response = $this->actingAs($this->userB)->get("/documents/{$docA->id}/download");
        $response->assertStatus(404);

        $previewResponse = $this->actingAs($this->userB)->get("/documents/{$docA->id}/preview");
        $previewResponse->assertStatus(404);
    }

    public function test_ai_extraction_pipeline_with_mocked_agent(): void
    {
        $file = $this->createFakePdf('cobe_cert.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'CERTIFICATE_OF_INCORPORATION', 'title' => 'Certificate of Incorporation'], $this->userA, $this->orgA);

        // Mock Laravel AI SDK agent response
        Ai::fakeAgent(RegistrationDocumentExtractorAgent::class, [
            [
                'company_name' => 'Highfield Engineering & Fabrication (Pvt) Ltd',
                'registration_number' => 'HEF-8921/2026',
                'incorporation_date' => '2026-01-14',
                'directors' => ['Tinashe Chikore', 'Farai Moyo'],
                'tax_clearance_number' => 'ITF263-2026-4412',
                'confidence_score' => 0.96,
                'issuing_authority' => 'Companies and Intellectual Property Zimbabwe',
                'document_type' => 'CERTIFICATE_OF_INCORPORATION',
                'issue_date' => '2026-01-14',
                'expiry_date' => null,
                'registered_address' => '14 Kelvin Road North, Graniteside, Harare',
                'taxpayer_identifier' => '100482910',
            ],
        ]);

        $this->actingAs($this->userA)->post("/documents/{$doc->id}/extract")->assertRedirect();

        $doc->refresh();
        $this->assertEquals('EXTRACTED', $doc->processing_status);
        $this->assertNotNull($doc->ai_verification_item_id);
        $this->assertEquals('2026-01-14', $doc->issue_date->toDateString());
        $this->assertEquals('Companies and Intellectual Property Zimbabwe', $doc->issuing_authority);

        // Assert pending verification item
        $verificationItem = AiVerificationItem::find($doc->ai_verification_item_id);
        $this->assertNotNull($verificationItem);
        $this->assertEquals('PENDING', $verificationItem->status);
        $this->assertEquals('Highfield Engineering & Fabrication (Pvt) Ltd', $verificationItem->proposed_payload['company_name']);
        $this->assertEquals('HEF-8921/2026', $verificationItem->proposed_payload['registration_number']);
        $this->assertEquals(0.96, $verificationItem->confidence_score);
    }

    public function test_ai_extraction_handles_missing_fields_gracefully(): void
    {
        $file = $this->createFakePdf('worn_cr6.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'CR6', 'title' => 'Worn CR6 Form'], $this->userA, $this->orgA);

        // Agent returns null / unresolved fields
        Ai::fakeAgent(RegistrationDocumentExtractorAgent::class, [
            [
                'company_name' => 'Highfield Engineering',
                'registration_number' => 'UNRESOLVED',
                'incorporation_date' => '',
                'directors' => [],
                'tax_clearance_number' => '',
                'confidence_score' => 0.45,
            ],
        ]);

        $service->processExtraction($doc, $this->userA);

        $doc->refresh();
        $this->assertEquals('EXTRACTED', $doc->processing_status);
        $this->assertNull($doc->issue_date);
        $this->assertNull($doc->expiry_date);

        $item = $doc->aiVerificationItem;
        $this->assertNotNull($item);
        $this->assertEquals(0.45, $item->confidence_score);
    }

    public function test_ai_extraction_quota_exhausted_sets_unavailable(): void
    {
        $file = $this->createFakePdf('tax_cert.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'ITF263', 'title' => 'ZIMRA Tax Clearance'], $this->userA, $this->orgA);

        // Mock usage monitor returning no quota
        $mockMonitor = $this->createMock(AiUsageMonitorInterface::class);
        $mockMonitor->method('hasSufficientQuota')->willReturn(false);

        $serviceWithMock = new ComplianceDocumentService(
            app(TenantContext::class),
            null,
            $mockMonitor
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('monthly token quota exhausted');

        try {
            $serviceWithMock->processExtraction($doc, $this->userA);
        } finally {
            $doc->refresh();
            $this->assertEquals('UNAVAILABLE', $doc->processing_status);
        }
    }

    public function test_reviewer_authorization_required(): void
    {
        $file = $this->createFakePdf('council_permit.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'MUNICIPAL_LICENCE', 'title' => 'Council Permit'], $this->userA, $this->orgA);

        // Member without reviewer permission tries to verify -> 403 / AuthorizationException
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('Reviewer authorization required');

        $service->verifyDocument($doc, $this->memberA);
    }

    public function test_human_correction_and_approval(): void
    {
        $file = $this->createFakePdf('nssa_p4.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'NSSA_REGISTRATION', 'title' => 'NSSA Certificate'], $this->userA, $this->orgA);

        // Pre-create linked verification item
        Ai::fakeAgent(RegistrationDocumentExtractorAgent::class, [
            [
                'company_name' => 'Highfield Eng Typo',
                'registration_number' => 'NSSA-991',
                'incorporation_date' => '2026-03-01',
                'directors' => [],
                'tax_clearance_number' => '',
                'confidence_score' => 0.88,
            ],
        ]);
        $service->processExtraction($doc, $this->userA);

        // Reviewer corrects name and approves
        $response = $this->actingAs($this->reviewerA)->post("/documents/{$doc->id}/verify", [
            'confirmed_payload' => [
                'company_name' => 'Highfield Engineering & Fabrication (Pvt) Ltd',
                'registration_number' => 'NSSA-991-CORRECTED',
                'issue_date' => '2026-03-01',
                'expiry_date' => '2026-12-31',
                'issuing_authority' => 'National Social Security Authority',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $doc->refresh();
        $this->assertEquals('VERIFIED', $doc->verification_status);
        $this->assertEquals($this->reviewerA->id, $doc->verified_by_user_id);
        $this->assertNotNull($doc->verified_at);
        $this->assertEquals('2026-12-31', $doc->expiry_date->toDateString());
        $this->assertEquals('National Social Security Authority', $doc->issuing_authority);

        // Linked AI item is also VERIFIED
        $item = $doc->aiVerificationItem;
        $this->assertEquals('VERIFIED', $item->status);
        $this->assertEquals('Highfield Engineering & Fabrication (Pvt) Ltd', $item->verified_payload['company_name']);
        $this->assertEquals('NSSA-991-CORRECTED', $item->verified_payload['registration_number']);

        // Assert Audit Event
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->reviewerA->id,
            'event_type' => 'DOCUMENT_VERIFIED',
        ]);
    }

    public function test_rejection_with_mandatory_reason(): void
    {
        $file = $this->createFakePdf('blurry_itf263.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'ITF263', 'title' => 'Blurry Tax Clearance'], $this->userA, $this->orgA);

        // Missing reason fails validation
        $failResponse = $this->actingAs($this->reviewerA)->post("/documents/{$doc->id}/reject", [
            'reason' => '',
        ]);
        $failResponse->assertSessionHasErrors(['reason']);

        // Provide mandatory reason
        $successResponse = $this->actingAs($this->reviewerA)->post("/documents/{$doc->id}/reject", [
            'reason' => 'Certificate expired in 2025. Current year valid ITF263 is required.',
        ]);

        $successResponse->assertRedirect();
        $successResponse->assertSessionHas('success');

        $doc->refresh();
        $this->assertEquals('REJECTED', $doc->verification_status);
        $this->assertEquals('Certificate expired in 2025. Current year valid ITF263 is required.', $doc->rejection_reason);
        $this->assertEquals($this->reviewerA->id, $doc->verified_by_user_id);
    }

    public function test_evidence_to_obligation_linking_and_unlinking(): void
    {
        $file = $this->createFakePdf('vat_cert.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, ['document_type' => 'ITF263', 'title' => 'VAT Proof'], $this->userA, $this->orgA);

        // Link document to VAT obligation
        $this->actingAs($this->userA)->post("/documents/{$doc->id}/link", [
            'obligation_instance_id' => $this->obligationA->id,
        ])->assertSessionHas('success');

        $doc->refresh();
        $this->obligationA->refresh();

        $this->assertEquals($this->obligationA->id, $doc->obligation_instance_id);
        $this->assertEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);

        // Unlink document
        $this->actingAs($this->userA)->delete("/documents/{$doc->id}/unlink")->assertSessionHas('success');

        $doc->refresh();
        $this->obligationA->refresh();

        $this->assertNull($doc->obligation_instance_id);
        $this->assertEquals('UNKNOWN', $this->obligationA->evidence_status);
    }

    public function test_cross_tenant_evidence_linking_prevented(): void
    {
        $file = $this->createFakePdf('docA.pdf');
        $service = app(ComplianceDocumentService::class);
        $docA = $service->upload($file, ['document_type' => 'OTHER', 'title' => 'Tenant A Doc'], $this->userA, $this->orgA);

        // Create obligation for Tenant B
        $obligationB = ObligationInstance::create([
            'tenant_id' => $this->tenantB->id,
            'organisation_id' => $this->orgB->id,
            'definition_id' => $this->vatDefinition->id,
            'status' => 'NOT_STARTED',
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('Cross-tenant obligation linking strictly prohibited');

        $service->linkToObligation($docA, $obligationB, $this->userA);
    }

    public function test_zero_automatic_compliant_status_upon_verification(): void
    {
        // CRITICAL STATUTORY SAFEGUARD (Section 7)
        $file = $this->createFakePdf('itf263_approved.pdf');
        $service = app(ComplianceDocumentService::class);
        $doc = $service->upload($file, [
            'document_type' => 'ITF263',
            'title' => 'Official ZIMRA Tax Clearance',
            'obligation_instance_id' => $this->obligationA->id,
        ], $this->userA, $this->orgA);

        $this->obligationA->refresh();
        $this->assertEquals('NOT_STARTED', $this->obligationA->status);
        $this->assertEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);

        // Reviewer verifies document
        $service->verifyDocument($doc, $this->reviewerA, ['company_name' => 'Highfield Engineering']);

        $doc->refresh();
        $this->obligationA->refresh();

        $this->assertTrue($doc->isVerified());
        $this->assertEquals('DOCUMENT_SUPPORTED', $this->obligationA->evidence_status);

        // Obligation status MUST NOT be automatically marked COMPLIANT
        $this->assertNotEquals('COMPLIANT', $this->obligationA->status);
        $this->assertEquals('NOT_STARTED', $this->obligationA->status);
    }

    public function test_audit_trail_created_for_all_document_events(): void
    {
        $file = $this->createFakePdf('audit_check.pdf');
        $service = app(ComplianceDocumentService::class);

        // 1. Upload Event
        $doc = $service->upload($file, ['document_type' => 'CR14', 'title' => 'Audit Test Document'], $this->userA, $this->orgA);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'event_type' => 'DOCUMENT_UPLOADED',
            'auditable_id' => $doc->id,
        ]);

        // 2. Linking Event
        $service->linkToObligation($doc, $this->obligationA, $this->userA);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'event_type' => 'DOCUMENT_LINKED_TO_OBLIGATION',
            'auditable_id' => $doc->id,
        ]);

        // 3. Verification Event
        $service->verifyDocument($doc, $this->reviewerA);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->reviewerA->id,
            'event_type' => 'DOCUMENT_VERIFIED',
            'auditable_id' => $doc->id,
        ]);
    }
}
