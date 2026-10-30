import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Award,
    Search,
    FileText,
    CheckCircle2,
    XCircle,
    AlertCircle,
    ArrowUpRight,
    ExternalLink,
} from 'lucide-react';

export default function TenderIndex() {
    const tenders = [
        {
            tenderNumber: 'ZETDC/DOM/34/2026',
            procuringEntity: 'ZETDC (ZESA Holdings)',
            title: 'Supply and Delivery of High-Voltage Steel Transmission Poles and Cross-Arms',
            closingDate: '18 Nov 2026',
            category: 'Engineering & Metal Fabrication (SC004)',
            readinessScore: 80,
            missingItems: ['CIFOZ / ZBCA Category B Membership Certificate'],
        },
        {
            tenderNumber: 'COH/WW/12/2026',
            procuringEntity: 'City of Harare Water Dept',
            title: 'Refurbishment of Morton Jaffray Water Works Pump Components',
            closingDate: '25 Nov 2026',
            category: 'Mechanical Engineering Services (ME002)',
            readinessScore: 100,
            missingItems: [],
        },
    ];

    return (
        <AppLayout
            title="PRAZ Supplier & Tender Intelligence"
            subtitle="Qualify for Zimbabwean public tenders under the Public Procurement and Disposal of Public Assets Act."
        >
            <div className="space-y-6">
                <div className="glass-panel p-5 rounded-xl border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div className="text-xs uppercase font-semibold text-purple-400 tracking-wider">PRAZ e-GP Status</div>
                        <h2 className="text-lg font-heading font-bold text-white mt-0.5">Category SC004 Registered (Valid until Dec 2026)</h2>
                    </div>
                    <button className="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-500 hover:bg-purple-400 text-xs font-bold text-slate-950 transition-colors">
                        <Award className="h-4 w-4" />
                        <span>Manage PRAZ Categories</span>
                    </button>
                </div>

                <div className="space-y-4">
                    <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                        <Search className="h-4 w-4 text-purple-400" />
                        Matched Public Procurement Notices
                    </h3>

                    <div className="space-y-3">
                        {tenders.map((tender, idx) => (
                            <div key={idx} className="glass-card p-5 rounded-xl border border-slate-800 space-y-3">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-xs text-purple-400 font-bold">{tender.tenderNumber}</span>
                                            <span className="text-slate-500">•</span>
                                            <span className="text-xs text-slate-400">{tender.procuringEntity}</span>
                                        </div>
                                        <h4 className="font-heading font-bold text-white text-sm mt-1">{tender.title}</h4>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <div className="text-xs text-slate-400">Closing: <strong className="text-white">{tender.closingDate}</strong></div>
                                        <div className="mt-1 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            Bid Readiness: {tender.readinessScore}%
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-3 border-t border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="text-slate-400">Category:</span>
                                        <span className="font-semibold text-slate-200">{tender.category}</span>
                                    </div>

                                    {tender.missingItems.length > 0 ? (
                                        <div className="flex items-center gap-1 text-amber-400">
                                            <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                                            <span>Missing: {tender.missingItems.join(', ')}</span>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-1 text-emerald-400 font-semibold">
                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                            <span>100% Eligible (All Mandatory Certificates Present)</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
