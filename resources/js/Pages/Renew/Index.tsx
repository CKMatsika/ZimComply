import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    RefreshCw,
    Building2,
    Car,
    Shield,
    Clock,
    AlertTriangle,
    CheckCircle2,
    Calendar,
    ArrowUpRight,
    Search,
    FileText,
    Plus,
    Flame,
    Radio,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

export default function RenewIndex() {
    const [filterCategory, setFilterCategory] = useState<'ALL' | 'MUNICIPAL' | 'FLEET' | 'SAFETY'>('ALL');
    const [searchQuery, setSearchQuery] = useState('');

    const licenses = [
        {
            id: 'lic-1',
            title: 'City of Harare Commercial Operating & Shop Licence',
            category: 'MUNICIPAL',
            identifier: 'COH-LIC-2025-8842',
            authority: 'City of Harare (Urban Councils Act [Chapter 29:15])',
            premises: 'Harare Industrial Workshop & HQ',
            expiry: '31 Dec 2026',
            daysRemaining: 62,
            status: 'ACTIVE_WINDOW',
            statusLabel: 'Renewal Window Open',
            statusVariant: 'warning' as const,
            feeEstimate: 'Approx. $450.00 USD / year',
            prereqs: 'Health Inspection, Fire Clearance, Property Lease',
        },
        {
            id: 'lic-2',
            title: 'Harare Fire Brigade Safety Clearance Certificate',
            category: 'SAFETY',
            identifier: 'FIRE-HRE-9921',
            authority: 'Harare Fire & Emergency Services',
            premises: 'Workshop & Fabrication Bay',
            expiry: '15 Jan 2027',
            daysRemaining: 77,
            status: 'GOOD_STANDING',
            statusLabel: 'Compliant (Good Standing)',
            statusVariant: 'success' as const,
            feeEstimate: '$120.00 USD',
            prereqs: 'Serviced Extinguishers & Fire Exit Signage',
        },
        {
            id: 'lic-3',
            title: 'Fleet Vehicle: Isuzu NPR 4.5T (Reg: AEV 4589)',
            category: 'FLEET',
            identifier: 'VIN: MP2389148012',
            authority: 'ZINARA + Insurance Council of Zimbabwe + ZBC',
            premises: 'Fleet Asset Unit #04',
            expiry: '30 Nov 2026',
            daysRemaining: 31,
            status: 'URGENT',
            statusLabel: 'Expires in 31 Days',
            statusVariant: 'danger' as const,
            feeEstimate: '$95.00 USD (Combo ZINARA + Insurance)',
            prereqs: 'Certificate of Fitness (VID)',
        },
        {
            id: 'lic-4',
            title: 'Fleet Vehicle: Toyota Hilux D4D (Reg: AFC 1120)',
            category: 'FLEET',
            identifier: 'VIN: TR8839104812',
            authority: 'ZINARA + Third Party Insurance + ZBC',
            premises: 'Field Operations Unit #01',
            expiry: '31 Jan 2027',
            daysRemaining: 93,
            status: 'GOOD_STANDING',
            statusLabel: 'Compliant (Good Standing)',
            statusVariant: 'success' as const,
            feeEstimate: '$75.00 USD',
            prereqs: 'Current Insurance Policy',
        },
    ];

    const filteredLicenses = licenses.filter((lic) => {
        const matchesCategory = filterCategory === 'ALL' || lic.category === filterCategory;
        const matchesSearch = lic.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            lic.identifier.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    return (
        <AppLayout
            title="Licence Renewals & Asset Compliance"
            subtitle="Manage recurring municipal licences, fire safety certificates, ZINARA vehicle licences, and fleet roadworthiness."
        >
            <div className="space-y-6">
                {/* Header Action Bar */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#19766F]">
                            Statutory Expiry Register
                        </span>
                        <h2 className="text-base font-heading font-bold text-slate-900 mt-0.5">
                            Active Operating Licences & Fleet Asset Registrations
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Automated tracking prevents municipal shutdowns, impoundments, and late penalty charges.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="primary"
                            size="sm"
                            icon={<Plus className="h-4 w-4" />}
                        >
                            Register New Licence or Vehicle
                        </Button>
                    </div>
                </div>

                {/* 3 Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold uppercase tracking-wider">
                                <span>Urgent Renewals (&lt; 45 Days)</span>
                                <AlertTriangle className="h-4 w-4 text-[#B84B46]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-[#933632] mt-2">
                                1 Asset
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">Vehicle AEV 4589 due 30 Nov</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold uppercase tracking-wider">
                                <span>Window Open (45–90 Days)</span>
                                <Clock className="h-4 w-4 text-[#D8A34B]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-2">
                                2 Licences
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">City of Harare & Fire Clearance</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold uppercase tracking-wider">
                                <span>Good Standing (&gt; 90 Days)</span>
                                <CheckCircle2 className="h-4 w-4 text-[#19766F]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-2">
                                1 Asset
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">Toyota Hilux AFC 1120</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Table-First Layout */}
                <Card>
                    <CardHeader className="flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                            <button
                                type="button"
                                onClick={() => setFilterCategory('ALL')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterCategory === 'ALL' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                All Licences ({licenses.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterCategory('MUNICIPAL')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterCategory === 'MUNICIPAL' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Municipal Permits
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterCategory('SAFETY')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterCategory === 'SAFETY' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Fire Safety
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilterCategory('FLEET')}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-colors ${
                                    filterCategory === 'FLEET' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                Fleet & ZINARA
                            </button>
                        </div>

                        <div className="relative w-full sm:w-64">
                            <Search className="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search licence or identifier..."
                                className="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                            />
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Licence / Asset Title</th>
                                        <th className="py-3 px-4">Identifier / Registration</th>
                                        <th className="py-3 px-4">Issuing Authority</th>
                                        <th className="py-3 px-4">Expiry Date</th>
                                        <th className="py-3 px-4">Estimated Renewal Fee</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredLicenses.map((lic) => (
                                        <tr key={lic.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-slate-900">{lic.title}</div>
                                                <div className="text-[10px] text-slate-500">{lic.premises}</div>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-[11px] text-slate-600">
                                                {lic.identifier}
                                            </td>
                                            <td className="py-3 px-4 text-slate-700 max-w-xs truncate">
                                                {lic.authority}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <div className="font-semibold text-slate-900">{lic.expiry}</div>
                                                <div className="text-[10px] text-slate-500">{lic.daysRemaining} days left</div>
                                            </td>
                                            <td className="py-3 px-4 text-slate-700 whitespace-nowrap">
                                                {lic.feeEstimate}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <Badge variant={lic.statusVariant} size="sm">
                                                    {lic.statusLabel}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right whitespace-nowrap">
                                                <Button
                                                    variant={lic.status === 'URGENT' ? 'secondary' : 'outline'}
                                                    size="sm"
                                                    icon={<ArrowUpRight className="h-3 w-3" />}
                                                >
                                                    Prepare Renewal
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
