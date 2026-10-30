import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight, Lock } from 'lucide-react';

export function FinalCta() {
    return (
        <section className="py-20 lg:py-28 bg-[#14263D] text-white relative overflow-hidden">
            {/* Background geometric accents */}
            <div className="absolute inset-0 opacity-[0.03] bg-[linear-gradient(to_right,#ffffff_1px,transparent_1px),linear-gradient(to_bottom,#ffffff_1px,transparent_1px)] bg-[size:4rem_4rem] pointer-events-none" />
            <div className="absolute -bottom-24 right-1/4 w-[500px] h-[300px] bg-[#19766F]/20 rounded-full blur-3xl pointer-events-none" />

            <div className="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
                <span className="text-xs font-bold uppercase tracking-wider text-[#4dd4c7] bg-[#19766F]/30 px-3 py-1 rounded-full border border-[#19766F]/50">
                    Get Started Today
                </span>

                <h2 className="text-3xl sm:text-5xl font-extrabold tracking-tight text-white leading-tight">
                    Know what your business needs.{' '}
                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-300 to-emerald-300">
                        Stay ready for what comes next.
                    </span>
                </h2>

                <p className="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto font-normal leading-relaxed">
                    Start organising your registration and compliance journey with greater clarity and confidence.
                    Create an account in less than two minutes.
                </p>

                <div className="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        href="/register"
                        id="final-cta-register"
                        className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-full bg-white hover:bg-slate-100 text-[#14263D] font-bold text-base transition-all shadow-xl hover:shadow-2xl hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-white"
                    >
                        <span>Get Started</span>
                        <ArrowRight className="w-4 h-4 text-[#14263D]" />
                    </Link>

                    <Link
                        href="/login"
                        id="final-cta-login"
                        className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-200 border border-slate-700 font-semibold text-base transition-all focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                    >
                        <Lock className="w-4 h-4 text-slate-400" />
                        <span>Sign In</span>
                    </Link>
                </div>
            </div>
        </section>
    );
}
