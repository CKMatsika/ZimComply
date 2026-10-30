import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    Settings,
    Building2,
    Users,
    Shield,
    MapPin,
    CreditCard,
    Key,
    Lock,
    CheckCircle2,
} from 'lucide-react';

interface SettingsProps {
    branches: any[];
}

export default function SettingsIndex({ branches }: SettingsProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <AppLayout
            title="Workspace & Company Settings"
            subtitle="Configure corporate legal identity, operating branches, team access, and data security."
        >
            <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Left Col: Company Profile Card */}
                    <div className="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                        <div className="flex items-center gap-2">
                            <Building2 className="h-5 w-5 text-emerald-400" />
                            <h3 className="font-heading font-bold text-white text-base">Corporate Profile</h3>
                        </div>

                        <div className="space-y-3 text-xs">
                            <div>
                                <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Legal Name</span>
                                <div className="text-white font-medium mt-0.5">{auth.active_organisation?.legal_name}</div>
                            </div>
                            <div>
                                <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Entity Type</span>
                                <div className="text-slate-200 mt-0.5">{auth.active_organisation?.entity_type}</div>
                            </div>
                            <div>
                                <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Reporting Currency</span>
                                <div className="text-emerald-400 font-bold mt-0.5">{auth.active_organisation?.base_currency} (USD & ZiG supported)</div>
                            </div>
                            <div>
                                <span className="text-slate-500 uppercase tracking-wider text-[10px] font-semibold">Tenant Account</span>
                                <div className="text-slate-200 mt-0.5">{auth.active_tenant?.name} ({auth.active_tenant?.type})</div>
                            </div>
                        </div>
                    </div>

                    {/* Middle Col: Branches & Operating Sites */}
                    <div className="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <MapPin className="h-5 w-5 text-cyan-400" />
                                <h3 className="font-heading font-bold text-white text-base">Operating Branches</h3>
                            </div>
                            <span className="text-xs text-slate-400">{branches.length} Registered</span>
                        </div>

                        <div className="space-y-3">
                            {branches.map((b) => (
                                <div key={b.id} className="p-3 rounded-lg bg-slate-900/60 border border-slate-800">
                                    <div className="flex items-center justify-between">
                                        <span className="font-bold text-white text-xs">{b.name}</span>
                                        {b.is_headquarters && (
                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                HQ
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-[11px] text-slate-400 mt-1">{b.physical_address}</p>
                                    <div className="text-[10px] text-slate-500 mt-1">Jurisdiction: {b.city}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Right Col: Security & MFA */}
                    <div className="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                        <div className="flex items-center gap-2">
                            <Shield className="h-5 w-5 text-purple-400" />
                            <h3 className="font-heading font-bold text-white text-base">Security & MFA</h3>
                        </div>

                        <div className="p-3 rounded-lg bg-slate-900/60 border border-slate-800 text-xs space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-300">Two-Factor Authentication</span>
                                <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    TOTP Ready
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-400 leading-relaxed">
                                Protect payroll, employee data, and tax filings with Google Authenticator or Microsoft Authenticator.
                            </p>
                        </div>

                        <div className="p-3 rounded-lg bg-slate-900/60 border border-slate-800 text-xs space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-300">Data Protection Act</span>
                                <span className="text-[10px] font-semibold text-emerald-400">Compliant</span>
                            </div>
                            <p className="text-[11px] text-slate-400 leading-relaxed">
                                Sensitive fields (TIN, National IDs, bank details) encrypted at rest via AES-256-GCM.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
