import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    FolderLock,
    Upload,
    FileText,
    ShieldCheck,
    CheckCircle2,
    Clock,
    AlertCircle,
    Download,
    Eye,
    Search,
    Filter,
    Shield,
    Lock,
    X,
    FileCheck,
    AlertTriangle,
    Copy,
    Check,
    Sparkles,
    Link as LinkIcon,
    Unlink,
    ChevronRight,
    Building2,
    Calendar,
    Hash,
    UserCheck,
    RefreshCw,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge, BadgeVariant } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { router, useForm } from '@inertiajs/react';

interface Obligation {
    id: string;
    code: string;
    name: string;
    authority?: string;
    status: string;
    evidence_status: string;
}

interface AiVerificationItem {
    id: string;
    confidence_score: number;
    status: string;
    proposed_payload: Record<string, any>;
    verified_payload?: Record<string, any> | null;
    rejection_reason?: string | null;
}

interface VaultDocument {
    id: string;
    title: string;
    category: string;
    category_label: string;
    file_name: string;
    mime_type: string;
    file_size: string;
    file_size_bytes: number;
    sha256_hash: string;
    uploaded_at: string;
    uploaded_by: string;
    verification_status: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'EXPIRED' | 'SUPERSEDED' | 'REVOKED';
    processing_status: 'PENDING' | 'QUEUED' | 'PROCESSING' | 'EXTRACTED' | 'FAILED' | 'UNAVAILABLE';
    malware_scan_status?: 'CLEARED' | 'SKIPPED' | 'QUARANTINED' | 'SCAN_FAILED';
    last_extraction_error?: string | null;
    extraction_attempts?: number;
    expiry_date?: string | null;
    issue_date?: string | null;
    issuing_authority?: string | null;
    rejection_reason?: string | null;
    verified_at?: string | null;
    verified_by?: string | null;
    extracted_metadata?: Record<string, any> | null;
    ai_verification_item?: AiVerificationItem | null;
    obligation_instance?: Obligation | null;
}

interface DocumentsIndexProps {
    documents: VaultDocument[];
    obligations: Obligation[];
    supported_categories: Record<string, string>;
    stats: {
        total_documents: number;
        verified_count: number;
        pending_count: number;
        rejected_count: number;
        expired_count?: number;
    };
    is_reviewer: boolean;
    filters: {
        category: string;
        status: string;
        search: string;
    };
}

