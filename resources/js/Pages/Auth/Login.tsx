import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { ShieldCheck, Lock, Mail, ArrowRight, Building, CheckCircle2 } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-emerald-500 selection:text-white">
            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                <div className="flex justify-center">
                    <div className="h-14 w-14 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-amber-500 p-0.5 shadow-xl shadow-emerald-500/25">
                        <div className="h-full w-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                            <ShieldCheck className="h-8 w-8 text-emerald-400" />
                        </div>
                    </div>
                </div>

                <h2 className="mt-6 text-center text-3xl font-heading font-extrabold tracking-tight text-white">
                    ZimComply<span className="text-emerald-400">AI</span>
                </h2>
                <p className="mt-2 text-center text-sm text-slate-400">
                    Register • Comply • Renew • Tender • Grow
                </p>
                <div className="mt-3 flex justify-center">
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Official Zimbabwe Statutory Hub
                    </span>
                </div>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="glass-panel py-8 px-6 sm:px-10 rounded-2xl shadow-2xl border border-slate-800">
                    <form onSubmit={submit} className="space-y-5">
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
                                    placeholder="founder@company.co.zw"
                                    className="block w-full pl-10 pr-3 py-2.5 bg-slate-900/90 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                                />
                            </div>
                            {errors.email && (
                                <p className="mt-1.5 text-xs text-red-400 font-medium">{errors.email}</p>
                            )}
                        </div>

                        {/* Password */}
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                Password
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
                                    className="block w-full pl-10 pr-3 py-2.5 bg-slate-900/90 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                                />
                            </div>
                            {errors.password && (
                                <p className="mt-1.5 text-xs text-red-400 font-medium">{errors.password}</p>
                            )}
                        </div>

                        {/* Remember Me */}
                        <div className="flex items-center justify-between text-sm">
                            <label className="flex items-center gap-2 text-slate-300 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="rounded border-slate-700 bg-slate-900 text-emerald-500 focus:ring-emerald-500/20"
                                />
                                <span className="text-xs">Remember this device</span>
                            </label>
                            <span className="text-xs text-emerald-400 hover:text-emerald-300 cursor-pointer">
                                Forgot password?
                            </span>
                        </div>

                        {/* Submit Button */}
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl shadow-lg shadow-emerald-500/25 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-heading font-bold text-sm tracking-wide transition-all disabled:opacity-50"
                        >
                            <span>{processing ? 'Authenticating...' : 'Sign In to Workspace'}</span>
                            <ArrowRight className="h-4 w-4" />
                        </button>
                    </form>

                    {/* Register Link */}
                    <div className="mt-6 pt-6 border-t border-slate-800 text-center">
                        <p className="text-xs text-slate-400">
                            Registering a new company or accounting practice?{' '}
                            <Link href="/register" className="font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">
                                Onboard in 2 minutes
                            </Link>
                        </p>
                    </div>
                </div>

                {/* Statutory Trust Notice */}
                <div className="mt-8 text-center text-xs text-slate-500 max-w-sm mx-auto">
                    Multi-tenant data isolation enforced with AES-256 field encryption. Zimbabwe Cyber & Data Protection Act [Chapter 12:07] compliant.
                </div>
            </div>
        </div>
    );
}
