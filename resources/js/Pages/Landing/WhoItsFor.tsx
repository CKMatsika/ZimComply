import React from 'react';
import {
    Rocket,
    Building2,
    Briefcase,
    Shield,
    FileSpreadsheet,
    Network,
} from 'lucide-react';

export function WhoItsFor() {
    const audiences = [
        {
            icon: Rocket,
            title: 'Entrepreneurs & Startups',
            badge: 'NEW VENTURES',
            description:
                'Launch your business on a solid legal footing. Avoid costly omissions by discovering foundational CIPZ incorporation, ZIMRA TIN registration, and municipal permit requirements before commercial launch.',
            keyBenefit: 'Clear step-by-step formation checklist from day one.',
        },
        {
            icon: Building2,
            title: 'Small & Medium Enterprises (SMEs)',
            badge: 'GROWING BUSINESSES',
            description:
                'Stay ahead of recurring payroll obligations (NSSA, ZIMDEF), monthly VAT return deadlines, and annual council licences without losing time or incurring unnecessary statutory fines.',
            keyBenefit: 'Automated deadline calendar and document organisation.',
        },
        {
            icon: Briefcase,
            title: 'Accountants & Accounting Firms',
            badge: 'PROFESSIONAL PRACTICES',
            description:
                'Manage multiple client compliance profiles across separate, secure tenant spaces. Keep tax clearances, QPD filing schedules, and statutory receipts organised in one reliable workspace.',
            keyBenefit: 'Portfolio overview across diverse client entities.',
        },
        {
            icon: Shield,
            title: 'Compliance Consultants',
            badge: 'ADVISORY FIRMS',
            description:
                'Standardise client compliance audits using deterministic statutory rule packs. Produce auditable roadmap reports with verifiable citations referencing enacted Acts of Parliament.',
            keyBenefit: 'Repeatable, defensible statutory assessment reports.',
        },
        {
            icon: FileSpreadsheet,
            title: 'Company Secretaries',
            badge: 'GOVERNANCE OFFICERS',
            description:
                'Maintain complete corporate governance records: CR14 director registers, CR6 registered office notifications, annual return dates, and statutory board resolutions in a cryptographically sealed vault.',
            keyBenefit: '5-tier evidence trust levels with SHA-256 integrity.',
        },
        {
            icon: Network,
            title: 'Multi-Branch Enterprises',
            badge: 'REGIONAL OPERATIONS',
            description:
                'Track location-specific municipal operating licences and health inspection requirements across City of Harare, Bulawayo, Chitungwiza, Gweru, Mutare, and other local authorities.',
            keyBenefit: 'Cross-jurisdiction branch licensing coordination.',
        },
    ];

    return (
        <section className="py-20 lg:py-28 bg-[#F7F8FA] border-b border-slate-200" id="who-its-for">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span className="text-xs font-bold uppercase tracking-wider text-[#19766F] bg-[#19766F]/10 px-3 py-1 rounded-full border border-[#19766F]/20">
                        Tailored Value
                    </span>
                    <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-[#14263D]">
                        Built for Zimbabwean Commercial Realities
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        Whether you are launching your first business, running an established company, or advising
                        dozens of corporate clients, ZimComply provides clarity at every step.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {audiences.map((audience, idx) => {
                        const Icon = audience.icon;
                        return (
                            <div
                                key={idx}
                                className="bg-white rounded-xl p-7 border border-slate-200/90 shadow-xs hover:shadow-md transition-all flex flex-col justify-between"
                            >
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="h-10 w-10 rounded-lg bg-[#14263D]/5 text-[#14263D] flex items-center justify-center border border-slate-200 shrink-0">
                                            <Icon className="w-5 h-5 text-[#19766F]" />
                                        </div>
                                        <span className="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 tracking-wider">
                                            {audience.badge}
                                        </span>
                                    </div>

                                    <h3 className="text-lg font-bold text-[#14263D]">
                                        {audience.title}
                                    </h3>

                                    <p className="text-sm text-slate-600 leading-relaxed font-normal">
                                        {audience.description}
                                    </p>
                                </div>

                                <div className="mt-6 pt-4 border-t border-slate-100">
                                    <span className="text-xs font-semibold text-[#19766F] block">
                                        ✓ {audience.keyBenefit}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
