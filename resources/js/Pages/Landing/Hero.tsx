import React from 'react';
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    ShieldCheck,
    Calendar,
    FileCheck2,
    CheckCircle2,
    Clock,
    AlertCircle,
    Building2,
    Lock,
    ExternalLink,
} from 'lucide-react';

export function Hero() {
    return (
        <section className="relative overflow-hidden bg-[#14263D] text-white pt-12 pb-24 lg:pt-16 lg:pb-32">
            {/* Subtle institutional background geometric grid */}
            <div className="absolute inset-0 opacity-[0.03] bg-[linear-gradient(to_right,#ffffff_1px,transparent_1px),linear-gradient(to_bottom,#ffffff_1px,transparent_1px)] bg-[size:4rem_4rem] pointer-events-none" />

            {/* Subtle teal background radial highlight */}
            <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-[#19766F]/15 rounded-full blur-3xl pointer-events-none" />

            <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Hero Header Card Container */}
                <div className="max-w-4xl mx-auto text-center space-y-6">
                    {/* Brand Identifier Badge */}
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/80 text-xs font-semibold tracking-wide text-slate-200 shadow-xs">
                        <span className="h-2 w-2 rounded-full bg-[#19766F] animate-pulse shrink-0" />
                        <span className="text-[#4dd4c7]">ZimComply AI</span>
                        <span className="text-slate-400">•</span>
                        <span>Authoritative Zimbabwe Regulatory Intelligence</span>
                    </div>

                    {/* Primary Headline */}
                    <h1 className="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.15]">
                        Running a business is hard enough.{' '}
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-300 via-teal-200 to-emerald-300">
                            Compliance shouldn't be.
                        </span>
                    </h1>

                    {/* Supporting Text */}
                    <p className="text-base sm:text-xl text-slate-300 max-w-3xl mx-auto font-normal leading-relaxed">
                        From company registration to tax, employment, licences and tender readiness, ZimComply helps
                        Zimbabwean businesses understand their obligations, organise their records and stay ahead of
                        important deadlines.
                    </p>

                    {/* Action Buttons */}
                    <div className="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
                        <Link
                            href="/register"
                            id="hero-primary-cta"
                            className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-full bg-white hover:bg-slate-100 text-[#14263D] font-bold text-sm transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-white cursor-pointer"
                        >
                            <span>Start Your Compliance Journey</span>
                            <ArrowRight className="w-4 h-4 text-[#14263D]" />
                        </Link>

                        <a
                            href="#how-it-works"
                            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full bg-[#0e1c2e] hover:bg-[#1a2e47] text-slate-200 border border-slate-700 font-semibold text-sm transition-all focus:outline-none focus:ring-2 focus:ring-[#19766F] cursor-pointer"
                        >
                            <span>Explore the Platform</span>
                        </a>
                    </div>

                    {/* Reassurance Subtitle */}
                    <p className="text-xs sm:text-sm text-slate-400 font-medium">
                        Built for Zimbabwean businesses, accountants and compliance professionals.
                    </p>
                </div>

                {/* High-Fidelity Product Interface Preview Window */}
                <div className="mt-14 lg:mt-20 max-w-6xl mx-auto rounded-2xl bg-slate-900/90 border border-slate-800 shadow-2xl overflow-hidden backdrop-blur-sm">
                    {/* Top Window Chrome Header */}
                    <div className="px-4 py-3 bg-slate-950/80 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div className="flex items-center gap-2">
                            <div className="flex gap-1.5">
                                <span className="h-2.5 w-2.5 rounded-full bg-rose-500/80" />
                                <span className="h-2.5 w-2.5 rounded-full bg-amber-500/80" />
                                <span className="h-2.5 w-2.5 rounded-full bg-emerald-500/80" />
                            </div>
                            <span className="text-slate-500 ml-2 font-mono text-[11px]">app.zimcomply.co.zw/dashboard</span>
                        </div>

                        <div className="flex items-center gap-3">
                            <span className="flex items-center gap-1.5 text-slate-400">
                                <Building2 className="w-3.5 h-3.5 text-teal-400" />
                                <span className="font-semibold text-slate-200">Highlands Engineering (Pvt) Ltd</span>
                            </span>
                            <span className="px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-800 text-[10px] font-semibold">
                                Active Workspace
                            </span>
                        </div>
                    </div>

                    {/* Inside Interface Preview: 3 Institutional Panels */}
                    <div className="p-4 sm:p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 bg-[#0f1d2e]">
                        {/* Panel 1: Statutory Obligation Sequence (Roadmap) - 5 Cols */}
                        <div className="lg:col-span-5 rounded-xl bg-slate-900 border border-slate-800 p-5 space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <span className="h-2 w-2 rounded-full bg-teal-400" />
                                    <h2 className="text-xs font-bold uppercase tracking-wider text-slate-200">
                                        Statutory Registration Sequence
                                    </h2>
                                </div>
                                <span className="text-[11px] text-teal-400 font-medium">Topological DAG</span>
                            </div>

                            <div className="space-y-2.5">
                                {/* Item 1 */}
                                <div className="p-3 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-start gap-3">
                                    <div className="h-6 w-6 rounded-md bg-emerald-900/60 text-emerald-400 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5">
                                        1
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-1">
                                            <span className="text-xs font-semibold text-white truncate">CIPZ Incorporation</span>
                                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-emerald-900/80 text-emerald-300 font-medium">COMPLIANT</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400 truncate">COBE Act [Chapter 24:31], s. 16 • CR14 & CR6</p>
                                    </div>
                                </div>

                                {/* Item 2 */}
                                <div className="p-3 rounded-lg bg-slate-800/90 border border-teal-500/40 flex items-start gap-3 shadow-xs">
                                    <div className="h-6 w-6 rounded-md bg-[#19766F] text-white flex items-center justify-center shrink-0 text-xs font-bold mt-0.5">
                                        2
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-1">
                                            <span className="text-xs font-semibold text-white truncate">ZIMRA TIN & TaRMS Registration</span>
                                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-teal-900 text-teal-300 font-medium">MANDATORY</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400 truncate">Income Tax Act [Chapter 23:06], s. 37</p>
                                    </div>
                                </div>

                                {/* Item 3 */}
                                <div className="p-3 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-start gap-3">
                                    <div className="h-6 w-6 rounded-md bg-slate-700 text-slate-300 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5">
                                        3
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-1">
                                            <span className="text-xs font-semibold text-white truncate">NSSA Employer Social Security</span>
                                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-teal-900 text-teal-300 font-medium">MANDATORY</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400 truncate">POBS Pension & APWCS Workers Comp</p>
                                    </div>
                                </div>

                                {/* Item 4 */}
                                <div className="p-3 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-start gap-3">
                                    <div className="h-6 w-6 rounded-md bg-slate-700 text-slate-300 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5">
                                        4
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-1">
                                            <span className="text-xs font-semibold text-white truncate">ZIMDEF 1% Manpower Training Levy</span>
                                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-teal-900 text-teal-300 font-medium">MANDATORY</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400 truncate">Chapter 28:02 • Monthly Payroll Levy</p>
                                    </div>
                                </div>

                                {/* Item 5 */}
                                <div className="p-3 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-start gap-3">
                                    <div className="h-6 w-6 rounded-md bg-slate-700 text-slate-300 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5">
                                        5
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-1">
                                            <span className="text-xs font-semibold text-white truncate">PRAZ e-GP Supplier Registration</span>
                                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-amber-900/80 text-amber-300 font-medium">TENDER ELIGIBLE</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400 truncate">PPDPA Act [Chapter 22:23] • Annual</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Panel 2: Verified Evidence Vault & Readiness Gauge - 3 Cols */}
                        <div className="lg:col-span-3 rounded-xl bg-slate-900 border border-slate-800 p-5 space-y-5 flex flex-col justify-between">
                            <div>
                                <div className="flex items-center gap-2 mb-4">
                                    <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                    <h2 className="text-xs font-bold uppercase tracking-wider text-slate-200">
                                        Audit Readiness
                                    </h2>
                                </div>

                                {/* Circular-styled Gauge Metric */}
                                <div className="py-4 text-center">
                                    <div className="inline-flex items-center justify-center h-28 w-28 rounded-full border-4 border-[#19766F] bg-slate-950/60 relative">
                                        <div className="flex flex-col items-center">
                                            <span className="text-2xl font-black text-white">84%</span>
                                            <span className="text-[10px] text-teal-300 font-semibold uppercase">VERIFIED</span>
                                        </div>
                                    </div>
                                    <p className="text-xs text-slate-300 font-medium mt-3">Evidence Trust Status</p>
                                    <p className="text-[11px] text-slate-500">6 of 7 Obligation Documents Sealed</p>
                                </div>
                            </div>

                            <div className="pt-2 border-t border-slate-800 space-y-2">
                                <div className="flex items-center justify-between text-[11px]">
                                    <span className="text-slate-400 flex items-center gap-1.5">
                                        <Lock className="w-3 h-3 text-slate-400" />
                                        SHA-256 Vault Hash
                                    </span>
                                    <span className="font-mono text-emerald-400 text-[10px]">Valid</span>
                                </div>
                                <div className="flex items-center justify-between text-[11px]">
                                    <span className="text-slate-400">Tenant Isolation Scope</span>
                                    <span className="text-slate-200 font-medium">PostgreSQL 16</span>
                                </div>
                            </div>
                        </div>

                        {/* Panel 3: Zimbabwe Statutory Deadlines Calendar - 4 Cols */}
                        <div className="lg:col-span-4 rounded-xl bg-slate-900 border border-slate-800 p-5 space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <span className="h-2 w-2 rounded-full bg-amber-400" />
                                    <h2 className="text-xs font-bold uppercase tracking-wider text-slate-200">
                                        Statutory Deadlines
                                    </h2>
                                </div>
                                <span className="text-[11px] text-amber-400 font-semibold">Q4 Filing Cycle</span>
                            </div>

                            <div className="space-y-3">
                                <div className="p-3 rounded-lg bg-slate-950/60 border border-slate-800 flex items-start justify-between gap-3">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-semibold text-xs text-white">NSSA Monthly Remittance</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400">POBS & APWCS Contributions</p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-xs font-bold text-amber-300 block">10th Nov</span>
                                        <span className="text-[10px] text-slate-500">Monthly</span>
                                    </div>
                                </div>

                                <div className="p-3 rounded-lg bg-slate-950/60 border border-slate-800 flex items-start justify-between gap-3">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-semibold text-xs text-white">ZIMDEF 1% Training Levy</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400">Gross Wage Bill Levy</p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-xs font-bold text-amber-300 block">15th Nov</span>
                                        <span className="text-[10px] text-slate-500">Monthly</span>
                                    </div>
                                </div>

                                <div className="p-3 rounded-lg bg-slate-950/60 border border-slate-800 flex items-start justify-between gap-3">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-semibold text-xs text-white">ZIMRA VAT Return & Payment</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400">Category A/B Tax Period</p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-xs font-bold text-amber-300 block">25th Nov</span>
                                        <span className="text-[10px] text-slate-500">Monthly</span>
                                    </div>
                                </div>

                                <div className="p-3 rounded-lg bg-slate-950/60 border border-slate-800 flex items-start justify-between gap-3">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-semibold text-xs text-white">PRAZ Annual Supplier Renewal</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400">e-GP Procurement Portal</p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-xs font-bold text-teal-300 block">31st Dec</span>
                                        <span className="text-[10px] text-slate-500">Calendar 2026</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
