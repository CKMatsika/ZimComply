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
    Building2,
    Shield,
    ArrowUpRight,
    CheckCircle2,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

export default function ReportsIndex() {
    const forecasts = [
        {
            period: 'November 2026',
            zimraUsd: '$1,420.50',
            nssaUsd: '$378.00',
            zimdefUsd: '$142.00',
            councilUsd: '$0.00',
            totalUsd: '$1,940.50',
            totalZig: '19,825.00 ZiG',
            status: 'UPCOMING',
            statusLabel: 'Due 10-15 Nov',
            statusVariant: 'warning' as const,
        },
        {
            period: 'December 2026',
            zimraUsd: '$1,420.50',
            nssaUsd: '$378.00',
            zimdefUsd: '$142.00',
            councilUsd: '$450.00',
            totalUsd: '$2,390.50',
            totalZig: '19,825.00 ZiG',
            status: 'PROJECTED',
            statusLabel: 'Annual Licences Due',
            statusVariant: 'info' as const,
        },
        {
            period: 'January 2027',
            zimraUsd: '$1,650.00',
            nssaUsd: '$378.00',
            zimdefUsd: '$142.00',
            councilUsd: '$0.00',
            totalUsd: '$2,170.00',
            totalZig: '21,900.00 ZiG',
            status: 'PROJECTED',
            statusLabel: 'Q1 Schedule',
            statusVariant: 'neutral' as const,
        },
    ];

    return (
        <AppLayout
            title="Compliance Finance & Statutory Reports"
            subtitle="Forecast upcoming statutory liabilities, generate multi-currency cash flows, and export certified compliance audit packs."
        >
            <div className="space-y-6">
                {/* Dossier Pack Export Banner */}
                <div className="bg-[#14263D] text-white p-5 sm:p-6 rounded-xl border border-[#1C3554] flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Badge variant="teal" size="sm">
                                Procurement & Banking Dossier
                            </Badge>
                            <span className="text-xs text-slate-300">
                                Tamper-Evident Compilation
                            </span>
                        </div>
                        <h2 className="font-heading font-extrabold text-lg sm:text-xl text-white">
                            Unified Corporate Compliance Audit Pack (ZIP / PDF)
                        </h2>
                        <p className="text-xs text-slate-300 max-w-2xl leading-relaxed">
                            Compile your Incorporation CR14, ZIMRA ITF263, NSSA clearance certificate, and municipal operating licences into a single verifiable compliance submission pack.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Button
                            variant="primary"
                            size="md"
                            icon={<Download className="h-4 w-4" />}
                        >
                            Generate Certified Audit Pack
                        </Button>
                    </div>
                </div>

                {/* 3 Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">90-Day USD Liability</span>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1">$6,501.00</div>
                            <span className="text-[11px] text-slate-500">PAYE, NSSA, ZIMDEF, Council</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">90-Day ZiG Liability</span>
                            <div className="text-2xl font-heading font-extrabold text-[#19766F] mt-1">61,550.00 ZiG</div>
                            <span className="text-[11px] text-slate-500">Local currency portion</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Audit Pack Status</span>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-1">Ready</div>
                            <span className="text-[11px] text-slate-500">4 verified documents included</span>
                        </CardContent>
                    </Card>
                </div>

                {/* 90-Day Cash-Flow Table */}
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>
                                <Coins className="h-4 w-4 text-[#19766F]" />
                                90-Day Statutory Cash Flow Liability Forecast
                            </CardTitle>
                            <CardDescription>
                                Projected remittance liabilities across gazetted Zimbabwean tax and social security schedules.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Period</th>
                                        <th className="py-3 px-4">ZIMRA (PAYE)</th>
                                        <th className="py-3 px-4">NSSA (POBS)</th>
                                        <th className="py-3 px-4">ZIMDEF (1%)</th>
                                        <th className="py-3 px-4">Council Licences</th>
                                        <th className="py-3 px-4 font-bold text-slate-900">Total USD</th>
                                        <th className="py-3 px-4 font-bold text-[#19766F]">Total ZiG</th>
                                        <th className="py-3 px-4 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {forecasts.map((f, idx) => (
                                        <tr key={idx} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4 font-bold text-slate-900">
                                                {f.period}
                                            </td>
                                            <td className="py-3 px-4 text-slate-700">
                                                {f.zimraUsd}
                                            </td>
                                            <td className="py-3 px-4 text-slate-700">
                                                {f.nssaUsd}
                                            </td>
                                            <td className="py-3 px-4 text-slate-700">
                                                {f.zimdefUsd}
                                            </td>
                                            <td className="py-3 px-4 text-slate-700">
                                                {f.councilUsd}
                                            </td>
                                            <td className="py-3 px-4 font-bold text-slate-900">
                                                {f.totalUsd}
                                            </td>
                                            <td className="py-3 px-4 font-bold text-[#19766F]">
                                                {f.totalZig}
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <Badge variant={f.statusVariant} size="sm">
                                                    {f.statusLabel}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
