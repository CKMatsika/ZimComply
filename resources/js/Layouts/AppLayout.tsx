import React, { useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    LayoutDashboard,
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
    Search,
    ChevronRight,
    Shield,
    ExternalLink,
    HelpCircle,
    UserCircle,
} from 'lucide-react';
import { Badge } from '@/Components/UI/Badge';
import { AiDrawer } from '@/Components/UI/AiDrawer';

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
    const [aiDrawerOpen, setAiDrawerOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const currentRoute = window.location.pathname;

    const navItems = [
        { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, active: currentRoute === '/dashboard' },
        { name: 'Registration', href: '/registration', icon: Compass, active: currentRoute.startsWith('/registration') },
        { name: 'Compliance', href: '/comply', icon: ShieldCheck, active: currentRoute.startsWith('/comply') },
        { name: 'Renewals', href: '/renew', icon: RefreshCw, active: currentRoute.startsWith('/renew') },
        { name: 'Procurement / Tender', href: '/tender', icon: Award, active: currentRoute.startsWith('/tender') },
        { name: 'Documents', href: '/documents', icon: FolderLock, active: currentRoute.startsWith('/documents') },
        { name: 'Reports', href: '/reports', icon: BarChart3, active: currentRoute.startsWith('/reports') },
        { name: 'Settings', href: '/settings', icon: Settings, active: currentRoute.startsWith('/settings') },
    ];

    const handleSwitchOrg = (orgId: string) => {
        router.post('/switch-organisation', { organisation_id: orgId }, {
            onSuccess: () => setOrgMenuOpen(false),
        });
    };

    // Determine current breadcrumb section name
    const currentNavItem = navItems.find((item) => item.active) || navItems[0];

    return (
        <div className="min-h-screen bg-[#F7F8FA] text-slate-800 flex font-sans antialiased">
            {/* Left Enterprise Navigation Sidebar */}
            <aside
                className="hidden lg:flex lg:flex-col lg:w-64 bg-[#14263D] border-r border-[#1C3554] text-slate-300 fixed inset-y-0 z-30"
                aria-label="Main Navigation"
            >
                {/* Brand Header */}
                <div className="h-16 flex items-center px-6 border-b border-[#1C3554] bg-[#0E1B2C]">
                    <Link href="/dashboard" className="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-400 rounded-lg">
                        <div className="h-9 w-9 rounded-lg bg-[#19766F] text-white flex items-center justify-center font-bold shadow-xs">
                            <Shield className="h-5 w-5 text-teal-100" />
                        </div>
                        <div className="flex flex-col">
                            <div className="font-heading font-extrabold text-base tracking-tight text-white flex items-center gap-1">
                                <span>ZimComply</span>
                                <span className="text-[#2cb3a8] text-xs font-semibold px-1 py-0.2 bg-teal-950/70 border border-teal-700/60 rounded">AI</span>
                            </div>
                            <span className="text-[10px] font-medium text-slate-400 tracking-wider uppercase -mt-0.5">
                                Regulatory SaaS
                            </span>
                        </div>
                    </Link>
                </div>

                {/* Organisation Switcher (Multi-company enterprise) */}
                <div className="p-3 border-b border-[#1C3554]">
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setOrgMenuOpen(!orgMenuOpen)}
                            className="w-full flex items-center justify-between p-2 rounded-lg bg-[#1C3554]/70 hover:bg-[#1C3554] border border-[#25466D] text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-400"
                            aria-expanded={orgMenuOpen}
                            aria-label="Switch organisation"
                        >
                            <div className="flex items-center gap-2.5 truncate">
                                <Building2 className="h-4 w-4 text-[#2cb3a8] shrink-0" />
                                <div className="truncate">
                                    <div className="text-xs font-semibold text-white truncate">
                                        {auth.active_organisation?.legal_name || 'Select Entity'}
                                    </div>
                                    <div className="text-[10px] text-slate-400 truncate">
                                        {auth.active_organisation?.entity_type ? auth.active_organisation.entity_type.replace(/_/g, ' ') : 'Corporate Entity'}
                                    </div>
                                </div>
                            </div>
                            <ChevronDown className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                        </button>

                        {orgMenuOpen && (
                            <div className="absolute left-0 right-0 mt-1.5 bg-[#14263D] border border-[#25466D] rounded-xl shadow-2xl py-1 z-50">
                                <div className="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-[#1C3554]">
                                    Associated Entities
                                </div>
                                <div className="max-h-56 overflow-y-auto">
                                    {auth.available_organisations.map((org) => (
                                        <button
                                            key={org.id}
                                            type="button"
                                            onClick={() => handleSwitchOrg(org.id)}
                                            className={`w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-[#1C3554] transition-colors ${
                                                auth.active_organisation?.id === org.id
                                                    ? 'text-[#2cb3a8] font-bold bg-[#1C3554]/40'
                                                    : 'text-slate-200'
                                            }`}
                                        >
                                            <div className="truncate">
                                                <div className="truncate">{org.legal_name}</div>
                                                <div className="text-[10px] text-slate-400">{org.entity_type} • {org.base_currency}</div>
                                            </div>
                                            {auth.active_organisation?.id === org.id && (
                                                <CheckCircle2 className="h-3.5 w-3.5 text-[#2cb3a8] shrink-0" />
                                            )}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Navigation Items List */}
                <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <div className="px-3 pb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Core Workspaces
                    </div>
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-400 ${
                                    item.active
                                        ? 'bg-[#19766F] text-white font-semibold shadow-xs'
                                        : 'text-slate-300 hover:text-white hover:bg-[#1C3554]'
                                }`}
                            >
                                <Icon className={`h-4 w-4 shrink-0 ${item.active ? 'text-white' : 'text-slate-400'}`} />
                                <span className="truncate">{item.name}</span>
                            </Link>
                        );
                    })}
                </nav>

                {/* Regulatory Citation Footer Badge */}
                <div className="p-3 border-t border-[#1C3554] bg-[#0E1B2C]/70">
                    <div className="p-2.5 rounded-lg bg-[#14263D] border border-[#1C3554] text-[11px] space-y-1">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-200">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            <span>Statutory Registry</span>
                        </div>
                        <p className="text-[10px] text-slate-400 leading-snug">
                            Grounded in Acts & SIs of Zimbabwe. Deterministic rules engine active.
                        </p>
                    </div>
                </div>
            </aside>

            {/* Mobile Navigation Drawer */}
            {mobileNavOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        onClick={() => setMobileNavOpen(false)}
                        className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
                    />
                    <div className="fixed inset-y-0 left-0 w-64 bg-[#14263D] text-white p-4 flex flex-col z-50">
                        <div className="flex items-center justify-between pb-4 border-b border-[#1C3554]">
                            <div className="flex items-center gap-2">
                                <div className="h-8 w-8 rounded-lg bg-[#19766F] flex items-center justify-center font-bold">
                                    <Shield className="h-4 w-4 text-white" />
                                </div>
                                <span className="font-heading font-bold text-sm">ZimComply AI</span>
                            </div>
                            <button
                                onClick={() => setMobileNavOpen(false)}
                                className="p-1 rounded-lg hover:bg-slate-800"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <nav className="flex-1 py-4 space-y-1 overflow-y-auto">
                            {navItems.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    onClick={() => setMobileNavOpen(false)}
                                    className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium ${
                                        item.active ? 'bg-[#19766F] text-white font-semibold' : 'text-slate-300 hover:bg-[#1C3554]'
                                    }`}
                                >
                                    <item.icon className="h-4 w-4" />
                                    <span>{item.name}</span>
                                </Link>
                            ))}
                        </nav>
                    </div>
                </div>
            )}

            {/* Main Application Area (Header + Body) */}
            <div className="flex-1 flex flex-col lg:pl-64 min-w-0">
                {/* Institutional Top Header */}
                <header className="sticky top-0 z-20 h-16 bg-white border-b border-slate-200 shadow-2xs flex items-center justify-between px-4 sm:px-6 lg:px-8">
                    {/* Left: Mobile Toggle & Breadcrumbs */}
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setMobileNavOpen(true)}
                            className="lg:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100"
                            aria-label="Open menu"
                        >
                            <Menu className="h-5 w-5" />
                        </button>

                        {/* Breadcrumbs */}
                        <div className="hidden sm:flex items-center gap-1.5 text-xs text-slate-500">
                            <span className="font-medium text-slate-700">
                                {auth.active_tenant?.name || 'Workspace'}
                            </span>
                            <ChevronRight className="h-3.5 w-3.5 text-slate-400" />
                            <span className="font-semibold text-slate-900">
                                {currentNavItem.name}
                            </span>
                        </div>
                    </div>

                    {/* Right: Search, AI Advisor Toggle, Notifications & User Menu */}
                    <div className="flex items-center gap-2.5 sm:gap-3">
                        {/* Contextual AI Regulatory Advisor Button */}
                        <button
                            type="button"
                            onClick={() => setAiDrawerOpen(true)}
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#e2f4f2] hover:bg-[#cbeee9] border border-[#a5dcd6] text-[#125752] text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"
                        >
                            <Sparkles className="h-3.5 w-3.5 text-[#19766F]" />
                            <span className="hidden md:inline">Regulatory AI Advisor</span>
                        </button>

                        {/* Notifications Indicator */}
                        <div className="relative">
                            <button
                                type="button"
                                className="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                aria-label="Notifications"
                            >
                                <Bell className="h-4 w-4" />
                                <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-[#B84B46]" />
                            </button>
                        </div>

                        {/* User Account Dropdown */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"
                                aria-expanded={userMenuOpen}
                                aria-label="User account menu"
                            >
                                <div className="h-8 w-8 rounded-lg bg-[#14263D] text-white flex items-center justify-center font-bold text-xs">
                                    {auth.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'U'}
                                </div>
                                <div className="hidden sm:block text-left">
                                    <div className="text-xs font-semibold text-slate-900 leading-tight">
                                        {auth.user?.name || 'Administrator'}
                                    </div>
                                    <div className="text-[10px] text-slate-500">
                                        {auth.role || 'Compliance Lead'}
                                    </div>
                                </div>
                                <ChevronDown className="h-3 w-3 text-slate-400 hidden sm:block" />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-xl py-1.5 z-50">
                                    <div className="px-4 py-2 border-b border-slate-100">
                                        <div className="text-xs font-bold text-slate-900">{auth.user?.name}</div>
                                        <div className="text-[11px] text-slate-500 truncate">{auth.user?.email}</div>
                                        <div className="mt-1">
                                            <Badge variant="teal" size="sm">
                                                Role: {auth.role || 'Admin'}
                                            </Badge>
                                        </div>
                                    </div>

                                    <Link
                                        href="/settings"
                                        onClick={() => setUserMenuOpen(false)}
                                        className="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 hover:text-slate-900"
                                    >
                                        <Settings className="h-4 w-4 text-slate-400" />
                                        <span>Company Settings</span>
                                    </Link>

                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="w-full text-left flex items-center gap-2 px-4 py-2 text-xs text-[#B84B46] hover:bg-red-50 border-t border-slate-100"
                                    >
                                        <LogOut className="h-4 w-4" />
                                        <span>Sign Out</span>
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Flash Alerts */}
                {flash?.success && (
                    <div className="bg-emerald-50 border-b border-emerald-200 text-emerald-800 px-6 py-2.5 text-xs flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="bg-[#fdf2f2] border-b border-[#f5c7c5] text-[#933632] px-6 py-2.5 text-xs flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 text-[#B84B46] shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}
                {flash?.info && (
                    <div className="bg-sky-50 border-b border-sky-200 text-sky-800 px-6 py-2.5 text-xs flex items-center gap-2">
                        <Info className="h-4 w-4 text-sky-600 shrink-0" />
                        <span>{flash.info}</span>
                    </div>
                )}

                {/* Page Title & Context Header Bar */}
                {title && (
                    <div className="bg-white border-b border-slate-200/90 py-5 px-4 sm:px-6 lg:px-8">
                        <div className="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <h1 className="text-xl sm:text-2xl font-heading font-extrabold text-[#14263D] tracking-tight">
                                    {title}
                                </h1>
                                {subtitle && (
                                    <p className="text-xs sm:text-sm text-slate-500 mt-1">
                                        {subtitle}
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <Badge variant="teal" size="md" dot>
                                    Statutory Jurisdiction: Republic of Zimbabwe
                                </Badge>
                            </div>
                        </div>
                    </div>
                )}

                {/* Main Content Body */}
                <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    {children}
                </main>

                {/* Institutional Clean Enterprise Footer */}
                <footer className="border-t border-slate-200 bg-white py-4 px-4 sm:px-6 lg:px-8 text-xs text-slate-500">
                    <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <span className="font-bold text-slate-800">ZimComply AI</span>
                            <span>•</span>
                            <span>Zimbabwe Statutory & Corporate Compliance SaaS</span>
                        </div>
                        <div className="text-[11px] text-slate-400 text-center sm:text-right">
                            Deterministic Rule Engine • ADR-0005 Governance • Official Gazetted Zimbabwean Statutory Instruments
                        </div>
                    </div>
                </footer>
            </div>

            {/* Contextual AI Regulatory Assistant Drawer */}
            <AiDrawer
                isOpen={aiDrawerOpen}
                onClose={() => setAiDrawerOpen(false)}
                activeContext={title}
            />
        </div>
    );
}
