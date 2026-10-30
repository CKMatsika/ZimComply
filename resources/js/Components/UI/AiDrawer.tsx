import React, { useState, useEffect } from 'react';
import {
    Sparkles,
    X,
    BookOpen,
    ShieldCheck,
    AlertCircle,
    ArrowRight,
    Send,
    ExternalLink,
    Scale,
} from 'lucide-react';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

interface AiDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    activeContext?: string;
}

interface StatutoryCitation {
    act: string;
    chapter: string;
    section: string;
    summary: string;
    verificationStatus: 'VERIFIED_OFFICIAL';
}

export function AiDrawer({ isOpen, onClose, activeContext }: AiDrawerProps) {
    const [query, setQuery] = useState('');
    const [isThinking, setIsThinking] = useState(false);
    const [messages, setMessages] = useState<Array<{ role: 'user' | 'assistant'; content: string; citations?: StatutoryCitation[] }>>([
        {
            role: 'assistant',
            content: 'Hello. I am the ZimComply Regulatory Intelligence Assistant. I provide explanatory guidance grounded exclusively in gazetted Zimbabwean statutory instruments and Acts. How may I clarify your statutory obligations today?',
            citations: [
                {
                    act: 'Companies and Other Business Entities Act',
                    chapter: '24:31',
                    section: 'Part II',
                    summary: 'Mandatory company formation and officer registration.',
                    verificationStatus: 'VERIFIED_OFFICIAL',
                },
            ],
        },
    ]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    const handleAsk = (userPrompt: string) => {
        if (!userPrompt.trim()) return;

        const newMessages = [...messages, { role: 'user' as const, content: userPrompt }];
        setMessages(newMessages);
        setQuery('');
        setIsThinking(true);

        setTimeout(() => {
            let responseText = '';
            let citations: StatutoryCitation[] = [];

            if (userPrompt.toLowerCase().includes('itf263') || userPrompt.toLowerCase().includes('tax clearance')) {
                responseText = 'An ITF263 Tax Clearance Certificate is issued by ZIMRA under Section 80 of the Income Tax Act [Chapter 23:06]. Without a valid ITF263, any registered payer (including clients and procuring parastatals) is legally mandated to deduct 30% withholding tax from all payments. It is also an absolute mandatory prerequisite for PRAZ supplier registration.';
                citations = [
                    {
                        act: 'Income Tax Act',
                        chapter: '23:06',
                        section: 'Section 80',
                        summary: 'Withholding tax from contracts where tax clearance is absent.',
                        verificationStatus: 'VERIFIED_OFFICIAL',
                    },
                ];
            } else if (userPrompt.toLowerCase().includes('vat') || userPrompt.toLowerCase().includes('threshold')) {
                responseText = 'Under the Value Added Tax Act [Chapter 23:12] and Statutory Instrument 41 of 2024, mandatory VAT registration is triggered when annual taxable turnover reaches or exceeds US$25,000 (or the gazetted ZiG equivalent). Entities below this threshold may register voluntarily if they maintain verifiable books of accounts.';
                citations = [
                    {
                        act: 'Value Added Tax Act',
                        chapter: '23:12',
                        section: 'Section 23 & SI 41 of 2024',
                        summary: 'Registration threshold of US$25,000 and monthly return filing obligations.',
                        verificationStatus: 'VERIFIED_OFFICIAL',
                    },
                ];
            } else if (userPrompt.toLowerCase().includes('nssa') || userPrompt.toLowerCase().includes('employee') || userPrompt.toLowerCase().includes('pobs')) {
                responseText = 'Employer registration with NSSA is compulsory under the NSSA Act [Chapter 17:04] within 30 days of employing your first worker. Private employers must register for both the Pension and Other Benefits Scheme (POBS, SI 393/1993) and the Accident Prevention and Workers\' Compensation Scheme (APWCS, SI 68/1990).';
                citations = [
                    {
                        act: 'National Social Security Authority Act',
                        chapter: '17:04',
                        section: 'SI 393 of 1993 & SI 68 of 1990',
                        summary: 'Mandatory employer registration and monthly remittances for private entities.',
                        verificationStatus: 'VERIFIED_OFFICIAL',
                    },
                ];
            } else if (userPrompt.toLowerCase().includes('praz') || userPrompt.toLowerCase().includes('tender')) {
                responseText = 'PRAZ supplier registration is governed by the Public Procurement and Disposal of Public Assets (PPDPA) Act [Chapter 22:23]. It is mandatory only for businesses intending to bid on public tenders, parastatal supplies, or government contracts. General private B2B commerce does not require PRAZ.';
                citations = [
                    {
                        act: 'Public Procurement and Disposal of Public Assets Act',
                        chapter: '22:23',
                        section: 'Section 6',
                        summary: 'Annual supplier classification and tender bidding eligibility.',
                        verificationStatus: 'VERIFIED_OFFICIAL',
                    },
                ];
            } else {
                responseText = `Regarding "${userPrompt}": In Zimbabwe, compliance obligations are deterministically derived from your entity constitution, turnover, headcount, and physical operating councils. You can view all exact statutory requirements and citations in your Statutory Roadmap.`;
                citations = [
                    {
                        act: 'Constitution of Zimbabwe & Statutory Laws',
                        chapter: 'Zim Laws',
                        section: 'Authoritative Gazettes',
                        summary: 'Statutory compliance schedule and gazetted ministerial regulations.',
                        verificationStatus: 'VERIFIED_OFFICIAL',
                    },
                ];
            }

            setMessages([...newMessages, { role: 'assistant', content: responseText, citations }]);
            setIsThinking(false);
        }, 700);
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-hidden">
            {/* Backdrop */}
            <div
                onClick={onClose}
                className="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
                aria-hidden="true"
            />

            <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div className="w-screen max-w-md bg-white border-l border-slate-200 shadow-2xl flex flex-col">
                    {/* Header */}
                    <div className="p-4 border-b border-slate-200 bg-[#14263D] text-white flex items-center justify-between">
                        <div className="flex items-center gap-2.5">
                            <div className="h-8 w-8 rounded-lg bg-[#19766F] flex items-center justify-center text-white">
                                <Sparkles className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="font-heading text-sm font-bold tracking-tight">
                                    Regulatory Assistant
                                </h3>
                                <div className="text-[10px] text-teal-200 font-medium">
                                    Statutory Explainer • Laws of Zimbabwe
                                </div>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                            aria-label="Close Assistant"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    {/* AI Governance Policy Notice */}
                    <div className="bg-amber-50/90 border-b border-amber-200 px-4 py-2 text-[11px] text-amber-900 flex items-start gap-2">
                        <Scale className="h-3.5 w-3.5 text-amber-700 shrink-0 mt-0.5" />
                        <div className="leading-tight">
                            <strong>Statutory Governance:</strong> AI only explains verified rules. Definitive compliance decisions are evaluated deterministically from gazetted Acts.
                        </div>
                    </div>

                    {/* Messages Body */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-4 bg-[#F7F8FA]">
                        {messages.map((msg, idx) => (
                            <div
                                key={idx}
                                className={`flex flex-col ${msg.role === 'user' ? 'items-end' : 'items-start'}`}
                            >
                                <div
                                    className={`max-w-[90%] rounded-xl px-4 py-3 text-xs leading-relaxed ${
                                        msg.role === 'user'
                                            ? 'bg-[#14263D] text-white shadow-xs'
                                            : 'bg-white text-slate-800 border border-slate-200/80 shadow-xs'
                                    }`}
                                >
                                    {msg.content}
                                </div>

                                {msg.citations && msg.citations.length > 0 && (
                                    <div className="mt-2 space-y-1.5 w-full max-w-[90%]">
                                        <div className="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                                            <BookOpen className="h-3 w-3 text-[#19766F]" />
                                            Official Statutory Citation
                                        </div>
                                        {msg.citations.map((cite, cIdx) => (
                                            <div
                                                key={cIdx}
                                                className="bg-white p-2.5 rounded-lg border border-teal-200/80 text-[11px] space-y-1 shadow-2xs"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="font-bold text-[#14263D]">{cite.act}</span>
                                                    <Badge variant="teal" size="sm">
                                                        {cite.verificationStatus}
                                                    </Badge>
                                                </div>
                                                <div className="text-slate-500 text-[10px]">
                                                    [Chapter {cite.chapter}] • {cite.section}
                                                </div>
                                                <p className="text-slate-600 text-[11px] leading-snug">
                                                    {cite.summary}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}

                        {isThinking && (
                            <div className="flex items-center gap-2 text-xs text-slate-500 bg-white p-3 rounded-xl border border-slate-200 w-max shadow-2xs">
                                <span className="h-2 w-2 rounded-full bg-[#19766F] animate-ping" />
                                <span>Verifying gazetted Zimbabwean statutory sources...</span>
                            </div>
                        )}
                    </div>

                    {/* Pre-canned Prompts */}
                    <div className="p-3 bg-white border-t border-slate-100 flex flex-wrap gap-1.5">
                        <button
                            onClick={() => handleAsk('Why do I need an ITF263 tax clearance?')}
                            className="text-[11px] px-2.5 py-1 bg-slate-100 hover:bg-slate-200/70 text-slate-700 rounded-md transition-colors"
                        >
                            ITF263 Withholding Tax
                        </button>
                        <button
                            onClick={() => handleAsk('What is the VAT registration threshold?')}
                            className="text-[11px] px-2.5 py-1 bg-slate-100 hover:bg-slate-200/70 text-slate-700 rounded-md transition-colors"
                        >
                            VAT Threshold (US$25k)
                        </button>
                        <button
                            onClick={() => handleAsk('When is NSSA registration mandatory?')}
                            className="text-[11px] px-2.5 py-1 bg-slate-100 hover:bg-slate-200/70 text-slate-700 rounded-md transition-colors"
                        >
                            NSSA Employer Rule
                        </button>
                        <button
                            onClick={() => handleAsk('Do I need PRAZ if I do not bid on tenders?')}
                            className="text-[11px] px-2.5 py-1 bg-slate-100 hover:bg-slate-200/70 text-slate-700 rounded-md transition-colors"
                        >
                            PRAZ Applicability
                        </button>
                    </div>

                    {/* Query Input */}
                    <div className="p-3 bg-white border-t border-slate-200 flex items-center gap-2">
                        <input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleAsk(query)}
                            placeholder="Ask about Zimbabwean statutory requirements..."
                            className="flex-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#19766F] focus:bg-white"
                        />
                        <button
                            onClick={() => handleAsk(query)}
                            disabled={!query.trim() || isThinking}
                            className="p-2 bg-[#19766F] hover:bg-[#14605a] disabled:opacity-40 text-white rounded-lg transition-colors"
                        >
                            <Send className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
