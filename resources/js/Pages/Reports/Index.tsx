import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    BarChart3,
    Download,
    FileSpreadsheet,
    DollarSign,
    Calendar,
    Layers,
    FileCheck,
    Coins,
} from 'lucide-react';

export default function ReportsIndex() {
    const forecasts = [
        { period: 'November 2026', zimraUsd: '$1,420.50', nssaUsd: '$378.00', councilUsd: '$0.00', totalUsd: '$1,798.50', totalZig: '18,400.00 ZiG' },
        { period: 'December 2026', zimraUsd: '$1,420.50', nssaUsd: '$378.00', councilUsd: '$450.00', totalUsd: '$2,248.50', totalZig: '18,400.00 ZiG' },
        { period: 'January 2027', zimraUsd: '$1,650.00', nssaUsd: '$378.00', councilUsd: '$0.00', totalUsd: '$2,028.50', totalZig: '20,500.00 ZiG' },
    ];

    return (
        <AppLayout
            title="Compliance Finance & Regulatory Reports"
            subtitle="Forecast upcoming statutory liabilities, generate multi-currency cash flows, and export compliance audit packs."
        >
            <div className="space-y-6">
                {/* Audit Pack Export Banner */}
                <div className="glass-panel p-6 rounded-2xl border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="text-xs font-semibold uppercase text-emerald-400 tracking-wider">Tender & Banking Dossier</div>
                        <h2 className="text-lg font-heading font-bold text-white mt-0.5">
                            Unified Compliance Audit Pack (ZIP / PDF)
                        </h2>
                        <p className="text-xs text-slate-300 mt-1 max-w-xl">
                            Compile your Incorporation CR14, ZIMRA ITF263, NSSA clearance certificate, and municipal licences into a certified, tamper-evident submission pack.
                        </p>
                    </div>
                    <button className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-heading font-bold text-xs shadow-lg transition-all shrink-0">
                        <Download className="h-4 w-4" />
                        <span>Generate Audit Pack</span>
                    </button>
                </div>

                {/* 90-Day Cash-Flow Liability Forecast */}
                <div className="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                    <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                        <Coins className="h-4 w-4 text-emerald-400" />
                        90-Day Statutory Cash Flow Liability Forecast
                    </h3>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                <tr>
                                    <th className="py-3 px-4">Period</th>
                                    <th className="py-3 px-4">ZIMRA (PAYE)</th>
                                    <th className="py-3 px-4">NSSA (POBS)</th>
                                    <th className="py-3 px-4">Council Licences</th>
                                    <th className="py-3 px-4 font-bold text-white">Total USD Liability</th>
                                    <th className="py-3 px-4 font-bold text-emerald-400">Total ZiG Liability</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800 text-slate-200">
                                {forecasts.map((row, idx) => (
                                    <tr key={idx} className="hover:bg-slate-900/40">
                                        <td className="py-3 px-4 font-medium text-white">{row.period}</td>
                                        <td className="py-3 px-4">{row.zimraUsd}</td>
                                        <td className="py-3 px-4">{row.nssaUsd}</td>
                                        <td className="py-3 px-4">{row.councilUsd}</td>
                                        <td className="py-3 px-4 font-bold text-white">{row.totalUsd}</td>
                                        <td className="py-3 px-4 font-semibold text-emerald-400">{row.totalZig}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
