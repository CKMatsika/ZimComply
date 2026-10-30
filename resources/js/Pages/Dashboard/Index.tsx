import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    ShieldCheck,
    AlertCircle,
    Calendar,
    ArrowUpRight,
    FileCheck,
    Clock,
    CheckCircle2,
    Building2,
    Compass,
    Sparkles,
    FileText,
    Award,
    AlertTriangle,
    Coins,
    ChevronRight,
    Lock,
    ExternalLink,
    Briefcase,
    FileSpreadsheet,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

interface DashboardProps {
    metrics: {
        registration_status: string;
        compliance_score: number;
        urgent_deadlines_count: number;
        documents_verified_count: number;
        branches_count: number;
    };
    recent_events: any[];
}

export default function Dashboard({ metrics, recent_events }: DashboardProps) {
    const { auth } = usePage<PageProps>().props;

    // Prioritised Action Queue
    const prioritisedActions = [
        {
            id: 'act-1',
            title: 'Complete Statutory Applicability Questionnaire',
            category: 'DISCOVERY',
            authority: 'Companies Registry & Multi-Agency',
            deadline: 'Required to generate complete roadmap',
            urgency: 'high',
            href: '/registration',
            actionText: 'Launch Discovery Wizard',
            reason: 'Determine mandatory NEC collective agreements, council licences, and ZIMDEF liabilities.',
        },
        {
            id: 'act-2',
            title: 'Verify ZIMRA ITF263 Tax Clearance Prerequisite',
            category: 'TAX_CLEARANCE',
            authority: 'ZIMRA Section 80',
            deadline: '10 Nov 2026',
            urgency: 'high',
            href: '/comply',
            actionText: 'Review ITF263 Blockers',
            reason: 'Avoid mandatory 30% withholding tax on institutional payments and maintain PRAZ eligibility.',
        },
        {
            id: 'act-3',
            title: 'Prepare Annual Municipal Operating Licence Renewal',
            category: 'LOCAL_AUTHORITY',
            authority: 'City of Harare',
            deadline: '31 Dec 2026 (45 days left)',
            urgency: 'medium',
            href: '/renew',
            actionText: 'Prepare Renewal',
            reason: 'Premises operating permit renewal under Urban Councils Act [Chapter 29:15].',
        },
    ];

    // Core Statutory Standings
    const statutoryStandings = [
        {
            authority: 'ZIMRA',
            title: 'Revenue Authority Standing',
            statusText: 'ITF263 Audit Needed',
            statusVariant: 'warning' as const,
            citation: 'Income Tax Act [Chapter 23:06] & VAT Act [Chapter 23:12]',
            details: 'BP Number active. Monthly PAYE returns current. VAT threshold under evaluation.',
            evidenceStatus: 'DOCUMENT_SUPPORTED',
            actionUrl: '/comply',
            actionText: 'View Tax Returns',
        },
        {
            authority: 'NSSA',
            title: 'National Social Security Standing',
            statusText: 'POBS & APWCS Active',
            statusVariant: 'success' as const,
            citation: 'NSSA Act [Chapter 17:04] & SI 393/1993',
            details: '12 employees enrolled under mandatory pension & accident compensation schemes.',
            evidenceStatus: 'VERIFIED_BY_ISSUER',
            actionUrl: '/comply',
            actionText: 'Inspect Remittances',
        },
        {
            authority: 'LOCAL_COUNCIL',
            title: 'Municipal Operating Licence',
            statusText: 'Renewal Window Open',
            statusVariant: 'info' as const,
            citation: 'Urban Councils Act [Chapter 29:15]',
            details: 'Harare Head Office commercial workshop permit active until 31 December 2026.',
            evidenceStatus: 'DOCUMENT_SUPPORTED',
            actionUrl: '/renew',
            actionText: 'Manage Licences',
        },
        {
            authority: 'PRAZ',
            title: 'Public Procurement Status',
            statusText: 'Registration Incomplete',
            statusVariant: 'warning' as const,
            citation: 'PPDPA Act [Chapter 22:23]',
            details: 'Engineering category SC004 profile initiated. Valid ITF263 required for submission.',
            evidenceStatus: 'USER_DECLARED',
            actionUrl: '/tender',
            actionText: 'Tender Readiness',
        },
    ];

    // Upcoming Statutory Deadlines
    const upcomingDeadlines = [
        {
            date: '10 Nov 2026',
            daysLeft: '11 days left',
            title: 'ZIMRA PAYE & AIDS Levy Remittance (Form P2)',
            regulator: 'ZIMRA',
            type: 'Monthly Statutory Return',
            currency: 'USD & ZiG Split',
            urgencyVariant: 'danger' as const,
        },
        {
            date: '10 Nov 2026',
            daysLeft: '11 days left',
            title: 'NSSA Monthly POBS & APWCS Contribution (Form P4)',
            regulator: 'NSSA',
            type: 'Social Security Remittance',
            currency: 'USD & ZiG Ceilings',
            urgencyVariant: 'danger' as const,
        },
        {
            date: '15 Nov 2026',
            daysLeft: '16 days left',
            title: 'ZIMDEF 1% Manpower Training Levy Assessment',
            regulator: 'ZIMDEF',
            type: 'Statutory Training Levy',
            currency: '1% Gross Wage Bill',
            urgencyVariant: 'warning' as const,
        },
        {
            date: '30 Nov 2026',
            daysLeft: '31 days left',
            title: 'Fleet ZINARA & Third-Party Motor Insurance Renewal',
            regulator: 'ZINARA',
            type: 'Commercial Fleet',
            currency: 'ZINARA Tariff Schedule',
            urgencyVariant: 'info' as const,
        },
    ];

    return (
        <AppLayout
            title="Compliance Overview"
            subtitle={`${auth.active_organisation?.legal_name || 'Corporate Entity'} • Base Currency: ${auth.active_organisation?.base_currency || 'USD & ZiG'} • Workspace: ${auth.active_tenant?.name || 'SME Workspace'}`}
        >
            <div className="space-y-6">
                {/* Organisation Identity & Posture Banner */}
                <div className="bg-[#14263D] text-white rounded-xl p-5 sm:p-6 shadow-sm border border-[#1C3554] flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-heading font-extrabold text-lg sm:text-xl tracking-tight text-white">
                                {auth.active_organisation?.legal_name || 'My Corporate Entity'}
                            </span>
                            <Badge variant="teal" size="sm">
                                {auth.active_organisation?.entity_type ? auth.active_organisation.entity_type.replace(/_/g, ' ') : 'PRIVATE_LIMITED_COMPANY'}
                            </Badge>
                            <Badge variant="neutral" size="sm">
                                Reporting Currency: {auth.active_organisation?.base_currency || 'USD'}
                            </Badge>
                        </div>
                        <p className="text-xs text-slate-300 max-w-2xl leading-relaxed">
                            Continuous statutory compliance monitoring under the Laws of Zimbabwe. {metrics.branches_count} registered operating branch(es) monitored.
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5 shrink-0">
                        <Button
                            href="/registration"
                            variant="primary"
                            size="md"
                            icon={<Compass className="h-4 w-4" />}
                        >
                            Statutory Discovery Wizard
                        </Button>
                    </div>
                </div>

                {/* 4 Purposeful Institutional KPI Widgets */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500 font-semibold uppercase tracking-wider">
                                <span>Statutory Standing</span>
                                <ShieldCheck className="h-4 w-4 text-[#19766F]" />
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="font-heading font-extrabold text-2xl text-slate-900">
                                    Good Standing
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">
                                4 of 6 core obligations active
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500 font-semibold uppercase tracking-wider">
                                <span>Upcoming Deadlines</span>
                                <Clock className="h-4 w-4 text-[#D8A34B]" />
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="font-heading font-extrabold text-2xl text-slate-900">
                                    {metrics.urgent_deadlines_count} Remittances
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">
                                Next: 10 Nov (PAYE & NSSA)
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500 font-semibold uppercase tracking-wider">
                                <span>Vault Certificates</span>
                                <FileCheck className="h-4 w-4 text-[#19766F]" />
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="font-heading font-extrabold text-2xl text-slate-900">
                                    {metrics.documents_verified_count} Stored
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">
                                SHA-256 tamper-evident proof
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500 font-semibold uppercase tracking-wider">
                                <span>Tender Qualification</span>
                                <Award className="h-4 w-4 text-[#B84B46]" />
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="font-heading font-extrabold text-2xl text-[#8e6015]">
                                    Action Required
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">
                                ITF263 clearance pending review
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Prioritised Action Queue */}
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>
                                <AlertTriangle className="h-4 w-4 text-[#D8A34B]" />
                                Prioritised Statutory Action List
                            </CardTitle>
                            <CardDescription>
                                Tasks requiring immediate attention to avoid statutory penalties, interest, or public procurement disqualification.
                            </CardDescription>
                        </div>
                        <Badge variant="warning" size="sm">
                            {prioritisedActions.length} Pending
                        </Badge>
                    </CardHeader>
                    <CardContent className="p-0 divide-y divide-slate-100">
                        {prioritisedActions.map((act) => (
                            <div key={act.id} className="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Badge variant={act.urgency === 'high' ? 'danger' : 'warning'} size="sm">
                                            {act.authority}
                                        </Badge>
                                        <span className="text-xs font-semibold text-slate-500">
                                            {act.deadline}
                                        </span>
                                    </div>
                                    <h4 className="font-heading font-bold text-sm text-slate-900">
                                        {act.title}
                                    </h4>
                                    <p className="text-xs text-slate-600 max-w-2xl leading-relaxed">
                                        {act.reason}
                                    </p>
                                </div>
                                <div className="shrink-0">
                                    <Button
                                        href={act.href}
                                        variant={act.urgency === 'high' ? 'secondary' : 'outline'}
                                        size="sm"
                                        icon={<ArrowUpRight className="h-3.5 w-3.5" />}
                                    >
                                        {act.actionText}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {/* Core Statutory Standings Grid */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-heading text-base font-bold text-slate-900 flex items-center gap-2">
                                <ShieldCheck className="h-4 w-4 text-[#19766F]" />
                                Key Regulatory Authority Standings
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Real-time evaluation of mandatory registration dependencies and operational compliance.
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {statutoryStandings.map((standing, idx) => (
                            <Card key={idx} className="flex flex-col justify-between">
                                <CardHeader className="pb-3">
                                    <div>
                                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                            {standing.authority}
                                        </span>
                                        <CardTitle className="text-sm mt-0.5">
                                            {standing.title}
                                        </CardTitle>
                                    </div>
                                    <Badge variant={standing.statusVariant} size="sm">
                                        {standing.statusText}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="pt-0 space-y-2">
                                    <div className="text-[11px] font-mono text-slate-500 bg-slate-50 px-2.5 py-1 rounded border border-slate-100">
                                        {standing.citation}
                                    </div>
                                    <p className="text-xs text-slate-600 leading-relaxed">
                                        {standing.details}
                                    </p>
                                    <div className="pt-2 flex items-center justify-between border-t border-slate-100 text-xs">
                                        <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-tight">
                                            Evidence Trust: <strong className="text-slate-700">{standing.evidenceStatus.replace(/_/g, ' ')}</strong>
                                        </span>
                                        <Link
                                            href={standing.actionUrl}
                                            className="font-semibold text-[#19766F] hover:text-[#125752] flex items-center gap-1"
                                        >
                                            <span>{standing.actionText}</span>
                                            <ArrowUpRight className="h-3 w-3" />
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                {/* Deadlines & Evidence Trust Hierarchy (2 Cols) */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Statutory Deadlines Table */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div>
                                    <CardTitle>
                                        <Calendar className="h-4 w-4 text-[#19766F]" />
                                        Upcoming Statutory Filing Deadlines
                                    </CardTitle>
                                    <CardDescription>
                                        Official gazetted submission deadlines for periodic remittances across Zimbabwean authorities.
                                    </CardDescription>
                                </div>
                                <Link
                                    href="/comply"
                                    className="text-xs font-semibold text-[#19766F] hover:underline"
                                >
                                    View Monthly Schedules →
                                </Link>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                            <tr>
                                                <th className="py-2.5 px-4">Due Date</th>
                                                <th className="py-2.5 px-4">Authority & Obligation</th>
                                                <th className="py-2.5 px-4">Schedule</th>
                                                <th className="py-2.5 px-4 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {upcomingDeadlines.map((dl, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/60 transition-colors">
                                                    <td className="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">
                                                        <div>{dl.date}</div>
                                                        <span className="text-[10px] text-slate-500">{dl.daysLeft}</span>
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div className="font-semibold text-slate-900">{dl.title}</div>
                                                        <div className="text-[10px] text-slate-500">{dl.regulator} • {dl.type}</div>
                                                    </td>
                                                    <td className="py-3 px-4 text-slate-600 whitespace-nowrap">
                                                        <Badge variant="neutral" size="sm">{dl.currency}</Badge>
                                                    </td>
                                                    <td className="py-3 px-4 text-right whitespace-nowrap">
                                                        <Link
                                                            href="/comply"
                                                            className="text-xs font-semibold text-[#19766F] hover:text-[#125752]"
                                                        >
                                                            Prepare Return
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right 1 Col: Evidence Trust Framework & Audit Trail */}
                    <div className="space-y-4">
                        {/* 5-Tier Evidence Trust Framework */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div>
                                    <CardTitle className="text-xs uppercase tracking-wider text-slate-500">
                                        Evidence Trust Hierarchy
                                    </CardTitle>
                                    <CardDescription>
                                        Compliance requires verifiable proof, not mere declarations.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-0 space-y-2 text-xs">
                                <div className="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200">
                                    <div className="font-bold text-emerald-900 flex items-center justify-between">
                                        <span>Tier 1: Issuer API Verified</span>
                                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-700" />
                                    </div>
                                    <p className="text-[10px] text-emerald-800 mt-0.5">Direct cryptographic or gateway confirmation.</p>
                                </div>

                                <div className="p-2.5 rounded-lg bg-[#e2f4f2] border border-[#a5dcd6]">
                                    <div className="font-bold text-[#125752] flex items-center justify-between">
                                        <span>Tier 2: Document Supported</span>
                                        <FileCheck className="h-3.5 w-3.5 text-[#19766F]" />
                                    </div>
                                    <p className="text-[10px] text-[#125752] mt-0.5">Uploaded official certificate verified by human reviewer.</p>
                                </div>

                                <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                                    <div className="font-bold text-slate-700 flex items-center justify-between">
                                        <span>Tier 3: User Declared</span>
                                        <Clock className="h-3.5 w-3.5 text-slate-500" />
                                    </div>
                                    <p className="text-[10px] text-slate-600 mt-0.5">Declared by business; pending supporting evidence.</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Recent Activity Log */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-xs uppercase tracking-wider text-slate-500">
                                    Recent Audit Activity
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-0">
                                {recent_events && recent_events.length > 0 ? (
                                    <div className="space-y-3 text-xs">
                                        {recent_events.map((evt: any, i: number) => (
                                            <div key={i} className="flex items-start gap-2 border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                                                <div className="h-2 w-2 rounded-full bg-[#19766F] mt-1.5 shrink-0" />
                                                <div>
                                                    <div className="font-medium text-slate-800">{evt.event_type || 'Audit Action'}</div>
                                                    <div className="text-[10px] text-slate-400">{evt.created_at || 'Just now'}</div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="space-y-2 text-xs">
                                        <div className="flex items-start gap-2 border-b border-slate-100 pb-2">
                                            <div className="h-2 w-2 rounded-full bg-emerald-500 mt-1.5 shrink-0" />
                                            <div>
                                                <div className="font-semibold text-slate-800">Milestone 2.1 Schema Initialised</div>
                                                <div className="text-[10px] text-slate-500">Centrally governed regulatory catalog loaded</div>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="h-2 w-2 rounded-full bg-[#19766F] mt-1.5 shrink-0" />
                                            <div>
                                                <div className="font-semibold text-slate-800">Statutory Authorities Verified</div>
                                                <div className="text-[10px] text-slate-500">11 Gazetted Acts and SIs authenticated</div>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
