import React, { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    Compass,
    ShieldCheck,
    RefreshCw,
    Award,
    FolderLock,
    BarChart3,
    Settings,
    Building2,
    ChevronDown,
    LogOut,
    Menu,
    X,
    Bell,
    CheckCircle2,
    AlertTriangle,
    Info,
    Sparkles,
} from 'lucide-react';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
    subtitle?: string;
}

export default function AppLayout({ children, title, subtitle }: AppLayoutProps) {
    const { auth, flash } = usePage<PageProps>().props;
    const [orgMenuOpen, setOrgMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    const currentRoute = window.location.pathname;

    const navItems = [
        { name: 'Register', href: '/registration', icon: Compass, active: currentRoute.startsWith('/registration') },
        { name: 'Comply', href: '/comply', icon: ShieldCheck, active: currentRoute.startsWith('/comply') },
        { name: 'Renew', href: '/renew', icon: RefreshCw, active: currentRoute.startsWith('/renew') },
        { name: 'Tender', href: '/tender', icon: Award, active: currentRoute.startsWith('/tender') },
        { name: 'Documents', href: '/documents', icon: FolderLock, active: currentRoute.startsWith('/documents') },
        { name: 'Reports', href: '/reports', icon: BarChart3, active: currentRoute.startsWith('/reports') },
        { name: 'Settings', href: '/settings', icon: Settings, active: currentRoute.startsWith('/settings') },
    ];

    const handleSwitchOrg = (orgId: string) => {
        router.post('/switch-organisation', { organisation_id: orgId }, {
            onSuccess: () => setOrgMenuOpen(false),
        });
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
            {/* Top Navigation Bar */}
            <header className="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        {/* Logo & Main Nav */}
                        <div className="flex items-center gap-8">
                            <Link href="/dashboard" className="flex items-center gap-3 group">
                                <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-emerald-500 via-teal-600 to-amber-500 p-0.5 shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                    <div className="h-full w-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                                        <ShieldCheck className="h-5 w-5 text-emerald-400 group-hover:text-emerald-300" />
                                    </div>
                                </div>
                                <div className="flex flex-col">
                                    <span className="font-heading font-extrabold text-lg tracking-tight bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
                                        ZimComply<span className="text-emerald-400">AI</span>
                                    </span>
                                    <span className="text-[10px] font-semibold tracking-wider uppercase text-emerald-400/90 -mt-1">
                                        Zimbabwe Enterprise
                                    </span>
                                </div>
                            </Link>

                            {/* Desktop Nav Items */}
                            <nav className="hidden lg:flex items-center gap-1">
                                <Link
                                    href="/dashboard"
                                    className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                                        currentRoute === '/dashboard'
                                            ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                            : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                    }`}
                                >
                                    Overview
                                </Link>
                                {navItems.map((item) => {
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                                                item.active
                                                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                    : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                            }`}
                                        >
                                            <Icon className="h-4 w-4" />
                                            {item.name}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>

                        {/* Right Area: Org Switcher, User Menu */}
                        <div className="flex items-center gap-3">
                            {/* Company Switcher Dropdown */}
                            <div className="relative">
                                <button
                                    onClick={() => setOrgMenuOpen(!orgMenuOpen)}
                                    className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 border border-slate-700/80 text-xs font-medium text-slate-200 transition-colors"
                                >
                                    <Building2 className="h-3.5 w-3.5 text-emerald-400" />
                                    <span className="max-w-[120px] sm:max-w-[180px] truncate">
                                        {auth.active_organisation?.legal_name || 'Select Company'}
                                    </span>
                                    <ChevronDown className="h-3 w-3 text-slate-400" />
                                </button>

                                {orgMenuOpen && (
                                    <div className="absolute right-0 mt-2 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-xl py-2 z-50">
                                        <div className="px-3 py-1.5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                            Active Organisation
                                        </div>
                                        {auth.available_organisations.map((org) => (
                                            <button
                                                key={org.id}
                                                onClick={() => handleSwitchOrg(org.id)}
                                                className={`w-full text-left px-3 py-2 text-sm flex items-center justify-between hover:bg-slate-800/60 transition-colors ${
                                                    auth.active_organisation?.id === org.id
                                                        ? 'text-emerald-400 font-medium bg-emerald-500/5'
                                                        : 'text-slate-300'
                                                }`}
                                            >
                                                <div className="truncate">
                                                    <div className="truncate">{org.legal_name}</div>
                                                    <div className="text-[10px] text-slate-500">{org.entity_type} • {org.base_currency}</div>
                                                </div>
                                                {auth.active_organisation?.id === org.id && (
                                                    <span className="h-2 w-2 rounded-full bg-emerald-400"></span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* User Profile Dropdown */}
                            <div className="relative">
                                <button
                                    onClick={() => setUserMenuOpen(!userMenuOpen)}
                                    className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-800 transition-colors"
                                >
                                    <div className="h-8 w-8 rounded-lg bg-emerald-600/30 border border-emerald-500/40 text-emerald-300 flex items-center justify-center font-bold text-xs">
                                        {auth.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'U'}
                                    </div>
                                    <ChevronDown className="h-3 w-3 text-slate-400 hidden sm:block" />
                                </button>

                                {userMenuOpen && (
                                    <div className="absolute right-0 mt-2 w-56 bg-slate-900 border border-slate-800 rounded-xl shadow-xl py-2 z-50">
                                        <div className="px-4 py-2 border-b border-slate-800">
                                            <div className="text-sm font-semibold text-white">{auth.user?.name}</div>
                                            <div className="text-xs text-slate-400 truncate">{auth.user?.email}</div>
                                            <div className="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300">
                                                Role: {auth.role || 'Member'}
                                            </div>
                                        </div>

                                        <Link
                                            href="/settings"
                                            onClick={() => setUserMenuOpen(false)}
                                            className="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white"
                                        >
                                            <Settings className="h-4 w-4 text-slate-400" />
                                            Settings
                                        </Link>

                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300 border-t border-slate-800"
                                        >
                                            <LogOut className="h-4 w-4" />
                                            Sign out
                                        </Link>
                                    </div>
                                )}
                            </div>

                            {/* Mobile Hamburger Button */}
                            <button
                                onClick={() => setMobileNavOpen(!mobileNavOpen)}
                                className="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800"
                            >
                                {mobileNavOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile Navigation Drawer */}
                {mobileNavOpen && (
                    <div className="lg:hidden border-b border-slate-800 bg-slate-900 px-4 pt-2 pb-4 space-y-1">
                        <Link
                            href="/dashboard"
                            onClick={() => setMobileNavOpen(false)}
                            className="block px-3 py-2 rounded-lg text-sm font-medium text-slate-200 hover:bg-slate-800"
                        >
                            Overview
                        </Link>
                        {navItems.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                onClick={() => setMobileNavOpen(false)}
                                className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-200 hover:bg-slate-800"
                            >
                                <item.icon className="h-4 w-4 text-emerald-400" />
                                {item.name}
                            </Link>
                        ))}
                    </div>
                )}
            </header>

            {/* Flash Notification Alerts */}
            {flash?.success && (
                <div className="bg-emerald-950/80 border-b border-emerald-800/50 text-emerald-200 px-4 py-2.5 text-sm flex items-center gap-2.5 shadow-sm">
                    <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0" />
                    <span>{flash.success}</span>
                </div>
            )}
            {flash?.error && (
                <div className="bg-red-950/80 border-b border-red-800/50 text-red-200 px-4 py-2.5 text-sm flex items-center gap-2.5 shadow-sm">
                    <AlertTriangle className="h-4 w-4 text-red-400 shrink-0" />
                    <span>{flash.error}</span>
                </div>
            )}
            {flash?.info && (
                <div className="bg-cyan-950/80 border-b border-cyan-800/50 text-cyan-200 px-4 py-2.5 text-sm flex items-center gap-2.5 shadow-sm">
                    <Info className="h-4 w-4 text-cyan-400 shrink-0" />
                    <span>{flash.info}</span>
                </div>
            )}

            {/* Sub-header / Breadcrumb Bar */}
            {title && (
                <div className="bg-slate-900/40 border-b border-slate-800/60 py-4 px-4 sm:px-6 lg:px-8">
                    <div className="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h1 className="text-xl sm:text-2xl font-heading font-bold text-white tracking-tight flex items-center gap-2">
                                {title}
                            </h1>
                            {subtitle && (
                                <p className="text-xs sm:text-sm text-slate-400 mt-0.5">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Regulated under Laws of Zimbabwe
                            </span>
                        </div>
                    </div>
                </div>
            )}

            {/* Main Page Body */}
            <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {children}
            </main>

            {/* Modern Footer */}
            <footer className="border-t border-slate-800 bg-slate-900/60 py-6 text-xs text-slate-400">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <span className="font-bold text-slate-300">ZimComply AI</span>
                        <span>•</span>
                        <span>Multi-Tenant Zimbabwe Compliance SaaS</span>
                    </div>
                    <div className="text-slate-500 text-center sm:text-right">
                        Notice: Compliance assistance & workflow platform. Statutory rules cited from official Zimbabwean Gazettes.
                    </div>
                </div>
            </footer>
        </div>
    );
}
