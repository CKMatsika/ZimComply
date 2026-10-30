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
    Plus,
    UserCheck,
    History,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

interface SettingsProps {
    branches: any[];
}

export default function SettingsIndex({ branches }: SettingsProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <AppLayout
            title="Workspace & Corporate Settings"
            subtitle="Configure corporate legal identity, operating branches, team access, and statutory governance parameters."
        >
            <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Left Col: Corporate Profile Card */}
                    <Card className="space-y-2">
                        <CardHeader>
                            <CardTitle>
                                <Building2 className="h-4 w-4 text-[#19766F]" />
                                Corporate Identity
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-xs">
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Legal Entity Name</span>
                                <div className="text-sm font-bold text-slate-900 mt-0.5">{auth.active_organisation?.legal_name}</div>
                            </div>
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Entity Structure</span>
                                <div className="text-slate-700 mt-0.5 font-medium">{auth.active_organisation?.entity_type.replace(/_/g, ' ')}</div>
                            </div>
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Base Currency</span>
                                <div className="text-[#19766F] font-bold mt-0.5">{auth.active_organisation?.base_currency} (USD & ZiG Dual Schedule)</div>
                            </div>
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tenant Account</span>
                                <div className="text-slate-700 mt-0.5">{auth.active_tenant?.name} ({auth.active_tenant?.type})</div>
                            </div>
                            <div className="pt-2 border-t border-slate-100">
                                <Badge variant="teal" size="sm" dot>
                                    Statutory Registry Synchronised
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Middle Col: Branches & Operating Sites */}
                    <Card className="space-y-2">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>
                                <MapPin className="h-4 w-4 text-[#19766F]" />
                                Operating Sites ({branches.length})
                            </CardTitle>
                            <Button variant="outline" size="sm" icon={<Plus className="h-3 w-3" />}>
                                Add
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {branches.length > 0 ? (
                                branches.map((b, idx) => (
                                    <div key={idx} className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1">
                                        <div className="font-bold text-slate-900 flex items-center justify-between">
                                            <span>{b.name}</span>
                                            <Badge variant="teal" size="sm">{b.branch_code || 'HQ'}</Badge>
                                        </div>
                                        <div className="text-[11px] text-slate-500">{b.jurisdiction_code || 'ZW-HARARE'}</div>
                                        <div className="text-[11px] text-slate-600 truncate">{b.physical_address || 'Harare Industrial Area'}</div>
                                    </div>
                                ))
                            ) : (
                                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1">
                                    <div className="font-bold text-slate-900 flex items-center justify-between">
                                        <span>Harare Headquarters & Workshop</span>
                                        <Badge variant="teal" size="sm">HQ-01</Badge>
                                    </div>
                                    <div className="text-[11px] text-slate-500">City of Harare (ZW-HARARE)</div>
                                    <div className="text-[11px] text-slate-600">Workington Industrial Area, Harare</div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Right Col: Team Roles & AI Governance */}
                    <Card className="space-y-2">
                        <CardHeader>
                            <CardTitle>
                                <Shield className="h-4 w-4 text-[#19766F]" />
                                Access & AI Governance
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-xs">
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Authenticated User</span>
                                <div className="font-bold text-slate-900 mt-0.5">{auth.user?.name}</div>
                                <div className="text-[11px] text-slate-500">{auth.user?.email}</div>
                            </div>
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assigned Role</span>
                                <div className="mt-1">
                                    <Badge variant="neutral" size="sm">
                                        {auth.role || 'Compliance Administrator'}
                                    </Badge>
                                </div>
                            </div>
                            <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1">
                                <div className="font-bold text-slate-800 flex items-center gap-1.5">
                                    <Lock className="h-3.5 w-3.5 text-[#19766F]" />
                                    <span>ADR-0005 AI Governance</span>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-snug">
                                    AI models operate strictly in an advisory/explanatory capacity. Rules are evaluated deterministically.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
