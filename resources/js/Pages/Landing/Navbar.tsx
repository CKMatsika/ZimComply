import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ShieldCheck, Menu, X, ArrowRight, LayoutDashboard, Lock } from 'lucide-react';

interface NavbarProps {
    auth?: {
        user?: {
            name: string;
            email: string;
        } | null;
    };
}

export function Navbar({ auth }: NavbarProps) {
    const [mobileOpen, setMobileOpen] = useState(false);

    const navLinks = [
        { label: 'Features', href: '#features' },
        { label: 'How It Works', href: '#how-it-works' },
        { label: 'Who It\'s For', href: '#who-its-for' },
        { label: 'Trust & Standards', href: '#trust' },
        { label: 'FAQ', href: '#faq' },
    ];

    const isAuthenticated = !!auth?.user;

    return (
        <header className="sticky top-0 z-40 bg-[#14263D]/95 backdrop-blur-md border-b border-slate-800 text-white transition-all">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                {/* Brand Wordmark */}
                <Link href="/" className="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-[#19766F] rounded-lg p-1">
                    <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-[#19766F] to-[#125752] flex items-center justify-center text-white shadow-md border border-[#238c84] group-hover:scale-105 transition-transform">
                        <ShieldCheck className="w-5 h-5" />
                    </div>
                    <div className="flex flex-col">
                        <div className="flex items-center gap-1.5">
                            <span className="font-bold text-lg tracking-tight text-white">ZimComply</span>
                            <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-[#19766F]/40 text-[#4dd4c7] border border-[#19766F]/60">AI</span>
                        </div>
                        <span className="text-[10px] text-slate-400 font-medium tracking-wider uppercase">Zimbabwe RegTech</span>
                    </div>
                </Link>

                {/* Desktop Nav Links */}
                <nav className="hidden md:flex items-center gap-8" aria-label="Main Navigation">
                    {navLinks.map((link) => (
                        <a
                            key={link.label}
                            href={link.href}
                            className="text-sm font-medium text-slate-300 hover:text-white transition-colors focus:outline-none focus:text-white"
                        >
                            {link.label}
                        </a>
                    ))}
                </nav>

                {/* Desktop Action CTAs */}
                <div className="hidden md:flex items-center gap-4">
                    {isAuthenticated ? (
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#19766F] hover:bg-[#14605a] text-white text-sm font-semibold transition-all shadow-sm border border-[#19766F] focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                        >
                            <LayoutDashboard className="w-4 h-4" />
                            <span>Dashboard ({auth.user?.name})</span>
                        </Link>
                    ) : (
                        <>
                            <Link
                                href="/login"
                                className="text-sm font-medium text-slate-300 hover:text-white px-3 py-2 transition-colors focus:outline-none"
                            >
                                Sign In
                            </Link>
                            <Link
                                href="/register"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white hover:bg-slate-100 text-[#14263D] text-sm font-semibold transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-white"
                            >
                                <span>Get Started</span>
                                <ArrowRight className="w-4 h-4" />
                            </Link>
                        </>
                    )}
                </div>

                {/* Mobile Menu Toggle Button */}
                <div className="flex md:hidden">
                    <button
                        type="button"
                        onClick={() => setMobileOpen(!mobileOpen)}
                        className="p-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                        aria-label="Toggle Navigation Menu"
                        aria-expanded={mobileOpen}
                    >
                        {mobileOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                    </button>
                </div>
            </div>

            {/* Mobile Drawer Navigation */}
            {mobileOpen && (
                <div className="md:hidden bg-[#14263D] border-b border-slate-800 px-4 pt-2 pb-6 space-y-3 animate-in slide-in-from-top-2">
                    <div className="flex flex-col space-y-2 py-2 border-b border-slate-800">
                        {navLinks.map((link) => (
                            <a
                                key={link.label}
                                href={link.href}
                                onClick={() => setMobileOpen(false)}
                                className="px-3 py-2 text-base font-medium text-slate-200 hover:bg-slate-800/80 rounded-lg transition-colors"
                            >
                                {link.label}
                            </a>
                        ))}
                    </div>

                    <div className="pt-2 flex flex-col gap-2.5">
                        {isAuthenticated ? (
                            <Link
                                href="/dashboard"
                                className="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg bg-[#19766F] text-white font-semibold text-sm"
                            >
                                <LayoutDashboard className="w-4 h-4" />
                                <span>Go to Dashboard</span>
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href="/login"
                                    className="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg border border-slate-700 text-slate-200 font-medium text-sm hover:bg-slate-800"
                                >
                                    <Lock className="w-4 h-4 text-slate-400" />
                                    <span>Sign In</span>
                                </Link>
                                <Link
                                    href="/register"
                                    className="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg bg-white text-[#14263D] font-semibold text-sm hover:bg-slate-100"
                                >
                                    <span>Start Your Compliance Journey</span>
                                    <ArrowRight className="w-4 h-4" />
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            )}
        </header>
    );
}
