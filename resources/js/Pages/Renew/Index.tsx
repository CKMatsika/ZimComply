import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    RefreshCw,
    Building2,
    Car,
    Shield,
    Radio,
    Clock,
    AlertTriangle,
    CheckCircle2,
    Calendar,
} from 'lucide-react';

export default function RenewIndex() {
    const licenses = [
        {
            title: 'City of Harare Shop & Operating Licence',
            type: 'Municipal Licence',
            identifier: 'COH-LIC-2025-8842',
            authority: 'City of Harare',
            expiry: '31 Dec 2026',
            daysRemaining: 62,
            status: 'ACTIVE_RENEWAL_WINDOW',
            statusColor: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
        },
        {
            title: 'Commercial Fleet Vehicle (AEV 4589) - Isuzu NPR',
            type: 'Fleet Multi-Licence',
            identifier: 'VIN: MP2389148012',
            authority: 'ZINARA + Insurance + ZBC Radio',
            expiry: '30 Nov 2026',
            daysRemaining: 31,
            status: 'RENEWAL_URGENT',
            statusColor: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
        },
        {
            title: 'City Fire Clearance Certificate',
            type: 'Premises Safety',
            identifier: 'FIRE-HRE-9921',
            authority: 'Harare Fire Brigade',
            expiry: '15 Jan 2027',
            daysRemaining: 77,
            status: 'GOOD_STANDING',
            statusColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
        },
    ];

    return (
        <AppLayout
            title="Licence Renewals & Fleet Assets"
            subtitle="Track municipal permits, fire certificates, ZINARA vehicle licences, and motor insurance policies."
        >
            <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400">Expiring in 60 Days</span>
                            <Clock className="h-5 w-5 text-amber-400" />
                        </div>
                        <div className="text-2xl font-heading font-extrabold text-white mt-2">2 Licences</div>
                        <p className="text-xs text-slate-400 mt-1">Vehicle fleet & municipal shop permit</p>
                    </div>

                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400">Total Registered Vehicles</span>
                            <Car className="h-5 w-5 text-cyan-400" />
                        </div>
                        <div className="text-2xl font-heading font-extrabold text-white mt-2">4 Vehicles</div>
                        <p className="text-xs text-slate-400 mt-1">Tripartite disc compliance monitored</p>
                    </div>

                    <div className="glass-card p-5 rounded-xl border border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase text-slate-400">Premises Permits</span>
                            <Building2 className="h-5 w-5 text-emerald-400" />
                        </div>
                        <div className="text-2xl font-heading font-extrabold text-white mt-2">Harare HQ</div>
                        <p className="text-xs text-slate-400 mt-1">Health & fire clearance active</p>
                    </div>
                </div>

                <div className="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                    <h3 className="text-base font-heading font-bold text-white flex items-center gap-2">
                        <RefreshCw className="h-4 w-4 text-emerald-400" />
                        Active Licence & Asset Registry
                    </h3>

                    <div className="space-y-3">
                        {licenses.map((item, idx) => (
                            <div key={idx} className="glass-card p-4 rounded-xl border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-bold text-emerald-400">{item.authority}</span>
                                        <span className="text-slate-500">•</span>
                                        <span className="text-xs text-slate-400">{item.type}</span>
                                    </div>
                                    <h4 className="font-heading font-bold text-white text-sm mt-1">{item.title}</h4>
                                    <div className="text-xs font-mono text-slate-500 mt-0.5">{item.identifier}</div>
                                </div>
                                <div className="flex items-center gap-4">
                                    <div className="text-right">
                                        <div className="text-xs text-slate-300">Expires: <strong className="text-white">{item.expiry}</strong></div>
                                        <div className="text-[11px] text-amber-400">{item.daysRemaining} days remaining</div>
                                    </div>
                                    <span className={`px-2.5 py-1 rounded text-xs font-semibold border ${item.statusColor}`}>
                                        {item.status.replace(/_/g, ' ')}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
