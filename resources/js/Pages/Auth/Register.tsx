import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Shield, Lock, Mail, Building, Phone, User, ArrowRight, CheckCircle2 } from 'lucide-react';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        company_name: '',
        entity_type: 'PRIVATE_LIMITED_COMPANY',
        account_type: 'SME',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <div className="min-h-screen bg-[#F7F8FA] text-slate-800 flex flex-col justify-center py-10 px-4 sm:px-6 lg:px-8 font-sans antialiased">
            <div className="sm:mx-auto sm:w-full sm:max-w-xl">
                <div className="flex justify-center">
                    <div className="h-12 w-12 rounded-xl bg-[#14263D] text-white flex items-center justify-center font-bold shadow-md">
                        <Shield className="h-6 w-6 text-[#2cb3a8]" />
                    </div>
                </div>

                <h2 className="mt-4 text-center text-2xl font-heading font-extrabold tracking-tight text-[#14263D]">
                    Register with ZimComply AI
                </h2>
                <p className="mt-1 text-center text-xs text-slate-500">
                    Establish your corporate workspace and initialize your statutory roadmap.
                </p>
                <div className="mt-2.5 flex justify-center">
                    <Badge variant="teal" size="sm" dot>
                        Laws of Zimbabwe Statutory Registry
                    </Badge>
                </div>
            </div>

            <div className="mt-6 sm:mx-auto sm:w-full sm:max-w-xl">
                <div className="bg-white py-8 px-6 sm:px-10 rounded-xl shadow-[0_1px_3px_0_rgba(15,23,42,0.06)] border border-slate-200">
                    <form onSubmit={submit} className="space-y-4">
                        {/* Account Type Selector */}
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Account Profile
                            </label>
                            <div className="grid grid-cols-3 gap-2">
                                {[
                                    { id: 'SME', label: 'Single SME', desc: '1 Business' },
                                    { id: 'ENTERPRISE', label: 'Enterprise', desc: 'Multi-branch' },
                                    { id: 'ACCOUNTING_FIRM', label: 'Advisory Firm', desc: 'Multi-client' },
                                ].map((type) => (
                                    <button
                                        key={type.id}
                                        type="button"
                                        onClick={() => setData('account_type', type.id as any)}
                                        className={`p-2.5 rounded-lg border text-left transition-colors cursor-pointer ${
                                            data.account_type === type.id
                                                ? 'bg-[#e2f4f2] border-[#19766F] text-[#125752]'
                                                : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        <div className="text-xs font-bold">{type.label}</div>
                                        <div className="text-[10px] text-slate-500 mt-0.5">{type.desc}</div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Company Name & Entity Type */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Company / Trading Name
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.company_name}
                                    onChange={(e) => setData('company_name', e.target.value)}
                                    placeholder="Apex Holdings (Pvt) Ltd"
                                    className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                                {errors.company_name && (
                                    <p className="mt-1 text-[11px] text-[#B84B46]">{errors.company_name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Entity Structure
                                </label>
                                <select
                                    value={data.entity_type}
                                    onChange={(e) => setData('entity_type', e.target.value)}
                                    className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                >
                                    <option value="PRIVATE_LIMITED_COMPANY">Private Limited Company (PLC)</option>
                                    <option value="PRIVATE_BUSINESS_CORPORATION">Private Business Corporation (PBC)</option>
                                    <option value="REGISTERED_TRUST">Registered Deed of Trust</option>
                                    <option value="PARTNERSHIP">Commercial Partnership</option>
                                </select>
                            </div>
                        </div>

                        {/* User Name & Email */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Full Name (Director / Officer)
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Tatenda Chidzero"
                                    className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-[11px] text-[#B84B46]">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Official Email Address
                                </label>
                                <input
                                    type="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="tatenda@company.co.zw"
                                    className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                                {errors.email && (
                                    <p className="mt-1 text-[11px] text-[#B84B46]">{errors.email}</p>
                                )}
                            </div>
                        </div>

                        {/* Phone */}
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Phone Number (WhatsApp / Mobile)
                            </label>
                            <input
                                type="text"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                placeholder="+263 77 123 4567"
                                className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                            />
                            {errors.phone && (
                                <p className="mt-1 text-[11px] text-[#B84B46]">{errors.phone}</p>
                            )}
                        </div>

                        {/* Password & Confirm */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    required
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••••••"
                                    className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                                {errors.password && (
                                    <p className="mt-1 text-[11px] text-[#B84B46]">{errors.password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Confirm Password
                                </label>
                                <input
                                    type="password"
                                    required
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder="••••••••••••"
                                    className="mt-1 block w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                            </div>
                        </div>

                        {/* Submit Button */}
                        <div className="pt-2">
                            <Button
                                type="submit"
                                variant="secondary"
                                size="md"
                                disabled={processing}
                                className="w-full justify-center"
                                icon={<ArrowRight className="h-4 w-4" />}
                            >
                                {processing ? 'Creating Workspace...' : 'Initialize Corporate Workspace'}
                            </Button>
                        </div>
                    </form>

                    <div className="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
                        <span>Already registered? </span>
                        <Link href="/login" className="font-bold text-[#19766F] hover:underline">
                            Sign In
                        </Link>
                    </div>
                </div>

                <div className="mt-6 text-center text-[11px] text-slate-400">
                    Republic of Zimbabwe • Deterministic Statutory Compliance Engine • ADR-0005
                </div>
            </div>
        </div>
    );
}
