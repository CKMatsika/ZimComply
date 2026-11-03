import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Calendar as CalendarIcon,
    List,
    Clock,
    AlertTriangle,
    AlertCircle,
    CheckCircle2,
    ShieldCheck,
    ShieldAlert,
    FileCheck2,
    FolderLock,
    Search,
    Filter,
    X,
    ChevronLeft,
    ChevronRight,
    UserCheck,
    UserX,
    Building2,
    RefreshCw,
    ExternalLink,
    FileText,
    Info,
    Send,
    Eye,
    Ban,
    Link2,
    Unlink,
    Scale,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge, BadgeVariant } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { PageProps } from '@/types';

interface AuthorityOption {
    id: string;
    code: string;
    name: string;
}

interface ObligationOption {
    id: string;
    code: string;
    title: string;
}

interface MemberOption {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface LinkedDocument {
    id: string;
    title?: string;
    file_name: string;
    document_type: string;
    verification_status: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'EXPIRED';
    is_verified: boolean;
    is_pending: boolean;
    is_rejected: boolean;
    is_expired: boolean;
    rejection_reason?: string | null;
    expiry_date?: string | null;
    uploaded_at?: string;
    uploader_name?: string;
    verified_by_name?: string;
    verified_at?: string;
}

interface VaultDocumentOption {
    id: string;
    title: string;
    file_name: string;
    document_type: string;
    verification_status: string;
    filing_occurrence_id?: string | null;
}

interface TransitionOption {
    status: string;
    label: string;
    allowed: boolean;
    reason: string | null;
}

interface ActivityLogItem {
    id: string;
    activity_type: string;
    from_status?: string | null;
    to_status?: string | null;
    from_official_status?: string | null;
    to_official_status?: string | null;
    actor?: { id: string; name: string } | null;
    from_user?: { id: string; name: string } | null;
    to_user?: { id: string; name: string } | null;
    notes?: string | null;
    metadata?: Record<string, any> | null;
    created_at?: string;
}

interface FilingOccurrenceItem {
    id: string;
    obligation_instance_id: string;
    obligation_definition_id: string;
    title: string;
    obligation_code: string;
    authority: {
        id: string;
        code: string;
        name: string;
        portal_url?: string | null;
    };
    schedule_identity: string;
    filing_frequency: string;
    filing_period: {
        start: string;
        end: string;
        label: string;
    };
    due_dates: {
        original_statutory_due_date: string | null;
        current_due_date: string | null;
        payment_due_date: string | null;
        timezone: string;
        is_unverified: boolean;
    };
    deadline_calculation_basis: {
        pattern?: string;
        schedule_identity?: string;
        rule_version_id?: string | null;
        rule_version_code?: string | null;
        effective_from?: string | null;
        effective_to?: string | null;
        authority_code?: string;
        obligation_code?: string;
        source_citation?: string | null;
        weekend_adjustment_policy?: string;
        weekend_adjusted?: boolean;
        original_unadjusted_date?: string | null;
        payment_due_date?: string | null;
        payment_explanation?: string | null;
        explanation?: string;
        timezone?: string;
        authorised_amendments?: Array<{
            previous_due_date: string | null;
            amended_due_date: string;
            legal_basis_citation: string;
            justification: string;
            amended_at: string;
        }>;
    };
    display_classification: {
        classification: 'UPCOMING' | 'DUE_SOON' | 'DUE_TODAY' | 'OVERDUE' | 'COMPLETED' | 'DEADLINE_UNVERIFIED';
        label: string;
        days_delta: number | null;
        due_soon_window_days: number;
    };
    workflow_status: string;
    workflow_status_label: string;
    official_submission_status: string;
    official_submission_status_label: string;
    submission_reference?: string | null;
    official_receipt_number?: string | null;
    responsible_user?: {
        id: string;
        name: string;
        email: string;
    } | null;
    evidence_status: string;
    evidence_summary: {
        total_linked: number;
        verified: number;
        pending: number;
        rejected: number;
        required_documents: string[];
    };
    source_governance: {
        source_citation: string | null;
        verification_status: string;
        is_verified: boolean;
        rule_version_id?: string | null;
        rule_version_code?: string | null;
        effective_from?: string | null;
    };
    timestamps: {
        internally_approved_at?: string | null;
        internally_approved_by?: string | null;
        submitted_at?: string | null;
        submitted_by?: string | null;
        acknowledged_at?: string | null;
        acknowledged_by?: string | null;
        closed_at?: string | null;
        closed_by?: string | null;
    };
    internal_notes?: string | null;
    is_obligation_currently_applicable: boolean;
    lock_version: number;
}

interface CalendarEventItem {
    event_id: string;
    event_type: 'FILING_DUE_DATE' | 'PAYMENT_DUE_DATE' | 'INSTRUMENT_EXPIRY_DATE' | 'RENEWAL_SUBMISSION_DEADLINE';
    event_type_label: string;
    date: string;
    title: string;
    source_module: string;
    source_record_id: string;
    obligation_instance_id?: string | null;
    authority_code: string;
    reference_or_period: string;
    verification_status: string;
    status: string;
}

interface CalendarPageProps {
    occurrences: FilingOccurrenceItem[];
    calendar_events?: CalendarEventItem[];
    renewal_events?: CalendarEventItem[];
    metrics: {
        total: number;
        upcoming: number;
        due_soon: number;
        due_today: number;
        overdue: number;
        completed: number;
        deadline_unverified: number;
        submitted_internal: number;
        officially_acknowledged: number;
    };
    members: MemberOption[];
    filters: {
        month?: string;
        authority?: string;
        obligation?: string;
        responsible_user?: string;
        workflow_status?: string;
        classification?: string;
        search?: string;
        view?: string;
    };
    authorities: AuthorityOption[];
    obligations: ObligationOption[];
    can_manage: boolean;
    reference_date: string;
    timezone: string;
}

export default function ComplianceCalendar({
    occurrences = [],
    calendar_events = [],
    renewal_events = [],
    metrics = {
        total: 0,
        upcoming: 0,
        due_soon: 0,
        due_today: 0,
        overdue: 0,
        completed: 0,
        deadline_unverified: 0,
        submitted_internal: 0,
        officially_acknowledged: 0,
    },
    members = [],
    filters = {},
    authorities = [],
    obligations = [],
    can_manage = false,
    reference_date = '2026-11-03',
    timezone = 'Africa/Harare',
}: CalendarPageProps) {
    const { errors, auth } = usePage<PageProps>().props;

    // View mode: 'calendar' | 'list' | 'upcoming' | 'overdue'
    const [viewMode, setViewMode] = useState<'calendar' | 'list' | 'upcoming' | 'overdue'>(
        (filters.view as any) || 'calendar'
    );

    // Selected month for calendar grid (defaults to filter month or reference_date month)
    const initialMonth = filters.month || reference_date.slice(0, 7);
    const [selectedMonth, setSelectedMonth] = useState<string>(initialMonth);

    // Filter controls
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedAuthority, setSelectedAuthority] = useState(filters.authority || '');
    const [selectedObligation, setSelectedObligation] = useState(filters.obligation || '');
    const [selectedUser, setSelectedUser] = useState(filters.responsible_user || '');
    const [selectedWorkflowStatus, setSelectedWorkflowStatus] = useState(filters.workflow_status || '');
    const [selectedClassification, setSelectedClassification] = useState(filters.classification || '');

