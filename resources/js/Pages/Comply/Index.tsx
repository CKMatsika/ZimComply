import React, { useState } from 'react';
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
    Search,
    Filter,
    Coins,
    Clock,
    FileCheck,
    Info,
    ExternalLink,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

export default function ComplyIndex() {
    const [filterTab, setFilterTab] = useState<'ALL' | 'ACTION_REQUIRED' | 'READY' | 'RECONCILED'>('ALL');
    const [searchQuery, setSearchQuery] = useState('');

    const obligations = [
        {
            id: 'obl-1',
            regulator: 'ZIMRA',
            name: 'PAYE & AIDS Levy Monthly Remittance (Form P2)',
            citation: 'Income Tax Act [Chapter 23:06] & National AIDS Council Act',
            period: 'October 2026',
            dueDate: '10 Nov 2026',
            daysLeft: '11 days left',
            status: 'READY_FOR_APPROVAL',
            statusLabel: 'Ready for Review',
            statusVariant: 'warning' as const,
            usdEstimated: '$1,420.50',
            zigEstimated: '14,250.00 ZiG',
            payrollLines: 12,
            actionText: 'Review Form P2',
        },
        {
            id: 'obl-2',
            regulator: 'NSSA',
            name: 'POBS & APWCS Monthly Contribution (Form P4)',
            citation: 'NSSA Act [Chapter 17:04] & SI 393/1993',
            period: 'October 2026',
            dueDate: '10 Nov 2026',
            daysLeft: '11 days left',
            status: 'READY_FOR_APPROVAL',
            statusLabel: 'Ready for Review',
            statusVariant: 'warning' as const,
            usdEstimated: '$378.00',
            zigEstimated: '4,150.00 ZiG',
            payrollLines: 12,
            actionText: 'Review Form P4',
        },
        {
            id: 'obl-3',
            regulator: 'ZIMDEF',
            name: 'Manpower Development 1% Training Levy',
            citation: 'Manpower Planning and Development Act [Chapter 28:02] Section 53',
            period: 'October 2026',
            dueDate: '15 Nov 2026',
            daysLeft: '16 days left',
            status: 'RECONCILED',
            statusLabel: 'Reconciled',
            statusVariant: 'success' as const,
            usdEstimated: '$142.00',
            zigEstimated: '1,425.00 ZiG',
            payrollLines: 12,
            actionText: 'View Assessment',
        },
        {
            id: 'obl-4',
            regulator: 'NEC_ENG',
            name: 'NEC Engineering & Iron/Steel Council Dues',
            citation: 'Labour Act [Chapter 28:01] & Gazetted Engineering CBA',
            period: 'October 2026',
            dueDate: '15 Nov 2026',
            daysLeft: '16 days left',
            status: 'ACTION_REQUIRED',
            statusLabel: 'Grade Capture Required',
            statusVariant: 'danger' as const,
            usdEstimated: '$84.00',
            zigEstimated: '920.00 ZiG',
            payrollLines: 12,
            actionText: 'Capture Grades',
        },
        {
            id: 'obl-5',
            regulator: 'ZIMRA',
            name: 'Value Added Tax (VAT 7 Monthly Return)',
            citation: 'Value Added Tax Act [Chapter 23:12] Section 28; SI 41/2024',
            period: 'October 2026',
            dueDate: '25 Nov 2026',
            daysLeft: '26 days left',
            status: 'ACTION_REQUIRED',
            statusLabel: 'Input Tax Reconciliation',
            statusVariant: 'warning' as const,
            usdEstimated: '$2,150.00',
            zigEstimated: '22,800.00 ZiG',
            payrollLines: 0,
            actionText: 'Reconcile VAT 7',
        },
    ];

    const filteredObligations = obligations.filter((item) => {
        const matchesSearch = item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            item.regulator.toLowerCase().includes(searchQuery.toLowerCase());

        if (!matchesSearch) return false;

        if (filterTab === 'ACTION_REQUIRED') return item.status === 'ACTION_REQUIRED';
        if (filterTab === 'READY') return item.status === 'READY_FOR_APPROVAL';
        if (filterTab === 'RECONCILED') return item.status === 'RECONCILED';
        return true;
    });

    return (
        <AppLayout
            title="Periodic Statutory Compliance & Payroll"
            subtitle="Manage monthly PAYE, NSSA, ZIMDEF, VAT and NEC returns across USD and ZiG multi-currency schedules."
        >
            <div className="space-y-6">
                {/* Header Action Strip */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#19766F]">
                            Current Active Statutory Cycle
                        </span>
                        <h2 className="text-base font-heading font-bold text-slate-900 mt-0.5">
                            October 2026 Payroll & Tax Period
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Dual-currency remittance cycle under gazetted Reserve Bank and ZIMRA exchange rules.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            icon={<FileSpreadsheet className="h-4 w-4 text-[#19766F]" />}
                        >
                            Import Payroll CSV
                        </Button>
                        <Button
                            variant="primary"
                            size="sm"
                            icon={<Download className="h-4 w-4" />}
                        >
                            Export ZIMRA P2 Batch
                        </Button>
                    </div>
                </div>

                {/* 4 Summary Stat Widgets */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total USD Liability</span>
                            <div className="text-xl font-heading font-extrabold text-slate-900 mt-1">$4,174.50</div>
                            <span className="text-[11px] text-slate-500">PAYE, NSSA, ZIMDEF, VAT</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total ZiG Liability</span>
                            <div className="text-xl font-heading font-extrabold text-[#19766F] mt-1">43,545.00 ZiG</div>
                            <span className="text-[11px] text-slate-500">Local currency portion</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Active Staff Payroll</span>
                            <div className="text-xl font-heading font-extrabold text-slate-900 mt-1">12 Employees</div>
                            <span className="text-[11px] text-slate-500">Across 2 wage grades</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Next Statutory Due Date</span>
                            <div className="text-xl font-heading font-extrabold text-[#B84B46] mt-1">10 Nov 2026</div>
                            <span className="text-[11px] text-slate-500">11 days remaining (P2 & P4)</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Table-First Layout */}
                <Card>
                    <CardHeader className="flex-col sm:flex-row sm:items-center justify-between gap-3">
                        {/* Filter Tabs */}
                        <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                            <button
                                type="button"
                                onClick={() => setFilterTab('ALL')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterTab === 'ALL' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                All ({obligations.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterTab('ACTION_REQUIRED')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterTab === 'ACTION_REQUIRED' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Action Required
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterTab('READY')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterTab === 'READY' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Ready for Filing
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterTab('RECONCILED')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterTab === 'RECONCILED' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Reconciled
                            </button>
                        </div>

                        {/* Search Bar */}
                        <div className="relative w-full sm:w-64">
                            <Search className="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search obligations or authorities..."
                                className="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                            />
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Authority & Obligation</th>
                                        <th className="py-3 px-4">Statutory Citation</th>
                                        <th className="py-3 px-4">Due Date</th>
                                        <th className="py-3 px-4">Liability (USD)</th>
                                        <th className="py-3 px-4">Liability (ZiG)</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredObligations.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-slate-900">{item.name}</div>
                                                <div className="text-[10px] text-slate-500 font-semibold">{item.regulator} • Period: {item.period}</div>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-[11px] text-slate-600 max-w-xs">
                                                {item.citation}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <div className="font-semibold text-slate-900">{item.dueDate}</div>
                                                <div className="text-[10px] text-slate-500">{item.daysLeft}</div>
                                            </td>
                                            <td className="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">
                                                {item.usdEstimated}
                                            </td>
                                            <td className="py-3 px-4 font-semibold text-[#19766F] whitespace-nowrap">
                                                {item.zigEstimated}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <Badge variant={item.statusVariant} size="sm">
                                                    {item.statusLabel}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right whitespace-nowrap">
                                                <Button
                                                    variant={item.status === 'ACTION_REQUIRED' ? 'secondary' : 'outline'}
                                                    size="sm"
                                                    icon={<ArrowUpRight className="h-3 w-3" />}
                                                >
                                                    {item.actionText}
                                                </Button>
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
