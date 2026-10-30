import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    ShieldCheck,
    FileSpreadsheet,
    DollarSign,
    Users,
    Calendar,
    ArrowUpRight,
    CheckCircle2,
    AlertCircle,
    Download,
} from 'lucide-react';

export default function ComplyIndex() {
    const monthlyObligations = [
        {
            regulator: 'ZIMRA',
            name: 'PAYE & AIDS Levy Remittance (Form P2)',
            citation: 'Income Tax Act & National AIDS Council Act',
            dueDate: '10 Nov 2026',
            status: 'DRAFT_READY',
            statusColor: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
            usdEstimated: '$1,420.50',
            zigEstimated: '14,250.00 ZiG',
            description: 'Computed from 12 active employee wage lines across USD and ZiG split brackets.',
        },
        {
            regulator: 'NSSA',
            name: 'Monthly POBS & APWCS Remittance (Form P4)',
            citation: 'NSSA Act & SI 393/1993',
            dueDate: '10 Nov 2026',
            status: 'READY_FOR_APPROVAL',
            statusColor: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
            usdEstimated: '$378.00',
            zigEstimated: '4,150.00 ZiG',
            description: '4.5% Employee + 4.5% Employer portion capped at gazetted insurable earnings ceilings.',
        },
        {
            regulator: 'ZIMDEF',
            name: 'Manpower Development 1% Training Levy',
            citation: 'Manpower Planning and Development Act [Chapter 28:02]',
            dueDate: '15 Nov 2026',
            status: 'RECONCILED',
            statusColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
            usdEstimated: '$142.00',
            zigEstimated: '1,425.00 ZiG',
            description: '1% statutory levy assessed on gross monthly wage bill.',
        },
        {
            regulator: 'NEC_ENG',
            name: 'NEC Engineering Council Levies & Dues',
            citation: 'Labour Act CBA SI Reference',
            dueDate: '15 Nov 2026',
            status: 'PENDING_CAPTURE',
            statusColor: 'text-slate-400 bg-slate-800 border-slate-700',
            usdEstimated: '$84.00',
            zigEstimated: '920.00 ZiG',
            description: 'Administration dues calculated on classified trade grades (Grades 1-10).',
        },
    ];

    return (
        <AppLayout
            title="Periodic Statutory Compliance & Payroll"
            subtitle="Manage monthly PAYE, NSSA, ZIMDEF, and NEC returns across USD and ZiG multi-currency schedules."
        >
            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 glass-panel p-5 rounded-xl border border-slate-800">
                    <div>
                        <div className="text-xs uppercase font-semibold text-emerald-400 tracking-wider">Current Period</div>
                        <h2 className="text-lg font-heading font-bold text-white mt-0.5">October 2026 Statutory Cycle</h2>
                    </div>
                    <div className="flex items-center gap-2">
                        <button className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white border border-slate-700 transition-colors">
                            <FileSpreadsheet className="h-4 w-4 text-emerald-400" />
                            <span>Import Payroll CSV</span>
                        </button>
                        <button className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-xs font-bold text-slate-950 transition-colors">
                            <Download className="h-4 w-4" />
                            <span>Export ZIMRA P2 Upload Sheet</span>
                        </button>
                    </div>
                </div>

                {/* Obligation Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {monthlyObligations.map((item, idx) => (
                        <div key={idx} className="glass-card p-5 rounded-xl border border-slate-800 flex flex-col justify-between">
                            <div>
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">{item.regulator}</span>
                                    <span className={`px-2 py-0.5 rounded text-[11px] font-semibold border ${item.statusColor}`}>
                                        {item.status.replace(/_/g, ' ')}
                                    </span>
                                </div>
                                <h3 className="font-heading font-bold text-white text-sm mt-2">{item.name}</h3>
                                <p className="text-[11px] font-mono text-slate-400 mt-0.5">{item.citation}</p>
                                <p className="text-xs text-slate-300 mt-2 leading-relaxed">{item.description}</p>
                            </div>

                            <div className="mt-4 pt-3 border-t border-slate-800/80">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-slate-400">Due: <strong className="text-white">{item.dueDate}</strong></span>
                                    <div className="text-right">
                                        <div className="font-bold text-white">{item.usdEstimated} <span className="text-slate-400 text-[10px]">USD</span></div>
                                        <div className="text-[10px] text-emerald-400">{item.zigEstimated}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
