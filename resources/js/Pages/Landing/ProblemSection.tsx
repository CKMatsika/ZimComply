import React from 'react';
import {
    HelpCircle,
    Layers,
    FileWarning,
    CalendarX,
    FolderSync,
    Compass,
} from 'lucide-react';

export function ProblemSection() {
    const problems = [
        {
            icon: HelpCircle,
            title: 'Uncertainty About Which Registrations Apply',
            description:
                'Whether you operate a Private Limited Company or a PBC, identifying whether VAT thresholds (USD 25,000), ZIMDEF levies, or specific NEC collective agreements apply to your business can be confusing and ambiguous.',
        },
        {
            icon: Layers,
            title: 'Multiple Regulators & Disjointed Portals',
            description:
                'Navigating separate portals and procedures across CIPZ, ZIMRA (TaRMS), NSSA, ZIMDEF, PRAZ (e-GP), and municipal councils means hours lost tracking requirements in different places.',
        },
        {
            icon: FileWarning,
            title: 'Missing Documents at Tender Deadlines',
            description:
                'Discovering that your ITF263 tax clearance certificate or NSSA compliance certificate expired just hours before a critical government procurement tender closes costs businesses valuable contracts.',
        },
        {
            icon: CalendarX,
            title: 'Forgotten Licence & Permit Renewals',
            description:
                'Municipal trading licences, health inspection receipts, and annual returns slip by without centralized reminders, risking municipal enforcement, commercial disruptions, and statutory penalties.',
        },
        {
            icon: FolderSync,
            title: 'Scattered Records Across Chats & Folders',
            description:
                'Certificates of incorporation, CR14 director lists, registered office filings, and receipts end up scattered across emails, WhatsApp messages, individual laptops, and physical lever-arch files.',
        },
        {
            icon: Compass,
            title: 'Difficulty Knowing What to Do Next',
            description:
                'Entrepreneurs and growing teams often lack a structured, step-by-step roadmap showing which prerequisite registrations must be completed before applying for subsequent licences.',
        },
    ];

    return (
        <section className="py-20 lg:py-28 bg-[#F7F8FA] border-b border-slate-200" id="challenges">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span className="text-xs font-bold uppercase tracking-wider text-[#19766F] bg-[#19766F]/10 px-3 py-1 rounded-full border border-[#19766F]/20">
                        The Regulatory Reality in Zimbabwe
                    </span>
                    <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-[#14263D]">
                        Compliance shouldn't slow your business down.
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        Zimbabwean entrepreneurs, finance teams, and company secretaries spend countless hours navigating
                        complex, fragmented compliance rules. Here are the everyday hurdles we help you solve:
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    {problems.map((problem, idx) => {
                        const Icon = problem.icon;
                        return (
                            <div
                                key={idx}
                                className="bg-white rounded-xl p-6 sm:p-7 border border-slate-200/90 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between"
                            >
                                <div className="space-y-3.5">
                                    <div className="h-11 w-11 rounded-lg bg-[#14263D]/5 text-[#14263D] flex items-center justify-center border border-slate-200 shrink-0">
                                        <Icon className="w-5 h-5 text-[#19766F]" />
                                    </div>
                                    <h3 className="text-base font-bold text-[#14263D] leading-snug">
                                        {problem.title}
                                    </h3>
                                    <p className="text-sm text-slate-600 leading-relaxed font-normal">
                                        {problem.description}
                                    </p>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
