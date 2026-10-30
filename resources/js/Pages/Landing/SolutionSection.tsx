import React from 'react';
import {
    Building,
    Scale,
    CalendarCheck,
    FolderLock,
    Award,
    Sparkles,
    Check,
} from 'lucide-react';

export function SolutionSection() {
    const features = [
        {
            icon: Building,
            category: 'ENTITY FORMATION',
            title: 'Business Registration Roadmap',
            description:
                'Discover foundational incorporation requirements under the Companies and Other Business Entities Act [Chapter 24:31], registered office filings (CR6), and director registers (CR14) structured in proper dependency order.',
            benefits: [
                'Entity structure evaluation (Private Limited vs PBC)',
                'Prerequisite dependency mapping (Kahn\'s algorithm)',
                'Document checklist for company secretarial filings',
            ],
            status: 'AVAILABLE NOW',
            statusVariant: 'active',
        },
        {
            icon: Scale,
            category: 'STATUTORY COMPLIANCE',
            title: 'Deterministic Rules Engine',
            description:
                'Evaluate applicable national taxes and employer contributions without guesswork. Rules trace directly to gazetted Acts and statutory instruments for ZIMRA (TIN, VAT, PAYE), NSSA, and ZIMDEF.',
            benefits: [
                'USD 25,000 statutory VAT threshold evaluation',
                'NSSA POBS & APWCS employer coverage rules',
                'ZIMDEF 1% monthly payroll levy applicability',
            ],
            status: 'AVAILABLE NOW',
            statusVariant: 'active',
        },
        {
            icon: CalendarCheck,
            category: 'RENEWAL LIFECYCLE',
            title: 'Licence & Certificate Renewals',
            description:
                'Track municipal operating licences (City of Harare, City of Bulawayo), annual tax clearances, and periodic returns in a unified timeline with advance warning periods.',
            benefits: [
                'Municipal trading licence and health inspection tracking',
                'ITF263 annual tax clearance renewal alerts',
                'Quarterly QPD and monthly payroll due dates',
            ],
            status: 'AVAILABLE NOW',
            statusVariant: 'active',
        },
        {
            icon: FolderLock,
            category: 'RECORD GOVERNANCE',
            title: 'Cryptographic Document Vault',
            description:
                'Store, organise, and tag all compliance certificates and corporate filings in a secure, tenant-isolated vault backed by SHA-256 cryptographic hashes and 5-tier evidence trust levels.',
            benefits: [
                'SHA-256 file tamper-evident hashing',
                '5-tier evidence trust tracking (Verified vs Declared)',
                'Tenant-isolated storage with private access controls',
            ],
            status: 'AVAILABLE NOW',
            statusVariant: 'active',
        },
        {
            icon: Award,
            category: 'PUBLIC PROCUREMENT',
            title: 'PRAZ Tender Readiness',
            description:
                'Identify and verify prerequisite corporate registrations, tax clearance, and social security standing needed when preparing to bid on government tenders under the PPDPA Act [Chapter 22:23].',
            benefits: [
                'e-GP prerequisite checklist mapping',
                'ITF263 and NSSA clearance status monitoring',
                'Tender eligibility gap diagnostics',
            ],
            status: 'AVAILABLE NOW',
            statusVariant: 'active',
        },
        {
            icon: Sparkles,
            category: 'REGULATORY EXPLANATIONS',
            title: 'AI Regulatory Advisory Drawer',
            description:
                'Receive plain-English statutory explanations backed strictly by gazetted Zimbabwean Acts and approved rule versions. Governed under ADR-0005: AI never calculates arithmetic or invents legal requirements.',
            benefits: [
                'Plain-English guidance on statutory obligations',
                'Citations grounded in verified Acts and Statutory Instruments',
                'Human verification queue for document extraction',
            ],
            status: 'IN DEVELOPMENT',
            statusVariant: 'roadmap',
        },
    ];

    return (
        <section className="py-20 lg:py-28 bg-white border-b border-slate-200" id="features">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span className="text-xs font-bold uppercase tracking-wider text-[#19766F] bg-[#19766F]/10 px-3 py-1 rounded-full border border-[#19766F]/20">
                        Platform Capabilities
                    </span>
                    <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-[#14263D]">
                        One place to understand and organise your compliance.
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        A structured regulatory platform engineered specifically around Zimbabwean legislation,
                        empowering your business to achieve clarity and stay audit-ready.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {features.map((feature, idx) => {
                        const Icon = feature.icon;
                        const isAvailable = feature.statusVariant === 'active';

                        return (
                            <div
                                key={idx}
                                className="rounded-xl border border-slate-200 bg-[#F7F8FA] p-7 flex flex-col justify-between hover:border-slate-300 transition-all hover:shadow-md"
                            >
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="h-10 w-10 rounded-lg bg-[#14263D] text-white flex items-center justify-center shrink-0 shadow-xs">
                                            <Icon className="w-5 h-5 text-teal-300" />
                                        </div>
                                        <span
                                            className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded tracking-wider border ${
                                                isAvailable
                                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                    : 'bg-amber-50 text-amber-800 border-amber-200'
                                            }`}
                                        >
                                            {feature.status}
                                        </span>
                                    </div>

                                    <div>
                                        <span className="text-[11px] font-bold uppercase text-[#19766F] tracking-wider block">
                                            {feature.category}
                                        </span>
                                        <h3 className="text-lg font-bold text-[#14263D] mt-1">
                                            {feature.title}
                                        </h3>
                                    </div>

                                    <p className="text-sm text-slate-600 leading-relaxed font-normal">
                                        {feature.description}
                                    </p>

                                    <div className="pt-2 border-t border-slate-200 space-y-2">
                                        {feature.benefits.map((benefit, bIdx) => (
                                            <div key={bIdx} className="flex items-start gap-2 text-xs text-slate-700">
                                                <Check className="w-3.5 h-3.5 text-[#19766F] shrink-0 mt-0.5" />
                                                <span>{benefit}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
