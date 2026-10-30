import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Compass,
    Building2,
    CheckCircle2,
    AlertCircle,
    ArrowRight,
    Scale,
    FileCheck,
    Coins,
    Clock,
    HelpCircle,
    Layers,
} from 'lucide-react';

interface RegisterIndexProps {
    sectors: { code: string; name: string }[];
    jurisdictions: { code: string; name: string }[];
}

export default function RegisterIndex({ sectors, jurisdictions }: RegisterIndexProps) {
    const [selectedSector, setSelectedSector] = useState('ENGINEERING');
    const [selectedJurisdiction, setSelectedJurisdiction] = useState('ZW-HARARE');
    const [headcount, setHeadcount] = useState('12');
    const [premisesType, setPremisesType] = useState('LEASED_WORKSHOP');
    const [generated, setGenerated] = useState(true);

    const roadmapItems = [
        {
            title: 'ZIMRA Tax Registration & TIN Activation',
            authority: 'ZIMRA',
            type: 'MANDATORY_STATUTORY',
            legalBasis: 'Income Tax Act [Chapter 23:06] Section 73',
            feeEstimate: 'Free (Statutory Registration)',
            timeline: '3 - 5 Business Days',
            prerequisites: ['Certificate of Incorporation', 'CR6 / CR14 List of Directors'],
            evidenceRequired: 'Official ZIMRA TIN Confirmation Notice',
            status: 'COMPLIANT',
            statusColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
        },
        {
            title: 'NSSA Employer Registration (POBS & APWCS)',
            authority: 'NSSA',
            type: 'MANDATORY_STATUTORY',
            legalBasis: 'National Social Security Authority Act [Chapter 17:04] & SI 393/1993',
            feeEstimate: 'Free (Monthly contributions commence upon hiring)',
            timeline: '5 - 7 Business Days',
            prerequisites: ['ZIMRA TIN', 'Director National IDs'],
            evidenceRequired: 'NSSA Employer Registration Certificate (Form P3)',
            status: 'IN_PROGRESS',
            statusColor: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
        },
        {
            title: 'NEC for Engineering, Iron & Steel Industry Registration',
            authority: 'NEC_ENG',
            type: 'CBA_COLLECTIVE_AGREEMENT',
            legalBasis: 'Labour Act [Chapter 28:01] & Gazetted Engineering CBA',
            feeEstimate: 'Annual Council Administration Dues (Employee/Employer Split)',
            timeline: '7 - 10 Business Days',
            prerequisites: ['NSSA Number', 'Employee Grade Register (Grades 1-10)'],
            evidenceRequired: 'NEC Council Registration Certificate',
            status: 'ACTION_REQUIRED',
            statusColor: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
        },
        {
            title: 'City of Harare Municipal Operating & Shop Licence',
            authority: 'City of Harare',
            type: 'MANDATORY_STATUTORY',
            legalBasis: 'Urban Councils Act [Chapter 29:15] & Shop Licences Act [Chapter 14:17]',
            feeEstimate: 'Approx. $350 - $650 USD (Based on workshop square meterage)',
            timeline: '14 - 21 Business Days',
            prerequisites: ['Health & Hygiene Inspection', 'City Fire Brigade Clearance', 'Lease Agreement'],
            evidenceRequired: 'Valid Municipal Operating Licence Disc / Certificate',
            status: 'ACTION_REQUIRED',
            statusColor: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
        },
        {
            title: 'Engineering Council of Zimbabwe (ECZ) Firm Licence',
            authority: 'ECZ',
            type: 'PROFESSIONAL_REGULATION',
            legalBasis: 'Engineering Council Act [Chapter 27:22]',
            feeEstimate: 'Annual Corporate Practice Fee',
            timeline: '10 - 14 Business Days',
            prerequisites: ['Registered PrEng / TechEng Director Credentials'],
            evidenceRequired: 'ECZ Corporate Practising Certificate',
            status: 'VERIFICATION_REQUIRED',
            statusColor: 'text-purple-400 bg-purple-500/10 border-purple-500/20',
        },
    ];

    return (
        <AppLayout
            title="Registration Discovery & Statutory Roadmap"
            subtitle="Determine applicable Zimbabwean registrations, legal basis, fee estimates, and prerequisites."
        >
            <div className="space-y-6">
                {/* Questionnaire Controls Box */}
                <div className="glass-panel p-6 rounded-2xl border border-slate-800">
                    <div className="flex items-center gap-2 mb-4">
                        <Compass className="h-5 w-5 text-emerald-400" />
                        <h2 className="text-base font-heading font-bold text-white">
                            Applicability Discovery Questionnaire
                        </h2>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Primary Industry Sector
                            </label>
                            <select
                                value={selectedSector}
                                onChange={(e) => setSelectedSector(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                {sectors.map((s) => (
                                    <option key={s.code} value={s.code}>{s.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Operating Jurisdiction
                            </label>
                            <select
                                value={selectedJurisdiction}
                                onChange={(e) => setSelectedJurisdiction(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                {jurisdictions.map((j) => (
                                    <option key={j.code} value={j.code}>{j.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Estimated Headcount
                            </label>
                            <input
                                type="number"
                                value={headcount}
                                onChange={(e) => setHeadcount(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Operating Premises
                            </label>
                            <select
                                value={premisesType}
                                onChange={(e) => setPremisesType(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                <option value="LEASED_WORKSHOP">Industrial Workshop (Leased)</option>
                                <option value="OWNED_PROPERTY">Commercial Property (Owned)</option>
                                <option value="RETAIL_SHOP">Retail Commercial Storefront</option>
                                <option value="OFFICE_SUITE">Commercial Office Suite</option>
                            </select>
                        </div>
                    </div>

                    <div className="mt-4 pt-4 border-t border-slate-800/80 flex justify-end">
                        <button
                            onClick={() => setGenerated(true)}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-heading font-bold text-xs shadow-md transition-all"
                        >
                            <span>Re-Evaluate Statutory Obligations</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                {/* Personalized Roadmap Results */}
                {generated && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                                    <Layers className="h-4 w-4 text-emerald-400" />
                                    Tailored Registration Roadmap (5 Identified Obligations)
                                </h3>
                                <p className="text-xs text-slate-400 mt-0.5">
                                    Citations grounded in official Zimbabwean statutes. Tasks can proceed in parallel where prerequisites permit.
                                </p>
                            </div>
                            <span className="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                1 of 5 Completed
                            </span>
                        </div>

                        <div className="space-y-3">
                            {roadmapItems.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="glass-card p-5 rounded-xl border border-slate-800 hover:border-slate-700 transition-colors"
                                >
                                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-2 pb-3 border-b border-slate-800/80">
                                        <div className="flex items-center gap-3">
                                            <span className="h-7 w-7 rounded-lg bg-slate-800 text-slate-200 flex items-center justify-center font-bold text-xs">
                                                {idx + 1}
                                            </span>
                                            <div>
                                                <h4 className="font-heading font-bold text-white text-sm">{item.title}</h4>
                                                <div className="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
                                                    <span className="text-emerald-400 font-medium">{item.authority}</span>
                                                    <span>•</span>
                                                    <span className="font-mono text-[11px] text-slate-300">{item.legalBasis}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className={`px-2.5 py-1 rounded text-[11px] font-semibold border ${item.statusColor}`}>
                                                {item.status.replace(/_/g, ' ')}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Item Details */}
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-3 text-xs">
                                        <div>
                                            <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Official Fees</span>
                                            <div className="text-slate-200 mt-0.5">{item.feeEstimate}</div>
                                        </div>
                                        <div>
                                            <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Estimated Timeline</span>
                                            <div className="text-slate-200 mt-0.5">{item.timeline}</div>
                                        </div>
                                        <div>
                                            <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Evidence Required</span>
                                            <div className="text-slate-200 mt-0.5">{item.evidenceRequired}</div>
                                        </div>
                                    </div>

                                    {/* Prerequisites */}
                                    <div className="mt-3 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2">
                                            <span className="text-slate-500 text-[11px]">Prerequisites:</span>
                                            <div className="flex flex-wrap gap-1.5">
                                                {item.prerequisites.map((req, rIdx) => (
                                                    <span key={rIdx} className="px-2 py-0.5 rounded bg-slate-800 text-[10px] text-slate-300">
                                                        {req}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <button className="text-emerald-400 hover:text-emerald-300 font-semibold text-xs">
                                            Prepare Dossier →
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
