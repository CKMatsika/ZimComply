import React, { useState } from 'react';
import { ChevronDown } from 'lucide-react';

export function FaqSection() {
    const [openIndex, setOpenIndex] = useState<number | null>(0);

    const faqs = [
        {
            question: 'Is ZimComply AI an official government portal or agency?',
            answer:
                'No. ZimComply AI is an independent compliance technology platform. We are not a government department, statutory regulator, or law firm. We help Zimbabwean businesses, accountants, and consultants discover applicable statutory obligations, organize supporting documents, and track compliance deadlines.',
        },
        {
            question: 'How does ZimComply determine which registrations apply to my business?',
            answer:
                'Our engine uses a deterministic, version-controlled rules system based directly on enacted Zimbabwean statutes and statutory instruments. By evaluating your entity type (Private Limited vs PBC), industry activities, employee headcount, annual turnover, and operational jurisdictions, the system matches your profile against approved regulatory rule parameters.',
        },
        {
            question: 'Does ZimComply automatically issue certificates or submit tax returns?',
            answer:
                'No. Official registration certificates and statutory approvals are issued exclusively by the respective authorities—such as CIPZ for certificates of incorporation, ZIMRA for tax clearances, and PRAZ for procurement registration. ZimComply assists you in identifying prerequisites, preparing required documents, and tracking the status of each filing.',
        },
        {
            question: 'Which Zimbabwean statutory authorities and frameworks are covered?',
            answer:
                'The platform catalogues obligations under the Companies and Other Business Entities Act (CIPZ), the Income Tax Act and VAT Act (ZIMRA), the National Social Security Authority Act (NSSA), the Manpower Planning and Development Act (ZIMDEF), the Public Procurement and Disposal of Public Assets Act (PRAZ), and municipal licensing under the Urban Councils Act.',
        },
        {
            question: 'Can accountants and consultants manage multiple client companies?',
            answer:
                'Yes. ZimComply AI features a strict multi-tenant architecture. Accounting practitioners and corporate secretarial firms can create separate, isolated organisation workspaces for each client, maintaining distinct document vaults, assessment histories, and user access controls.',
        },
        {
            question: 'How is my business data and uploaded documentation kept secure?',
            answer:
                'All document uploads are cryptographically hashed using SHA-256 for audit provenance, stored in private encrypted storage disks, and protected by PostgreSQL row-level tenant security scopes. Cross-tenant data access is strictly prevented by automated architectural controls.',
        },
    ];

    const toggle = (idx: number) => {
        setOpenIndex(openIndex === idx ? null : idx);
    };

    return (
        <section className="py-20 lg:py-28 bg-[#F7F8FA] border-b border-slate-200" id="faq">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center space-y-4 mb-16">
                    <span className="text-xs font-bold uppercase tracking-wider text-[#19766F] bg-[#19766F]/10 px-3 py-1 rounded-full border border-[#19766F]/20">
                        Frequently Asked Questions
                    </span>
                    <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-[#14263D]">
                        Everything You Need to Know
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        Clear, transparent answers about the ZimComply platform, regulatory coverage, and data governance.
                    </p>
                </div>

                <div className="space-y-4">
                    {faqs.map((faq, idx) => {
                        const isOpen = openIndex === idx;
                        return (
                            <div
                                key={idx}
                                className="bg-white rounded-xl border border-slate-200/90 shadow-xs overflow-hidden transition-all"
                            >
                                <button
                                    type="button"
                                    onClick={() => toggle(idx)}
                                    className="w-full px-6 py-5 text-left flex items-center justify-between gap-4 focus:outline-none focus:bg-slate-50 transition-colors"
                                    aria-expanded={isOpen}
                                >
                                    <span className="font-bold text-base text-[#14263D]">
                                        {faq.question}
                                    </span>
                                    <ChevronDown
                                        className={`w-5 h-5 text-slate-400 shrink-0 transition-transform duration-200 ${
                                            isOpen ? 'rotate-180 text-[#19766F]' : ''
                                        }`}
                                    />
                                </button>
                                {isOpen && (
                                    <div className="px-6 pb-5 pt-1 text-sm text-slate-600 leading-relaxed border-t border-slate-100 font-normal">
                                        {faq.answer}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
