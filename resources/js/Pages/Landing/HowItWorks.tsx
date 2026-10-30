import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight, ClipboardCheck, Cpu, Map } from 'lucide-react';

export function HowItWorks() {
    const steps = [
        {
            number: '01',
            icon: ClipboardCheck,
            title: 'Tell Us About Your Business',
            subtitle: '5-Minute Guided Questionnaire',
            description:
                'Answer targeted questions about your legal entity structure (Company or PBC), industry sector, employee headcount, annual turnover estimate, and operating premises locations.',
            details: 'Covers physical premises, licensed professions, and public procurement bidding intent.',
        },
        {
            number: '02',
            icon: Cpu,
            title: 'Understand Your Requirements',
            subtitle: 'Deterministic Statutory Evaluation',
            description:
                'Our engine queries approved, effective-dated regulatory rule versions—evaluating exact legal triggers across CIPZ, ZIMRA, NSSA, ZIMDEF, PRAZ, and municipal councils without generative guesswork.',
            details: 'Classifies obligations into Mandatory, Conditional, Exempt, or Requires Information.',
        },
        {
            number: '03',
            icon: Map,
            title: 'Build Your Compliance Roadmap',
            subtitle: 'Auditable & Prerequisite-Sorted Action Plan',
            description:
                'Receive a topologically sorted registration sequence where foundational requirements (like CIPZ incorporation) precede dependent obligations (such as ZIMRA TIN, NSSA, and PRAZ supplier registration).',
            details: 'Upload verified documents, monitor expiry dates, and download auditable compliance records.',
        },
    ];

    return (
        <section className="py-20 lg:py-28 bg-[#14263D] text-white border-b border-slate-800" id="how-it-works">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span className="text-xs font-bold uppercase tracking-wider text-[#4dd4c7] bg-[#19766F]/30 px-3 py-1 rounded-full border border-[#19766F]/50">
                        Three-Step Journey
                    </span>
                    <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-white">
                        How ZimComply Works
                    </h2>
                    <p className="text-base sm:text-lg text-slate-300 leading-relaxed">
                        From uncertainty to an ordered, auditable registration and compliance roadmap in three simple steps.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    {steps.map((step, idx) => {
                        const Icon = step.icon;
                        return (
                            <div
                                key={idx}
                                className="relative rounded-2xl bg-slate-900/90 border border-slate-800 p-8 flex flex-col justify-between hover:border-teal-500/40 transition-colors shadow-lg"
                            >
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-3xl font-black text-slate-700 font-mono">
                                            {step.number}
                                        </span>
                                        <div className="h-11 w-11 rounded-xl bg-[#19766F] text-white flex items-center justify-center shadow-md">
                                            <Icon className="w-5 h-5 text-teal-100" />
                                        </div>
                                    </div>

                                    <div>
                                        <span className="text-[11px] font-bold uppercase text-teal-300 tracking-wider block">
                                            {step.subtitle}
                                        </span>
                                        <h3 className="text-xl font-bold text-white mt-1">
                                            {step.title}
                                        </h3>
                                    </div>

                                    <p className="text-sm text-slate-300 leading-relaxed font-normal">
                                        {step.description}
                                    </p>
                                </div>

                                <div className="mt-6 pt-4 border-t border-slate-800">
                                    <p className="text-xs text-slate-400 italic">
                                        {step.details}
                                    </p>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Section CTA */}
                <div className="mt-16 text-center">
                    <Link
                        href="/register"
                        className="inline-flex items-center gap-2.5 px-7 py-3.5 rounded-full bg-white hover:bg-slate-100 text-[#14263D] font-bold text-sm transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-white"
                    >
                        <span>Start Your Discovery Assessment</span>
                        <ArrowRight className="w-4 h-4 text-[#14263D]" />
                    </Link>
                </div>
            </div>
        </section>
    );
}