    // Detail Drawer state
    const [activeOccurrence, setActiveOccurrence] = useState<FilingOccurrenceItem | null>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [drawerLoading, setDrawerLoading] = useState(false);
    const [linkedDocuments, setLinkedDocuments] = useState<LinkedDocument[]>([]);
    const [availableVaultDocs, setAvailableVaultDocs] = useState<VaultDocumentOption[]>([]);
    const [availableTransitions, setAvailableTransitions] = useState<Record<string, TransitionOption>>({});
    const [timeline, setTimeline] = useState<ActivityLogItem[]>([]);

    // Drawer action forms
    const [assigneeId, setAssigneeId] = useState('');
    const [assignmentNotes, setAssignmentNotes] = useState('');
    const [isAssigning, setIsAssigning] = useState(false);

    const [transitionNotes, setTransitionNotes] = useState('');
    const [submissionReference, setSubmissionReference] = useState('');
    const [officialReceiptNumber, setOfficialReceiptNumber] = useState('');
    const [isTransitioning, setIsTransitioning] = useState(false);

    const [selectedVaultDocId, setSelectedVaultDocId] = useState('');
    const [isLinkingDoc, setIsLinkingDoc] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);

    // Apply server-side filters
    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const params: Record<string, string | undefined> = {
            search: searchQuery || undefined,
            authority: selectedAuthority || undefined,
            obligation: selectedObligation || undefined,
            responsible_user: selectedUser || undefined,
            workflow_status: selectedWorkflowStatus || undefined,
            classification: selectedClassification || undefined,
            view: viewMode,
            ...overrides,
        };

        router.get('/comply/calendar', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setSearchQuery('');
        setSelectedAuthority('');
        setSelectedObligation('');
        setSelectedUser('');
        setSelectedWorkflowStatus('');
        setSelectedClassification('');
        router.get('/comply/calendar', { view: viewMode }, { preserveState: true, replace: true });
    };

    const handleGenerateSchedule = () => {
        setIsGenerating(true);
        router.post(
            '/compliance/filings/generate',
            { horizon_months: 12 },
            {
                preserveScroll: true,
                onFinish: () => setIsGenerating(false),
            }
        );
    };

    // Month navigation helpers
    const navigateMonth = (delta: number) => {
        const [yrStr, moStr] = selectedMonth.split('-');
        const d = new Date(Number(yrStr), Number(moStr) - 1 + delta, 1);
        const nextYr = d.getFullYear();
        const nextMo = String(d.getMonth() + 1).padStart(2, '0');
        setSelectedMonth(`${nextYr}-${nextMo}`);
    };

    const formattedMonthHeader = useMemo(() => {
        const [yrStr, moStr] = selectedMonth.split('-');
        const d = new Date(Number(yrStr), Number(moStr) - 1, 1);
        return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    }, [selectedMonth]);

    // Build calendar days grid for selectedMonth
    const calendarGridDays = useMemo(() => {
        const [yrStr, moStr] = selectedMonth.split('-');
        const year = Number(yrStr);
        const monthIndex = Number(moStr) - 1;
        const firstDayOfMonth = new Date(year, monthIndex, 1);
        const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

        // Monday = 0 ... Sunday = 6
        const startWeekday = (firstDayOfMonth.getDay() + 6) % 7;

        const cells: Array<{
            dateStr: string | null;
            dayNumber: number | null;
            isToday: boolean;
            items: FilingOccurrenceItem[];
        }> = [];

        for (let i = 0; i < startWeekday; i++) {
            cells.push({ dateStr: null, dayNumber: null, isToday: false, items: [] });
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayItems = occurrences.filter((occ) => occ.due_dates.current_due_date === dateStr);
            cells.push({
                dateStr,
                dayNumber: day,
                isToday: dateStr === reference_date,
                items: dayItems,
            });
        }

        return cells;
    }, [selectedMonth, occurrences, reference_date]);

    // Unverified occurrences belonging to the selected month (or all unverified)
    const unverifiedOccurrences = useMemo(
        () => occurrences.filter((occ) => occ.due_dates.is_unverified),
        [occurrences]
    );

    const overdueOccurrences = useMemo(
        () => occurrences.filter((occ) => occ.display_classification.classification === 'OVERDUE'),
        [occurrences]
    );

    const upcomingOccurrences = useMemo(
        () =>
            occurrences.filter((occ) =>
                ['DUE_TODAY', 'DUE_SOON', 'UPCOMING'].includes(occ.display_classification.classification)
            ),
        [occurrences]
    );

    // Open Filing Detail Drawer
    const handleOpenFiling = async (item: FilingOccurrenceItem) => {
        setActiveOccurrence(item);
        setDrawerOpen(true);
        setDrawerLoading(true);
        setAssigneeId(item.responsible_user?.id || '');
        setAssignmentNotes('');
        setTransitionNotes('');
        setSubmissionReference(item.submission_reference || '');
        setOfficialReceiptNumber(item.official_receipt_number || '');
        setSelectedVaultDocId('');

        try {
            const res = await fetch(`/compliance/filings/${item.id}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data = await res.json();
                setActiveOccurrence(data.occurrence);
                setLinkedDocuments(data.documents || []);
                setAvailableVaultDocs(data.available_vault_documents || []);
                setAvailableTransitions(data.available_transitions || {});
                setTimeline(data.timeline || []);
                setAssigneeId(data.occurrence.responsible_user?.id || '');
                setSubmissionReference(data.occurrence.submission_reference || '');
                setOfficialReceiptNumber(data.occurrence.official_receipt_number || '');
            }
        } catch (err) {
            console.error('Failed to load filing occurrence detail', err);
        } finally {
            setDrawerLoading(false);
        }
    };

    const handleCloseDrawer = () => {
        setDrawerOpen(false);
        setActiveOccurrence(null);
    };

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && drawerOpen) {
                handleCloseDrawer();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [drawerOpen]);

    // Assign Owner
    const handleAssignOwner = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeOccurrence) return;

        setIsAssigning(true);
        router.post(
            `/compliance/filings/${activeOccurrence.id}/assign`,
            {
                assigned_to_user_id: assigneeId || null,
                lock_version: activeOccurrence.lock_version,
                notes: assignmentNotes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAssigning(false);
                    setAssignmentNotes('');
                    handleOpenFiling(activeOccurrence);
                },
                onError: () => setIsAssigning(false),
            }
        );
    };

    // Transition Workflow Status
    const handleTransitionStatus = (targetStatus: string) => {
        if (!activeOccurrence) return;

        setIsTransitioning(true);
        router.post(
            `/compliance/filings/${activeOccurrence.id}/transition`,
            {
                status: targetStatus,
                lock_version: activeOccurrence.lock_version,
                notes: transitionNotes || null,
                submission_reference: submissionReference || null,
                official_receipt_number: officialReceiptNumber || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsTransitioning(false);
                    setTransitionNotes('');
                    handleOpenFiling(activeOccurrence);
                },
                onError: () => setIsTransitioning(false),
            }
        );
    };

    // Link period-specific evidence document
    const handleLinkEvidence = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeOccurrence || !selectedVaultDocId) return;

        setIsLinkingDoc(true);
        router.post(
            `/compliance/filings/${activeOccurrence.id}/evidence/link`,
            { document_id: selectedVaultDocId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsLinkingDoc(false);
                    setSelectedVaultDocId('');
                    handleOpenFiling(activeOccurrence);
                },
                onError: () => setIsLinkingDoc(false),
            }
        );
    };

    // Unlink evidence document
    const handleUnlinkEvidence = (docId: string) => {
        if (!activeOccurrence) return;

        router.delete(`/compliance/filings/${activeOccurrence.id}/evidence/${docId}/unlink`, {
            preserveScroll: true,
            onSuccess: () => handleOpenFiling(activeOccurrence),
        });
    };

    // Classification badge helper
    const getClassificationBadge = (classification: string, label: string) => {
        switch (classification) {
            case 'OVERDUE':
                return (
                    <Badge variant="danger" size="sm" dot>
                        {label}
                    </Badge>
                );
            case 'DUE_TODAY':
                return (
                    <Badge variant="danger" size="sm" dot>
                        {label}
                    </Badge>
                );
            case 'DUE_SOON':
                return (
                    <Badge variant="warning" size="sm" dot>
                        {label}
                    </Badge>
                );
            case 'COMPLETED':
                return (
                    <Badge variant="success" size="sm" dot>
                        {label}
                    </Badge>
                );
            case 'DEADLINE_UNVERIFIED':
                return (
                    <Badge variant="purple" size="sm" dot>
                        {label}
                    </Badge>
                );
            default:
                return (
                    <Badge variant="info" size="sm" dot>
                        {label}
                    </Badge>
                );
        }
    };

    // Workflow status badge helper
    const getWorkflowBadge = (status: string, label: string) => {
        switch (status) {
            case 'ACKNOWLEDGED':
                return (
                    <Badge variant="success" size="sm">
                        <ShieldCheck className="w-3 h-3 mr-1 inline" />
                        {label}
                    </Badge>
                );
            case 'SUBMITTED':
                return (
                    <Badge variant="teal" size="sm">
                        <Send className="w-3 h-3 mr-1 inline" />
                        {label}
                    </Badge>
                );
            case 'INTERNALLY_APPROVED':
                return (
                    <Badge variant="info" size="sm">
                        <FileCheck2 className="w-3 h-3 mr-1 inline" />
                        {label}
                    </Badge>
                );
            case 'READY_FOR_REVIEW':
                return (
                    <Badge variant="purple" size="sm">
                        <Eye className="w-3 h-3 mr-1 inline" />
                        {label}
                    </Badge>
                );
            case 'PREPARING':
                return (
                    <Badge variant="warning" size="sm">
                        <Clock className="w-3 h-3 mr-1 inline" />
                        {label}
                    </Badge>
                );
            case 'REJECTED':
                return (
                    <Badge variant="danger" size="sm">
                        <Ban className="w-3 h-3 mr-1 inline" />
                        {label}
                    </Badge>
                );
            case 'CLOSED':
                return (
                    <Badge variant="slate" size="sm">
                        {label}
                    </Badge>
                );
            default:
                return (
                    <Badge variant="neutral" size="sm">
                        {label}
                    </Badge>
                );
        }
    };

    return (
        <AppLayout
            title="Statutory Filing Calendar & Deadline Engine"
            subtitle="Deterministic period-by-period statutory deadlines, internal preparation workflow, and verified regulatory receipts"
        >
            <Head title="Compliance Calendar — ZimComply" />

            <div className="space-y-6">
                {/* Validation / Concurrency Error Banner */}
                {errors && Object.keys(errors).length > 0 && (
                    <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-900 text-sm flex items-start gap-3 shadow-xs">
                        <AlertCircle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="font-semibold">Filing Validation Notice</p>
                            {Object.entries(errors).map(([key, msg]) => (
                                <p key={key} className="text-red-700">
                                    {String(msg)}
                                </p>
                            ))}
                        </div>
                    </div>
                )}

                {/* Top Workspace Navigation & Action Bar */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h2 className="text-lg font-heading font-bold text-slate-900">
                                    Statutory Filing Occurrences & Deadlines
                                </h2>
                                <Badge variant="teal" size="sm">
                                    Timezone: {timezone}
                                </Badge>
                            </div>
                            <p className="text-xs sm:text-sm text-slate-600 max-w-3xl">
                                Every filing deadline is deterministically computed from an approved effective-dated rule version.
                                Internal submission declarations are tracked separately from verified official authority acknowledgement.
                            </p>
                        </div>

                        <div className="flex items-center gap-2 flex-wrap shrink-0">
                            <Button
                                variant="outline"
                                size="sm"
                                href="/comply"
                                icon={<ShieldCheck className="w-4 h-4 text-slate-600" />}
                            >
                                Obligation Register
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                href="/documents"
                                icon={<FolderLock className="w-4 h-4 text-slate-600" />}
                            >
                                Document Vault
                            </Button>
                            <Button
                                variant="primary"
                                size="sm"
                                onClick={handleGenerateSchedule}
                                disabled={isGenerating}
                                icon={<RefreshCw className={`w-4 h-4 ${isGenerating ? 'animate-spin' : ''}`} />}
                            >
                                {isGenerating ? 'Synchronizing...' : 'Generate 12-Month Schedule'}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* 6-Card Operational Summary Strip */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <span>Total Filings</span>
                                <CalendarIcon className="w-4 h-4 text-slate-400" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1.5">
                                {metrics.total}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Active horizon</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-red-700">
                                <span>Overdue</span>
                                <AlertCircle className="w-4 h-4 text-red-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-red-700 mt-1.5">
                                {metrics.overdue}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Verified past due</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-amber-700">
                                <span>Due Soon</span>
                                <Clock className="w-4 h-4 text-amber-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-amber-700 mt-1.5">
                                {metrics.due_soon + metrics.due_today}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Within 14 days</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-sky-700">
                                <span>Upcoming</span>
                                <CalendarIcon className="w-4 h-4 text-sky-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1.5">
                                {metrics.upcoming}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Scheduled ahead</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                                <span>Completed</span>
                                <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-emerald-700 mt-1.5">
                                {metrics.completed}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">
                                {metrics.officially_acknowledged} Acknowledged • {metrics.submitted_internal} Declared
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-purple-700">
                                <span>Unverified Hold</span>
                                <ShieldAlert className="w-4 h-4 text-purple-600" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-purple-800 mt-1.5">
                                {metrics.deadline_unverified}
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">Never marked overdue</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter Toolbar & View Mode Switcher */}
                <Card>
                    <CardContent className="p-4 space-y-4">
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                            {/* View Switcher Tabs */}
                            <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg self-start">
                                <button
                                    type="button"
                                    onClick={() => setViewMode('calendar')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                        viewMode === 'calendar'
                                            ? 'bg-white text-[#14263D] shadow-2xs'
                                            : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    <CalendarIcon className="w-3.5 h-3.5" />
                                    <span>Monthly Calendar</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setViewMode('list')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                        viewMode === 'list'
                                            ? 'bg-white text-[#14263D] shadow-2xs'
                                            : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    <List className="w-3.5 h-3.5" />
                                    <span>All Filings List ({occurrences.length})</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setViewMode('upcoming')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                        viewMode === 'upcoming'
                                            ? 'bg-white text-[#14263D] shadow-2xs'
                                            : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    <Clock className="w-3.5 h-3.5" />
                                    <span>Upcoming Deadlines ({upcomingOccurrences.length})</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setViewMode('overdue')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                        viewMode === 'overdue'
                                            ? 'bg-white text-red-700 shadow-2xs'
                                            : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    <AlertTriangle className="w-3.5 h-3.5 text-red-600" />
                                    <span>Overdue ({overdueOccurrences.length})</span>
                                </button>
                            </div>

                            {/* Month Selector */}
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => navigateMonth(-1)}
                                    className="p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
                                    aria-label="Previous Month"
                                >
                                    <ChevronLeft className="w-4 h-4" />
                                </button>
                                <input
                                    type="month"
                                    value={selectedMonth}
                                    onChange={(e) => setSelectedMonth(e.target.value)}
                                    aria-label="Select Month"
                                    className="px-3 py-1.5 text-xs font-semibold border border-slate-300 rounded-lg bg-white text-slate-900 focus:ring-2 focus:ring-[#19766F]"
                                />
                                <button
                                    type="button"
                                    onClick={() => navigateMonth(1)}
                                    className="p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
                                    aria-label="Next Month"
                                >
                                    <ChevronRight className="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        {/* Multi-Dimensional Filter Controls */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 pt-2 border-t border-slate-100">
                            <div className="relative">
                                <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="Search period, citation, ref..."
                                    className="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-300 rounded-lg focus:ring-1 focus:ring-[#19766F]"
                                />
                            </div>

                            <select
                                value={selectedAuthority}
                                onChange={(e) => setSelectedAuthority(e.target.value)}
                                aria-label="Filter by Authority"
                                className="py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white text-slate-700"
                            >
                                <option value="">All Authorities</option>
                                {authorities.map((a) => (
                                    <option key={a.id} value={a.code}>
                                        {a.code} — {a.name}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={selectedObligation}
                                onChange={(e) => setSelectedObligation(e.target.value)}
                                aria-label="Filter by Obligation"
                                className="py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white text-slate-700"
                            >
                                <option value="">All Obligations</option>
                                {obligations.map((ob) => (
                                    <option key={ob.id} value={ob.id}>
                                        {ob.code} — {ob.title}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={selectedUser}
                                onChange={(e) => setSelectedUser(e.target.value)}
                                aria-label="Filter by Responsible User"
                                className="py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white text-slate-700"
                            >
                                <option value="">All Responsible Owners</option>
                                <option value="unassigned">Unassigned Only</option>
                                {members.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({m.role})
                                    </option>
                                ))}
                            </select>

                            <select
                                value={selectedWorkflowStatus}
                                onChange={(e) => setSelectedWorkflowStatus(e.target.value)}
                                aria-label="Filter by Filing Status"
                                className="py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white text-slate-700"
                            >
                                <option value="">All Filing Statuses</option>
                                <option value="NOT_STARTED">Not Started</option>
                                <option value="PREPARING">Preparing Return</option>
                                <option value="READY_FOR_REVIEW">Ready for Review</option>
                                <option value="INTERNALLY_APPROVED">Internally Approved</option>
                                <option value="SUBMITTED">Submitted (Declared)</option>
                                <option value="ACKNOWLEDGED">Acknowledged (Official)</option>
                                <option value="REJECTED">Rejected</option>
                                <option value="CLOSED">Closed</option>
                            </select>

                            <div className="flex items-center gap-1.5">
                                <Button variant="primary" size="sm" onClick={() => applyFilters()} className="flex-1">
                                    Filter
                                </Button>
                                <Button variant="outline" size="sm" onClick={clearFilters}>
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* VIEW 1: MONTHLY CALENDAR VIEW */}
                {viewMode === 'calendar' && (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-4">
                                <div>
                                    <CardTitle className="text-base font-bold text-slate-900">
                                        {formattedMonthHeader} — Statutory Deadlines
                                    </CardTitle>
                                    <CardDescription className="text-xs text-slate-500">
                                        Click any filing occurrence badge to inspect the legal basis, period calculation, evidence, and workflow.
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2 text-[11px]">
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200">
                                        <span className="w-1.5 h-1.5 rounded-full bg-red-600" /> Overdue / Due Today
                                    </span>
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
                                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500" /> Due Soon
                                    </span>
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-600" /> Completed
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                {/* Weekday Headers */}
                                <div className="grid grid-cols-7 bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500 text-center">
                                    <div className="py-2.5">Mon</div>
                                    <div className="py-2.5">Tue</div>
                                    <div className="py-2.5">Wed</div>
                                    <div className="py-2.5">Thu</div>
                                    <div className="py-2.5">Fri</div>
                                    <div className="py-2.5">Sat</div>
                                    <div className="py-2.5">Sun</div>
                                </div>

                                {/* Calendar Days Grid */}
                                <div className="grid grid-cols-1 sm:grid-cols-7 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 border-b border-slate-200">
                                    {calendarGridDays.map((cell, idx) => (
                                        <div
                                            key={idx}
                                            className={`min-h-28 p-2 border-b border-slate-100 flex flex-col justify-between ${
                                                cell.dateStr === null
                                                    ? 'bg-slate-50/50 hidden sm:flex'
                                                    : cell.isToday
                                                    ? 'bg-teal-50/40'
                                                    : 'bg-white'
                                            }`}
                                        >
                                            {cell.dayNumber !== null && (
                                                <>
                                                    <div className="flex items-center justify-between mb-1.5">
                                                        <span
                                                            className={`text-xs font-bold ${
                                                                cell.isToday
                                                                    ? 'bg-[#19766F] text-white px-2 py-0.5 rounded-full'
                                                                    : 'text-slate-700'
                                                            }`}
                                                        >
                                                            {cell.dayNumber}
                                                        </span>
                                                        {cell.items.length > 0 && (
                                                            <span className="text-[10px] font-semibold text-slate-400">
                                                                {cell.items.length} {cell.items.length === 1 ? 'filing' : 'filings'}
                                                            </span>
                                                        )}
                                                    </div>

                                                    <div className="space-y-1.5 flex-1">
                                                        {cell.items.map((occ) => {
                                                            const cls = occ.display_classification.classification;
                                                            const pillStyles =
                                                                cls === 'OVERDUE' || cls === 'DUE_TODAY'
                                                                    ? 'bg-red-50 hover:bg-red-100 border-red-200 text-red-900'
                                                                    : cls === 'DUE_SOON'
                                                                    ? 'bg-amber-50 hover:bg-amber-100 border-amber-200 text-amber-900'
                                                                    : cls === 'COMPLETED'
                                                                    ? 'bg-emerald-50 hover:bg-emerald-100 border-emerald-200 text-emerald-900'
                                                                    : 'bg-sky-50 hover:bg-sky-100 border-sky-200 text-sky-900';

                                                            return (
                                                                <button
                                                                    key={occ.id}
                                                                    type="button"
                                                                    onClick={() => handleOpenFiling(occ)}
                                                                    className={`w-full text-left p-1.5 rounded-md border text-[11px] transition-colors ${pillStyles}`}
                                                                >
                                                                    <div className="font-bold truncate">
                                                                        {occ.authority.code}: {occ.title}
                                                                    </div>
                                                                    <div className="text-[10px] opacity-80 truncate">
                                                                        Period: {occ.filing_period.label} • {occ.workflow_status_label}
                                                                    </div>
                                                                </button>
                                                            );
                                                        })}

                                                        {renewal_events
                                                            .filter((re) => re.date === cell.dateStr)
                                                            .map((re) => (
                                                                <Link
                                                                    key={re.event_id}
                                                                    href="/renew"
                                                                    className={`block w-full text-left p-1.5 rounded-md border text-[11px] transition-colors ${
                                                                        re.event_type === 'INSTRUMENT_EXPIRY_DATE'
                                                                            ? 'bg-indigo-50 hover:bg-indigo-100 border-indigo-200 text-indigo-950'
                                                                            : 'bg-teal-50 hover:bg-teal-100 border-teal-200 text-teal-950'
                                                                    }`}
                                                                >
                                                                    <div className="font-bold truncate">
                                                                        {re.event_type === 'INSTRUMENT_EXPIRY_DATE' ? 'Expiry: ' : 'Renewal Due: '}
                                                                        {re.title}
                                                                    </div>
                                                                    <div className="text-[10px] opacity-80 truncate">
                                                                        {re.event_type_label} • {re.reference_or_period}
                                                                    </div>
                                                                </Link>
                                                            ))}
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Integrated Licence & Certificate Renewal Deadlines */}
                        {renewal_events.length > 0 && (
                            <Card>
                                <CardHeader className="border-b border-indigo-100 bg-indigo-50/40">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <RefreshCw className="w-4 h-4 text-indigo-700" />
                                            <CardTitle className="text-sm font-bold text-indigo-950">
                                                Licence, Certificate & Registration Renewal Events ({renewal_events.length})
                                            </CardTitle>
                                        </div>
                                        <Link
                                            href="/renew"
                                            className="text-xs font-bold text-[#19766F] hover:underline flex items-center gap-1"
                                        >
                                            <span>Open Renewal Workspace</span>
                                            <ExternalLink className="w-3.5 h-3.5" />
                                        </Link>
                                    </div>
                                    <CardDescription className="text-xs text-indigo-800">
                                        Distinguishes established Instrument Expiry Dates from verified Statutory Renewal Submission Deadlines.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {renewal_events.map((re) => (
                                            <Link
                                                key={re.event_id}
                                                href="/renew"
                                                className="p-3 rounded-xl border border-indigo-200 bg-white hover:border-[#19766F] transition-colors space-y-1"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <Badge
                                                        variant={re.event_type === 'INSTRUMENT_EXPIRY_DATE' ? 'warning' : 'teal'}
                                                        size="sm"
                                                    >
                                                        {re.event_type_label}
                                                    </Badge>
                                                    <span className="text-xs font-bold text-slate-900">{re.date}</span>
                                                </div>
                                                <div className="text-xs font-bold text-slate-900 truncate">{re.title}</div>
                                                <div className="text-[11px] text-slate-500">
                                                    {re.authority_code} • Ref: {re.reference_or_period}
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Unverified Deadlines Hold Section */}
                        {unverifiedOccurrences.length > 0 && (
                            <Card>
                                <CardHeader className="border-b border-purple-100 bg-purple-50/50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <ShieldAlert className="w-4 h-4 text-purple-700" />
                                            <CardTitle className="text-sm font-bold text-purple-950">
                                                Filings Held — Statutory Due Date Unverified ({unverifiedOccurrences.length})
                                            </CardTitle>
                                        </div>
                                        <Badge variant="purple" size="sm">
                                            DEADLINE_UNVERIFIED / UNVERIFIED_HOLD
                                        </Badge>
                                    </div>
                                    <CardDescription className="text-xs text-purple-800">
                                        Rule-governance safeguard: These filing occurrences lack an officially verified due-date schedule rule and are never fabricated or marked overdue.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {unverifiedOccurrences.slice(0, 6).map((occ) => (
                                            <button
                                                key={occ.id}
                                                type="button"
                                                onClick={() => handleOpenFiling(occ)}
                                                className="text-left p-3 rounded-xl border border-purple-200 bg-white hover:border-purple-400 transition-colors space-y-1.5"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-xs font-bold text-slate-900 truncate">
                                                        {occ.title}
                                                    </span>
                                                    <Badge variant="purple" size="sm">
                                                        Unverified Date
                                                    </Badge>
                                                </div>
                                                <div className="text-[11px] text-slate-600">
                                                    Authority: {occ.authority.code} • Period: {occ.filing_period.label}
                                                </div>
                                                <div className="text-[10px] text-purple-700 truncate">
                                                    {occ.deadline_calculation_basis.explanation || 'Awaiting verified schedule rule approval.'}
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* VIEW 2, 3, 4: LIST / UPCOMING / OVERDUE TABLE VIEW */}
                {(viewMode === 'list' || viewMode === 'upcoming' || viewMode === 'overdue') && (
                    <Card>
                        <CardHeader className="border-b border-slate-200">
                            <CardTitle className="text-base font-bold text-slate-900">
                                {viewMode === 'overdue'
                                    ? 'Overdue Statutory Filing Occurrences'
                                    : viewMode === 'upcoming'
                                    ? 'Upcoming Statutory Deadlines'
                                    : 'All Statutory Filing Occurrences'}
                            </CardTitle>
                            <CardDescription className="text-xs text-slate-500">
                                Click any row to open the filing detail drawer, inspect the statutory deadline calculation, assign owners, and link period-specific evidence.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {(() => {
                                const rows =
                                    viewMode === 'overdue'
                                        ? overdueOccurrences
                                        : viewMode === 'upcoming'
                                        ? upcomingOccurrences
                                        : occurrences;

                                if (rows.length === 0) {
                                    return (
                                        <div className="p-10 text-center space-y-2">
                                            <CalendarIcon className="w-8 h-8 text-slate-300 mx-auto" />
                                            <p className="text-sm font-semibold text-slate-700">
                                                No filing occurrences match the selected criteria
                                            </p>
                                            <p className="text-xs text-slate-500 max-w-md mx-auto">
                                                Complete the registration discovery questionnaire or click "Generate 12-Month Schedule" to populate recurring statutory filing periods.
                                            </p>
                                        </div>
                                    );
                                }

                                return (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-xs">
                                            <thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                                <tr>
                                                    <th className="py-3 px-4">Filing Obligation</th>
                                                    <th className="py-3 px-4">Authority</th>
                                                    <th className="py-3 px-4">Filing Period</th>
                                                    <th className="py-3 px-4">Statutory Due Date</th>
                                                    <th className="py-3 px-4">Deadline Classification</th>
                                                    <th className="py-3 px-4">Internal Workflow</th>
                                                    <th className="py-3 px-4">Official Status</th>
                                                    <th className="py-3 px-4">Assigned Owner</th>
                                                    <th className="py-3 px-4 text-right">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {rows.map((occ) => (
                                                    <tr
                                                        key={occ.id}
                                                        onClick={() => handleOpenFiling(occ)}
                                                        className="hover:bg-slate-50/90 cursor-pointer transition-colors"
                                                    >
                                                        <td className="py-3 px-4">
                                                            <div className="font-bold text-slate-900">{occ.title}</div>
                                                            <div className="text-[11px] text-slate-500 font-mono">
                                                                {occ.obligation_code} • {occ.filing_frequency}
                                                            </div>
                                                        </td>
                                                        <td className="py-3 px-4">
                                                            <Badge variant="slate" size="sm">
                                                                {occ.authority.code}
                                                            </Badge>
                                                        </td>
                                                        <td className="py-3 px-4 whitespace-nowrap">
                                                            <div className="font-semibold text-slate-900">
                                                                {occ.filing_period.label}
                                                            </div>
                                                            <div className="text-[10px] text-slate-500">
                                                                {occ.filing_period.start} → {occ.filing_period.end}
                                                            </div>
                                                        </td>
                                                        <td className="py-3 px-4 whitespace-nowrap">
                                                            {occ.due_dates.is_unverified ? (
                                                                <span className="text-purple-700 font-semibold">
                                                                    Unverified (Hold)
                                                                </span>
                                                            ) : (
                                                                <div>
                                                                    <div className="font-bold text-slate-900">
                                                                        {occ.due_dates.current_due_date}
                                                                    </div>
                                                                    {occ.due_dates.payment_due_date &&
                                                                        occ.due_dates.payment_due_date !==
                                                                            occ.due_dates.current_due_date && (
                                                                            <div className="text-[10px] text-slate-500">
                                                                                Payment: {occ.due_dates.payment_due_date}
                                                                            </div>
                                                                        )}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="py-3 px-4 whitespace-nowrap">
                                                            {getClassificationBadge(
                                                                occ.display_classification.classification,
                                                                occ.display_classification.label
                                                            )}
                                                        </td>
                                                        <td className="py-3 px-4 whitespace-nowrap">
                                                            {getWorkflowBadge(occ.workflow_status, occ.workflow_status_label)}
                                                        </td>
                                                        <td className="py-3 px-4 whitespace-nowrap">
                                                            <Badge
                                                                variant={
                                                                    occ.official_submission_status === 'OFFICIALLY_ACKNOWLEDGED'
                                                                        ? 'success'
                                                                        : occ.official_submission_status === 'DECLARED_SUBMITTED'
                                                                        ? 'warning'
                                                                        : occ.official_submission_status === 'OFFICIALLY_REJECTED'
                                                                        ? 'danger'
                                                                        : 'neutral'
                                                                }
                                                                size="sm"
                                                            >
                                                                {occ.official_submission_status_label}
                                                            </Badge>
                                                        </td>
                                                        <td className="py-3 px-4 whitespace-nowrap">
                                                            {occ.responsible_user ? (
                                                                <span className="text-xs font-medium text-slate-800">
                                                                    {occ.responsible_user.name}
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
                                                                    handleOpenFiling(occ);
                                                                }}
                                                            >
                                                                Inspect Filing
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                );
                            })()}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* CLICKABLE FILING DETAIL DRAWER */}
            {drawerOpen && activeOccurrence && (
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
                                            {activeOccurrence.authority.code}
                                        </Badge>
                                        <span className="text-xs font-mono text-teal-200">
                                            Period: {activeOccurrence.filing_period.label} ({activeOccurrence.filing_period.start} to{' '}
                                            {activeOccurrence.filing_period.end})
                                        </span>
                                    </div>
                                    <h2 className="text-lg font-heading font-bold text-white leading-snug">
                                        {activeOccurrence.title}
                                    </h2>
                                    <div className="flex items-center gap-2 pt-1 flex-wrap">
                                        {getClassificationBadge(
                                            activeOccurrence.display_classification.classification,
                                            activeOccurrence.display_classification.label
                                        )}
                                        {getWorkflowBadge(
                                            activeOccurrence.workflow_status,
                                            activeOccurrence.workflow_status_label
                                        )}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleCloseDrawer}
                                    className="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800"
                                    aria-label="Close drawer"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>

                            {/* Drawer Body */}
                            <div className="flex-1 overflow-y-auto p-5 space-y-5 bg-slate-50/60">
                                {/* Dual-Control & Official Acceptance Safeguard Banner */}
                                <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-950 text-xs flex items-start gap-2.5">
                                    <Info className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                                    <div className="space-y-1">
                                        <div className="font-bold">
                                            Statutory Filing Governance Safeguard
                                        </div>
                                        <p className="text-[11px] text-amber-800 leading-relaxed">
                                            Entering a submission reference records an <strong>Internal Submission Declaration</strong> only.
                                            Official regulatory acceptance (<strong>ACKNOWLEDGED</strong>) requires a human-verified supporting receipt linked to this specific filing period.
                                        </p>
                                    </div>
                                </div>

                                {/* 1. Legal Basis, Filing Period & Deterministic Due-Date Calculation */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <Scale className="w-4 h-4 text-[#19766F]" />
                                        Statutory Legal Basis & Deadline Calculation
                                    </h3>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="text-[10px] font-semibold uppercase text-slate-400">
                                                Filing Period
                                            </div>
                                            <div className="font-bold text-slate-900 mt-0.5">
                                                {activeOccurrence.filing_period.label}
                                            </div>
                                            <div className="text-[11px] text-slate-500">
                                                {activeOccurrence.filing_period.start} to {activeOccurrence.filing_period.end}
                                            </div>
                                        </div>

                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="text-[10px] font-semibold uppercase text-slate-400">
                                                Statutory Due Date ({activeOccurrence.due_dates.timezone})
                                            </div>
                                            {activeOccurrence.due_dates.is_unverified ? (
                                                <div className="font-bold text-purple-700 mt-0.5">
                                                    Unverified Hold (No Invented Date)
                                                </div>
                                            ) : (
                                                <>
                                                    <div className="font-bold text-slate-900 mt-0.5">
                                                        {activeOccurrence.due_dates.current_due_date}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500">
                                                        Original Statutory: {activeOccurrence.due_dates.original_statutory_due_date}
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    <div className="text-xs space-y-1.5 pt-1 text-slate-600">
                                        <div>
                                            <span className="font-semibold text-slate-800">Source Citation: </span>
                                            <span>{activeOccurrence.source_governance.source_citation || 'Pending Official Citation'}</span>
                                        </div>
                                        <div>
                                            <span className="font-semibold text-slate-800">Rule Version & Governance: </span>
                                            <span className="font-mono">
                                                {activeOccurrence.source_governance.rule_version_code || 'UNVERIFIED'}
                                            </span>
                                            {activeOccurrence.source_governance.effective_from && (
                                                <span className="text-slate-400 text-[11px] ml-1.5">
                                                    (Effective from {activeOccurrence.source_governance.effective_from})
                                                </span>
                                            )}
                                            <span className="ml-2">
                                                <Badge
                                                    variant={activeOccurrence.source_governance.is_verified ? 'success' : 'purple'}
                                                    size="sm"
                                                >
                                                    {activeOccurrence.source_governance.verification_status}
                                                </Badge>
                                            </span>
                                        </div>
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px] text-slate-700">
                                            <span className="font-semibold text-slate-900 block mb-0.5">
                                                Deadline Calculation Explanation:
                                            </span>
                                            {activeOccurrence.deadline_calculation_basis.explanation ||
                                                'Calculated deterministically from approved schedule parameters.'}
                                            {activeOccurrence.deadline_calculation_basis.payment_explanation && (
                                                <span className="block mt-1 text-teal-800 font-medium">
                                                    {activeOccurrence.deadline_calculation_basis.payment_explanation}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* 2. Responsible Owner Assignment */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <UserCheck className="w-4 h-4 text-[#19766F]" />
                                            Filing Responsibility Assignment
                                        </h3>
                                        {activeOccurrence.responsible_user ? (
                                            <Badge variant="teal" size="sm">
                                                Assigned: {activeOccurrence.responsible_user.name}
                                            </Badge>
                                        ) : (
                                            <Badge variant="neutral" size="sm">
                                                Unassigned
                                            </Badge>
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
                                                        aria-label="Assign to Organisation Member"
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white"
                                                    >
                                                        <option value="">-- Unassign Responsibility --</option>
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
                                                        value={assignmentNotes}
                                                        onChange={(e) => setAssignmentNotes(e.target.value)}
                                                        placeholder="Instructions for this filing period..."
                                                        className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                                    />
                                                </div>
                                            </div>
                                            <div className="flex justify-end">
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={isAssigning}
                                                >
                                                    {isAssigning ? 'Saving...' : 'Assign Filing Owner'}
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </div>

                                {/* 3. Internal Filing Progress & Official Submission Status */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between flex-wrap gap-2">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <RefreshCw className="w-4 h-4 text-[#19766F]" />
                                            Internal Progress & Submission Lifecycle
                                        </h3>
                                        <Badge
                                            variant={
                                                activeOccurrence.official_submission_status === 'OFFICIALLY_ACKNOWLEDGED'
                                                    ? 'success'
                                                    : activeOccurrence.official_submission_status === 'DECLARED_SUBMITTED'
                                                    ? 'warning'
                                                    : 'neutral'
                                            }
                                            size="sm"
                                        >
                                            Official: {activeOccurrence.official_submission_status_label}
                                        </Badge>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        <div>
                                            <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                Submission Reference (Internal Declaration):
                                            </label>
                                            <input
                                                type="text"
                                                value={submissionReference}
                                                onChange={(e) => setSubmissionReference(e.target.value)}
                                                placeholder="e.g. TARMS-SUB-2026-11"
                                                className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                Official Receipt Number (If Acknowledged):
                                            </label>
                                            <input
                                                type="text"
                                                value={officialReceiptNumber}
                                                onChange={(e) => setOfficialReceiptNumber(e.target.value)}
                                                placeholder="e.g. ZIMRA-REC-99481"
                                                className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                            Transition Notes / Justification:
                                        </label>
                                        <textarea
                                            value={transitionNotes}
                                            onChange={(e) => setTransitionNotes(e.target.value)}
                                            rows={2}
                                            placeholder="Enter progress notes, submission details, or rejection reason..."
                                            className="w-full p-2 text-xs border border-slate-300 rounded-lg"
                                        />
                                    </div>

                                    <div className="space-y-2 pt-1">
                                        <p className="text-[11px] font-medium text-slate-500">
                                            Available Workflow Transitions:
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
                                                        variant={opt.status === 'ACKNOWLEDGED' ? 'primary' : 'outline'}
                                                        size="sm"
                                                        disabled={!opt.allowed || isTransitioning}
                                                        onClick={() => handleTransitionStatus(opt.status)}
                                                    >
                                                        Move to {opt.label}
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* 4. Period-Specific Supporting Evidence */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                            <FolderLock className="w-4 h-4 text-[#19766F]" />
                                            Period-Specific Evidence ({activeOccurrence.filing_period.label})
                                        </h3>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="slate" size="sm">
                                                State: {activeOccurrence.evidence_status}
                                            </Badge>
                                            <Link
                                                href="/documents"
                                                className="text-[11px] font-semibold text-[#19766F] hover:underline flex items-center gap-1"
                                            >
                                                Vault <ExternalLink className="w-3 h-3" />
                                            </Link>
                                        </div>
                                    </div>

                                    {linkedDocuments.length === 0 ? (
                                        <div className="p-3.5 bg-slate-50 border border-dashed border-slate-200 rounded-lg text-center space-y-1">
                                            <p className="text-xs font-medium text-slate-600">
                                                No evidence linked to {activeOccurrence.filing_period.label}
                                            </p>
                                            <p className="text-[11px] text-slate-400">
                                                Required: {activeOccurrence.evidence_summary.required_documents.join(', ') || 'Official Return / Payment Receipt'}
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {linkedDocuments.map((doc) => (
                                                <div
                                                    key={doc.id}
                                                    className="p-3 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between gap-3 text-xs"
                                                >
                                                    <div className="space-y-0.5">
                                                        <p className="font-semibold text-slate-800">{doc.file_name}</p>
                                                        <p className="text-[11px] text-slate-500">
                                                            Type: {doc.document_type} • Uploaded by {doc.uploader_name || 'Staff'}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2 shrink-0">
                                                        {doc.is_verified ? (
                                                            <Badge variant="success" size="sm">
                                                                Human Verified
                                                            </Badge>
                                                        ) : doc.is_pending ? (
                                                            <Badge variant="purple" size="sm">
                                                                Uploaded (Pending Review)
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="danger" size="sm">
                                                                {doc.verification_status}
                                                            </Badge>
                                                        )}
                                                        <button
                                                            type="button"
                                                            onClick={() => handleUnlinkEvidence(doc.id)}
                                                            className="p-1 text-slate-400 hover:text-red-600"
                                                            title="Unlink from this period"
                                                        >
                                                            <Unlink className="w-3.5 h-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Link Existing Vault Document to this Period */}
                                    {availableVaultDocs.length > 0 && (
                                        <form onSubmit={handleLinkEvidence} className="pt-2 border-t border-slate-100 flex items-center gap-2">
                                            <select
                                                value={selectedVaultDocId}
                                                onChange={(e) => setSelectedVaultDocId(e.target.value)}
                                                aria-label="Link Vault Document"
                                                className="flex-1 py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg bg-white"
                                            >
                                                <option value="">-- Select Vault Document to Link to {activeOccurrence.filing_period.label} --</option>
                                                {availableVaultDocs.map((vd) => (
                                                    <option key={vd.id} value={vd.id}>
                                                        {vd.file_name} ({vd.document_type} • {vd.verification_status})
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={!selectedVaultDocId || isLinkingDoc}
                                                icon={<Link2 className="w-3.5 h-3.5" />}
                                            >
                                                Link to Period
                                            </Button>
                                        </form>
                                    )}
                                </div>

                                {/* 5. Immutable Filing Audit History */}
                                <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <Clock className="w-4 h-4 text-[#19766F]" />
                                        Filing Occurrence Audit History
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
                                                        {log.to_status && (
                                                            <span className="text-[11px] text-[#19766F] font-semibold">
                                                                {log.from_status ? `${log.from_status} → ` : ''}
                                                                {log.to_status}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {log.notes && (
                                                        <p className="text-slate-600 text-[11px] italic">
                                                            "{log.notes}"
                                                        </p>
                                                    )}
                                                    {log.created_at && (
                                                        <p className="text-[10px] text-slate-400">
                                                            {new Date(log.created_at).toLocaleString()}
                                                        </p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Drawer Footer */}
                            <div className="p-4 border-t border-slate-200 bg-white flex items-center justify-between">
                                <span className="text-[11px] text-slate-500 font-mono">
                                    Occurrence Lock #{activeOccurrence.lock_version}
                                </span>
                                <Button variant="outline" size="sm" onClick={handleCloseDrawer}>
                                    Close Filing Panel
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

