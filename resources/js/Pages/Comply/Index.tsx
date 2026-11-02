import React, { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    ShieldCheck,
    AlertCircle,
    CheckCircle2,
    Clock,
    Search,
    Filter,
    X,
    UserCheck,
    UserX,
    FileText,
    ArrowRight,
    ExternalLink,
    AlertTriangle,
    Building2,
    Calendar,
    ChevronRight,
    RotateCcw,
    FileCheck2,
    Lock,
    Send,
    Eye,
    ShieldAlert,
    Ban,
    FileQuestion,
    FolderLock,
    RefreshCw,
    Info,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge, BadgeVariant } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { PageProps } from '@/types';

interface Authority {
    id: string;
    code: string;
    name: string;
}

interface CategoryOption {
    code: string;
    name: string;
}

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface LinkedDocument {
    id: string;
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

interface ActivityLogItem {
    id: string;
    activity_type: string;
    from_status?: string | null;
    to_status?: string | null;
    actor?: { id: string; name: string } | null;
    from_user?: { id: string; name: string } | null;
    to_user?: { id: string; name: string } | null;
    notes?: string | null;
    created_at?: string;
}

interface TransitionOption {
    status: string;
    label: string;
    allowed: boolean;
    reason: string | null;
}

interface ObligationItem {
    id: string;
    name: string;
    code: string;
    authority: {
        id: string;
        code: string;
        name: string;
    };
    category: string;
    obligation_type: string;
    applicability_decision: {
        decision: string;
        is_unverified_hold: boolean;
        is_requires_info: boolean;
        trigger_reasons: string[];
    };
    approved_source: {
        citation: string;
        rule_version_id?: string;
        rule_version_number: string;
        is_verified: boolean;
        effective_from?: string;
    };
    compliance_frequency: string;
    responsible_user?: {
        id: string;
        name: string;
        email: string;
    } | null;
    lifecycle_status: string;
    lifecycle_status_label: string;
    evidence_status: string;
    evidence_summary: {
        total_linked: number;
        verified: number;
        pending: number;
        rejected: number;
        expired: number;
        requires_attention: boolean;
    };
    evidence_requirements: string[];
    relevant_dates: {
        next_due_date?: string | null;
        last_reviewed_at?: string | null;
        declared_registered_at?: string | null;
        last_assessment_date?: string | null;
    };
    outstanding_actions: string;
    declared_registration_number?: string | null;
    internal_notes?: string | null;
    lock_version: number;
    created_at?: string;
    updated_at?: string;
}

interface ComplyIndexProps {
    obligations: ObligationItem[];
    metrics: {
        total: number;
        not_started: number;
        in_progress: number;
        awaiting_evidence: number;
        under_review: number;
        completed_internal: number;
        verified_complete: number;
        blocked: number;
        not_applicable: number;
        overdue: number;
    };
    members: Member[];
    filters: {
        authority?: string;
        category?: string;
        lifecycle_status?: string;
        responsible_user?: string;
        search?: string;
    };
    authorities: Authority[];
    categories: CategoryOption[];
    can_manage: boolean;
}

export default function ComplyIndex({
    obligations = [],
    metrics = {
        total: 0,
        not_started: 0,
        in_progress: 0,
        awaiting_evidence: 0,
        under_review: 0,
        completed_internal: 0,
        verified_complete: 0,
        blocked: 0,
        not_applicable: 0,
        overdue: 0,
    },
    members = [],
    filters = {},
    authorities = [],
    categories = [],
    can_manage = false,
}: ComplyIndexProps) {
    const { errors, flash } = usePage<PageProps>().props;

    // Filter controls state
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedAuthority, setSelectedAuthority] = useState(filters.authority || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.lifecycle_status || '');
    const [selectedUser, setSelectedUser] = useState(filters.responsible_user || '');

    // Drawer / Detail View state
    const [activeObligation, setActiveObligation] = useState<ObligationItem | null>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [drawerLoading, setDrawerLoading] = useState(false);
    const [obligationDocuments, setObligationDocuments] = useState<LinkedDocument[]>([]);
    const [obligationTransitions, setObligationTransitions] = useState<Record<string, TransitionOption>>({});
    const [obligationTimeline, setObligationTimeline] = useState<ActivityLogItem[]>([]);

    // Action forms state inside drawer
    const [assigneeId, setAssigneeId] = useState('');
    const [assignmentNotes, setAssignmentNotes] = useState('');
    const [isAssigning, setIsAssigning] = useState(false);

    const [selectedTransitionStatus, setSelectedTransitionStatus] = useState('');
    const [transitionNotes, setTransitionNotes] = useState('');
    const [isTransitioning, setIsTransitioning] = useState(false);

    // Apply filters
    const applyFilters = () => {
        router.get(
            '/comply',
            {
                search: searchQuery || undefined,
                authority: selectedAuthority || undefined,
                category: selectedCategory || undefined,
                lifecycle_status: selectedStatus || undefined,
                responsible_user: selectedUser || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        setSearchQuery('');
        setSelectedAuthority('');
        setSelectedCategory('');
        setSelectedStatus('');
        setSelectedUser('');
        router.get('/comply', {}, { preserveState: true, replace: true });
    };

    // Open detail drawer and fetch full backend detail
    const handleOpenDetail = async (item: ObligationItem) => {
        setActiveObligation(item);
        setDrawerOpen(true);
        setDrawerLoading(true);
        setAssigneeId(item.responsible_user?.id || '');
        setAssignmentNotes('');
        setSelectedTransitionStatus('');
        setTransitionNotes('');

        try {
            const res = await fetch(`/compliance/obligations/${item.id}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data = await res.json();
                setActiveObligation(data.obligation);
                setObligationDocuments(data.documents || []);
                setObligationTransitions(data.available_transitions || {});
                setObligationTimeline(data.timeline || []);
            }
        } catch (err) {
            console.error('Failed to load obligation detail', err);
        } finally {
            setDrawerLoading(false);
        }
    };

    const handleCloseDrawer = () => {
        setDrawerOpen(false);
        setActiveObligation(null);
    };

    // Close on escape key
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && drawerOpen) {
                handleCloseDrawer();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [drawerOpen]);

    // Submit assignment
    const handleAssign = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeObligation) return;

        setIsAssigning(true);
        router.post(
            `/compliance/obligations/${activeObligation.id}/assign`,
            {
                assigned_to_user_id: assigneeId || null,
                lock_version: activeObligation.lock_version,
                notes: assignmentNotes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAssigning(false);
                    // Refresh detail
                    handleOpenDetail(activeObligation);
                },
                onError: () => {
                    setIsAssigning(false);
                },
            }
        );
    };

    // Submit lifecycle transition
    const handleTransition = (targetStatus: string) => {
        if (!activeObligation) return;

        setIsTransitioning(true);
        router.post(
            `/compliance/obligations/${activeObligation.id}/transition`,
            {
                status: targetStatus,
                lock_version: activeObligation.lock_version,
                notes: transitionNotes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsTransitioning(false);
                    setSelectedTransitionStatus('');
                    setTransitionNotes('');
                    handleOpenDetail(activeObligation);
                },
                onError: () => {
                    setIsTransitioning(false);
                },
            }
        );
    };

    // Status variant mapper
    const getStatusBadge = (status: string, label: string) => {
        switch (status) {
            case 'VERIFIED_COMPLETE':
                return (
                    <Badge variant="success" size="sm" dot>
                        <ShieldCheck className="w-3 h-3 text-emerald-600 inline mr-1" />
                        {label || 'Verified Complete'}
                    </Badge>
                );
            case 'COMPLETED_INTERNAL':
                return (
                    <Badge variant="teal" size="sm" dot>
                        <FileCheck2 className="w-3 h-3 text-[#19766F] inline mr-1" />
                        {label || 'Internal Complete'}
                    </Badge>
                );
            case 'UNDER_REVIEW':
                return (
                    <Badge variant="purple" size="sm" dot>
                        <Eye className="w-3 h-3 text-purple-600 inline mr-1" />
                        {label || 'Under Review'}
                    </Badge>
                );
            case 'AWAITING_EVIDENCE':
                return (
                    <Badge variant="warning" size="sm" dot>
                        <AlertTriangle className="w-3 h-3 text-[#D8A34B] inline mr-1" />
                        {label || 'Awaiting Evidence'}
                    </Badge>
                );
            case 'IN_PROGRESS':
                return (
                    <Badge variant="info" size="sm" dot>
                        <Clock className="w-3 h-3 text-sky-600 inline mr-1" />
                        {label || 'In Progress'}
                    </Badge>
                );
            case 'BLOCKED':
                return (
                    <Badge variant="danger" size="sm" dot>
                        <Ban className="w-3 h-3 text-[#B84B46] inline mr-1" />
                        {label || 'Blocked'}
                    </Badge>
                );
            case 'NOT_APPLICABLE':
                return (
                    <Badge variant="slate" size="sm" dot>
                        {label || 'Not Applicable'}
                    </Badge>
                );
            default:
                return (
                    <Badge variant="neutral" size="sm" dot>
                        {label || 'Not Started'}
                    </Badge>
                );
        }
    };

    return (
        <AppLayout
            title="Compliance Register"
            subtitle="Authoritative statutory obligation tracking, ownership assignment, and dual-control compliance verification"
        >
            <Head title="Compliance Register — ZimComply" />

            <div className="space-y-6">
                {/* Global Error Alerts if any */}
                {errors && Object.keys(errors).length > 0 && (
                    <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-900 text-sm flex items-start gap-3 shadow-xs">
                        <AlertCircle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="font-semibold">Compliance Validation Notice</p>
                            {Object.entries(errors).map(([key, msg]) => (
                                <p key={key} className="text-red-700">
                                    {String(msg)}
                                </p>
                            ))}
                        </div>
                    </div>
                )}

                {/* Section Header & Guidance Banner */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-slate-900">
                                    Ongoing Compliance Obligation Register
                                </h1>
                                <Badge variant="teal" size="sm">
                                    Deterministic Engine v2.6
                                </Badge>
                            </div>
                            <p className="text-xs sm:text-sm text-slate-600 max-w-3xl">
                                Statutory responsibilities mapped from gazetted Acts, Statutory Instruments, and approved rule packs.
                                Internal task completion is explicitly separated from independently verified statutory compliance.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
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
                                href="/registration"
                                icon={<RotateCcw className="w-4 h-4" />}
                            >
                                Reassess Profile
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Metrics Summary Strip */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Total Registered
                        </p>
                        <p className="mt-1 text-2xl font-bold text-slate-900">{metrics.total}</p>
                        <p className="text-[11px] text-slate-400 mt-1">Applicable rules</p>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <p className="text-xs font-semibold text-sky-700 uppercase tracking-wider">
                            In Progress
                        </p>
                        <p className="mt-1 text-2xl font-bold text-sky-900">
                            {metrics.in_progress + metrics.awaiting_evidence}
                        </p>
                        <p className="text-[11px] text-sky-600 mt-1">Active workflows</p>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <p className="text-xs font-semibold text-purple-700 uppercase tracking-wider">
                            Under Review
                        </p>
                        <p className="mt-1 text-2xl font-bold text-purple-900">{metrics.under_review}</p>
                        <p className="text-[11px] text-purple-600 mt-1">Awaiting sign-off</p>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <p className="text-xs font-semibold text-[#19766F] uppercase tracking-wider">
                            Internal Complete
                        </p>
                        <p className="mt-1 text-2xl font-bold text-[#14263D]">
                            {metrics.completed_internal}
                        </p>
                        <p className="text-[11px] text-[#19766F] mt-1">Drafts compiled</p>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <p className="text-xs font-semibold text-emerald-700 uppercase tracking-wider">
                            Verified Complete
                        </p>
                        <p className="mt-1 text-2xl font-bold text-emerald-900">
                            {metrics.verified_complete}
                        </p>
                        <p className="text-[11px] text-emerald-600 mt-1">Evidence confirmed</p>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
                        <p className="text-xs font-semibold text-rose-700 uppercase tracking-wider">
                            Blocked / Overdue
                        </p>
                        <p className="mt-1 text-2xl font-bold text-rose-900">
                            {metrics.blocked + metrics.overdue}
                        </p>
                        <p className="text-[11px] text-rose-600 mt-1">Action required</p>
                    </div>
                </div>

                {/* Filter and Search Toolbar */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        {/* Search Input */}
                        <div className="relative">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                placeholder="Search by name, citation..."
                                className="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:border-[#19766F]"
                            />
                        </div>

                        {/* Authority Select */}
                        <div>
                            <select
                                value={selectedAuthority}
                                onChange={(e) => setSelectedAuthority(e.target.value)}
                                className="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:border-[#19766F] bg-white"
                            >
                                <option value="">All Authorities (ZIMRA, NSSA...)</option>
                                {authorities.map((auth) => (
                                    <option key={auth.id} value={auth.code}>
                                        {auth.code} — {auth.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Category Select */}
                        <div>
                            <select
                                value={selectedCategory}
                                onChange={(e) => setSelectedCategory(e.target.value)}
                                className="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:border-[#19766F] bg-white"
                            >
                                <option value="">All Categories</option>
                                {categories.map((cat) => (
                                    <option key={cat.code} value={cat.code}>
                                        {cat.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Lifecycle Status Select */}
                        <div>
                            <select
                                value={selectedStatus}
                                onChange={(e) => setSelectedStatus(e.target.value)}
                                className="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:border-[#19766F] bg-white"
                            >
                                <option value="">All Lifecycle Statuses</option>
                                <option value="NOT_STARTED">Not Started</option>
                                <option value="IN_PROGRESS">In Progress</option>
                                <option value="AWAITING_EVIDENCE">Awaiting Evidence</option>
                                <option value="UNDER_REVIEW">Under Review</option>
                                <option value="COMPLETED_INTERNAL">Completed (Internal Only)</option>
                                <option value="VERIFIED_COMPLETE">Verified Complete (Official)</option>
                                <option value="BLOCKED">Blocked</option>
                                <option value="NOT_APPLICABLE">Not Applicable / Exempt</option>
                            </select>
                        </div>

                        {/* Responsible User Select */}
                        <div>
                            <select
                                value={selectedUser}
                                onChange={(e) => setSelectedUser(e.target.value)}
                                className="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:border-[#19766F] bg-white"
                            >
                                <option value="">All Owners</option>
                                <option value="unassigned">Unassigned Only</option>
                                {members.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({m.role})
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="flex items-center justify-between pt-1 text-xs">
                        <div className="flex items-center gap-2">
                            <Button variant="primary" size="sm" onClick={applyFilters}>
                                Apply Filters
                            </Button>
                            {(searchQuery || selectedAuthority || selectedCategory || selectedStatus || selectedUser) && (
                                <Button variant="ghost" size="sm" onClick={clearFilters}>
                                    Clear Filters
                                </Button>
                            )}
                        </div>
                        <span className="text-slate-500 font-medium">
                            Showing {obligations.length} {obligations.length === 1 ? 'obligation' : 'obligations'}
                        </span>
                    </div>
                </div>

                {/* Compliance Register Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                    {obligations.length === 0 ? (
                        <div className="p-12 text-center space-y-4">
                            <div className="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                                <FileQuestion className="w-6 h-6" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold text-slate-800">
                                    No statutory obligations match criteria
                                </h3>
                                <p className="text-xs text-slate-500 max-w-md mx-auto">
                                    Try resetting your filters or run the statutory applicability questionnaire to identify your organisation's compliance mandates.
                                </p>
                            </div>
                            <div className="pt-2">
                                <Button variant="outline" size="sm" onClick={clearFilters}>
                                    Reset Filters
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-slate-50/75 border-b border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                                        <th className="py-3.5 px-4">Statutory Obligation</th>
                                        <th className="py-3.5 px-4">Authority & Basis</th>
                                        <th className="py-3.5 px-4">Responsible Owner</th>
                                        <th className="py-3.5 px-4">Lifecycle Status</th>
                                        <th className="py-3.5 px-4">Evidence Status</th>
                                        <th className="py-3.5 px-4">Due Date</th>
                                        <th className="py-3.5 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-xs">
                                    {obligations.map((item) => {
                                        const isUnverifiedHold = item.applicability_decision.is_unverified_hold;

                                        return (
                                            <tr
                                                key={item.id}
                                                onClick={() => handleOpenDetail(item)}
                                                className="hover:bg-slate-50/80 transition-colors cursor-pointer group"
                                            >
                                                {/* Obligation Title & Code */}
                                                <td className="py-3.5 px-4 max-w-xs">
                                                    <div className="font-semibold text-slate-900 group-hover:text-[#19766F] transition-colors">
                                                        {item.name}
                                                    </div>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <span className="font-mono text-[10px] text-slate-500">
                                                            {item.code}
                                                        </span>
                                                        <span className="text-[10px] text-slate-400">•</span>
                                                        <span className="text-[11px] text-slate-500">
                                                            {item.compliance_frequency}
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Authority & Citation */}
                                                <td className="py-3.5 px-4">
                                                    <div className="font-medium text-slate-800">
                                                        {item.authority.code}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 truncate max-w-[220px]" title={item.approved_source.citation}>
                                                        {item.approved_source.citation}
                                                    </div>
                                                    {isUnverifiedHold && (
                                                        <div className="mt-1">
                                                            <Badge variant="warning" size="sm">
                                                                UNVERIFIED_HOLD
                                                            </Badge>
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Responsible Owner */}
                                                <td className="py-3.5 px-4">
                                                    {item.responsible_user ? (
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-6 h-6 rounded-full bg-[#19766F]/10 text-[#19766F] flex items-center justify-center font-bold text-[10px] shrink-0">
                                                                {item.responsible_user.name.charAt(0)}
                                                            </div>
                                                            <div className="truncate max-w-[130px]">
                                                                <p className="font-medium text-slate-800 truncate">
                                                                    {item.responsible_user.name}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-[11px] text-slate-400 italic">
                                                            <UserX className="w-3.5 h-3.5 text-slate-300" />
                                                            Unassigned
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Lifecycle Status */}
                                                <td className="py-3.5 px-4">
                                                    {getStatusBadge(item.lifecycle_status, item.lifecycle_status_label)}
                                                </td>

                                                {/* Evidence Status */}
                                                <td className="py-3.5 px-4">
                                                    {item.evidence_summary.verified > 0 ? (
                                                        <Badge variant="success" size="sm">
                                                            <CheckCircle2 className="w-3 h-3 text-emerald-600 inline mr-1" />
                                                            {item.evidence_summary.verified} Verified
                                                        </Badge>
                                                    ) : item.evidence_summary.pending > 0 ? (
                                                        <Badge variant="purple" size="sm">
                                                            <Eye className="w-3 h-3 text-purple-600 inline mr-1" />
                                                            {item.evidence_summary.pending} Review Pending
                                                        </Badge>
                                                    ) : item.evidence_summary.expired > 0 ? (
                                                        <Badge variant="danger" size="sm">
                                                            <AlertTriangle className="w-3 h-3 text-red-600 inline mr-1" />
                                                            Expired Evidence
                                                        </Badge>
                                                    ) : item.evidence_summary.rejected > 0 ? (
                                                        <Badge variant="danger" size="sm">
                                                            Rejected
                                                        </Badge>
                                                    ) : item.evidence_status === 'USER_DECLARED' ? (
                                                        <Badge variant="neutral" size="sm">
                                                            Declared Only
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-400">None Linked</span>
                                                    )}
                                                </td>

                                                {/* Due Date */}
                                                <td className="py-3.5 px-4 whitespace-nowrap">
                                                    {item.relevant_dates.next_due_date ? (
                                                        <div className="flex items-center gap-1.5 font-medium text-slate-700">
                                                            <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                                            <span>{item.relevant_dates.next_due_date}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-400">Periodic</span>
                                                    )}
                                                </td>

                                                {/* Action */}
                                                <td className="py-3.5 px-4 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleOpenDetail(item);
                                                        }}
                                                    >
                                                        Details
                                                        <ChevronRight className="w-3.5 h-3.5 ml-1 text-slate-400" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Slide-Over Obligation Detail Drawer */}
            {drawerOpen && activeObligation && (
                <div
                    className="fixed inset-0 z-50 overflow-hidden bg-slate-900/50 backdrop-blur-xs flex justify-end transition-opacity"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="obligation-drawer-title"
                    onClick={handleCloseDrawer}
                >
                    <div
                        className="w-full max-w-2xl bg-white h-full shadow-2xl flex flex-col overflow-y-auto overscroll-contain animate-in slide-in-from-right duration-200"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {/* Drawer Header */}
                        <div className="p-5 border-b border-slate-200 bg-slate-50 flex items-start justify-between gap-4 sticky top-0 z-10">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <Badge variant="teal" size="sm">
                                        {activeObligation.authority.code}
                                    </Badge>
                                    <span className="font-mono text-xs text-slate-500 font-semibold">
                                        {activeObligation.code}
                                    </span>
                                    <Badge variant="neutral" size="sm">
                                        {activeObligation.category}
                                    </Badge>
                                </div>
                                <h2
                                    id="obligation-drawer-title"
                                    className="text-lg font-bold text-slate-900 leading-snug"
                                >
                                    {activeObligation.name}
                                </h2>
                                <p className="text-xs text-slate-500 flex items-center gap-2">
                                    <span>{activeObligation.authority.name}</span>
                                    <span>•</span>
                                    <span>Frequency: {activeObligation.compliance_frequency}</span>
                                </p>
                            </div>
                            <button
                                onClick={handleCloseDrawer}
                                className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors cursor-pointer"
                                aria-label="Close detail panel"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Drawer Content */}
                        <div className="p-6 space-y-6 flex-1">
                            {/* Dual-Control Regulatory Status Banner */}
                            <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Current Lifecycle Status
                                    </span>
                                    {getStatusBadge(
                                        activeObligation.lifecycle_status,
                                        activeObligation.lifecycle_status_label
                                    )}
                                </div>

                                <div className="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200/80">
                                    <span className="font-semibold text-slate-800">Next Action: </span>
                                    {activeObligation.outstanding_actions}
                                </div>

                                {/* Statutory Notice */}
                                <div className="flex items-start gap-2.5 text-[11px] text-slate-500 bg-sky-50/50 p-2.5 rounded-lg border border-sky-100">
                                    <Info className="w-4 h-4 text-sky-600 shrink-0 mt-0.5" />
                                    <span>
                                        <strong>Dual-Control Protection:</strong> Internal task completion (
                                        <code className="text-[#19766F] font-semibold">COMPLETED_INTERNAL</code>)
                                        records employee readiness. Statutory completion (
                                        <code className="text-emerald-700 font-semibold">VERIFIED_COMPLETE</code>)
                                        strictly requires verified proof from the document vault or official authority certification.
                                    </span>
                                </div>
                            </div>

                            {/* Responsibility & Ownership Section */}
                            <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <UserCheck className="w-4 h-4 text-[#19766F]" />
                                        Responsible Owner
                                    </h3>
                                    {activeObligation.responsible_user ? (
                                        <span className="text-xs font-medium text-slate-700">
                                            {activeObligation.responsible_user.name} ({activeObligation.responsible_user.email})
                                        </span>
                                    ) : (
                                        <span className="text-xs italic text-slate-400">Unassigned</span>
                                    )}
                                </div>

                                {can_manage && (
                                    <form onSubmit={handleAssign} className="pt-2 border-t border-slate-100 space-y-3">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <div>
                                                <label className="block text-[11px] font-medium text-slate-600 mb-1">
                                                    Assign to Organisation Member:
                                                </label>
                                                <select
                                                    value={assigneeId}
                                                    onChange={(e) => setAssigneeId(e.target.value)}
                                                    className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg focus:ring-1 focus:ring-[#19766F] bg-white"
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
                                                    Assignment Notes / Instructions:
                                                </label>
                                                <input
                                                    type="text"
                                                    value={assignmentNotes}
                                                    onChange={(e) => setAssignmentNotes(e.target.value)}
                                                    placeholder="Optional instructions..."
                                                    className="w-full py-1.5 px-2.5 text-xs border border-slate-300 rounded-lg focus:ring-1 focus:ring-[#19766F]"
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
                                                {isAssigning ? 'Updating...' : 'Update Owner'}
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </div>

                            {/* Lifecycle State Transitions */}
                            <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                    <RefreshCw className="w-4 h-4 text-[#19766F]" />
                                    Lifecycle Transitions & Permissions
                                </h3>

                                <div className="space-y-2">
                                    <label className="block text-[11px] font-medium text-slate-600">
                                        Transition Reason / Legal Justification (Required for Blocked or Exempt):
                                    </label>
                                    <textarea
                                        value={transitionNotes}
                                        onChange={(e) => setTransitionNotes(e.target.value)}
                                        rows={2}
                                        placeholder="Record reason for this status change..."
                                        className="w-full p-2 text-xs border border-slate-300 rounded-lg focus:ring-1 focus:ring-[#19766F]"
                                    />
                                </div>

                                <div className="space-y-2 pt-2">
                                    <p className="text-[11px] font-medium text-slate-500">Available Transitions:</p>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        {Object.entries(obligationTransitions).map(([statusKey, opt]) => (
                                            <div
                                                key={statusKey}
                                                className={`p-3 rounded-lg border text-xs flex flex-col justify-between gap-2 ${
                                                    opt.allowed
                                                        ? 'border-slate-200 bg-white hover:border-[#19766F]'
                                                        : 'border-slate-100 bg-slate-50/70 opacity-60'
                                                }`}
                                            >
                                                <div>
                                                    <span className="font-semibold text-slate-800">{opt.label}</span>
                                                    {!opt.allowed && (
                                                        <p className="text-[10px] text-amber-700 mt-0.5 leading-snug">
                                                            {opt.reason}
                                                        </p>
                                                    )}
                                                </div>
                                                <Button
                                                    variant={opt.status === 'VERIFIED_COMPLETE' ? 'primary' : 'outline'}
                                                    size="sm"
                                                    disabled={!opt.allowed || isTransitioning}
                                                    onClick={() => handleTransition(opt.status)}
                                                >
                                                    Move to {opt.label}
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Evidence & Linked Documents */}
                            <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <FolderLock className="w-4 h-4 text-[#19766F]" />
                                        Linked Statutory Evidence
                                    </h3>
                                    <Link
                                        href="/documents"
                                        className="text-[11px] font-semibold text-[#19766F] hover:underline flex items-center gap-1"
                                    >
                                        Open Document Vault
                                        <ExternalLink className="w-3 h-3" />
                                    </Link>
                                </div>

                                {obligationDocuments.length === 0 ? (
                                    <div className="p-4 bg-slate-50 border border-dashed border-slate-200 rounded-lg text-center space-y-1">
                                        <p className="text-xs text-slate-600 font-medium">
                                            No verified documents linked
                                        </p>
                                        <p className="text-[11px] text-slate-400">
                                            Required documents: {activeObligation.evidence_requirements.join(', ') || 'Statutory filing receipt'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {obligationDocuments.map((doc) => (
                                            <div
                                                key={doc.id}
                                                className="p-3 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between gap-3 text-xs"
                                            >
                                                <div className="space-y-0.5">
                                                    <p className="font-semibold text-slate-800">{doc.file_name}</p>
                                                    <p className="text-[11px] text-slate-500">
                                                        Type: {doc.document_type} • Uploaded by {doc.uploader_name || 'Staff'}
                                                    </p>
                                                    {doc.expiry_date && (
                                                        <p className="text-[10px] text-slate-400">
                                                            Expiry Date: {doc.expiry_date}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="shrink-0">
                                                    {doc.is_verified ? (
                                                        <Badge variant="success" size="sm">
                                                            Verified
                                                        </Badge>
                                                    ) : doc.is_pending ? (
                                                        <Badge variant="purple" size="sm">
                                                            Review Pending
                                                        </Badge>
                                                    ) : doc.is_expired ? (
                                                        <Badge variant="danger" size="sm">
                                                            Expired
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="danger" size="sm">
                                                            Rejected
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Statutory Authority & Governance */}
                            <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                    <Building2 className="w-4 h-4 text-[#19766F]" />
                                    Statutory Basis & Rule Governance
                                </h3>
                                <div className="text-xs space-y-2 text-slate-600">
                                    <div>
                                        <span className="font-semibold text-slate-700">Official Citation: </span>
                                        <span>{activeObligation.approved_source.citation}</span>
                                    </div>
                                    <div>
                                        <span className="font-semibold text-slate-700">Approved Rule Version: </span>
                                        <span className="font-mono">{activeObligation.approved_source.rule_version_number}</span>
                                        {activeObligation.approved_source.effective_from && (
                                            <span className="text-slate-400 text-[11px] ml-2">
                                                (Effective from {activeObligation.approved_source.effective_from})
                                            </span>
                                        )}
                                    </div>
                                    {activeObligation.applicability_decision.trigger_reasons.length > 0 && (
                                        <div>
                                            <span className="font-semibold text-slate-700">Evaluation Rationale: </span>
                                            <ul className="list-disc pl-4 mt-1 space-y-0.5 text-slate-500">
                                                {activeObligation.applicability_decision.trigger_reasons.map((r, i) => (
                                                    <li key={i}>{r}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Activity Log & Audit History */}
                            <div className="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                    <Clock className="w-4 h-4 text-[#19766F]" />
                                    Activity & Transition Audit Trail
                                </h3>

                                {obligationTimeline.length === 0 ? (
                                    <p className="text-xs text-slate-400 italic">No transition activity logged yet.</p>
                                ) : (
                                    <div className="space-y-3 border-l-2 border-slate-200 pl-3">
                                        {obligationTimeline.map((log) => (
                                            <div key={log.id} className="text-xs space-y-0.5">
                                                <div className="flex items-center gap-2 text-slate-700">
                                                    <span className="font-semibold">
                                                        {log.actor?.name || 'System'}
                                                    </span>
                                                    <span className="text-slate-400">•</span>
                                                    <span className="font-mono text-[10px] text-slate-500">
                                                        {log.activity_type}
                                                    </span>
                                                    {log.to_status && (
                                                        <span className="text-[11px] text-[#19766F] font-semibold">
                                                            → {log.to_status}
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
                        <div className="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                            <span className="text-[11px] text-slate-500 font-mono">
                                Version Lock #{activeObligation.lock_version}
                            </span>
                            <Button variant="outline" size="sm" onClick={handleCloseDrawer}>
                                Close Panel
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