export default function DocumentsIndex({
    documents = [],
    obligations = [],
    supported_categories = {},
    stats = {
        total_documents: 0,
        verified_count: 0,
        pending_count: 0,
        rejected_count: 0,
        expired_count: 0,
    },
    is_reviewer: isReviewer = false,
    filters = { category: 'ALL', status: 'ALL', search: '' },
}: DocumentsIndexProps) {
    const [selectedCategory, setSelectedCategory] = useState<string>(filters.category || 'ALL');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'ALL');
    const [searchQuery, setSearchQuery] = useState<string>(filters.search || '');
    const [uploadModalOpen, setUploadModalOpen] = useState(false);
    const [inspectDocument, setInspectDocument] = useState<VaultDocument | null>(null);
    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [linkModalOpen, setLinkModalOpen] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');
    const [selectedObligationId, setSelectedObligationId] = useState('');
    const [copiedHash, setCopiedHash] = useState<string | null>(null);
    const [isExtracting, setIsExtracting] = useState<string | null>(null);

    // Edit fields state for reviewer
    const [editPayload, setEditPayload] = useState<Record<string, any>>({});

    // Upload Form
    const { data, setData, post, processing, errors, reset } = useForm({
        file: null as File | null,
        document_type: 'CERTIFICATE_OF_INCORPORATION',
        title: '',
        obligation_instance_id: '',
        auto_extract: true,
        expiry_date: '',
        issue_date: '',
        issuing_authority: '',
    });

    const handleCopyHash = (hash: string) => {
        navigator.clipboard.writeText(hash);
        setCopiedHash(hash);
        setTimeout(() => setCopiedHash(null), 2000);
    };

    const handleUploadSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/documents/upload', {
            onSuccess: () => {
                setUploadModalOpen(false);
                reset();
            },
        });
    };

    const handleOpenInspect = (doc: VaultDocument) => {
        setInspectDocument(doc);
        const payload = doc.ai_verification_item?.proposed_payload || doc.extracted_metadata || {};
        setEditPayload({
            company_name: payload.company_name || '',
            registration_number: payload.registration_number || '',
            issue_date: payload.issue_date || doc.issue_date || '',
            expiry_date: payload.expiry_date || doc.expiry_date || '',
            issuing_authority: payload.issuing_authority || doc.issuing_authority || '',
            tax_clearance_number: payload.tax_clearance_number || '',
            registered_address: payload.registered_address || '',
        });
    };

    const handleTriggerExtraction = (docId: string, force: boolean = false) => {
        setIsExtracting(docId);
        router.post(`/documents/${docId}/extract`, { force }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsExtracting(null);
                const updated = documents.find((d) => d.id === docId);
                if (updated) setInspectDocument(updated);
            },
            onError: () => {
                setIsExtracting(null);
            },
            onFinish: () => {
                setIsExtracting(null);
            },
        });
    };

    const handleApproveDocument = (docId: string) => {
        router.post(`/documents/${docId}/verify`, {
            confirmed_payload: editPayload,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setInspectDocument(null);
            },
        });
    };

    const handleRejectDocument = (docId: string) => {
        if (!rejectionReason.trim()) return;
        router.post(`/documents/${docId}/reject`, {
            reason: rejectionReason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectModalOpen(false);
                setRejectionReason('');
                setInspectDocument(null);
            },
        });
    };

    const handleLinkObligation = (docId: string) => {
        if (!selectedObligationId) return;
        router.post(`/documents/${docId}/link`, {
            obligation_instance_id: selectedObligationId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setLinkModalOpen(false);
                setSelectedObligationId('');
                if (inspectDocument) {
                    const linked = obligations.find((o) => o.id === selectedObligationId);
                    setInspectDocument({
                        ...inspectDocument,
                        obligation_instance: linked || null,
                    });
                }
            },
        });
    };

    const handleUnlinkObligation = (docId: string) => {
        router.delete(`/documents/${docId}/unlink`, {
            preserveScroll: true,
            onSuccess: () => {
                if (inspectDocument) {
                    setInspectDocument({
                        ...inspectDocument,
                        obligation_instance: null,
                    });
                }
            },
        });
    };

    // Client-side filtering
    const filteredDocs = documents.filter((doc) => {
        const matchesCat = selectedCategory === 'ALL' || doc.category === selectedCategory;
        const matchesStat = selectedStatus === 'ALL' || doc.verification_status === selectedStatus;
        const matchesSearch = searchQuery === '' ||
            doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            doc.file_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (doc.issuing_authority && doc.issuing_authority.toLowerCase().includes(searchQuery.toLowerCase()));
        return matchesCat && matchesStat && matchesSearch;
    });

    const getVerificationBadge = (status: VaultDocument['verification_status']): { variant: BadgeVariant; label: string } => {
        switch (status) {
            case 'VERIFIED':
                return { variant: 'success', label: 'Human Verified' };
            case 'REJECTED':
                return { variant: 'danger', label: 'Rejected' };
            case 'EXPIRED':
                return { variant: 'danger', label: 'Expired Evidence' };
            case 'SUPERSEDED':
                return { variant: 'slate', label: 'Superseded' };
            case 'REVOKED':
                return { variant: 'danger', label: 'Revoked' };
            case 'PENDING':
            default:
                return { variant: 'warning', label: 'Pending Review' };
        }
    };

    const getProcessingBadge = (status: VaultDocument['processing_status']): { variant: BadgeVariant; label: string } => {
        switch (status) {
            case 'QUEUED':
                return { variant: 'purple', label: 'Extraction Queued' };
            case 'PROCESSING':
                return { variant: 'info', label: 'Extracting...' };
            case 'EXTRACTED':
                return { variant: 'teal', label: 'AI Extracted' };
            case 'FAILED':
                return { variant: 'danger', label: 'Extraction Failed' };
            case 'UNAVAILABLE':
                return { variant: 'neutral', label: 'AI Unavailable' };
            case 'PENDING':
            default:
                return { variant: 'neutral', label: 'Not Extracted' };
        }
    };


    return (
        <AppLayout
            title="Secure Document Vault"
            subtitle="Tenant-isolated evidence repository with SHA-256 integrity verification, governed AI extraction, and human compliance sign-off."
        >
            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-[#19766F] bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
                                Tamper-Evident Evidence Vault
                            </span>
                            <span className="text-[10px] text-slate-500 font-mono">
                                Private Disk Storage • AES-256
                            </span>
                        </div>
                        <h2 className="text-base font-heading font-bold text-slate-900 mt-1">
                            Statutory Certificates & Registration Instruments
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Upload Certificate of Incorporation, CR6, CR14/CR5, ZIMRA ITF263, NSSA, and Municipal permits. Every file is sealed with a cryptographic SHA-256 checksum.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setUploadModalOpen(true)}
                            icon={<Upload className="h-4 w-4" />}
                        >
                            Upload Statutory Evidence
                        </Button>
                    </div>
                </div>

                {/* Vault Metrics Strip */}
                <div className="grid grid-cols-1 sm:grid-cols-5 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Vault Evidence</span>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1">
                                {stats.total_documents} Stored
                            </div>
                            <span className="text-[11px] text-slate-500">Encrypted in private storage</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Human Verified</span>
                            <div className="text-2xl font-heading font-extrabold text-emerald-700 mt-1">
                                {stats.verified_count} Approved
                            </div>
                            <span className="text-[11px] text-slate-500">Tier-2 document supported</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-amber-600">Pending Review</span>
                            <div className="text-2xl font-heading font-extrabold text-amber-700 mt-1">
                                {stats.pending_count} Awaiting Sign-off
                            </div>
                            <span className="text-[11px] text-slate-500">Requires human review</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-rose-600">Action Required</span>
                            <div className="text-2xl font-heading font-extrabold text-rose-700 mt-1">
                                {stats.rejected_count} Rejected
                            </div>
                            <span className="text-[11px] text-slate-500">Requires fresh upload</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-rose-700">Expired Evidence</span>
                            <div className="text-2xl font-heading font-extrabold text-rose-700 mt-1">
                                {stats.expired_count ?? 0} Expired
                            </div>
                            <span className="text-[11px] text-slate-500">Requires renewed filing</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters & Search Toolbar */}
                <Card>
                    <CardContent className="p-4 space-y-3">
                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
                            <div className="relative flex-1 max-w-md">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search by title, filename, or issuing authority..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#19766F] focus:border-[#19766F]"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500 font-medium flex items-center gap-1">
                                    <Filter className="h-3.5 w-3.5" /> Status:
                                </span>
                                <select
                                    value={selectedStatus}
                                    onChange={(e) => setSelectedStatus(e.target.value)}
                                    className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#19766F]"
                                >
                                    <option value="ALL">All Verification Statuses</option>
                                    <option value="PENDING">Pending Review</option>
                                    <option value="VERIFIED">Human Verified</option>
                                    <option value="REJECTED">Rejected</option>
                                    <option value="EXPIRED">Expired Evidence</option>
                                    <option value="SUPERSEDED">Superseded</option>
                                </select>
                            </div>
                        </div>

                        {/* Category Filter Pills */}
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                            <button
                                onClick={() => setSelectedCategory('ALL')}
                                className={`px-2.5 py-1 rounded-md text-xs font-medium transition-colors shrink-0 ${
                                    selectedCategory === 'ALL'
                                        ? 'bg-[#19766F] text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                            >
                                All Evidence
                            </button>
                            {Object.entries(supported_categories).map(([code, label]) => (
                                <button
                                    key={code}
                                    onClick={() => setSelectedCategory(code)}
                                    className={`px-2.5 py-1 rounded-md text-xs font-medium transition-colors shrink-0 ${
                                        selectedCategory === code
                                            ? 'bg-[#19766F] text-white'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    {label.split('(')[0].trim()}
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Documents Table */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2 border-b border-slate-100">
                        <div>
                            <CardTitle>Evidence Records ({filteredDocs.length})</CardTitle>
                            <CardDescription>
                                Official documents stored in tenant private storage with cryptographic checksum verification.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {filteredDocs.length === 0 ? (
                            <div className="p-12 text-center space-y-3">
                                <FolderLock className="h-10 w-10 text-slate-300 mx-auto" />
                                <h3 className="text-sm font-bold text-slate-800">No Documents Found</h3>
                                <p className="text-xs text-slate-500 max-w-md mx-auto">
                                    {documents.length === 0
                                        ? 'Your secure document vault is currently empty. Upload company incorporation documents, tax clearances, or licences to establish your compliance evidence.'
                                        : 'No documents match your selected category and status filters. Try clearing the filters.'}
                                </p>
                                {documents.length === 0 && (
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        onClick={() => setUploadModalOpen(true)}
                                        icon={<Upload className="h-4 w-4" />}
                                    >
                                        Upload First Document
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                                        <tr>
                                            <th className="py-3 px-4">Document Details</th>
                                            <th className="py-3 px-4">Issuing Authority & Dates</th>
                                            <th className="py-3 px-4">Cryptographic Integrity</th>
                                            <th className="py-3 px-4">Linked Obligation</th>
                                            <th className="py-3 px-4">AI Extraction</th>
                                            <th className="py-3 px-4">Verification</th>
                                            <th className="py-3 px-4 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {filteredDocs.map((doc) => {
                                            const verBadge = getVerificationBadge(doc.verification_status);
                                            const procBadge = getProcessingBadge(doc.processing_status);

                                            return (
                                                <tr key={doc.id} className="hover:bg-slate-50/70 transition-colors">
                                                    {/* Title & File */}
                                                    <td className="py-3 px-4">
                                                        <div className="font-bold text-slate-900 flex items-center gap-1.5">
                                                            <FileText className="h-3.5 w-3.5 text-teal-700 shrink-0" />
                                                            <span>{doc.title}</span>
                                                        </div>
                                                        <div className="text-[11px] text-slate-500 mt-0.5 flex items-center gap-2">
                                                            <span className="font-mono">{doc.file_name}</span>
                                                            <span>•</span>
                                                            <span>{doc.file_size}</span>
                                                        </div>
                                                        <Badge variant="neutral" size="sm" className="mt-1">
                                                            {doc.category_label}
                                                        </Badge>
                                                    </td>

                                                    {/* Authority & Dates */}
                                                    <td className="py-3 px-4 text-slate-600">
                                                        <div className="font-medium text-slate-800 text-[11px]">
                                                            {doc.issuing_authority || 'Statutory Authority'}
                                                        </div>
                                                        <div className="text-[10px] text-slate-500 mt-0.5 space-y-0.5">
                                                            {doc.issue_date && (
                                                                <div>Issued: {doc.issue_date}</div>
                                                            )}
                                                            {doc.expiry_date ? (
                                                                <div className="font-semibold text-slate-700">Expires: {doc.expiry_date}</div>
                                                            ) : (
                                                                <div className="text-slate-400">Perpetual / No Expiry</div>
                                                            )}
                                                        </div>
                                                    </td>

                                                    {/* Cryptographic SHA-256 */}
                                                    <td className="py-3 px-4">
                                                        <div className="flex items-center gap-1 font-mono text-[10px] text-slate-600 bg-slate-100 px-2 py-1 rounded border border-slate-200 w-fit">
                                                            <Lock className="h-2.5 w-2.5 text-emerald-600 shrink-0" />
                                                            <span>{doc.sha256_hash.substring(0, 14)}...</span>
                                                            <button
                                                                onClick={() => handleCopyHash(doc.sha256_hash)}
                                                                className="text-slate-400 hover:text-slate-700 ml-1"
                                                                title="Copy full SHA-256 hash"
                                                            >
                                                                {copiedHash === doc.sha256_hash ? (
                                                                    <Check className="h-3 w-3 text-emerald-600" />
                                                                ) : (
                                                                    <Copy className="h-3 w-3" />
                                                                )}
                                                            </button>
                                                        </div>
                                                        <div className="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                                                            <span>Uploaded: {doc.uploaded_at}</span>
                                                            <span className="text-[9px] px-1 py-0.2 rounded bg-slate-100 text-slate-500 font-mono">
                                                                {doc.malware_scan_status ?? 'SKIPPED'}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    {/* Linked Obligation */}
                                                    <td className="py-3 px-4">
                                                        {doc.obligation_instance ? (
                                                            <div>
                                                                <div className="font-semibold text-slate-800 text-[11px]">
                                                                    {doc.obligation_instance.name}
                                                                </div>
                                                                <div className="text-[10px] text-teal-700 font-medium">
                                                                    {doc.obligation_instance.code}
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="text-[11px] text-slate-400 italic">
                                                                Unlinked
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* AI Extraction Status */}
                                                    <td className="py-3 px-4 whitespace-nowrap">
                                                        <Badge variant={procBadge.variant} size="sm">
                                                            {procBadge.label}
                                                        </Badge>
                                                        {doc.ai_verification_item && (
                                                            <div className="text-[10px] text-slate-500 mt-1">
                                                                Score: {Math.round(doc.ai_verification_item.confidence_score * 100)}%
                                                            </div>
                                                        )}
                                                    </td>

                                                    {/* Verification Status */}
                                                    <td className="py-3 px-4 whitespace-nowrap">
                                                        <Badge variant={verBadge.variant} size="sm">
                                                            {verBadge.label}
                                                        </Badge>
                                                        {doc.verified_by && (
                                                            <div className="text-[10px] text-slate-500 mt-0.5">
                                                                by {doc.verified_by}
                                                            </div>
                                                        )}
                                                        {doc.rejection_reason && (
                                                            <div className="text-[10px] text-rose-600 font-medium max-w-xs truncate" title={doc.rejection_reason}>
                                                                Reason: {doc.rejection_reason}
                                                            </div>
                                                        )}
                                                    </td>

                                                    {/* Actions */}
                                                    <td className="py-3 px-4 text-right whitespace-nowrap space-x-1">
                                                        <a
                                                            href={`/documents/${doc.id}/preview`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex items-center p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded transition-colors"
                                                            title="Preview document"
                                                        >
                                                            <Eye className="h-3.5 w-3.5" />
                                                        </a>
                                                        <a
                                                            href={`/documents/${doc.id}/download`}
                                                            className="inline-flex items-center p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded transition-colors"
                                                            title="Download secure copy"
                                                        >
                                                            <Download className="h-3.5 w-3.5" />
                                                        </a>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => handleOpenInspect(doc)}
                                                        >
                                                            Inspect
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* MODAL: UPLOAD EVIDENCE */}
                {uploadModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
                        <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                            <div className="flex items-center justify-between p-4 border-b border-slate-100 bg-slate-50">
                                <div>
                                    <h3 className="text-sm font-heading font-bold text-slate-900">
                                        Upload Statutory Document
                                    </h3>
                                    <p className="text-[11px] text-slate-500">
                                        Secured with SHA-256 integrity hash in private vault.
                                    </p>
                                </div>
                                <button
                                    onClick={() => setUploadModalOpen(false)}
                                    className="text-slate-400 hover:text-slate-600 p-1"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>

                            <form onSubmit={handleUploadSubmit} className="p-4 space-y-3.5 text-xs">
                                {errors.file && (
                                    <div className="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-md text-xs flex items-center gap-2">
                                        <AlertCircle className="h-4 w-4 shrink-0" />
                                        <span>{errors.file}</span>
                                    </div>
                                )}

                                <div>
                                    <label className="block font-semibold text-slate-700 mb-1">
                                        Document Category *
                                    </label>
                                    <select
                                        value={data.document_type}
                                        onChange={(e) => setData('document_type', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-[#19766F]"
                                        required
                                    >
                                        {Object.entries(supported_categories).map(([code, label]) => (
                                            <option key={code} value={code}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-semibold text-slate-700 mb-1">
                                        Display Title
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="e.g. 2026 ZIMRA Tax Clearance Certificate"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-[#19766F]"
                                    />
                                </div>

                                <div>
                                    <label className="block font-semibold text-slate-700 mb-1">
                                        File (PDF, JPEG, PNG • Max 10MB) *
                                    </label>
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(e) => {
                                            if (e.target.files && e.target.files[0]) {
                                                setData('file', e.target.files[0]);
                                            }
                                        }}
                                        className="w-full border border-slate-200 rounded-lg p-2 text-xs bg-slate-50 file:mr-3 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100"
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block font-semibold text-slate-700 mb-1">
                                            Issue Date
                                        </label>
                                        <input
                                            type="date"
                                            value={data.issue_date}
                                            onChange={(e) => setData('issue_date', e.target.value)}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <label className="block font-semibold text-slate-700 mb-1">
                                            Expiry Date (if applicable)
                                        </label>
                                        <input
                                            type="date"
                                            value={data.expiry_date}
                                            onChange={(e) => setData('expiry_date', e.target.value)}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block font-semibold text-slate-700 mb-1">
                                        Link to Applicable Obligation (Optional)
                                    </label>
                                    <select
                                        value={data.obligation_instance_id}
                                        onChange={(e) => setData('obligation_instance_id', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs"
                                    >
                                        <option value="">-- No Obligation Linked --</option>
                                        {obligations.map((ob) => (
                                            <option key={ob.id} value={ob.id}>
                                                {ob.code} — {ob.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center gap-2 pt-1">
                                    <input
                                        type="checkbox"
                                        id="auto_extract"
                                        checked={data.auto_extract}
                                        onChange={(e) => setData('auto_extract', e.target.checked)}
                                        className="rounded border-slate-300 text-[#19766F] focus:ring-[#19766F]"
                                    />
                                    <label htmlFor="auto_extract" className="text-slate-700 cursor-pointer flex items-center gap-1">
                                        <Sparkles className="h-3.5 w-3.5 text-teal-600" />
                                        <span>Trigger AI structured extraction upon upload</span>
                                    </label>
                                </div>

                                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setUploadModalOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        disabled={processing}
                                        icon={<Upload className="h-3.5 w-3.5" />}
                                    >
                                        {processing ? 'Sealing...' : 'Upload & Seal'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* MODAL: INSPECT & VERIFICATION DRAWER */}
                {inspectDocument && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
                        <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                            {/* Modal Header */}
                            <div className="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-sm font-heading font-bold text-slate-900">
                                            {inspectDocument.title}
                                        </h3>
                                        <Badge
                                            variant={getVerificationBadge(inspectDocument.verification_status).variant}
                                            size="sm"
                                        >
                                            {getVerificationBadge(inspectDocument.verification_status).label}
                                        </Badge>
                                    </div>
                                    <p className="text-[11px] text-slate-500 font-mono mt-0.5">
                                        {inspectDocument.file_name} • {inspectDocument.file_size}
                                    </p>
                                </div>
                                <button
                                    onClick={() => setInspectDocument(null)}
                                    className="text-slate-400 hover:text-slate-600 p-1"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>

                            {/* Modal Content */}
                            <div className="p-5 overflow-y-auto space-y-5 text-xs flex-1">
                                {/* Cryptographic Proof Strip */}
                                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1.5">
                                    <div className="flex items-center justify-between text-[11px]">
                                        <span className="font-semibold text-slate-700 flex items-center gap-1.5">
                                            <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                                            Cryptographic SHA-256 Checksum:
                                        </span>
                                        <button
                                            onClick={() => handleCopyHash(inspectDocument.sha256_hash)}
                                            className="text-teal-700 hover:underline flex items-center gap-1"
                                        >
                                            {copiedHash === inspectDocument.sha256_hash ? (
                                                <span className="text-emerald-700 font-bold">Copied!</span>
                                            ) : (
                                                <span>Copy Full Hash</span>
                                            )}
                                        </button>
                                    </div>
                                    <div className="font-mono text-[10px] text-slate-600 break-all bg-white p-1.5 rounded border border-slate-100">
                                        {inspectDocument.sha256_hash}
                                    </div>
                                    <div className="flex items-center justify-between text-[10px] text-slate-500 pt-1">
                                        <div className="flex items-center gap-2">
                                            <span>Uploaded by {inspectDocument.uploaded_by} on {inspectDocument.uploaded_at}</span>
                                            <span className="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 font-mono">
                                                Malware: {inspectDocument.malware_scan_status ?? 'SKIPPED'}
                                            </span>
                                        </div>
                                        <div className="space-x-2">
                                            <a
                                                href={`/documents/${inspectDocument.id}/preview`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-teal-700 font-semibold hover:underline"
                                            >
                                                Preview Inline
                                            </a>
                                            <span>•</span>
                                            <a
                                                href={`/documents/${inspectDocument.id}/download`}
                                                className="text-teal-700 font-semibold hover:underline"
                                            >
                                                Download Original
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {/* Statutory Expiry Notice if applicable */}
                                {inspectDocument.verification_status === 'EXPIRED' && (
                                    <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 space-y-1">
                                        <div className="font-bold flex items-center gap-1.5 text-xs text-rose-800">
                                            <AlertTriangle className="h-4 w-4 text-rose-600" />
                                            Statutory Evidence Expired:
                                        </div>
                                        <p className="text-[11px] text-rose-700">
                                            This document expired on {inspectDocument.expiry_date}. Per regulatory rules, expired documents cannot maintain DOCUMENT_SUPPORTED status. Please upload an active renewal certificate.
                                        </p>
                                    </div>
                                )}

                                {/* Rejection Notice if applicable */}
                                {inspectDocument.verification_status === 'REJECTED' && inspectDocument.rejection_reason && (
                                    <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 space-y-1">
                                        <div className="font-bold flex items-center gap-1.5">
                                            <AlertTriangle className="h-4 w-4 text-rose-600" />
                                            Evidence Rejected by Reviewer:
                                        </div>
                                        <p className="text-xs text-rose-700">
                                            {inspectDocument.rejection_reason}
                                        </p>
                                    </div>
                                )}

                                {/* Linked Obligation Section */}
                                <div className="border border-slate-200 rounded-lg p-3 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-slate-700 flex items-center gap-1.5">
                                            <LinkIcon className="h-3.5 w-3.5 text-slate-500" />
                                            Linked Statutory Obligation:
                                        </span>
                                        {inspectDocument.obligation_instance && (
                                            <button
                                                onClick={() => handleUnlinkObligation(inspectDocument.id)}
                                                className="text-rose-600 text-[11px] font-semibold hover:underline flex items-center gap-1"
                                            >
                                                <Unlink className="h-3 w-3" /> Unlink
                                            </button>
                                        )}
                                    </div>

                                    {inspectDocument.obligation_instance ? (
                                        <div className="bg-slate-50 p-2.5 rounded border border-slate-200 flex items-center justify-between">
                                            <div>
                                                <span className="font-bold text-slate-800 block text-xs">
                                                    {inspectDocument.obligation_instance.name}
                                                </span>
                                                <span className="text-[10px] text-slate-500 font-mono">
                                                    {inspectDocument.obligation_instance.code} • Authority: {inspectDocument.obligation_instance.authority}
                                                </span>
                                            </div>
                                            <Badge variant="teal" size="sm">
                                                {inspectDocument.obligation_instance.evidence_status}
                                            </Badge>
                                        </div>
                                    ) : (
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-400 italic">No obligation currently linked.</span>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setLinkModalOpen(true)}
                                            >
                                                Link to Obligation
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                {/* Extraction In Progress Banner */}
                                {(inspectDocument.processing_status === 'QUEUED' || inspectDocument.processing_status === 'PROCESSING' || isExtracting === inspectDocument.id) && (
                                    <div className="p-3 bg-purple-50 border border-purple-200 rounded-lg text-purple-900 space-y-1">
                                        <div className="font-bold flex items-center gap-1.5 text-xs text-purple-800">
                                            <Clock className="h-4 w-4 text-purple-600 animate-spin" />
                                            Asynchronous Extraction In Progress:
                                        </div>
                                        <p className="text-[11px] text-purple-700">
                                            This document is queued for background AI extraction. Run your local queue worker (<code className="bg-purple-100 px-1 py-0.5 rounded font-mono">php artisan queue:work</code>) to process.
                                        </p>
                                    </div>
                                )}

                                {/* Extraction Failure Banner */}
                                {inspectDocument.processing_status === 'FAILED' && (
                                    <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-900 space-y-1">
                                        <div className="font-bold flex items-center gap-1.5 text-xs text-rose-800">
                                            <AlertCircle className="h-4 w-4 text-rose-600" />
                                            AI Extraction Unsuccessful:
                                        </div>
                                        <p className="text-[11px] text-rose-700">
                                            {inspectDocument.last_extraction_error || 'An unexpected error occurred during extraction.'}
                                            {inspectDocument.extraction_attempts ? ` (Attempts: ${inspectDocument.extraction_attempts})` : ''}
                                        </p>
                                    </div>
                                )}

                                {/* AI Extraction & Verification Panel */}
                                <div className="border border-teal-200 bg-teal-50/30 rounded-lg p-4 space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Sparkles className="h-4 w-4 text-teal-700" />
                                            <h4 className="font-bold text-slate-800">
                                                AI Structured Extraction & Review
                                            </h4>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {inspectDocument.ai_verification_item && (
                                                <Badge variant="teal" size="sm">
                                                    {Math.round(inspectDocument.ai_verification_item.confidence_score * 100)}% Confidence
                                                </Badge>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    inspectDocument.processing_status === 'PROCESSING' ||
                                                    inspectDocument.processing_status === 'QUEUED' ||
                                                    isExtracting === inspectDocument.id
                                                }
                                                onClick={() => handleTriggerExtraction(
                                                    inspectDocument.id,
                                                    inspectDocument.processing_status === 'EXTRACTED' || inspectDocument.processing_status === 'FAILED'
                                                )}
                                                icon={<RefreshCw className={`h-3 w-3 ${isExtracting === inspectDocument.id || inspectDocument.processing_status === 'PROCESSING' ? 'animate-spin' : ''}`} />}
                                            >
                                                {isExtracting === inspectDocument.id
                                                    ? 'Queueing...'
                                                    : inspectDocument.processing_status === 'QUEUED'
                                                    ? 'Queued'
                                                    : inspectDocument.processing_status === 'PROCESSING'
                                                    ? 'Extracting...'
                                                    : inspectDocument.processing_status === 'EXTRACTED'
                                                    ? 'Re-extract'
                                                    : inspectDocument.processing_status === 'FAILED'
                                                    ? 'Retry Extraction'
                                                    : 'Extract with AI'}
                                            </Button>
                                        </div>
                                    </div>

                                    <p className="text-[11px] text-slate-500">
                                        Extracted using official Laravel AI SDK (`RegistrationDocumentExtractorAgent`) with automated privacy sanitization.
                                    </p>

                                    {/* Extraction Fields (editable for reviewer) */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-white p-3 rounded-lg border border-slate-200">
                                        <div>
                                            <label className="block font-semibold text-slate-600 text-[10px] uppercase mb-1">
                                                Company Name
                                            </label>
                                            {isReviewer ? (
                                                <input
                                                    type="text"
                                                    value={editPayload.company_name || ''}
                                                    onChange={(e) => setEditPayload({ ...editPayload, company_name: e.target.value })}
                                                    className="w-full p-1.5 text-xs bg-slate-50 border border-slate-200 rounded focus:ring-1 focus:ring-[#19766F]"
                                                />
                                            ) : (
                                                <span className="font-medium text-slate-800">
                                                    {editPayload.company_name || 'Not detected'}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-600 text-[10px] uppercase mb-1">
                                                Registration / Ref Number
                                            </label>
                                            {isReviewer ? (
                                                <input
                                                    type="text"
                                                    value={editPayload.registration_number || ''}
                                                    onChange={(e) => setEditPayload({ ...editPayload, registration_number: e.target.value })}
                                                    className="w-full p-1.5 text-xs bg-slate-50 border border-slate-200 rounded focus:ring-1 focus:ring-[#19766F]"
                                                />
                                            ) : (
                                                <span className="font-medium text-slate-800 font-mono">
                                                    {editPayload.registration_number || 'Not detected'}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-600 text-[10px] uppercase mb-1">
                                                Issuing Authority
                                            </label>
                                            {isReviewer ? (
                                                <input
                                                    type="text"
                                                    value={editPayload.issuing_authority || ''}
                                                    onChange={(e) => setEditPayload({ ...editPayload, issuing_authority: e.target.value })}
                                                    className="w-full p-1.5 text-xs bg-slate-50 border border-slate-200 rounded focus:ring-1 focus:ring-[#19766F]"
                                                />
                                            ) : (
                                                <span className="font-medium text-slate-800">
                                                    {editPayload.issuing_authority || 'Not detected'}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-600 text-[10px] uppercase mb-1">
                                                Tax Clearance / BP Number
                                            </label>
                                            {isReviewer ? (
                                                <input
                                                    type="text"
                                                    value={editPayload.tax_clearance_number || ''}
                                                    onChange={(e) => setEditPayload({ ...editPayload, tax_clearance_number: e.target.value })}
                                                    className="w-full p-1.5 text-xs bg-slate-50 border border-slate-200 rounded focus:ring-1 focus:ring-[#19766F]"
                                                />
                                            ) : (
                                                <span className="font-medium text-slate-800 font-mono">
                                                    {editPayload.tax_clearance_number || 'None'}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-600 text-[10px] uppercase mb-1">
                                                Issue Date
                                            </label>
                                            {isReviewer ? (
                                                <input
                                                    type="date"
                                                    value={editPayload.issue_date || ''}
                                                    onChange={(e) => setEditPayload({ ...editPayload, issue_date: e.target.value })}
                                                    className="w-full p-1.5 text-xs bg-slate-50 border border-slate-200 rounded focus:ring-1 focus:ring-[#19766F]"
                                                />
                                            ) : (
                                                <span className="font-medium text-slate-800">
                                                    {editPayload.issue_date || 'None'}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-600 text-[10px] uppercase mb-1">
                                                Expiry Date
                                            </label>
                                            {isReviewer ? (
                                                <input
                                                    type="date"
                                                    value={editPayload.expiry_date || ''}
                                                    onChange={(e) => setEditPayload({ ...editPayload, expiry_date: e.target.value })}
                                                    className="w-full p-1.5 text-xs bg-slate-50 border border-slate-200 rounded focus:ring-1 focus:ring-[#19766F]"
                                                />
                                            ) : (
                                                <span className="font-medium text-slate-800">
                                                    {editPayload.expiry_date || 'Perpetual / None'}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Modal Footer / Reviewer Actions */}
                            <div className="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                                <span className="text-[11px] text-slate-500">
                                    {isReviewer
                                        ? 'Reviewer mode: You may verify or reject this statutory document.'
                                        : 'Viewer mode: Only Compliance Officers or Admins may verify documents.'}
                                </span>

                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setInspectDocument(null)}
                                    >
                                        Close
                                    </Button>

                                    {isReviewer && inspectDocument.verification_status !== 'REJECTED' && (
                                        <Button
                                            variant="danger"
                                            size="sm"
                                            onClick={() => setRejectModalOpen(true)}
                                        >
                                            Reject Evidence
                                        </Button>
                                    )}

                                    {isReviewer && inspectDocument.verification_status !== 'VERIFIED' && (
                                        <Button
                                            variant="primary"
                                            size="sm"
                                            onClick={() => handleApproveDocument(inspectDocument.id)}
                                            icon={<UserCheck className="h-3.5 w-3.5" />}
                                        >
                                            Approve Evidence
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* MODAL: REJECT REASON */}
                {rejectModalOpen && inspectDocument && (
                    <div className="fixed inset-0 z-60 flex items-center justify-center bg-slate-950/70 p-4">
                        <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-md w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                            <div>
                                <h3 className="text-sm font-heading font-bold text-slate-900">
                                    Reject Statutory Evidence
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Provide mandatory justification for regulatory audit compliance.
                                </p>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Rejection Reason *
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="e.g. Document copy is illegible or certificate expired in prior tax year."
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    className="w-full p-2.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:ring-1 focus:ring-[#19766F]"
                                    required
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRejectModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="danger"
                                    size="sm"
                                    disabled={!rejectionReason.trim()}
                                    onClick={() => handleRejectDocument(inspectDocument.id)}
                                >
                                    Confirm Rejection
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* MODAL: LINK TO OBLIGATION */}
                {linkModalOpen && inspectDocument && (
                    <div className="fixed inset-0 z-60 flex items-center justify-center bg-slate-950/70 p-4">
                        <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-md w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                            <div>
                                <h3 className="text-sm font-heading font-bold text-slate-900">
                                    Link Document to Obligation
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Select which statutory requirement this evidence supports.
                                </p>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Statutory Obligation
                                </label>
                                <select
                                    value={selectedObligationId}
                                    onChange={(e) => setSelectedObligationId(e.target.value)}
                                    className="w-full p-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:ring-1 focus:ring-[#19766F]"
                                >
                                    <option value="">-- Choose Obligation --</option>
                                    {obligations.map((ob) => (
                                        <option key={ob.id} value={ob.id}>
                                            {ob.code} — {ob.name} ({ob.authority})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setLinkModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="primary"
                                    size="sm"
                                    disabled={!selectedObligationId}
                                    onClick={() => handleLinkObligation(inspectDocument.id)}
                                >
                                    Link Evidence
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
