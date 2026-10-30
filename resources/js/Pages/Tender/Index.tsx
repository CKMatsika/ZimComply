import React, { useState } from 'react';
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
    Shield,
    Check,
    AlertTriangle,
    Layers,
    FileCheck,
    Coins,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

export default function TenderIndex() {
    // Procurement Prerequisites Checklist under PPDPA Act [Chapter 22:23]
    const prerequisites = [
        {
            title: 'ZIMRA ITF263 Tax Clearance Certificate',
            citation: 'Income Tax Act Section 80 & PPDPA Act Section 34',
            status: 'PENDING_REVIEW',
            statusLabel: 'Filing Audit Needed',
            statusVariant: 'warning' as const,
            impact: 'Non-compliant bidders are disqualified from public tender evaluation and subject to 30% withholding tax.',
            actionUrl: '/comply',
            actionText: 'Review Tax Returns',
        },
        {
            title: 'NSSA Compliance Clearance Certificate',
            citation: 'NSSA Act [Chapter 17:04]',
            status: 'COMPLIANT',
            statusLabel: 'Valid & Verified',
            statusVariant: 'success' as const,
            impact: 'Confirms zero outstanding POBS/APWCS arrears. Valid for October 2026.',
            actionUrl: '/documents',
            actionText: 'View Certificate',
        },
        {
            title: 'Certificate of Incorporation & CR14/CR6',
            citation: 'COBE Act [Chapter 24:31]',
            status: 'COMPLIANT',
            statusLabel: 'Valid & Verified',
            statusVariant: 'success' as const,
            impact: 'Confirms corporate legal identity and list of active company directors.',
            actionUrl: '/documents',
            actionText: 'View CR14',
        },
        {
            title: 'City of Harare Commercial Operating Licence',
            citation: 'Urban Councils Act [Chapter 29:15]',
            status: 'COMPLIANT',
            statusLabel: 'Valid (Expires 31 Dec 2026)',
            statusVariant: 'success' as const,
            impact: 'Proof of lawful commercial workshop and operating premises.',
            actionUrl: '/renew',
            actionText: 'Inspect Licence',
        },
        {
            title: 'Certified Financial Statements (Past 2 Years)',
            citation: 'Standard Bidding Document (SBD) Qualification Criteria',
            status: 'MISSING',
            statusLabel: 'Missing from Vault',
            statusVariant: 'danger' as const,
            impact: 'Required for competitive tenders exceeding threshold $100,000 USD.',
            actionUrl: '/documents',
            actionText: 'Upload Financials',
        },
        {
            title: 'NEC Engineering Council Registration Proof',
            citation: 'Labour Act [Chapter 28:01] Section 82',
            status: 'IN_PROGRESS',
            statusLabel: 'Grade Review In Progress',
            statusVariant: 'warning' as const,
            impact: 'Demonstrates labour law and minimum collective wage compliance.',
            actionUrl: '/comply',
            actionText: 'Complete NEC Dues',
        },
    ];

    // PRAZ Registered Categories
    const prazCategories = [
        {
            code: 'SC004',
            name: 'Structural Steelwork, Metal Fabrication & Mechanical Contracting',
            status: 'ACTIVE_REGISTERED',
            validUntil: '31 Dec 2026',
            statusVariant: 'success' as const,
            annualFee: '$120.00 USD / year',
        },
        {
            code: 'GS001',
            name: 'General Hardware, Industrial Supplies & Engineering Consumables',
            status: 'RENEWAL_RECOMMENDED',
            validUntil: 'Expired',
            statusVariant: 'warning' as const,
            annualFee: '$120.00 USD / year',
        },
    ];

    const compliantCount = prerequisites.filter((p) => p.status === 'COMPLIANT').length;
    const readinessScore = Math.round((compliantCount / prerequisites.length) * 100);

    return (
        <AppLayout
            title="Public Procurement & Tender Readiness"
            subtitle="Manage supplier qualification, PRAZ e-GP accreditation, and mandatory statutory prerequisites under the PPDPA Act."
        >
            <div className="space-y-6">
                {/* PRAZ Authority Accreditation Header */}
                <div className="bg-[#14263D] text-white rounded-xl p-5 sm:p-6 shadow-sm border border-[#1C3554] flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Badge variant="teal" size="sm">
                                Procurement Regulatory Authority of Zimbabwe (PRAZ)
                            </Badge>
                            <span className="text-xs text-slate-300">
                                PPDPA Act [Chapter 22:23]
                            </span>
                        </div>
                        <h2 className="font-heading font-extrabold text-lg sm:text-xl text-white">
                            PRAZ Supplier Readiness: {readinessScore}% Compliant
                        </h2>
                        <p className="text-xs text-slate-300 max-w-2xl leading-relaxed">
                            Public procuring entities (Government ministries, City Councils, ZESA, TelOne, NRZ) are prohibited by law from awarding tenders to suppliers without valid ZIMRA tax clearance and PRAZ category accreditation.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Button
                            variant="primary"
                            size="sm"
                            icon={<ExternalLink className="h-3.5 w-3.5" />}
                            onClick={() => window.open('https://egp.praz.org.zw', '_blank')}
                        >
                            Open PRAZ e-GP Portal
                        </Button>
                    </div>
                </div>

                {/* 3 Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold uppercase tracking-wider">
                                <span>Bid Qualification Score</span>
                                <Award className="h-4 w-4 text-[#19766F]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-2">
                                {readinessScore}% Ready
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">{compliantCount} of {prerequisites.length} prerequisites satisfied</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold uppercase tracking-wider">
                                <span>Active PRAZ Categories</span>
                                <Layers className="h-4 w-4 text-[#19766F]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-slate-900 mt-2">
                                1 Active
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">SC004 Metal Fabrication valid for 2026</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold uppercase tracking-wider">
                                <span>Disqualifying Blockers</span>
                                <AlertTriangle className="h-4 w-4 text-[#B84B46]" />
                            </div>
                            <div className="text-2xl font-heading font-extrabold text-[#933632] mt-2">
                                2 Blockers
                            </div>
                            <p className="text-[11px] text-slate-500 mt-1">ITF263 Tax Clearance & Financials</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Mandatory Prerequisites Audit Table */}
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>
                                <Shield className="h-4 w-4 text-[#19766F]" />
                                Mandatory Public Procurement Prerequisites Audit
                            </CardTitle>
                            <CardDescription>
                                Statutory conditions precedent required for public tender awards across all Zimbabwean parastatals and local authorities.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Statutory Document / Prerequisite</th>
                                        <th className="py-3 px-4">Legal Citation</th>
                                        <th className="py-3 px-4">Procurement Impact</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4 text-right">Required Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {prerequisites.map((p, idx) => (
                                        <tr key={idx} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="py-3 px-4 font-bold text-slate-900">
                                                {p.title}
                                            </td>
                                            <td className="py-3 px-4 font-mono text-[11px] text-slate-600 max-w-xs">
                                                {p.citation}
                                            </td>
                                            <td className="py-3 px-4 text-slate-600 max-w-sm">
                                                {p.impact}
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <Badge variant={p.statusVariant} size="sm">
                                                    {p.statusLabel}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right whitespace-nowrap">
                                                <Button
                                                    href={p.actionUrl}
                                                    variant={p.status === 'COMPLIANT' ? 'outline' : 'secondary'}
                                                    size="sm"
                                                    icon={<ArrowUpRight className="h-3 w-3" />}
                                                >
                                                    {p.actionText}
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* PRAZ Categories Module */}
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>
                                <Award className="h-4 w-4 text-[#19766F]" />
                                PRAZ e-GP Supplier Classifications
                            </CardTitle>
                            <CardDescription>
                                Accreditations held under the Government of Zimbabwe Electronic Government Procurement (e-GP) platform.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-slate-100 text-xs">
                            {prazCategories.map((cat, idx) => (
                                <div key={idx} className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/60 transition-colors">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-bold text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-800">
                                                Category: {cat.code}
                                            </span>
                                            <Badge variant={cat.statusVariant} size="sm">
                                                {cat.status.replace(/_/g, ' ')}
                                            </Badge>
                                        </div>
                                        <h4 className="font-heading font-bold text-sm text-slate-900">{cat.name}</h4>
                                        <div className="text-[11px] text-slate-500">
                                            Accreditation Tariff: {cat.annualFee} • Valid through {cat.validUntil}
                                        </div>
                                    </div>
                                    <div className="shrink-0">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            icon={<ArrowUpRight className="h-3 w-3" />}
                                        >
                                            Manage Category
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
