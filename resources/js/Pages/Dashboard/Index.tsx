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
    Car,
    Award,
    AlertTriangle,
} from 'lucide-react';

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

    const complianceCards = [
        {
            title: 'ZIMRA Tax Clearance (ITF263)',
            authority: 'ZIMRA',
            status: 'ELIGIBLE_PENDING_FILING',
            statusText: 'Audit Needed',
            statusColor: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
            description: 'Section 80 Withholding Tax exemption. 0 outstanding returns required.',
            actionText: 'Review ITF263 Blockers',
            actionHref: '/comply',
        },
        {
            title: 'NSSA Employer Registration',
            authority: 'NSSA',
            status: 'COMPLIANT',
            statusText: 'Active • POBS & APWCS',
            statusColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
            description: 'Social Security Pension Scheme SI 393/1993 verified.',
            actionText: 'View Remittances',
            actionHref: '/comply',
        },
        {
            title: 'City Council Operating Licence',
            authority: 'City of Harare',
            status: 'RENEWAL_DUE',
            statusText: 'Expiring in 45 Days',
            statusColor: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
            description: 'Urban Councils Act [Chapter 29:15] premises permit for Harare HQ.',
            actionText: 'Prepare Renewal',
            actionHref: '/renew',
        },
        {
            title: 'PRAZ Supplier Accreditation',
            authority: 'PRAZ',
            status: 'UNREGISTERED',
            statusText: 'Roadmap Recommended',
            statusColor: 'text-purple-400 bg-purple-500/10 border-purple-500/20',
            description: 'Required to qualify for Zimbabwean government and parastatal tenders.',
            actionText: 'Choose Categories',
            actionHref: '/tender',
        },
    ];

    const upcomingDeadlines = [
        {
            date: '10 Nov 2026',
            daysLeft: '11 days left',
            title: 'ZIMRA PAYE & AIDS Levy Remittance (P2)',
            regulator: 'ZIMRA',
            type: 'Statutory Filing',
            urgency: 'high',
        },
        {
            date: '10 Nov 2026',
            daysLeft: '11 days left',
            title: 'NSSA Monthly POBS & APWCS Contribution (P4)',
            regulator: 'NSSA',
            type: 'Social Security',
            urgency: 'high',
        },
        {
            date: '15 Nov 2026',
            daysLeft: '16 days left',
            title: 'ZIMDEF 1% Manpower Training Levy',
            regulator: 'ZIMDEF',
            type: 'Statutory Levy',
            urgency: 'medium',
        },
        {
            date: '30 Nov 2026',
            daysLeft: '31 days left',
            title: 'Fleet ZINARA & Third-Party Motor Insurance Renewal',
            regulator: 'ZINARA',
            type: 'Fleet Licensing',
            urgency: 'low',
        },
    ];

    return (
        <AppLayout
            title={`Dashboard: ${auth.active_organisation?.legal_name || 'My Business'}`}
            subtitle={`${auth.active_organisation?.entity_type.replace(/_/g, ' ')} • Base Currency: ${auth.active_organisation?.base_currency} • Workspace: ${auth.active_tenant?.name}`}
        >
            <div className="space-y-6">
                {/* Onboarding / Roadmap Alert Banner */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-950 via-slate-900 to-slate-900 border border-emerald-500/30 p-6 shadow-xl">
                    <div className="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    <Sparkles className="h-3.5 w-3.5 text-emerald-400" />
                                    Registration-First Intelligence
                                </span>
                                <span className="text-xs text-slate-400 font-medium">Zimbabwe Statutory Discovery</span>
                            </div>
                            <h2 className="text-xl font-heading font-extrabold text-white">
                                Complete your Statutory Applicability Questionnaire
                            </h2>
                            <p className="text-sm text-slate-300 max-w-2xl">
                                Determine your company's mandatory legal requirements, applicable NEC agreements, council licences, and official government fee estimates across all Zimbabwean operating sites.
                            </p>
                        </div>
                        <Link
                            href="/registration"
                            className="shrink-0 flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-heading font-bold text-sm shadow-lg shadow-emerald-500/20 transition-all hover:scale-105"
                        >
                            <Compass className="h-4 w-4" />
                            <span>Launch Roadmap Questionnaire</span>
                        </Link>
                    </div>
                </div>

                {/* 4 Stat Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400 tracking-wider">Compliance Health</span>
                            <span className="h-8 w-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-xs">
                                68%
                            </span>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-heading font-extrabold text-white">Good Standing</div>
                            <div className="mt-2 w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                <div className="bg-gradient-to-r from-emerald-500 to-teal-400 h-full w-[68%] rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400 tracking-wider">ITF263 Status</span>
                            <ShieldCheck className="h-5 w-5 text-amber-400" />
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-heading font-extrabold text-amber-400">Readiness: 85%</div>
                            <p className="text-xs text-slate-400 mt-1">1 monthly return pending review</p>
                        </div>
                    </div>

                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400 tracking-wider">Upcoming Deadlines</span>
                            <Clock className="h-5 w-5 text-cyan-400" />
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-heading font-extrabold text-white">3 Remittances</div>
                            <p className="text-xs text-slate-400 mt-1">Due by 10th of this month</p>
                        </div>
                    </div>

                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400 tracking-wider">Verified Documents</span>
                            <FileCheck className="h-5 w-5 text-emerald-400" />
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-heading font-extrabold text-white">4 Certificates</div>
                            <p className="text-xs text-slate-400 mt-1">In compliance wallet vault</p>
                        </div>
                    </div>
                </div>

                {/* Primary Work Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Key Regulatory Standing */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                                <ShieldCheck className="h-4 w-4 text-emerald-400" />
                                Core Statutory Standings
                            </h3>
                            <span className="text-xs text-slate-400">Updated in real-time</span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {complianceCards.map((card, idx) => (
                                <div key={idx} className="glass-card p-5 rounded-xl border border-slate-800 flex flex-col justify-between hover:border-slate-700 transition-colors">
                                    <div>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">{card.authority}</span>
                                            <span className={`px-2 py-0.5 rounded text-[11px] font-semibold border ${card.statusColor}`}>
                                                {card.statusText}
                                            </span>
                                        </div>
                                        <h4 className="font-heading font-bold text-white text-sm mt-2">{card.title}</h4>
                                        <p className="text-xs text-slate-400 mt-1 leading-relaxed">{card.description}</p>
                                    </div>
                                    <div className="mt-4 pt-3 border-t border-slate-800/80">
                                        <Link
                                            href={card.actionHref}
                                            className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition-colors"
                                        >
                                            <span>{card.actionText}</span>
                                            <ArrowUpRight className="h-3.5 w-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Evidence Trust Tier Explainer */}
                        <div className="glass-card p-5 rounded-xl border border-slate-800">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                                5-Tier Evidence Trust Framework
                            </h4>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                                <div className="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                                    <div className="text-[11px] font-bold text-emerald-400">Issuer Verified</div>
                                    <div className="text-[10px] text-slate-400 mt-0.5">QR / Portal Certified</div>
                                </div>
                                <div className="p-2.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20">
                                    <div className="text-[11px] font-bold text-cyan-400">Doc Supported</div>
                                    <div className="text-[10px] text-slate-400 mt-0.5">Scanned & Hashed</div>
                                </div>
                                <div className="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20">
                                    <div className="text-[11px] font-bold text-amber-400">User Declared</div>
                                    <div className="text-[10px] text-slate-400 mt-0.5">Pending Proof</div>
                                </div>
                                <div className="p-2.5 rounded-lg bg-slate-800/80 border border-slate-700">
                                    <div className="text-[11px] font-bold text-slate-400">Unknown</div>
                                    <div className="text-[10px] text-slate-500 mt-0.5">Audit Required</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right 1 Col: Calendar & Deadlines */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-cyan-400" />
                                Remittance Calendar
                            </h3>
                            <Link href="/comply" className="text-xs text-emerald-400 hover:text-emerald-300">
                                View all
                            </Link>
                        </div>

                        <div className="glass-card p-4 rounded-xl border border-slate-800 space-y-3">
                            {upcomingDeadlines.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="p-3 rounded-lg bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition-colors"
                                >
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-semibold text-emerald-400">{item.regulator}</span>
                                        <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">
                                            {item.daysLeft}
                                        </span>
                                    </div>
                                    <div className="text-xs font-semibold text-white mt-1.5">{item.title}</div>
                                    <div className="flex items-center justify-between text-[11px] text-slate-400 mt-2">
                                        <span>Due: {item.date}</span>
                                        <span className="text-slate-500">{item.type}</span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Quick Action Box */}
                        <div className="glass-card p-4 rounded-xl border border-slate-800">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Quick Navigation</h4>
                            <div className="space-y-2">
                                <Link
                                    href="/documents"
                                    className="w-full flex items-center justify-between p-2.5 rounded-lg bg-slate-900/60 hover:bg-slate-800 text-xs text-slate-200 transition-colors"
                                >
                                    <span className="flex items-center gap-2">
                                        <FileText className="h-4 w-4 text-emerald-400" />
                                        Upload Statutory Document
                                    </span>
                                    <ArrowUpRight className="h-3.5 w-3.5 text-slate-500" />
                                </Link>
                                <Link
                                    href="/tender"
                                    className="w-full flex items-center justify-between p-2.5 rounded-lg bg-slate-900/60 hover:bg-slate-800 text-xs text-slate-200 transition-colors"
                                >
                                    <span className="flex items-center gap-2">
                                        <Award className="h-4 w-4 text-purple-400" />
                                        Check PRAZ Tender Readiness
                                    </span>
                                    <ArrowUpRight className="h-3.5 w-3.5 text-slate-500" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
