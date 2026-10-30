import React from 'react';
import {
    FileCheck,
    BookOpen,
    Users2,
    Database,
    History,
    AlertTriangle,
} from 'lucide-react';

export function TrustSection() {
    const pillars = [
        {
            icon: FileCheck,
            title: 'Deterministic Rule Engine',
            description:
                'Statutory eligibility is determined by version-controlled rule models with dual-key approval—never by ungrounded generative AI predictions.',
        },
        {
            icon: BookOpen,
            title: 'Authoritative Legal Citations',
            description:
                'Every requirement is directly linked to gazetted Acts of Parliament and Statutory Instruments, including the COBE Act, Income Tax Act, NSSA Act, and PPDPA Act.',
        },
        {
            icon: Users2,
            title: 'Human-in-the-Loop Verification',
            description:
                'Supporting evidence follows a strict 5-tier trust architecture. Compliance documents require human verification before being marked verified.',
        },
        {
            icon: Database,
            title: 'Strict Logical Tenant Separation',
            description:
                'Your company records, director information, and filing histories are partitioned at the database layer with automated query-level security scopes.',
        },
        {
            icon: History,
            title: 'Immutable Assessment Audits',
            description:
                'Historical assessments and roadmap versions are preserved as immutable records, providing a reliable audit trail for compliance officers and external auditors.',
        },
    ];

    return (
        <section className="py-20 lg:py-28 bg-white border-b border-slate-200" id="trust">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span className="text-xs font-bold uppercase tracking-wider text-[#19766F] bg-[#19766F]/10 px-3 py-1 rounded-full border border-[#19766F]/20">
                        Governance & Data Integrity
                    </span>
                    <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-[#14263D]">
                        Built with Enterprise Regulatory Rigor
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        In regulatory compliance, precision and integrity are non-negotiable. ZimComply is engineered
                        around verifiable statutory rules and defensible record governance.
                    </p>
                </div>

                {/* 5 Architectural Trust Pillars */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 mb-16">
                    {pillars.map((pillar, idx) => {
                        const Icon = pillar.icon;
                        return (
                            <div
                                key={idx}
                                className="p-6 rounded-xl border border-slate-200 bg-[#F7F8FA] space-y-3"
                            >
                                <div className="h-10 w-10 rounded-lg bg-[#14263D] text-white flex items-center justify-center">
                                    <Icon className="w-5 h-5 text-teal-300" />
                                </div>
                                <h3 className="text-base font-bold text-[#14263D]">
                                    {pillar.title}
                                </h3>
                                <p className="text-sm text-slate-600 leading-relaxed font-normal">
                                    {pillar.description}
                                </p>
                            </div>
                        );
                    })}
                </div>

                {/* Mandatory Independent Platform Disclosure Notice */}
                <div className="max-w-4xl mx-auto p-6 rounded-2xl bg-slate-50 border border-slate-300 flex items-start gap-4 shadow-xs">
                    <div className="h-10 w-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 border border-amber-200 mt-0.5">
                        <AlertTriangle className="w-5 h-5 text-[#D8A34B]" />
                    </div>
                    <div className="space-y-1.5">
                        <h4 className="text-sm font-bold text-[#14263D] tracking-tight">
                            Independent Software Platform Disclosure
                        </h4>
                        <p className="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
                            ZimComply AI is an independent software technology platform. We are not a government agency,
                            statutory regulator, or legal firm, and we do not claim official government endorsement or
                            accreditation. ZimComply AI provides regulatory intelligence, workflow tooling, and record
                            organisation to assist businesses and professionals with compliance management. Final statutory
                            determinations, registrations, and official certificates are issued solely by the respective
                            statutory authorities (CIPZ, ZIMRA, NSSA, ZIMDEF, PRAZ, and local municipal councils).
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}
