import React from 'react';
import { X, ShieldAlert, FileText } from 'lucide-react';

interface LegalModalProps {
    isOpen: boolean;
    title: string;
    type: 'terms' | 'privacy';
    onClose: () => void;
}

export function LegalModal({ isOpen, title, type, onClose }: LegalModalProps) {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/70 backdrop-blur-xs animate-in fade-in duration-200">
            <div className="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col max-h-[85vh] overflow-hidden">
                {/* Modal Header */}
                <div className="px-6 py-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div className="flex items-center gap-2.5">
                        <div className="h-8 w-8 rounded-lg bg-[#14263D]/10 text-[#14263D] flex items-center justify-center">
                            <FileText className="w-4 h-4 text-[#19766F]" />
                        </div>
                        <div>
                            <h3 className="text-base font-bold text-[#14263D]">{title}</h3>
                            <span className="text-[11px] font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded border border-amber-200">
                                DRAFT PLATFORM POLICY • SUBJECT TO FORMAL LEGAL REVIEW
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/80 transition-colors"
                        aria-label="Close dialog"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Modal Body */}
                <div className="p-6 sm:p-8 overflow-y-auto space-y-5 text-sm text-slate-600 leading-relaxed">
                    {type === 'terms' ? (
                        <>
                            <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                                <ShieldAlert className="w-4 h-4 shrink-0 text-amber-700 mt-0.5" />
                                <div>
                                    <strong>Important Statutory Notice:</strong> ZimComply AI is an independent software tool providing workflow management and regulatory intelligence. It does not replace independent legal advice or formal statutory filings with Zimbabwean authorities.
                                </div>
                            </div>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">1. Platform Scope & Purpose</h4>
                                <p>
                                    ZimComply AI provides software tools designed to assist Zimbabwean businesses, company secretaries, and professional practitioners in tracking statutory obligations, archiving compliance evidence, and sequencing prerequisite registration requirements.
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">2. Independent Non-Agency Status</h4>
                                <p>
                                    ZimComply AI is not a government agency, statutory regulator, or legal practitioner. Use of this platform does not constitute an official government filing, nor does it guarantee statutory certificate issuance, tender award, or exemption from statutory audits.
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">3. User Responsibility & Accuracy</h4>
                                <p>
                                    Users are solely responsible for ensuring the accuracy and veracity of profile declarations, employee headcounts, financial turnover figures, and uploaded corporate documentation.
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">4. Limitation of Liability</h4>
                                <p>
                                    While ZimComply AI maintains versioned rules grounded in published Acts of Parliament and Statutory Instruments, legislation and administrative practice in Zimbabwe are subject to amendment. Users should verify critical filing positions with statutory bodies.
                                </p>
                            </section>
                        </>
                    ) : (
                        <>
                            <div className="p-4 rounded-xl bg-teal-50 border border-teal-200 text-teal-950 text-xs flex items-start gap-2.5">
                                <ShieldAlert className="w-4 h-4 shrink-0 text-[#19766F] mt-0.5" />
                                <div>
                                    <strong>Data Privacy Principle:</strong> ZimComply AI enforces strict multi-tenant database isolation. Your company records and uploaded evidence are never commingled with other organisations.
                                </div>
                            </div>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">1. Information We Collect</h4>
                                <p>
                                    We collect account contact information (name, work email, encrypted password), business profile data (entity type, trading name, operational activities, staff headcount estimates), and user-uploaded corporate documents (e.g. CR14, incorporation certificates).
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">2. Use of Information</h4>
                                <p>
                                    Information collected is utilized exclusively to evaluate statutory compliance applicability, generate sequenced registration roadmaps, send deadline alerts, and maintain your private document vault.
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">3. Cryptographic Storage & Isolation</h4>
                                <p>
                                    Uploaded documents are stored on secure private storage disks, hashed using SHA-256 for audit provenance, and scoped strictly to your tenant ID at the database layer.
                                </p>
                            </section>

                            <section className="space-y-2">
                                <h4 className="font-bold text-[#14263D] text-sm">4. Data Sharing & Third Parties</h4>
                                <p>
                                    ZimComply AI does not sell, rent, or trade your corporate information or customer data to advertisers or third-party marketing brokers.
                                </p>
                            </section>
                        </>
                    )}
                </div>

                {/* Modal Footer */}
                <div className="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                    <span className="text-xs text-slate-500">Draft v1.0 • 2026 ZimComply AI</span>
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg bg-[#14263D] text-white text-xs font-semibold hover:bg-slate-800 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}
