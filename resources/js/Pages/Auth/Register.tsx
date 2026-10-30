import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { ShieldCheck, Lock, Mail, Building, Phone, User, ArrowRight, CheckCircle2 } from 'lucide-react';

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
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-center py-10 sm:px-6 lg:px-8 selection:bg-emerald-500 selection:text-white">
            <div className="sm:mx-auto sm:w-full sm:max-w-xl">
                <div className="flex justify-center">
                    <div className="h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-amber-500 p-0.5 shadow-xl shadow-emerald-500/25">
                        <div className="h-full w-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                            <ShieldCheck className="h-7 w-7 text-emerald-400" />
                        </div>
                    </div>
                </div>

                <h2 className="mt-4 text-center text-3xl font-heading font-extrabold tracking-tight text-white">
                    Start with ZimComply AI
                </h2>
                <p className="mt-1 text-center text-sm text-slate-400">
                    Register your business, discover obligations, and automate statutory compliance.
                </p>
            </div>

            <div className="mt-6 sm:mx-auto sm:w-full sm:max-w-xl">
                <div className="glass-panel py-8 px-6 sm:px-10 rounded-2xl shadow-2xl border border-slate-800">
                    <form onSubmit={submit} className="space-y-5">
                        {/* Account Type Selector */}
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                                Choose Account Profile
                            </label>
                            <div className="grid grid-cols-3 gap-2">
                                {[
                                    { id: 'SME', label: 'Single SME', desc: '1 Operating Business' },
                                    { id: 'ENTERPRISE', label: 'Enterprise', desc: 'Multi-branch Group' },
                                    { id: 'ACCOUNTING_FIRM', label: 'Accounting Firm', desc: 'Multi-client Portfolio' },
                                ].map((type) => (
                                    <button
                                        key={type.id}
                                        type="button"
                                        onClick={() => setData('account_type', type.id as any)}
                                        className={`p-3 rounded-xl border text-left transition-all ${
                                            data.account_type === type.id
                                                ? 'bg-emerald-500/15 border-emerald-500/60 text-white shadow-sm'
                                                : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:border-slate-700'
                                        }`}
                                    >
                                        <div className="text-xs font-bold text-white">{type.label}</div>
                                        <div className="text-[10px] text-slate-400 mt-0.5">{type.desc}</div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Company Details */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-800">
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Company / Firm Legal Name
                                </label>
                                <div className="mt-1 relative rounded-xl shadow-sm">
                                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                        <Building className="h-4 w-4" />
                                    </div>
                                    <input
                                        type="text"
                                        required
                                        value={data.company_name}
                                        onChange={(e) => setData('company_name', e.target.value)}
                                        placeholder="e.g. Apex Industrial Solutions (Pvt) Ltd"
                                        className="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                                {errors.company_name && (
                                    <p className="mt-1 text-xs text-red-400 font-medium">{errors.company_name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Entity Legal Structure
                                </label>
                                <select
                                    value={data.entity_type}
                                    onChange={(e) => setData('entity_type', e.target.value)}
                                    className="mt-1 block w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="PRIVATE_LIMITED_COMPANY">Private Limited Company ((Pvt) Ltd)</option>
                                    <option value="PBC">Private Business Corporation (PBC)</option>
                                    <option value="PARTNERSHIP">Registered Partnership</option>
                                    <option value="SOLE_PROPRIETORSHIP">Sole Proprietorship / Trader</option>
                                    <option value="TRUST">Registered Trust</option>
                                </select>
                            </div>
                        </div>

                        {/* Founder / Administrator Credentials */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Administrator Name
                                </label>
                                <div className="mt-1 relative rounded-xl shadow-sm">
                                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                        <User className="h-4 w-4" />
                                    </div>
                                    <input
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Tatenda Moyo"
                                        className="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                                {errors.name && (
                                    <p className="mt-1 text-xs text-red-400 font-medium">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Mobile Phone (+263)
                                </label>
                                <div className="mt-1 relative rounded-xl shadow-sm">
                                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                        <Phone className="h-4 w-4" />
                                    </div>
                                    <input
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="+263 77 123 4567"
                                        className="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Email */}
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                Official Email Address
                            </label>
                            <div className="mt-1 relative rounded-xl shadow-sm">
                                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                    <Mail className="h-4 w-4" />
                                </div>
                                <input
                                    type="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="tatenda@apexindustrial.co.zw"
                                    className="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>
                            {errors.email && (
                                <p className="mt-1 text-xs text-red-400 font-medium">{errors.email}</p>
                            )}
                        </div>

                        {/* Password */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Password (Min 8 chars)
                                </label>
                                <div className="mt-1 relative rounded-xl shadow-sm">
                                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                        <Lock className="h-4 w-4" />
                                    </div>
                                    <input
                                        type="password"
                                        required
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••••••"
                                        className="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                                {errors.password && (
                                    <p className="mt-1 text-xs text-red-400 font-medium">{errors.password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Confirm Password
                                </label>
                                <div className="mt-1 relative rounded-xl shadow-sm">
                                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                        <Lock className="h-4 w-4" />
                                    </div>
                                    <input
                                        type="password"
                                        required
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        placeholder="••••••••••••"
                                        className="block w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Submit Button */}
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl shadow-lg shadow-emerald-500/25 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-heading font-bold text-sm tracking-wide transition-all disabled:opacity-50 mt-4"
                        >
                            <span>{processing ? 'Provisioning Workspace...' : 'Initialize Company & Begin Discovery'}</span>
                            <ArrowRight className="h-4 w-4" />
                        </button>
                    </form>

                    <div className="mt-6 pt-5 border-t border-slate-800 text-center">
                        <p className="text-xs text-slate-400">
                            Already have an active compliance account?{' '}
                            <Link href="/login" className="font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">
                                Sign In
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
