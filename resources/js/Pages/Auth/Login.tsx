import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Shield, Lock, Mail, ArrowRight, Building, CheckCircle2, AlertCircle } from 'lucide-react';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

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
        <div className="min-h-screen bg-[#F7F8FA] text-slate-800 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 font-sans antialiased">
            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                {/* Brand Logo Header */}
                <div className="flex justify-center">
                    <div className="h-12 w-12 rounded-xl bg-[#14263D] text-white flex items-center justify-center font-bold shadow-md">
                        <Shield className="h-6 w-6 text-[#2cb3a8]" />
                    </div>
                </div>

                <h2 className="mt-4 text-center text-2xl font-heading font-extrabold tracking-tight text-[#14263D]">
                    ZimComply<span className="text-[#19766F]"> AI</span>
                </h2>
                <p className="mt-1 text-center text-xs text-slate-500">
                    Enterprise Statutory & Corporate Compliance SaaS
                </p>
                <div className="mt-2.5 flex justify-center">
                    <Badge variant="teal" size="sm" dot>
                        Laws of Zimbabwe Statutory Registry
                    </Badge>
                </div>
            </div>

            <div className="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-8 px-6 sm:px-8 rounded-xl shadow-[0_1px_3px_0_rgba(15,23,42,0.06)] border border-slate-200">
                    <form onSubmit={submit} className="space-y-4">
                        {/* Email */}
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Corporate Email Address
                            </label>
                            <div className="mt-1 relative rounded-lg">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <Mail className="h-4 w-4" />
                                </div>
                                <input
                                    type="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="officer@company.co.zw"
                                    className="block w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                            </div>
                            {errors.email && (
                                <p className="mt-1 text-[11px] text-[#B84B46]">{errors.email}</p>
                            )}
                        </div>

                        {/* Password */}
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Password
                            </label>
                            <div className="mt-1 relative rounded-lg">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <Lock className="h-4 w-4" />
                                </div>
                                <input
                                    type="password"
                                    required
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••••••"
                                    className="block w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                                />
                            </div>
                            {errors.password && (
                                <p className="mt-1 text-[11px] text-[#B84B46]">{errors.password}</p>
                            )}
                        </div>

                        {/* Remember Me */}
                        <div className="flex items-center justify-between text-xs">
                            <label className="flex items-center gap-2 text-slate-700 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="rounded border-slate-300 text-[#19766F] focus:ring-[#19766F]"
                                />
                                <span>Remember this corporate device</span>
                            </label>
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
                                {processing ? 'Authenticating...' : 'Sign In to Workspace'}
                            </Button>
                        </div>
                    </form>

                    {/* Registration Link */}
                    <div className="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
                        <span>New organisation to ZimComply? </span>
                        <Link href="/register" className="font-bold text-[#19766F] hover:underline">
                            Register Company
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
