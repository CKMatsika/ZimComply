import React, { useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout';
import {
    RefreshCw,
    Shield,
    Clock,
    AlertTriangle,
    CheckCircle2,
    Calendar as CalendarIcon,
    Search,
    FileText,
    Plus,
    X,
    Scale,
    UserCheck,
    FolderLock,
    Link2,
    Unlink,
    ExternalLink,
    Info,
    Ban,
    History,
    Edit3,
    Eye,
    Download,
    ShieldAlert,
} from 'lucide-react';
import { Card, CardContent } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { PageProps } from '@/types';

interface MemberOption {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface AuthorityOption {
    id: string;
    code: string;
    name: string;
}

interface ObligationOption {
    id: string;
    code: string;
    title: string;
    authority_name: string;
    authority_id?: string | null;
}

interface VaultDocumentOption {
    id: string;
    title: string;
    file_name: string;
    document_type: string;
    verification_status: string;
    issue_date?: string | null;
    expiry_date?: string | null;
}

interface InstrumentItem {
    id: string;
    tenant_id: string;
    organisation_id: string;
    obligation_instance_id?: string | null;
    obligation_code?: string | null;
    instrument_category: string;
    instrument_category_label: string;
    title: string;
    issuing_authority_name: string;
    authority_code?: string | null;
    reference_number?: string | null;
    premises_or_scope?: string | null;
    issue_date?: string | null;
    expiry_date?: string | null;
    renewal_window_opens_at?: string | null;
    statutory_renewal_deadline?: string | null;
    deadline_verification_status: string;
    deadline_calculation_basis: {
        cadence_type?: string;
        explanation?: string;
        source_citation?: string | null;
        rule_version_code?: string | null;
    };
    deadline_metrics: {
        days_remaining: number | null;
        days_until_renewal_deadline: number | null;
        is_expired: boolean;
        is_expiring_soon: boolean;
        is_renewal_window_open: boolean;
        is_renewal_deadline_overdue: boolean;
    };
    validity_status: string;
    validity_status_label: string;
    current_workflow_status: string;
    current_workflow_status_label: string;
    source_citation?: string | null;
    rule_version_code?: string | null;
    responsible_user?: {
        id: string;
        name: string;
        email: string;
    } | null;
    current_document?: {
        id: string;
        title: string;
        file_name: string;
        verification_status: string;
        sha256_hash: string;
        issue_date?: string | null;
        expiry_date?: string | null;
    } | null;
    active_attempt?: {
        id: string;
        attempt_number: number;
        workflow_status: string;
        workflow_status_label: string;
        submission_reference?: string | null;
        authority_receipt_number?: string | null;
        submitted_at?: string | null;
        lock_version: number;
    } | null;
    attempts_count: number;
    evidence_count: number;
    suspension_or_cancellation_reason?: string | null;
    notes?: string | null;
    metadata_version: number;
    lock_version: number;
}

interface RenewalAttemptDetail {
    id: string;
    attempt_number: number;
    is_active_attempt: boolean;
    workflow_status: string;
    workflow_status_label: string;
    prior_reference_number?: string | null;
    prior_issue_date?: string | null;
    prior_expiry_date?: string | null;
    replacement_reference_number?: string | null;
    replacement_issue_date?: string | null;
    replacement_expiry_date?: string | null;
    submission_reference?: string | null;
    authority_receipt_number?: string | null;
    submitted_at?: string | null;
    submitted_by?: string | null;
    decision_outcome?: string | null;
    decision_recorded_at?: string | null;
    decision_recorded_by?: string | null;
    rejection_reason?: string | null;
    notes?: string | null;
    lock_version: number;
}

interface EvidenceLinkDetail {
    id: string;
    renewal_attempt_id?: string | null;
    document_purpose: string;
    document_purpose_label: string;
    is_superseded: boolean;
    superseded_at?: string | null;
    superseded_by_document_name?: string | null;
    notes?: string | null;
    linked_by?: string | null;
    created_at?: string | null;
    document?: {
        id: string;
        title: string;
        file_name: string;
        document_type: string;
        mime_type: string;
        file_size: string;
        sha256_hash: string;
        verification_status: string;
        issue_date?: string | null;
        expiry_date?: string | null;
        uploader_name?: string | null;
        verified_by_name?: string | null;
        preview_url: string;
        download_url: string;
    } | null;
}

interface TransitionOption {
    status: string;
    label: string;
    allowed: boolean;
    reason?: string | null;
}

interface ActivityLogItem {
    id: string;
    activity_type: string;
    from_validity_status?: string | null;
    to_validity_status?: string | null;
    from_workflow_status?: string | null;
    to_workflow_status?: string | null;
    notes?: string | null;
    metadata?: Record<string, any> | null;
    actor?: {
        id: string;
        name: string;
    } | null;
    created_at?: string | null;
}

interface RenewPageProps {
    instruments: InstrumentItem[];
    metrics: {
        total: number;
        active_instruments: number;
        expiring_soon: number;
        expired_instruments: number;
        renewals_in_progress: number;
        awaiting_authority_decision: number;
        completed_renewals: number;
        suspended_or_cancelled: number;
        deadline_unverified: number;
    };
    members: MemberOption[];
    filters: {
        category?: string;
        authority?: string;
        validity_status?: string;
        workflow_status?: string;
        responsible_user?: string;
        tab?: string;
        search?: string;
    };
    authorities: AuthorityOption[];
    obligations: ObligationOption[];
    vault_documents: VaultDocumentOption[];
    supported_categories: Record<string, string>;
    supported_purposes: Record<string, string>;
    can_manage: boolean;
    reference_date: string;
    timezone: string;
}

export default function RenewIndex({
    instruments = [],
    metrics = {
        total: 0,
        active_instruments: 0,
        expiring_soon: 0,
        expired_instruments: 0,
        renewals_in_progress: 0,
        awaiting_authority_decision: 0,
        completed_renewals: 0,
        suspended_or_cancelled: 0,
        deadline_unverified: 0,
    },
    members = [],
    filters = {},
    authorities = [],
    obligations = [],
    vault_documents = [],
    supported_categories = {},
    supported_purposes = {},
    can_manage = false,
    reference_date = '2026-11-03',
    timezone = 'Africa/Harare',
}: RenewPageProps) {
    const { errors } = usePage<PageProps>().props;

    // Filter state
    const [activeTab, setActiveTab] = useState<string>(filters.tab || 'ALL');
    const [selectedCategory, setSelectedCategory] = useState<string>(filters.category || 'ALL');
    const [selectedValidity, setSelectedValidity] = useState<string>(filters.validity_status || 'ALL');
    const [selectedWorkflow, setSelectedWorkflow] = useState<string>(filters.workflow_status || 'ALL');
    const [selectedUser, setSelectedUser] = useState<string>(filters.responsible_user || '');
    const [searchQuery, setSearchQuery] = useState<string>(filters.search || '');

    // Register Existing Instrument Modal state
    const [registerModalOpen, setRegisterModalOpen] = useState(false);
    const [regTitle, setRegTitle] = useState('');
    const [regCategory, setRegCategory] = useState('LICENCE');
    const [regAuthorityName, setRegAuthorityName] = useState('');
    const [regAuthorityId, setRegAuthorityId] = useState('');
    const [regObligationId, setRegObligationId] = useState('');
    const [regRefNumber, setRegRefNumber] = useState('');
    const [regScope, setRegScope] = useState('');
    const [regIssueDate, setRegIssueDate] = useState('');
    const [regExpiryDate, setRegExpiryDate] = useState('');
    const [regCurrentDocId, setRegCurrentDocId] = useState('');
    const [regAssigneeId, setRegAssigneeId] = useState('');
    const [regCitation, setRegCitation] = useState('');
    const [regNotes, setRegNotes] = useState('');
    const [isRegistering, setIsRegistering] = useState(false);

    // Detail Drawer state
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [drawerLoading, setDrawerLoading] = useState(false);
    const [activeInstrument, setActiveInstrument] = useState<InstrumentItem | null>(null);
    const [activeAttempt, setActiveAttempt] = useState<RenewalAttemptDetail | null>(null);
    const [availableTransitions, setAvailableTransitions] = useState<Record<string, TransitionOption>>({});
    const [attemptsHistory, setAttemptsHistory] = useState<RenewalAttemptDetail[]>([]);
    const [evidenceLinks, setEvidenceLinks] = useState<EvidenceLinkDetail[]>([]);
    const [drawerVaultDocs, setDrawerVaultDocs] = useState<VaultDocumentOption[]>(vault_documents);
    const [timeline, setTimeline] = useState<ActivityLogItem[]>([]);

    // Drawer Action forms
    const [assigneeId, setAssigneeId] = useState('');
    const [assignNotes, setAssignNotes] = useState('');
    const [isAssigning, setIsAssigning] = useState(false);

    const [startNotes, setStartNotes] = useState('');
    const [isStartingAttempt, setIsStartingAttempt] = useState(false);

    const [submissionRef, setSubmissionRef] = useState('');
    const [authorityReceipt, setAuthorityReceipt] = useState('');
    const [replacementRef, setReplacementRef] = useState('');
    const [replacementIssueDate, setReplacementIssueDate] = useState('');
    const [replacementExpiryDate, setReplacementExpiryDate] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [transitionNotes, setTransitionNotes] = useState('');
    const [isTransitioning, setIsTransitioning] = useState(false);

    const [selectedDocId, setSelectedDocId] = useState('');
    const [selectedDocPurpose, setSelectedDocPurpose] = useState('APPLICATION_SUPPORTING_EVIDENCE');
    const [evidenceNotes, setEvidenceNotes] = useState('');
    const [isLinkingEvidence, setIsLinkingEvidence] = useState(false);

    // Metadata correction & Validity override toggles
    const [showMetadataForm, setShowMetadataForm] = useState(false);
    const [metaRefNumber, setMetaRefNumber] = useState('');
    const [metaIssueDate, setMetaIssueDate] = useState('');
    const [metaExpiryDate, setMetaExpiryDate] = useState('');
    const [metaScope, setMetaScope] = useState('');
    const [metaReason, setMetaReason] = useState('');
    const [isUpdatingMeta, setIsUpdatingMeta] = useState(false);

    const [validityTarget, setValidityTarget] = useState('SUSPENDED');
    const [validityReason, setValidityReason] = useState('');
    const [isChangingValidity, setIsChangingValidity] = useState(false);

    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const params: Record<string, string | undefined> = {
            tab: activeTab !== 'ALL' ? activeTab : undefined,
            category: selectedCategory !== 'ALL' ? selectedCategory : undefined,
            validity_status: selectedValidity !== 'ALL' ? selectedValidity : undefined,
            workflow_status: selectedWorkflow !== 'ALL' ? selectedWorkflow : undefined,
            responsible_user: selectedUser || undefined,
            search: searchQuery || undefined,
            ...overrides,
        };

        router.get('/renew', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const loadInstrumentDetail = async (instrumentId: string) => {
        setDrawerLoading(true);
        try {
            const res = await axios.get(`/renew/instruments/${instrumentId}`);
            const data = res.data;
            setActiveInstrument(data.instrument);
            setActiveAttempt(data.active_attempt || null);
            setAvailableTransitions(data.available_transitions || {});
            setAttemptsHistory(data.attempts || []);
            setEvidenceLinks(data.evidence_links || []);
            setDrawerVaultDocs(data.available_vault_documents || vault_documents);
            setTimeline(data.timeline || []);

            setAssigneeId(data.instrument?.responsible_user?.id || '');
            setSubmissionRef(data.active_attempt?.submission_reference || '');
            setAuthorityReceipt(data.active_attempt?.authority_receipt_number || '');
            setMetaRefNumber(data.instrument?.reference_number || '');
            setMetaIssueDate(data.instrument?.issue_date || '');
            setMetaExpiryDate(data.instrument?.expiry_date || '');
            setMetaScope(data.instrument?.premises_or_scope || '');
        } catch (e) {
            console.error('Failed to load instrument detail', e);
        } finally {
            setDrawerLoading(false);
        }
    };

    const handleOpenInstrument = (inst: InstrumentItem) => {
        setActiveInstrument(inst);
        setDrawerOpen(true);
        loadInstrumentDetail(inst.id);
    };

    const handleCloseDrawer = () => {
        setDrawerOpen(false);
        setActiveInstrument(null);
        setActiveAttempt(null);
        setShowMetadataForm(false);
    };

    const handleRegisterInstrument = (e: React.FormEvent) => {
        e.preventDefault();
        setIsRegistering(true);
        router.post(
            '/renew/instruments',
            {
                title: regTitle,
                instrument_category: regCategory,
                issuing_authority_name: regAuthorityName,
                regulatory_authority_id: regAuthorityId || null,
                obligation_instance_id: regObligationId || null,
                reference_number: regRefNumber || null,
                premises_or_scope: regScope || null,
                issue_date: regIssueDate || null,
                expiry_date: regExpiryDate || null,
                current_document_id: regCurrentDocId || null,
                assigned_to_user_id: regAssigneeId || null,
                source_citation: regCitation || null,
                notes: regNotes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRegisterModalOpen(false);
                    setRegTitle('');
                    setRegRefNumber('');
                    setRegScope('');
                    setRegIssueDate('');
                    setRegExpiryDate('');
                    setRegNotes('');
                },
                onFinish: () => setIsRegistering(false),
            }
        );
    };

    const handleAssignOwner = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeInstrument) return;
        setIsAssigning(true);
        router.post(
            `/renew/instruments/${activeInstrument.id}/assign`,
            {
                assigned_to_user_id: assigneeId || null,
                notes: assignNotes || null,
                lock_version: activeInstrument.lock_version,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAssignNotes('');
                    loadInstrumentDetail(activeInstrument.id);
                },
                onFinish: () => setIsAssigning(false),
            }
        );
    };

    const handleStartRenewal = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeInstrument) return;
        setIsStartingAttempt(true);
        router.post(
            `/renew/instruments/${activeInstrument.id}/attempts/start`,
            {
                initial_status: 'RENEWAL_IN_PROGRESS',
                notes: startNotes || 'Initiated statutory renewal preparation workflow.',
                lock_version: activeInstrument.lock_version,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setStartNotes('');
                    loadInstrumentDetail(activeInstrument.id);
                },
                onFinish: () => setIsStartingAttempt(false),
            }
        );
    };

    const handleTransitionAttempt = (targetStatus: string) => {
        if (!activeInstrument || !activeAttempt) return;
        setIsTransitioning(true);
        router.post(
            `/renew/attempts/${activeAttempt.id}/transition`,
            {
                status: targetStatus,
                submission_reference: submissionRef || null,
                authority_receipt_number: authorityReceipt || null,
                replacement_reference_number: replacementRef || null,
                replacement_issue_date: replacementIssueDate || null,
                replacement_expiry_date: replacementExpiryDate || null,
                rejection_reason: rejectionReason || null,
                notes: transitionNotes || null,
                lock_version: activeAttempt.lock_version,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTransitionNotes('');
                    setRejectionReason('');
                    loadInstrumentDetail(activeInstrument.id);
                },
                onFinish: () => setIsTransitioning(false),
            }
        );
    };

    const handleLinkEvidence = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeInstrument || !selectedDocId) return;
        setIsLinkingEvidence(true);
        router.post(
            `/renew/instruments/${activeInstrument.id}/evidence/link`,
            {
                document_id: selectedDocId,
                document_purpose: selectedDocPurpose,
                renewal_attempt_id: activeAttempt?.id || null,
                notes: evidenceNotes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedDocId('');
                    setEvidenceNotes('');
                    loadInstrumentDetail(activeInstrument.id);
                },
                onFinish: () => setIsLinkingEvidence(false),
            }
        );
    };

    const handleUnlinkEvidence = (linkId: string) => {
        if (!activeInstrument) return;
        router.delete(`/renew/instruments/${activeInstrument.id}/evidence/${linkId}/unlink`, {
            preserveScroll: true,
            onSuccess: () => loadInstrumentDetail(activeInstrument.id),
        });
    };

    const handleUpdateMetadata = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeInstrument) return;
        setIsUpdatingMeta(true);
        router.post(
            `/renew/instruments/${activeInstrument.id}/metadata`,
            {
                reference_number: metaRefNumber || null,
                issue_date: metaIssueDate || null,
                expiry_date: metaExpiryDate || null,
                premises_or_scope: metaScope || null,
                correction_reason: metaReason,
                lock_version: activeInstrument.lock_version,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowMetadataForm(false);
                    setMetaReason('');
                    loadInstrumentDetail(activeInstrument.id);
                },
                onFinish: () => setIsUpdatingMeta(false),
            }
        );
    };

    const handleChangeValidity = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeInstrument) return;
        setIsChangingValidity(true);
        router.post(
            `/renew/instruments/${activeInstrument.id}/validity`,
            {
                validity_status: validityTarget,
                reason: validityReason,
                lock_version: activeInstrument.lock_version,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setValidityReason('');
                    loadInstrumentDetail(activeInstrument.id);
                },
                onFinish: () => setIsChangingValidity(false),
            }
        );
    };

    const getValidityBadge = (status: string, label: string) => {
        switch (status) {
            case 'ACTIVE':
                return <Badge variant="success" size="sm">{label}</Badge>;
            case 'EXPIRED':
                return <Badge variant="danger" size="sm">{label}</Badge>;
            case 'SUSPENDED':
                return <Badge variant="warning" size="sm">{label}</Badge>;
            case 'CANCELLED':
                return <Badge variant="danger" size="sm">{label}</Badge>;
            case 'SUPERSEDED':
                return <Badge variant="slate" size="sm">{label}</Badge>;
            default:
                return <Badge variant="purple" size="sm">{label}</Badge>;
        }
    };

    const getWorkflowBadge = (status: string, label: string) => {
        switch (status) {
            case 'RENEWED':
                return <Badge variant="success" size="sm">{label}</Badge>;
            case 'DECLARED_SUBMITTED':
            case 'AWAITING_AUTHORITY_DECISION':
                return <Badge variant="warning" size="sm">{label}</Badge>;
            case 'RENEWAL_IN_PROGRESS':
            case 'RENEWAL_UPCOMING':
            case 'AWAITING_DOCUMENTS':
            case 'READY_FOR_SUBMISSION':
                return <Badge variant="teal" size="sm">{label}</Badge>;
            case 'REJECTED':
            case 'CANCELLED':
                return <Badge variant="danger" size="sm">{label}</Badge>;
            default:
                return <Badge variant="neutral" size="sm">{label}</Badge>;
        }
    };

    return (
        <AppLayout
            title="Licence, Certificate & Registration Renewals"
            subtitle="Track statutory instrument validity, source-governed renewal deadlines, application preparation, and verified replacement outcomes."
        >
            <div className="space-y-6">
                {/* Top Action Header */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-[#19766F]">
                                Statutory Instrument & Renewal Register • {timezone}
                            </span>
                            <Badge variant="slate" size="sm">
                                Ref: {reference_date}
                            </Badge>
                        </div>
                        <h1 className="text-lg font-heading font-bold text-slate-900 mt-1">
                            Organisation Licences, Permits, Certificates & Registrations
                        </h1>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Separates underlying legal instrument validity from renewal application workflow progress. Pending applications never automatically extend expired licences.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Link
                            href="/comply/calendar"
                            className="px-3 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 flex items-center gap-1.5"
                        >
                            <CalendarIcon className="w-3.5 h-3.5 text-[#19766F]" />
                            <span>Compliance Calendar</span>
                        </Link>
                        <Button
                            variant="primary"
                            size="sm"
                            icon={<Plus className="h-4 w-4" />}
                            onClick={() => setRegisterModalOpen(true)}
                        >
                            Register Existing Instrument
                        </Button>
                    </div>
                </div>

                {/* Error Feedback */}
                {Object.keys(errors || {}).length > 0 && (
                    <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-900 text-xs space-y-1">
                        <div className="font-bold flex items-center gap-1.5">
                            <AlertTriangle className="w-4 h-4 text-red-600" />
                            <span>Action could not be completed:</span>
                        </div>
                        {Object.entries(errors).map(([key, msg]) => (
                            <p key={key} className="pl-5">• {String(msg)}</p>
                        ))}
                    </div>
                )}

                {/* 6 Renewal Dashboard Metric Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                                <span>Active Valid</span>
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1.5">
                                {metrics.active_instruments}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Current instruments</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-amber-700">
                                <span>Expiring Soon</span>
                                <Clock className="h-4 w-4 text-amber-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-amber-700 mt-1.5">
                                {metrics.expiring_soon}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Within 30 days</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-red-700">
                                <span>Expired</span>
                                <AlertTriangle className="h-4 w-4 text-red-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-red-700 mt-1.5">
                                {metrics.expired_instruments}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Past expiry date</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-[#19766F]">
                                <span>In Preparation</span>
                                <RefreshCw className="h-4 w-4 text-[#19766F]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1.5">
                                {metrics.renewals_in_progress}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Renewal in progress</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-amber-800">
                                <span>Awaiting Decision</span>
                                <Scale className="h-4 w-4 text-amber-700" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1.5">
                                {metrics.awaiting_authority_decision}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Declared submitted</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-purple-700">
                                <span>Completed</span>
                                <Shield className="h-4 w-4 text-purple-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-emerald-700 mt-1.5">
                                {metrics.completed_renewals}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Verified renewals</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter Tabs & Search Toolbar */}
                <Card>
                    <CardContent className="p-4 space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-1 bg-slate-100 p-1 rounded-lg">
                                {[
                                    { key: 'ALL', label: `All Instruments (${metrics.total})` },
                                    { key: 'ACTIVE', label: `Active (${metrics.active_instruments})` },
                                    { key: 'EXPIRING_SOON', label: `Expiring Soon (${metrics.expiring_soon})` },
                                    { key: 'EXPIRED', label: `Expired (${metrics.expired_instruments})` },
                                    { key: 'IN_PROGRESS', label: `In Progress (${metrics.renewals_in_progress})` },
                                    { key: 'AWAITING_DECISION', label: `Awaiting Decision (${metrics.awaiting_authority_decision})` },
                                    { key: 'COMPLETED', label: `Completed (${metrics.completed_renewals})` },
                                ].map((t) => (
                                    <button
                                        key={t.key}
                                        type="button"
                                        onClick={() => {
                                            setActiveTab(t.key);
                                            applyFilters({ tab: t.key !== 'ALL' ? t.key : undefined });
                                        }}
                                        className={`px-2.5 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                                            activeTab === t.key
                                                ? 'bg-white text-[#14263D] shadow-2xs'
                                                : 'text-slate-600 hover:text-slate-900'
                                        }`}
                                    >
                                        {t.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
                            <div className="relative">
                                <Search className="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search: searchQuery || undefined })}
                                    placeholder="Search title, reference, authority..."
                                    className="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900"
                                />
                            </div>

                            <select
                                value={selectedCategory}
                                onChange={(e) => {
                                    setSelectedCategory(e.target.value);
                                    applyFilters({ category: e.target.value !== 'ALL' ? e.target.value : undefined });
                                }}
                                aria-label="Filter by Instrument Category"
                                className="py-1.5 px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-800"
                            >
                                <option value="ALL">All Categories</option>
                                {Object.entries(supported_categories).map(([code, label]) => (
                                    <option key={code} value={code}>{label}</option>
                                ))}
                            </select>

                            <select
                                value={selectedValidity}
                                onChange={(e) => {
                                    setSelectedValidity(e.target.value);
                                    applyFilters({ validity_status: e.target.value !== 'ALL' ? e.target.value : undefined });
                                }}
                                aria-label="Filter by Legal Validity Status"
                                className="py-1.5 px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-800"
                            >
                                <option value="ALL">All Validity States</option>
                                <option value="ACTIVE">Active (Valid)</option>
                                <option value="EXPIRED">Expired</option>
                                <option value="SUSPENDED">Suspended</option>
                                <option value="CANCELLED">Cancelled</option>
                                <option value="UNKNOWN">Unknown Expiry</option>
                            </select>

                            <select
                                value={selectedWorkflow}
                                onChange={(e) => {
                                    setSelectedWorkflow(e.target.value);
                                    applyFilters({ workflow_status: e.target.value !== 'ALL' ? e.target.value : undefined });
                                }}
                                aria-label="Filter by Renewal Workflow Status"
                                className="py-1.5 px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-800"
                            >
                                <option value="ALL">All Workflow States</option>
                                <option value="NONE">No Active Application</option>
                                <option value="RENEWAL_IN_PROGRESS">Renewal in Progress</option>
                                <option value="AWAITING_DOCUMENTS">Awaiting Documents</option>
                                <option value="READY_FOR_SUBMISSION">Ready for Submission</option>
                                <option value="DECLARED_SUBMITTED">Declared Submitted</option>
                                <option value="AWAITING_AUTHORITY_DECISION">Awaiting Authority Decision</option>
                                <option value="RENEWED">Officially Renewed</option>
                                <option value="REJECTED">Rejected</option>
                            </select>

                            <select
                                value={selectedUser}
                                onChange={(e) => {
                                    setSelectedUser(e.target.value);
                                    applyFilters({ responsible_user: e.target.value || undefined });
                                }}
                                aria-label="Filter by Responsible User"
                                className="py-1.5 px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-800"
                            >
                                <option value="">All Responsible Users</option>
                                <option value="unassigned">Unassigned Only</option>
                                {members.map((m) => (
                                    <option key={m.id} value={m.id}>{m.name}</option>
                                ))}
                            </select>
                        </div>
                    </CardContent>
                </Card>

                {/* Instruments Register Table */}
                <Card>
                    <CardContent className="p-0">
                        {instruments.length === 0 ? (
                            <div className="p-12 text-center space-y-3">
                                <Shield className="w-10 h-10 text-slate-300 mx-auto" />
                                <div className="space-y-1">
                                    <p className="text-sm font-bold text-slate-800">
                                        No statutory instruments match the current view
                                    </p>
                                    <p className="text-xs text-slate-500 max-w-md mx-auto">
                                        Register an existing municipal licence, tax clearance (ITF263), PRAZ certificate, or regulatory permit to track validity and manage renewals.
                                    </p>
                                </div>
                                <Button
                                    variant="primary"
                                    size="sm"
                                    icon={<Plus className="w-4 h-4" />}
                                    onClick={() => setRegisterModalOpen(true)}
                                >
                                    Register Existing Instrument
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                        <tr>
                                            <th className="py-3 px-4">Statutory Instrument</th>
                                            <th className="py-3 px-4">Category & Reference</th>
                                            <th className="py-3 px-4">Issuing Authority</th>
                                            <th className="py-3 px-4">Instrument Expiry</th>
                                            <th className="py-3 px-4">Statutory Renewal Deadline</th>
                                            <th className="py-3 px-4">Legal Validity</th>
                                            <th className="py-3 px-4">Renewal Workflow</th>
                                            <th className="py-3 px-4">Owner</th>
                                            <th className="py-3 px-4 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {instruments.map((inst) => (
                                            <tr
                                                key={inst.id}
                                                onClick={() => handleOpenInstrument(inst)}
                                                className="hover:bg-slate-50/90 cursor-pointer transition-colors"
                                            >
                                                <td className="py-3 px-4">
                                                    <div className="font-bold text-slate-900">{inst.title}</div>
                                                    <div className="text-[11px] text-slate-500">
                                                        {inst.premises_or_scope || 'Organisation-wide'}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4 whitespace-nowrap">
                                                    <Badge variant="slate" size="sm">
                                                        {inst.instrument_category}
                                                    </Badge>
                                                    <div className="font-mono text-[11px] text-slate-600 mt-1">
                                                        {inst.reference_number || 'No Ref Recorded'}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4 text-slate-700 max-w-xs truncate">
                                                    {inst.issuing_authority_name}
                                                </td>
                                                <td className="py-3 px-4 whitespace-nowrap">
                                                    {inst.expiry_date ? (
                                                        <div>
                                                            <div className="font-bold text-slate-900">{inst.expiry_date}</div>
                                                            <div className={`text-[10px] font-medium ${
                                                                inst.deadline_metrics.days_remaining !== null && inst.deadline_metrics.days_remaining < 0
                                                                    ? 'text-red-600'
                                                                    : inst.deadline_metrics.is_expiring_soon
                                                                    ? 'text-amber-700'
                                                                    : 'text-slate-500'
                                                            }`}>
                                                                {inst.deadline_metrics.days_remaining !== null
                                                                    ? inst.deadline_metrics.days_remaining < 0
                                                                        ? `${Math.abs(inst.deadline_metrics.days_remaining)} days expired`
                                                                        : `${inst.deadline_metrics.days_remaining} days remaining`
                                                                    : ''}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-purple-700 font-semibold">
                                                            Unknown Expiry
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 px-4 whitespace-nowrap">
                                                    {inst.deadline_verification_status === 'VERIFIED_OFFICIAL' && inst.statutory_renewal_deadline ? (
                                                        <div>
                                                            <div className="font-bold text-slate-900">{inst.statutory_renewal_deadline}</div>
                                                            <div className="text-[10px] text-emerald-700 font-medium">
                                                                Verified Rule ({inst.rule_version_code || 'Official'})
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-[11px] text-purple-700 font-semibold">
                                                            Unverified (Hold)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 px-4 whitespace-nowrap">
                                                    {getValidityBadge(inst.validity_status, inst.validity_status_label)}
                                                </td>
                                                <td className="py-3 px-4 whitespace-nowrap">
                                                    {getWorkflowBadge(inst.current_workflow_status, inst.current_workflow_status_label)}
                                                </td>
                                                <td className="py-3 px-4 whitespace-nowrap">
                                                    {inst.responsible_user ? (
                                                        <span className="text-xs font-medium text-slate-800">
                                                            {inst.responsible_user.name}
                                                        </span>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-400 italic">
                                                            Unassigned
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 px-4 text-right whitespace-nowrap">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleOpenInstrument(inst);
                                                        }}
                                                    >
                                                        Open Instrument
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* REGISTER EXISTING INSTRUMENT MODAL */}
            {registerModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden">
                        <div className="p-5 bg-[#14263D] text-white flex items-center justify-between">
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-teal-300">
                                    Statutory Instrument Onboarding
                                </span>
                                <h2 className="text-base font-heading font-bold text-white">
                                    Register Existing Licence, Permit, Certificate or Registration
                                </h2>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRegisterModalOpen(false)}
                                className="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800"
                                aria-label="Close registration modal"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleRegisterInstrument} className="p-5 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div className="sm:col-span-2">
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Instrument Title *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={regTitle}
                                        onChange={(e) => setRegTitle(e.target.value)}
                                        placeholder="e.g. City of Harare Commercial Operating & Shop Licence"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Instrument Category *
                                    </label>
                                    <select
                                        value={regCategory}
                                        onChange={(e) => setRegCategory(e.target.value)}
                                        aria-label="Instrument Category"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg bg-white"
                                    >
                                        {Object.entries(supported_categories).map(([code, label]) => (
                                            <option key={code} value={code}>{label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Issuing Authority Name *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={regAuthorityName}
                                        onChange={(e) => setRegAuthorityName(e.target.value)}
                                        placeholder="e.g. City of Harare / ZIMRA / PRAZ"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Related Compliance Obligation (Optional)
                                    </label>
                                    <select
                                        value={regObligationId}
                                        onChange={(e) => {
                                            setRegObligationId(e.target.value);
                                            const found = obligations.find((o) => o.id === e.target.value);
                                            if (found && !regAuthorityName) {
                                                setRegAuthorityName(found.authority_name);
                                            }
                                        }}
                                        aria-label="Related Compliance Obligation"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg bg-white"
                                    >
                                        <option value="">-- Optional Linked Obligation --</option>
                                        {obligations.map((ob) => (
                                            <option key={ob.id} value={ob.id}>
                                                {ob.code} — {ob.title}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Reference / Registration Number
                                    </label>
                                    <input
                                        type="text"
                                        value={regRefNumber}
                                        onChange={(e) => setRegRefNumber(e.target.value)}
                                        placeholder="e.g. COH-LIC-2026-8842"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Issue Date (Where Known)
                                    </label>
                                    <input
                                        type="date"
                                        value={regIssueDate}
                                        onChange={(e) => setRegIssueDate(e.target.value)}
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Expiry Date (Where Known)
                                    </label>
                                    <input
                                        type="date"
                                        value={regExpiryDate}
                                        onChange={(e) => setRegExpiryDate(e.target.value)}
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Premises / Branch / Asset Scope
                                    </label>
                                    <input
                                        type="text"
                                        value={regScope}
                                        onChange={(e) => setRegScope(e.target.value)}
                                        placeholder="e.g. Workington Heavy Industrial Workshop"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Link Current Document from Vault
                                    </label>
                                    <select
                                        value={regCurrentDocId}
                                        onChange={(e) => setRegCurrentDocId(e.target.value)}
                                        aria-label="Link Current Document from Vault"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg bg-white"
                                    >
                                        <option value="">-- No Current Vault Document Yet --</option>
                                        {vault_documents.map((doc) => (
                                            <option key={doc.id} value={doc.id}>
                                                {doc.file_name} ({doc.verification_status})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Assign Responsible User
                                    </label>
                                    <select
                                        value={regAssigneeId}
                                        onChange={(e) => setRegAssigneeId(e.target.value)}
                                        aria-label="Assign Responsible User"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg bg-white"
                                    >
                                        <option value="">-- Unassigned --</option>
                                        {members.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name} ({m.role})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Statutory Source Citation (Optional)
                                    </label>
                                    <input
                                        type="text"
                                        value={regCitation}
                                        onChange={(e) => setRegCitation(e.target.value)}
                                        placeholder="e.g. Urban Councils Act [Chapter 29:15]"
                                        className="w-full py-2 px-3 border border-slate-300 rounded-lg"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Registration Notes
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={regNotes}
                                        onChange={(e) => setRegNotes(e.target.value)}
                                        placeholder="Notes on conditions, scope, or renewal requirements..."
                                        className="w-full p-2 border border-slate-300 rounded-lg"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRegisterModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    disabled={isRegistering}
                                >
                                    {isRegistering ? 'Registering...' : 'Save Statutory Instrument'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* INSTRUMENT DETAIL DRAWER */}
            {drawerOpen && activeInstrument && (
                <div className="fixed inset-0 z-50 overflow-hidden">
                    <div
                        onClick={handleCloseDrawer}
                        className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
                    />
                    <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
                        <div className="w-screen max-w-2xl bg-white shadow-2xl flex flex-col">
                            {/* Drawer Header */}
                            <div className="p-5 bg-[#14263D] text-white flex items-start justify-between gap-4">
                                <div className="space-y-1.5">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <Badge variant="teal" size="sm">
                                            {activeInstrument.instrument_category}
                                        </Badge>
                                        <span className="text-xs font-mono text-teal-200">
                                            Ref: {activeInstrument.reference_number || 'Unrecorded'}
                                        </span>
                                        <span className="text-[11px] text-slate-300">
                                            • {activeInstrument.issuing_authority_name}
                                        </span>
                                    </div>
                                    <h2 className="text-lg font-heading font-bold text-white leading-snug">
                                        {activeInstrument.title}
                                    </h2>
                                    <div className="flex items-center gap-2 pt-1 flex-wrap">
                                        {getValidityBadge(activeInstrument.validity_status, `Validity: ${activeInstrument.validity_status_label}`)}
                                        {getWorkflowBadge(activeInstrument.current_workflow_status, `Workflow: ${activeInstrument.current_workflow_status_label}`)}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleCloseDrawer}
                                    className="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800"
                                    aria-label="Close instrument drawer"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>

                            {/* Drawer Body */}
                            <div className="flex-1 overflow-y-auto p-5 space-y-5 bg-slate-50/60">
                                {/* Governance Safeguard Banner */}
                                <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-950 text-xs flex items-start gap-2.5">
                                    <Info className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                                    <div className="space-y-1">
                                        <div className="font-bold">
                                            Statutory Validity & Renewal Governance Safeguard
                                        </div>
                                        <p className="text-[11px] text-amber-800 leading-relaxed">
                                            Underlying instrument validity (<strong>{activeInstrument.validity_status}</strong>) is tracked independently from the renewal application workflow (<strong>{activeInstrument.current_workflow_status}</strong>).
                                            Submitting a renewal application does not automatically extend an expired licence, and uploading a replacement certificate does not mark the instrument <strong>RENEWED</strong> until verified by an authorised reviewer.
                                        </p>
                                    </div>
                                </div>

                                {/* 1. Source-Governed Validity & Deadline Explanation */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <Scale className="w-4 h-4 text-[#19766F]" />
                                            Source-Governed Validity & Renewal Dates
                                        </h3>
                                        {can_manage && (
                                            <button
                                                type="button"
                                                onClick={() => setShowMetadataForm(!showMetadataForm)}
                                                className="text-[11px] font-semibold text-[#19766F] hover:underline flex items-center gap-1"
                                            >
                                                <Edit3 className="w-3 h-3" />
                                                <span>Correct Metadata (v{activeInstrument.metadata_version})</span>
                                            </button>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="text-[10px] font-semibold uppercase text-slate-400">
                                                Issue Date
                                            </div>
                                            <div className="font-bold text-slate-900 mt-0.5">
                                                {activeInstrument.issue_date || 'Not Recorded'}
                                            </div>
                                        </div>

                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="text-[10px] font-semibold uppercase text-slate-400">
                                                Instrument Expiry Date
                                            </div>
                                            <div className="font-bold text-slate-900 mt-0.5">
                                                {activeInstrument.expiry_date || 'Unknown Expiry'}
                                            </div>
                                            {activeInstrument.deadline_metrics.days_remaining !== null && (
                                                <div className="text-[10px] text-slate-500">
                                                    {activeInstrument.deadline_metrics.days_remaining < 0
                                                        ? `${Math.abs(activeInstrument.deadline_metrics.days_remaining)} days past expiry`
                                                        : `${activeInstrument.deadline_metrics.days_remaining} days remaining`}
                                                </div>
                                            )}
                                        </div>

                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="text-[10px] font-semibold uppercase text-slate-400">
                                                Statutory Renewal Deadline
                                            </div>
                                            {activeInstrument.deadline_verification_status === 'VERIFIED_OFFICIAL' && activeInstrument.statutory_renewal_deadline ? (
                                                <>
                                                    <div className="font-bold text-slate-900 mt-0.5">
                                                        {activeInstrument.statutory_renewal_deadline}
                                                    </div>
                                                    <div className="text-[10px] text-emerald-700 font-medium">
                                                        Verified Official Rule
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="font-bold text-purple-700 mt-0.5">
                                                    Unverified ({activeInstrument.deadline_verification_status})
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px] text-slate-700 space-y-1">
                                        <div>
                                            <span className="font-semibold text-slate-900">Source Citation: </span>
                                            <span>{activeInstrument.source_citation || 'Pending Official Source Citation'}</span>
                                        </div>
                                        <div>
                                            <span className="font-semibold text-slate-900">Deadline Calculation Basis: </span>
                                            <span>{activeInstrument.deadline_calculation_basis?.explanation}</span>
                                        </div>
                                    </div>

                                    {showMetadataForm && can_manage && (
                                        <form onSubmit={handleUpdateMetadata} className="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2.5 text-xs">
                                            <div className="font-bold text-slate-800">
                                                Audited Instrument Metadata Correction
                                            </div>
                                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                <div>
                                                    <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Reference Number</label>
                                                    <input
                                                        type="text"
                                                        value={metaRefNumber}
                                                        onChange={(e) => setMetaRefNumber(e.target.value)}
                                                        className="w-full py-1 px-2 border border-slate-300 rounded"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Issue Date</label>
                                                    <input
                                                        type="date"
                                                        value={metaIssueDate}
                                                        onChange={(e) => setMetaIssueDate(e.target.value)}
                                                        className="w-full py-1 px-2 border border-slate-300 rounded"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Expiry Date</label>
                                                    <input
                                                        type="date"
                                                        value={metaExpiryDate}
                                                        onChange={(e) => setMetaExpiryDate(e.target.value)}
                                                        className="w-full py-1 px-2 border border-slate-300 rounded"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Mandatory Correction Reason *</label>
                                                <input
                                                    type="text"
                                                    required
                                                    value={metaReason}
                                                    onChange={(e) => setMetaReason(e.target.value)}
                                                    placeholder="State legal/documentary basis for metadata correction..."
                                                    className="w-full py-1 px-2 border border-slate-300 rounded"
                                                />
                                            </div>
                                            <div className="flex justify-end gap-2">
                                                <Button type="submit" variant="primary" size="sm" disabled={isUpdatingMeta}>
                                                    {isUpdatingMeta ? 'Saving...' : 'Record Correction'}
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </div>

                                {/* 2. Responsible Owner Assignment */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <UserCheck className="w-4 h-4 text-[#19766F]" />
                                            Renewal Owner Assignment
                                        </h3>
                                        {activeInstrument.responsible_user ? (
                                            <Badge variant="teal" size="sm">
                                                Assigned: {activeInstrument.responsible_user.name}
                                            </Badge>
                                        ) : (
                                            <Badge variant="neutral" size="sm">Unassigned</Badge>
                                        )}
                                    </div>

                                    {can_manage && (
                                        <form onSubmit={handleAssignOwner} className="space-y-2.5 pt-1">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        Assign to Organisation Member:
                                                    </label>
                                                    <select
                                                        value={assigneeId}
                                                        onChange={(e) => setAssigneeId(e.target.value)}
                                                        aria-label="Assign Renewal Owner"
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white"
                                                    >
                                                        <option value="">-- Unassign --</option>
                                                        {members.map((m) => (
                                                            <option key={m.id} value={m.id}>
                                                                {m.name} ({m.role})
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        Assignment Notes:
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={assignNotes}
                                                        onChange={(e) => setAssignNotes(e.target.value)}
                                                        placeholder="Instructions for renewal owner..."
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                            </div>
                                            <div className="flex justify-end">
                                                <Button type="submit" variant="outline" size="sm" disabled={isAssigning}>
                                                    {isAssigning ? 'Assigning...' : 'Assign Renewal Owner'}
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </div>

                                {/* 3. Renewal Application Workflow & Transitions */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between flex-wrap gap-2">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <RefreshCw className="w-4 h-4 text-[#19766F]" />
                                            Renewal Application Workflow
                                        </h3>
                                        {activeAttempt ? (
                                            <Badge variant="teal" size="sm">
                                                Active Attempt #{activeAttempt.attempt_number}: {activeAttempt.workflow_status_label}
                                            </Badge>
                                        ) : (
                                            <Badge variant="neutral" size="sm">
                                                No Active Renewal Attempt
                                            </Badge>
                                        )}
                                    </div>

                                    {!activeAttempt ? (
                                        <form onSubmit={handleStartRenewal} className="p-3.5 rounded-lg bg-slate-50 border border-slate-200 space-y-2.5">
                                            <p className="text-xs text-slate-700 font-medium">
                                                Start a new renewal application attempt to track preparation, supporting documents, submission reference, and official outcome.
                                            </p>
                                            <input
                                                type="text"
                                                value={startNotes}
                                                onChange={(e) => setStartNotes(e.target.value)}
                                                placeholder="Optional note when starting renewal preparation..."
                                                className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white"
                                            />
                                            <Button
                                                type="submit"
                                                variant="primary"
                                                size="sm"
                                                disabled={isStartingAttempt || activeInstrument.validity_status === 'CANCELLED'}
                                                icon={<RefreshCw className="w-3.5 h-3.5" />}
                                            >
                                                {isStartingAttempt ? 'Starting...' : 'Start Renewal Application'}
                                            </Button>
                                        </form>
                                    ) : (
                                        <div className="space-y-3">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        Submission Reference (Internal Declaration):
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={submissionRef}
                                                        onChange={(e) => setSubmissionRef(e.target.value)}
                                                        placeholder="e.g. COH-REN-SUB-2026-09"
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        Authority Receipt / Ack Number:
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={authorityReceipt}
                                                        onChange={(e) => setAuthorityReceipt(e.target.value)}
                                                        placeholder="e.g. REC-88412"
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        Replacement Certificate Ref:
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={replacementRef}
                                                        onChange={(e) => setReplacementRef(e.target.value)}
                                                        placeholder="New certificate number"
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        New Issue Date:
                                                    </label>
                                                    <input
                                                        type="date"
                                                        value={replacementIssueDate}
                                                        onChange={(e) => setReplacementIssueDate(e.target.value)}
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                        New Expiry Date:
                                                    </label>
                                                    <input
                                                        type="date"
                                                        value={replacementExpiryDate}
                                                        onChange={(e) => setReplacementExpiryDate(e.target.value)}
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                    Transition Notes / Rejection Reason:
                                                </label>
                                                <textarea
                                                    rows={2}
                                                    value={transitionNotes}
                                                    onChange={(e) => {
                                                        setTransitionNotes(e.target.value);
                                                        setRejectionReason(e.target.value);
                                                    }}
                                                    placeholder="Record preparation progress, submission notes, or authority rejection reason..."
                                                    className="w-full p-2 text-xs border border-slate-300 rounded-lg"
                                                />
                                            </div>

                                            <div className="space-y-2 pt-1">
                                                <p className="text-[11px] font-medium text-slate-500">
                                                    Available Renewal Workflow Transitions:
                                                </p>
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    {Object.entries(availableTransitions).map(([statusKey, opt]) => (
                                                        <div
                                                            key={statusKey}
                                                            className={`p-3 rounded-lg border text-xs flex flex-col justify-between gap-2 ${
                                                                opt.allowed
                                                                    ? 'border-slate-200 bg-white hover:border-[#19766F]'
                                                                    : 'border-slate-100 bg-slate-50/70 opacity-65'
                                                            }`}
                                                        >
                                                            <div>
                                                                <span className="font-semibold text-slate-800">{opt.label}</span>
                                                                {!opt.allowed && opt.reason && (
                                                                    <p className="text-[10px] text-amber-700 mt-0.5 leading-snug">
                                                                        {opt.reason}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <Button
                                                                variant={opt.status === 'RENEWED' ? 'primary' : 'outline'}
                                                                size="sm"
                                                                disabled={!opt.allowed || isTransitioning}
                                                                onClick={() => handleTransitionAttempt(opt.status)}
                                                            >
                                                                Move to {opt.label}
                                                            </Button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* 4. Document Vault Integration & Certificate Versions */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <FolderLock className="w-4 h-4 text-[#19766F]" />
                                            Linked Certificates & Supporting Evidence ({evidenceLinks.length})
                                        </h3>
                                        <Link
                                            href="/documents"
                                            className="text-[11px] font-semibold text-[#19766F] hover:underline flex items-center gap-1"
                                        >
                                            Document Vault <ExternalLink className="w-3 h-3" />
                                        </Link>
                                    </div>

                                    {evidenceLinks.length === 0 ? (
                                        <div className="p-3.5 bg-slate-50 border border-dashed border-slate-200 rounded-lg text-center">
                                            <p className="text-xs font-medium text-slate-600">
                                                No documents linked to this statutory instrument yet.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {evidenceLinks.map((link) => (
                                                <div
                                                    key={link.id}
                                                    className={`p-3 rounded-lg border text-xs flex items-start justify-between gap-3 ${
                                                        link.is_superseded
                                                            ? 'border-slate-200 bg-slate-100/70 opacity-80'
                                                            : 'border-slate-200 bg-slate-50'
                                                    }`}
                                                >
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <Badge variant={link.is_superseded ? 'slate' : 'teal'} size="sm">
                                                                {link.document_purpose_label}
                                                            </Badge>
                                                            {link.document && (
                                                                <Badge
                                                                    variant={
                                                                        link.document.verification_status === 'VERIFIED'
                                                                            ? 'success'
                                                                            : link.document.verification_status === 'SUPERSEDED'
                                                                            ? 'slate'
                                                                            : link.document.verification_status === 'REJECTED' || link.document.verification_status === 'EXPIRED'
                                                                            ? 'danger'
                                                                            : 'purple'
                                                                    }
                                                                    size="sm"
                                                                >
                                                                    {link.document.verification_status}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="font-bold text-slate-900">
                                                            {link.document?.file_name || 'Document'}
                                                        </p>
                                                        {link.document?.sha256_hash && (
                                                            <p className="font-mono text-[10px] text-slate-500">
                                                                SHA-256: {link.document.sha256_hash.slice(0, 16)}...
                                                            </p>
                                                        )}
                                                        {link.is_superseded && (
                                                            <p className="text-[10px] text-slate-600 font-medium">
                                                                Superseded on {link.superseded_at} {link.superseded_by_document_name ? `by ${link.superseded_by_document_name}` : ''}
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="flex items-center gap-1.5 shrink-0">
                                                        {link.document && (
                                                            <>
                                                                <a
                                                                    href={link.document.preview_url}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="p-1.5 rounded border border-slate-200 bg-white text-slate-600 hover:text-[#19766F]"
                                                                    title="Preview Evidence"
                                                                >
                                                                    <Eye className="w-3.5 h-3.5" />
                                                                </a>
                                                                <a
                                                                    href={link.document.download_url}
                                                                    className="p-1.5 rounded border border-slate-200 bg-white text-slate-600 hover:text-[#19766F]"
                                                                    title="Download Evidence"
                                                                >
                                                                    <Download className="w-3.5 h-3.5" />
                                                                </a>
                                                            </>
                                                        )}
                                                        {!link.is_superseded && link.document_purpose !== 'PRIOR_INSTRUMENT' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => handleUnlinkEvidence(link.id)}
                                                                className="p-1.5 rounded border border-slate-200 bg-white text-slate-400 hover:text-red-600"
                                                                title="Unlink Evidence"
                                                            >
                                                                <Unlink className="w-3.5 h-3.5" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Attach Vault Document Form */}
                                    <form onSubmit={handleLinkEvidence} className="pt-2 border-t border-slate-100 space-y-2">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <div>
                                                <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                    Select Vault Document:
                                                </label>
                                                <select
                                                    value={selectedDocId}
                                                    onChange={(e) => setSelectedDocId(e.target.value)}
                                                    aria-label="Select Vault Document to Attach"
                                                    className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white"
                                                >
                                                    <option value="">-- Choose Vault Document --</option>
                                                    {drawerVaultDocs.map((vd) => (
                                                        <option key={vd.id} value={vd.id}>
                                                            {vd.file_name} ({vd.verification_status})
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                    Evidence Purpose:
                                                </label>
                                                <select
                                                    value={selectedDocPurpose}
                                                    onChange={(e) => setSelectedDocPurpose(e.target.value)}
                                                    aria-label="Select Evidence Purpose"
                                                    className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white"
                                                >
                                                    {Object.entries(supported_purposes).map(([code, label]) => (
                                                        <option key={code} value={code}>{label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                        <div className="flex justify-end">
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={!selectedDocId || isLinkingEvidence}
                                                icon={<Link2 className="w-3.5 h-3.5" />}
                                            >
                                                Attach Evidence to Renewal
                                            </Button>
                                        </div>
                                    </form>
                                </div>

                                {/* 5. Renewal Attempts History */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <History className="w-4 h-4 text-[#19766F]" />
                                        Renewal Attempts Ledger ({attemptsHistory.length})
                                    </h3>

                                    {attemptsHistory.length === 0 ? (
                                        <p className="text-xs text-slate-400 italic">No renewal attempts initiated yet.</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {attemptsHistory.map((att) => (
                                                <div key={att.id} className="p-3 rounded-lg border border-slate-200 bg-slate-50 text-xs space-y-1">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-bold text-slate-900">
                                                            Attempt #{att.attempt_number} {att.is_active_attempt ? '(Active)' : '(Historical)'}
                                                        </span>
                                                        {getWorkflowBadge(att.workflow_status, att.workflow_status_label)}
                                                    </div>
                                                    <div className="text-[11px] text-slate-600">
                                                        Prior Ref: {att.prior_reference_number || 'N/A'} (Exp: {att.prior_expiry_date || 'N/A'})
                                                        {att.replacement_reference_number && (
                                                            <span> → New Ref: {att.replacement_reference_number} (Exp: {att.replacement_expiry_date})</span>
                                                        )}
                                                    </div>
                                                    {att.submission_reference && (
                                                        <div className="text-[11px] text-slate-600">
                                                            Submission Ref: <span className="font-mono">{att.submission_reference}</span>
                                                        </div>
                                                    )}
                                                    {att.rejection_reason && (
                                                        <div className="text-[11px] text-red-700 font-medium">
                                                            Rejection Reason: {att.rejection_reason}
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {/* 6. Administrative Validity Controls (Suspend / Cancel / Restore) */}
                                {can_manage && (
                                    <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-2.5">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <Ban className="w-4 h-4 text-amber-700" />
                                            Administrative Legal Validity Status Override
                                        </h3>
                                        <form onSubmit={handleChangeValidity} className="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end text-xs">
                                            <div>
                                                <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Target Validity State</label>
                                                <select
                                                    value={validityTarget}
                                                    onChange={(e) => setValidityTarget(e.target.value)}
                                                    className="w-full py-1.5 px-2 border border-slate-300 rounded-lg bg-white"
                                                >
                                                    <option value="SUSPENDED">SUSPENDED</option>
                                                    <option value="CANCELLED">CANCELLED</option>
                                                    <option value="ACTIVE">RESTORE ACTIVE</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Mandatory Reason / Notice Ref *</label>
                                                <input
                                                    type="text"
                                                    required
                                                    value={validityReason}
                                                    onChange={(e) => setValidityReason(e.target.value)}
                                                    placeholder="Authority notice or legal basis..."
                                                    className="w-full py-1.5 px-2 border border-slate-300 rounded-lg"
                                                />
                                            </div>
                                            <Button type="submit" variant="outline" size="sm" disabled={isChangingValidity}>
                                                {isChangingValidity ? 'Updating...' : 'Apply Validity Status'}
                                            </Button>
                                        </form>
                                    </div>
                                )}

                                {/* 7. Immutable Renewal Audit History */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <Clock className="w-4 h-4 text-[#19766F]" />
                                        Immutable Renewal Audit Trail
                                    </h3>

                                    {timeline.length === 0 ? (
                                        <p className="text-xs text-slate-400 italic">No activity recorded yet.</p>
                                    ) : (
                                        <div className="space-y-3 border-l-2 border-slate-200 pl-3">
                                            {timeline.map((log) => (
                                                <div key={log.id} className="text-xs space-y-0.5">
                                                    <div className="flex items-center gap-2 text-slate-700 flex-wrap">
                                                        <span className="font-semibold">
                                                            {log.actor?.name || 'Deterministic Engine'}
                                                        </span>
                                                        <span className="text-slate-400">•</span>
                                                        <span className="font-mono text-[10px] text-slate-500">
                                                            {log.activity_type}
                                                        </span>
                                                        {log.to_workflow_status && (
                                                            <Badge variant="slate" size="sm">
                                                                {log.to_workflow_status}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {log.notes && (
                                                        <p className="text-[11px] text-slate-600">{log.notes}</p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
