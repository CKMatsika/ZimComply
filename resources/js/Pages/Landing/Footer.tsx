import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ShieldCheck, ExternalLink } from 'lucide-react';
import { LegalModal } from './LegalModal';

export function Footer() {
    const [modalConfig, setModalConfig] = useState<{
        isOpen: boolean;
        title: string;
        type: 'terms' | 'privacy';
    }>({
        isOpen: false,
        title: '',
        type: 'terms',
    });

    const openModal = (type: 'terms' | 'privacy', title: string) => {
        setModalConfig({
            isOpen: true,
            title,
            type,
        });
    };

    const closeModal = () => {
        setModalConfig((prev) => ({ ...prev, isOpen: false }));
    };

    return (
        <footer className="bg-[#0e1c2e] text-slate-400 border-t border-slate-800 text-xs sm:text-sm">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 lg:py-16">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">
                    {/* Brand Col */}
                    <div className="lg:col-span-2 space-y-4">
                        <Link href="/" className="flex items-center gap-2.5">
                            <div className="h-9 w-9 rounded-xl bg-gradient-to-br from-[#19766F] to-[#125752] flex items-center justify-center text-white shadow-xs border border-[#238c84]">
                                <ShieldCheck className="w-5 h-5" />
                            </div>
                            <span className="font-bold text-lg text-white tracking-tight">ZimComply AI</span>
                        </Link>
                        <p className="text-xs text-slate-400 max-w-sm leading-relaxed">
                            Authoritative compliance orchestration and registration roadmap software engineered for
                            Zimbabwean businesses, corporate secretaries, accounting practices, and compliance consultants.
                        </p>
                        <div className="pt-2 flex items-center gap-3">
                            <a
                                href="https://github.com/CKMatsika/ZimComply"
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition-colors border border-slate-700 text-xs"
                            >
                                <svg className="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                    <path fillRule="evenodd" clipRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                                </svg>
                                <span>GitHub Repository</span>
                                <ExternalLink className="w-3 h-3 text-slate-500" />
                            </a>
                        </div>
                    </div>

                    {/* Navigation Links Col */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-200">
                            Platform
                        </h4>
                        <ul className="space-y-2 text-xs">
                            <li><a href="#features" className="hover:text-white transition-colors">Business Registration</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">Statutory Compliance</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">Licence Renewals</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">Document Vault</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">PRAZ Tender Readiness</a></li>
                            <li><a href="#how-it-works" className="hover:text-white transition-colors">How It Works</a></li>
                        </ul>
                    </div>

                    {/* Regulatory Catalog Col */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-200">
                            Statutory Frameworks
                        </h4>
                        <ul className="space-y-2 text-xs">
                            <li className="text-slate-400">COBE Act [Chapter 24:31] (CIPZ)</li>
                            <li className="text-slate-400">Income Tax Act [Chapter 23:06] (ZIMRA)</li>
                            <li className="text-slate-400">VAT Act [Chapter 23:12] (ZIMRA)</li>
                            <li className="text-slate-400">NSSA Act [Chapter 17:04] (POBS/APWCS)</li>
                            <li className="text-slate-400">PPDPA Act [Chapter 22:23] (PRAZ)</li>
                            <li className="text-slate-400">Urban Councils Act [Chapter 29:15]</li>
                        </ul>
                    </div>

                    {/* Legal & Trust Col */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-200">
                            Trust & Legal
                        </h4>
                        <ul className="space-y-2 text-xs">
                            <li><a href="#trust" className="hover:text-white transition-colors">Regulatory Governance</a></li>
                            <li>
                                <button
                                    type="button"
                                    onClick={() => openModal('terms', 'Platform Terms of Service')}
                                    className="hover:text-white transition-colors text-left"
                                >
                                    Terms of Service (Draft)
                                </button>
                            </li>
                            <li>
                                <button
                                    type="button"
                                    onClick={() => openModal('privacy', 'Data Privacy & Protection Policy')}
                                    className="hover:text-white transition-colors text-left"
                                >
                                    Privacy Policy (Draft)
                                </button>
                            </li>
                            <li><a href="#faq" className="hover:text-white transition-colors">FAQ</a></li>
                            <li><Link href="/login" className="hover:text-white transition-colors">Sign In</Link></li>
                            <li><Link href="/register" className="hover:text-white transition-colors">Create Account</Link></li>
                        </ul>
                    </div>
                </div>

                {/* Bottom Legal Disclaimer & Copyright */}
                <div className="mt-12 pt-8 border-t border-slate-800/80 space-y-4">
                    <p className="text-[11px] text-slate-500 leading-relaxed max-w-5xl">
                        <strong>Statutory Disclaimer:</strong> ZimComply AI is an independent software technology platform
                        and is not an official government regulator, statutory authority, or accredited legal practice.
                        ZimComply AI provides regulatory intelligence, prerequisite sequencing, and workflow organisation
                        tools. Official certificates, statutory approvals, and formal registrations are issued exclusively
                        by designated Zimbabwean statutory authorities (CIPZ, ZIMRA, NSSA, ZIMDEF, PRAZ, and relevant
                        local councils).
                    </p>

                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                        <span>© {new Date().getFullYear()} ZimComply AI. All rights reserved.</span>
                        <div className="flex items-center gap-4 text-xs">
                            <button
                                type="button"
                                onClick={() => openModal('terms', 'Platform Terms of Service')}
                                className="hover:text-slate-300 transition-colors"
                            >
                                Terms
                            </button>
                            <span>•</span>
                            <button
                                type="button"
                                onClick={() => openModal('privacy', 'Data Privacy Policy')}
                                className="hover:text-slate-300 transition-colors"
                            >
                                Privacy
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Legal Draft Modal */}
            <LegalModal
                isOpen={modalConfig.isOpen}
                title={modalConfig.title}
                type={modalConfig.type}
                onClose={closeModal}
            />
        </footer>
    );
}
