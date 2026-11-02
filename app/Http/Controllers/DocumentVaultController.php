<?php

namespace App\Http\Controllers;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\RegistrationEngine\Jobs\ExtractDocumentJob;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Services\ComplianceDocumentService;
use App\Shared\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVaultController extends Controller
{
    public function __construct(
        protected ComplianceDocumentService $documentService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display the secure document vault interface.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $org = $this->tenantContext->getOrganisation();
        $user = $request->user();

        $documents = [];
        $obligations = [];
        $stats = [
            'total_documents' => 0,
            'verified_count' => 0,
            'pending_count' => 0,
            'rejected_count' => 0,
        ];
        $isReviewer = false;

        if ($org && $tenant) {
            // Check reviewer permissions
            $membership = Membership::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('organisation_id', $org->id)
                ->first();

            $isReviewer = $membership && in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER', 'AUDITOR'], true);

            // Fetch Documents with relationships
            $query = ComplianceDocument::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->with(['obligationInstance.definition.authority', 'aiVerificationItem', 'uploader', 'verifiedBy'])
                ->latest('created_at');

            // Apply Filters
            if ($request->filled('category') && $request->input('category') !== 'ALL') {
                $query->where('document_type', $request->input('category'));
            }

            if ($request->filled('status') && $request->input('status') !== 'ALL') {
                $query->where('verification_status', $request->input('status'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', "%{$search}%")
                      ->orWhere('file_name', 'ilike', "%{$search}%")
                      ->orWhere('issuing_authority', 'ilike', "%{$search}%");
                });
            }

            $docsCollection = $query->get();

            $stats['total_documents'] = ComplianceDocument::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->count();
            $stats['verified_count'] = ComplianceDocument::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->where('verification_status', 'VERIFIED')
                ->count();
            $stats['pending_count'] = ComplianceDocument::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->where('verification_status', 'PENDING')
                ->count();
            $stats['rejected_count'] = ComplianceDocument::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->where('verification_status', 'REJECTED')
                ->count();
            $stats['expired_count'] = ComplianceDocument::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->where('verification_status', 'EXPIRED')
                ->count();

            $documents = $docsCollection->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'category' => $doc->document_type,
                    'category_label' => ComplianceDocumentService::SUPPORTED_CATEGORIES[$doc->document_type] ?? $doc->document_type,
                    'file_name' => $doc->file_name,
                    'mime_type' => $doc->mime_type,
                    'file_size' => $doc->formattedFileSize(),
                    'file_size_bytes' => $doc->file_size_bytes,
                    'sha256_hash' => $doc->sha256_hash,
                    'uploaded_at' => $doc->created_at->format('d M Y, H:i'),
                    'uploaded_by' => $doc->uploader?->name ?? 'System',
                    'verification_status' => $doc->verification_status,
                    'processing_status' => $doc->processing_status,
                    'malware_scan_status' => $doc->malware_scan_status ?? 'SKIPPED',
                    'last_extraction_error' => $doc->last_extraction_error,
                    'extraction_attempts' => $doc->extraction_attempts ?? 0,
                    'expiry_date' => $doc->expiry_date?->toDateString(),
                    'issue_date' => $doc->issue_date?->toDateString(),
                    'issuing_authority' => $doc->issuing_authority,
                    'rejection_reason' => $doc->rejection_reason,
                    'verified_at' => $doc->verified_at?->format('d M Y, H:i'),
                    'verified_by' => $doc->verifiedBy?->name,
                    'extracted_metadata' => $doc->extracted_metadata,
                    'ai_verification_item' => $doc->aiVerificationItem ? [
                        'id' => $doc->aiVerificationItem->id,
                        'confidence_score' => $doc->aiVerificationItem->confidence_score,
                        'status' => $doc->aiVerificationItem->status,
                        'proposed_payload' => $doc->aiVerificationItem->proposed_payload,
                        'verified_payload' => $doc->aiVerificationItem->verified_payload,
                        'rejection_reason' => $doc->aiVerificationItem->rejection_reason,
                    ] : null,
                    'obligation_instance' => $doc->obligationInstance ? [
                        'id' => $doc->obligationInstance->id,
                        'code' => $doc->obligationInstance->definition?->code,
                        'name' => $doc->obligationInstance->definition?->name,
                        'authority' => $doc->obligationInstance->definition?->authority?->name,
                        'status' => $doc->obligationInstance->status,
                        'evidence_status' => $doc->obligationInstance->evidence_status,
                    ] : null,
                ];
            })->toArray();

            // Fetch Organisation's Obligations for linking
            $obligations = ObligationInstance::where('tenant_id', $tenant->id)
                ->where('organisation_id', $org->id)
                ->with(['definition.authority'])
                ->get()
                ->map(fn ($ob) => [
                    'id' => $ob->id,
                    'code' => $ob->definition?->code,
                    'name' => $ob->definition?->name,
                    'authority' => $ob->definition?->authority?->name,
                    'status' => $ob->status,
                    'evidence_status' => $ob->evidence_status,
                ])
                ->toArray();
        }

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'obligations' => $obligations,
            'supported_categories' => ComplianceDocumentService::SUPPORTED_CATEGORIES,
            'stats' => $stats,
            'is_reviewer' => $isReviewer,
            'filters' => [
                'category' => $request->input('category', 'ALL'),
                'status' => $request->input('status', 'ALL'),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    /**
     * Upload evidence document.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'document_type' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'obligation_instance_id' => ['nullable', 'uuid'],
            'auto_extract' => ['nullable', 'boolean'],
            'expiry_date' => ['nullable', 'date'],
            'issue_date' => ['nullable', 'date'],
            'issuing_authority' => ['nullable', 'string', 'max:128'],
        ]);

        $org = $this->tenantContext->getOrganisation();
        if (!$org) {
            return back()->withErrors(['organisation' => 'No active organisation selected.']);
        }

        try {
            $document = $this->documentService->upload(
                file: $request->file('file'),
                attributes: $request->only([
                    'document_type',
                    'title',
                    'obligation_instance_id',
                    'auto_extract',
                    'expiry_date',
                    'issue_date',
                    'issuing_authority',
                ]),
                uploader: $request->user(),
                organisation: $org
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', "Document [{$document->title}] securely uploaded and sealed with SHA-256 integrity hash.");
    }

    /**
     * Download secure document.
     */
    public function download(string $id, Request $request): StreamedResponse
    {
        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        return $this->documentService->download($document, $request->user(), inline: false);
    }

    /**
     * Preview secure document inline.
     */
    public function preview(string $id, Request $request): StreamedResponse
    {
        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        return $this->documentService->download($document, $request->user(), inline: true);
    }

    /**
     * Trigger AI extraction pipeline on stored document via background queue.
     */
    public function extract(string $id, Request $request): RedirectResponse
    {
        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        $force = $request->boolean('force');

        // Prevent accidental duplicate queueing
        if (!$force) {
            if (in_array($document->processing_status, ['QUEUED', 'PROCESSING'], true)) {
                return back()->with('info', 'Document extraction is already queued or processing.');
            }
            if ($document->processing_status === 'EXTRACTED') {
                return back()->with('info', 'Document is already extracted. Request re-extraction to re-run AI extraction.');
            }
        }

        // Persist QUEUED state and clear previous error
        $document->update([
            'processing_status' => 'QUEUED',
            'last_extraction_error' => null,
        ]);

        // Dispatch background job through queue
        ExtractDocumentJob::dispatch($document->id, $request->user()->id, $force);

        return back()->with('success', 'Document extraction queued for background processing.');
    }

    /**
     * Approve and verify document evidence.
     */
    public function verify(string $id, Request $request): RedirectResponse
    {
        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        $confirmedPayload = $request->input('confirmed_payload');

        $this->documentService->verifyDocument(
            document: $document,
            verifier: $request->user(),
            confirmedPayload: is_array($confirmedPayload) ? $confirmedPayload : null
        );

        return back()->with('success', "Evidence [{$document->title}] approved by human reviewer.");
    }

    /**
     * Reject document evidence with mandatory reason.
     */
    public function reject(string $id, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        $this->documentService->rejectDocument(
            document: $document,
            verifier: $request->user(),
            reason: $request->input('reason')
        );

        return back()->with('success', "Evidence [{$document->title}] rejected. Reason recorded in audit trail.");
    }

    /**
     * Link document to an obligation instance.
     */
    public function link(string $id, Request $request): RedirectResponse
    {
        $request->validate([
            'obligation_instance_id' => ['required', 'uuid'],
        ]);

        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        $obligation = ObligationInstance::where('id', $request->input('obligation_instance_id'))->firstOrFail();

        $this->documentService->linkToObligation($document, $obligation, $request->user());

        return back()->with('success', "Document linked to obligation [{$obligation->definition?->name}]. Evidence status updated.");
    }

    /**
     * Unlink document from an obligation instance.
     */
    public function unlink(string $id, Request $request): RedirectResponse
    {
        $document = ComplianceDocument::where('id', $id)->firstOrFail();

        $this->documentService->authorizeDocumentAccess($document, $request->user());

        $this->documentService->unlinkFromObligation($document, $request->user());

        return back()->with('success', "Document unlinked from obligation.");
    }
}
