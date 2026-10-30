import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Compass,
    Building2,
    CheckCircle2,
    AlertCircle,
    ArrowRight,
    ArrowLeft,
    Scale,
    FileCheck,
    Coins,
    Clock,
    HelpCircle,
    Layers,
    Shield,
    Briefcase,
    Users,
    MapPin,
    Award,
    RotateCcw,
    Save,
    FileText,
    ExternalLink,
    Info,
    Check,
    AlertTriangle,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';

interface RegisterIndexProps {
    sectors: { code: string; name: string }[];
    jurisdictions: { code: string; name: string }[];
}

export default function RegisterIndex({ sectors, jurisdictions }: RegisterIndexProps) {
    // Discovery flow state
    const [currentStep, setCurrentStep] = useState<number>(1);
    const [businessMode, setBusinessMode] = useState<'NEW' | 'EXISTING'>('EXISTING');

    // Step 1: Entity Structure
    const [entityType, setEntityType] = useState('PRIVATE_LIMITED_COMPANY');
    const [cobeRegistered, setCobeRegistered] = useState(true);
    const [companyRegNumber, setCompanyRegNumber] = useState('COBE-2023-48192');
    const [hasBpNumber, setHasBpNumber] = useState(true);
    const [bpNumber, setBpNumber] = useState('BP-020038914');

    // Step 2: Revenue & Scale
    const [selectedSector, setSelectedSector] = useState('ENGINEERING');
    const [annualTurnover, setAnnualTurnover] = useState('OVER_25K'); // UNDER_25K, OVER_25K, OVER_100K
    const [currencyMix, setCurrencyMix] = useState('USD_AND_ZIG');

    // Step 3: Workforce & Payroll
    const [headcount, setHeadcount] = useState('12');
    const [hasEmployees, setHasEmployees] = useState(true);
    const [hasNssaNumber, setHasNssaNumber] = useState(true);
    const [nssaNumber, setNssaNumber] = useState('NSSA-ER-89412');
    const [necIndustry, setNecIndustry] = useState('NEC_ENG');

    // Step 4: Premises & Location
    const [selectedJurisdiction, setSelectedJurisdiction] = useState('ZW-HARARE');
    const [premisesType, setPremisesType] = useState('COMMERCIAL_WORKSHOP');
    const [hasCouncilLicence, setHasCouncilLicence] = useState(false);

    // Step 5: Public Procurement & Industry
    const [bidOnTenders, setBidOnTenders] = useState(true);
    const [isEngineeringFirm, setIsEngineeringFirm] = useState(true);
    const [joinCzi, setJoinCzi] = useState(false);

    // Submission / review state
    const [submitted, setSubmitted] = useState(false);

    const steps = [
        { number: 1, title: 'Entity Structure', desc: 'Legal personality & incorporation' },
        { number: 2, title: 'Operations & Turnover', desc: 'Sector & VAT threshold' },
        { number: 3, title: 'Workforce & Payroll', desc: 'NSSA, ZIMDEF & NEC coverage' },
        { number: 4, title: 'Premises & Location', desc: 'Local authority licensing' },
        { number: 5, title: 'Procurement & Professional', desc: 'PRAZ tenders & council licences' },
    ];

    // Compute deterministic roadmap based on user inputs
    const generateRoadmap = () => {
        const items = [];

        // 1. DCIP / COBE
        if (cobeRegistered) {
            items.push({
                authority: 'DCIP',
                name: 'Company Incorporation & Constitution',
                citation: 'Companies and Other Business Entities Act [Chapter 24:31]',
                type: 'MANDATORY_STATUTORY',
                status: 'USER_DECLARED',
                statusLabel: 'Declared Active (Reg No: ' + (companyRegNumber || 'COBE-XXXX') + ')',
                statusVariant: 'success' as const,
                cost: 'Statutory Stamp Duties Paid',
                timeline: 'Completed',
                prereqs: 'None',
                evidenceNeeded: 'Official Certificate of Incorporation + CR6/CR14',
            });
        } else {
            items.push({
                authority: 'DCIP',
                name: 'Incorporate Company under COBE Act',
                citation: 'Companies and Other Business Entities Act [Chapter 24:31]',
                type: 'MANDATORY_STATUTORY',
                status: 'ACTION_REQUIRED',
                statusLabel: 'Mandatory Initial Step',
                statusVariant: 'danger' as const,
                cost: 'Approx. $50 - $120 USD (Registrar filing fees)',
                timeline: '3 - 5 Working Days',
                prereqs: 'Name Search & Reservation',
                evidenceNeeded: 'Incorporation Certificate & Registered Memorandum',
            });
        }

        // 2. ZIMRA BP Number
        items.push({
            authority: 'ZIMRA',
            name: 'ZIMRA Business Partner (BP) Tax Number Registration',
            citation: 'Income Tax Act [Chapter 23:06] Section 61',
            type: 'MANDATORY_STATUTORY',
            status: hasBpNumber ? 'USER_DECLARED' : 'ACTION_REQUIRED',
            statusLabel: hasBpNumber ? 'Declared Active (' + bpNumber + ')' : 'Mandatory within 30 days',
            statusVariant: hasBpNumber ? 'success' as const : 'danger' as const,
            cost: 'Free (Statutory Registration)',
            timeline: '1 - 3 Working Days',
            prereqs: 'Certificate of Incorporation & CR14/CR6',
            evidenceNeeded: 'Official ZIMRA Business Partner Confirmation Notice',
        });

        // 3. ZIMRA VAT Registration (Conditional)
        if (annualTurnover !== 'UNDER_25K') {
            items.push({
                authority: 'ZIMRA',
                name: 'Compulsory VAT Registration (SI 41 of 2024)',
                citation: 'Value Added Tax Act [Chapter 23:12] Section 23; SI 41/2024',
                type: 'CONDITIONAL_STATUTORY',
                status: 'ACTION_REQUIRED',
                statusLabel: 'Mandatory: Turnover >= US$25k',
                statusVariant: 'warning' as const,
                cost: 'Free (Statutory Registration)',
                timeline: '5 - 10 Working Days',
                prereqs: 'Active BP Number & Verifiable Turnover Invoices',
                evidenceNeeded: 'ZIMRA VAT Certificate & Fiscalised Electronic Invoicing Setup',
            });
        } else {
            items.push({
                authority: 'ZIMRA',
                name: 'VAT Registration (Voluntary Option)',
                citation: 'Value Added Tax Act [Chapter 23:12] Section 23(3)',
                type: 'CONDITIONAL_STATUTORY',
                status: 'EXEMPT',
                statusLabel: 'Exempt: Turnover < US$25,000 threshold',
                statusVariant: 'neutral' as const,
                cost: 'Optional',
                timeline: 'N/A',
                prereqs: 'Proper Books of Accounts',
                evidenceNeeded: 'Optional application if supplying zero-rated goods',
            });
        }

        // 4. ZIMRA ITF263 Tax Clearance
        items.push({
            authority: 'ZIMRA',
            name: 'ITF263 Tax Clearance Certificate',
            citation: 'Income Tax Act [Chapter 23:06] Section 80',
            type: 'CONDITIONAL_STATUTORY',
            status: 'ACTION_REQUIRED',
            statusLabel: 'Critical: Prevents 30% Withholding Tax',
            statusVariant: 'warning' as const,
            cost: 'Free (Requires tax return compliance)',
            timeline: 'Instant to 48 Hours',
            prereqs: 'Up-to-date Income Tax, PAYE & VAT filings',
            evidenceNeeded: 'Electronic ITF263 Certificate with verifiable QR Code',
        });

        // 5. NSSA Employer Registration
        if (parseInt(headcount) > 0) {
            items.push({
                authority: 'NSSA',
                name: 'NSSA Employer Registration (POBS & APWCS Schemes)',
                citation: 'NSSA Act [Chapter 17:04]; SI 393/1993 & SI 68/1990',
                type: 'CONDITIONAL_STATUTORY',
                status: hasNssaNumber ? 'USER_DECLARED' : 'ACTION_REQUIRED',
                statusLabel: hasNssaNumber ? 'Declared Active (' + nssaNumber + ')' : 'Mandatory for employers',
                statusVariant: hasNssaNumber ? 'success' as const : 'danger' as const,
                cost: 'Free (Monthly 4.5% + 4.5% contributions upon payroll)',
                timeline: '3 - 5 Working Days',
                prereqs: 'ZIMRA BP Number & Director IDs',
                evidenceNeeded: 'NSSA Employer Registration Certificate (Form P3)',
            });

            // 6. ZIMDEF Levy
            items.push({
                authority: 'ZIMDEF',
                name: 'ZIMDEF 1% Manpower Training Levy Registration',
                citation: 'Manpower Planning and Development Act [Chapter 28:02] Section 53',
                type: 'CONDITIONAL_STATUTORY',
                status: 'ACTION_REQUIRED',
                statusLabel: 'Mandatory: 1% of Gross Wage Bill',
                statusVariant: 'warning' as const,
                cost: '1% of Gross Wages payable monthly',
                timeline: '3 Working Days',
                prereqs: 'NSSA Employer Code & Active Payroll',
                evidenceNeeded: 'ZIMDEF Employer Assessment Record',
            });

            // 7. Sector NEC
            items.push({
                authority: 'SECTOR_NEC',
                name: selectedSector === 'ENGINEERING' ? 'NEC for Engineering, Iron & Steel Registration' : 'NEC for Commercial Sectors Registration',
                citation: 'Labour Act [Chapter 28:01] & Gazetted Sector Collective Bargaining Agreement',
                type: 'CBA_COLLECTIVE_AGREEMENT',
                status: 'ACTION_REQUIRED',
                statusLabel: 'Mandatory Collective Bargaining Agreement',
                statusVariant: 'warning' as const,
                cost: 'Employee & Employer split council dues',
                timeline: '5 - 7 Working Days',
                prereqs: 'NSSA Employer Registration & Wage Grade Sheet',
                evidenceNeeded: 'Official NEC Certificate of Registration',
            });
        }

        // 8. Municipal Operating Licence
        items.push({
            authority: 'LOCAL_AUTHORITY',
            name: `${selectedJurisdiction === 'ZW-HARARE' ? 'City of Harare' : 'Municipal'} Commercial Operating Licence`,
            citation: 'Urban Councils Act [Chapter 29:15] Section 229; Shop Licences Act [Chapter 14:17]',
            type: 'CONDITIONAL_STATUTORY',
            status: hasCouncilLicence ? 'USER_DECLARED' : 'ACTION_REQUIRED',
            statusLabel: hasCouncilLicence ? 'Declared Active' : 'Required for Physical Premises',
            statusVariant: hasCouncilLicence ? 'success' as const : 'warning' as const,
            cost: 'Approx. $300 - $800 USD/year (Varies by floor area & zoning)',
            timeline: '14 - 21 Working Days',
            prereqs: 'City Health Inspection, Fire Clearance, Commercial Lease Agreement',
            evidenceNeeded: 'Current Year Municipal Operating Licence Certificate / Disc',
        });

        // 9. PRAZ Supplier Registration (Tender Specific)
        if (bidOnTenders) {
            items.push({
                authority: 'PRAZ',
                name: 'PRAZ Supplier Registry Accreditation (e-GP Platform)',
                citation: 'Public Procurement and Disposal of Public Assets Act [Chapter 22:23]',
                type: 'TENDER_SPECIFIC',
                status: 'ACTION_REQUIRED',
                statusLabel: 'Mandatory for Public Tenders',
                statusVariant: 'info' as const,
                cost: 'Approx. $120 - $250 USD per category / calendar year',
                timeline: '2 - 3 Working Days (Online via PRAZ e-GP portal)',
                prereqs: 'Valid ZIMRA ITF263, NSSA Clearance, Certificate of Incorporation',
                evidenceNeeded: 'Official Annual PRAZ Supplier Registration Certificate',
            });
        }

        // 10. Engineering Council (Professional regulation)
        if (isEngineeringFirm && selectedSector === 'ENGINEERING') {
            items.push({
                authority: 'PROFESSIONAL_COUNCIL',
                name: 'Engineering Council of Zimbabwe (ECZ) Firm Practising Licence',
                citation: 'Engineering Council Act [Chapter 27:22]',
                type: 'PROFESSIONAL_REGULATION',
                status: 'ACTION_REQUIRED',
                statusLabel: 'Mandatory for Consulting Engineering',
                statusVariant: 'warning' as const,
                cost: 'Annual Corporate Practising Assessment',
                timeline: '10 - 14 Working Days',
                prereqs: 'Registered PrEng / TechEng Director Credentials',
                evidenceNeeded: 'Valid ECZ Corporate Practising Certificate',
            });
        }

        // 11. Voluntary Industry Body
        if (joinCzi) {
            items.push({
                authority: 'INDUSTRY_ASSOCIATION',
                name: 'Confederation of Zimbabwe Industries (CZI) Membership',
                citation: 'Private Industry Body Constitution',
                type: 'OPTIONAL_ASSOCIATION',
                status: 'OPTIONAL',
                statusLabel: 'Voluntary Association',
                statusVariant: 'neutral' as const,
                cost: 'Annual Association Subscription Fee',
                timeline: '7 Working Days',
                prereqs: 'Commercial Operation in Manufacturing or Industrial Sector',
                evidenceNeeded: 'CZI Membership Certificate',
            });
        }

        return items;
    };

    const roadmapItems = generateRoadmap();

    return (
        <AppLayout
            title="Business Discovery & Statutory Roadmap"
            subtitle="Determine exact statutory obligations, collective bargaining agreements, and licensing requirements under Zimbabwean law."
        >
            <div className="space-y-6">
                {/* Mode Selector & Introductory Context */}
                <div className="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-2xs">
                    <div className="space-y-1">
                        <div className="text-[10px] font-bold uppercase tracking-wider text-[#19766F] flex items-center gap-1.5">
                            <Scale className="h-3.5 w-3.5" />
                            <span>Statutory Discovery Engine</span>
                        </div>
                        <h2 className="text-base font-heading font-bold text-slate-900">
                            {businessMode === 'NEW' ? 'Initial Business Registration Roadmap' : 'Existing Business Statutory Audit & Gap Analysis'}
                        </h2>
                        <p className="text-xs text-slate-600 max-w-2xl leading-relaxed">
                            {businessMode === 'NEW'
                                ? 'Evaluate mandatory formation steps, initial tax registration, social security, and municipal premises permits.'
                                : 'Audit current compliance posture, identify outstanding regulatory renewals, declare existing certificates, and map tender eligibility.'}
                        </p>
                    </div>

                    <div className="flex items-center gap-2 bg-slate-100 p-1 rounded-lg shrink-0">
                        <button
                            type="button"
                            onClick={() => { setBusinessMode('EXISTING'); setSubmitted(false); }}
                            className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                                businessMode === 'EXISTING'
                                    ? 'bg-white text-slate-900 shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Existing Business Audit
                        </button>
                        <button
                            type="button"
                            onClick={() => { setBusinessMode('NEW'); setSubmitted(false); }}
                            className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                                businessMode === 'NEW'
                                    ? 'bg-white text-slate-900 shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            New Business Setup
                        </button>
                    </div>
                </div>

                {/* Step Navigation Bar */}
                {!submitted && (
                    <div className="bg-white border border-slate-200 rounded-xl p-3 sm:p-4 shadow-2xs overflow-x-auto">
                        <div className="flex items-center justify-between min-w-[640px] gap-2">
                            {steps.map((st) => (
                                <button
                                    key={st.number}
                                    type="button"
                                    onClick={() => setCurrentStep(st.number)}
                                    className={`flex-1 flex items-center gap-3 p-2 rounded-lg text-left transition-colors ${
                                        currentStep === st.number
                                            ? 'bg-[#e2f4f2] text-[#125752] border border-[#a5dcd6]'
                                            : currentStep > st.number
                                            ? 'bg-slate-50 text-slate-700 hover:bg-slate-100'
                                            : 'text-slate-400 hover:text-slate-600'
                                    }`}
                                >
                                    <div
                                        className={`h-7 w-7 rounded-md flex items-center justify-center font-bold text-xs shrink-0 ${
                                            currentStep === st.number
                                                ? 'bg-[#19766F] text-white'
                                                : currentStep > st.number
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-slate-200 text-slate-600'
                                        }`}
                                    >
                                        {currentStep > st.number ? <Check className="h-4 w-4" /> : st.number}
                                    </div>
                                    <div className="truncate">
                                        <div className="text-xs font-bold truncate">{st.title}</div>
                                        <div className="text-[10px] text-slate-500 truncate hidden sm:block">{st.desc}</div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Discovery Form Steps */}
                {!submitted ? (
                    <Card>
                        <CardHeader>
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Step {currentStep} of 5
                                </span>
                                <CardTitle className="text-base mt-0.5">
                                    {steps[currentStep - 1].title}
                                </CardTitle>
                                <CardDescription>
                                    {steps[currentStep - 1].desc}
                                </CardDescription>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            {/* STEP 1: Entity Structure */}
                            {currentStep === 1 && (
                                <div className="space-y-5">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Legal Entity Form
                                            </label>
                                            <select
                                                value={entityType}
                                                onChange={(e) => setEntityType(e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            >
                                                <option value="PRIVATE_LIMITED_COMPANY">Private Limited Company (PLC / Ltd)</option>
                                                <option value="PRIVATE_BUSINESS_CORPORATION">Private Business Corporation (PBC)</option>
                                                <option value="REGISTERED_TRUST">Registered Deed of Trust</option>
                                                <option value="PARTNERSHIP">Commercial Partnership</option>
                                                <option value="FOREIGN_BRANCH">Foreign Company Branch (Part XXIII)</option>
                                            </select>
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Governed by the Companies and Other Business Entities Act [Chapter 24:31].
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Incorporation Status (Companies Registry)
                                            </label>
                                            <div className="space-y-2">
                                                <label className="flex items-center gap-2 text-xs text-slate-800 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="cobe_status"
                                                        checked={cobeRegistered}
                                                        onChange={() => setCobeRegistered(true)}
                                                        className="text-[#19766F] focus:ring-[#19766F]"
                                                    />
                                                    <span>Already incorporated with Certificate of Incorporation & CR14/CR6</span>
                                                </label>
                                                <label className="flex items-center gap-2 text-xs text-slate-800 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="cobe_status"
                                                        checked={!cobeRegistered}
                                                        onChange={() => setCobeRegistered(false)}
                                                        className="text-[#19766F] focus:ring-[#19766F]"
                                                    />
                                                    <span>Not yet incorporated (Requires initial name search & registration)</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    {cobeRegistered && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                                    Company Registration Number
                                                </label>
                                                <input
                                                    type="text"
                                                    value={companyRegNumber}
                                                    onChange={(e) => setCompanyRegNumber(e.target.value)}
                                                    placeholder="e.g. 14829/2021"
                                                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                                    ZIMRA Business Partner (BP) Number
                                                </label>
                                                <input
                                                    type="text"
                                                    value={bpNumber}
                                                    onChange={(e) => setBpNumber(e.target.value)}
                                                    placeholder="e.g. 020038914"
                                                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                                />
                                            </div>
                                        </div>
                                    )}

                                    <div className="p-3 bg-teal-50 border border-teal-200 rounded-lg text-xs text-teal-900 flex items-start gap-2">
                                        <Info className="h-4 w-4 text-[#19766F] shrink-0 mt-0.5" />
                                        <div className="leading-relaxed">
                                            <strong>Statutory Citation:</strong> Under Section 61 of the Income Tax Act [Chapter 23:06], every registered corporate entity must apply for a Business Partner Number within 30 days of commencing commercial operations.
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* STEP 2: Operations & Turnover */}
                            {currentStep === 2 && (
                                <div className="space-y-5">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Primary Business Sector
                                            </label>
                                            <select
                                                value={selectedSector}
                                                onChange={(e) => setSelectedSector(e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            >
                                                {sectors.map((s) => (
                                                    <option key={s.code} value={s.code}>{s.name}</option>
                                                ))}
                                            </select>
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Dictates applicable National Employment Council (NEC) collective agreements.
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Annual Taxable Turnover Bracket
                                            </label>
                                            <select
                                                value={annualTurnover}
                                                onChange={(e) => setAnnualTurnover(e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            >
                                                <option value="UNDER_25K">Below US$ 25,000 / year (Exempt from compulsory VAT)</option>
                                                <option value="OVER_25K">US$ 25,000 – US$ 100,000 / year (Mandatory VAT SI 41/2024)</option>
                                                <option value="OVER_100K">US$ 100,000 – US$ 1,000,000 / year (Medium Taxpayer)</option>
                                                <option value="LARGE">Above US$ 1,000,000 / year (Large Taxpayer Office)</option>
                                            </select>
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Under VAT Act Section 23 & SI 41/2024, turnover $\ge$ US$25k triggers mandatory VAT.
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            Operational Accounting Currencies
                                        </label>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <label className={`p-3 border rounded-xl cursor-pointer transition-colors ${currencyMix === 'USD_AND_ZIG' ? 'bg-[#e2f4f2] border-[#a5dcd6]' : 'bg-white border-slate-200'}`}>
                                                <input
                                                    type="radio"
                                                    name="currency_mix"
                                                    checked={currencyMix === 'USD_AND_ZIG'}
                                                    onChange={() => setCurrencyMix('USD_AND_ZIG')}
                                                    className="sr-only"
                                                />
                                                <div className="text-xs font-bold text-slate-900">Multi-Currency (USD + ZiG)</div>
                                                <div className="text-[10px] text-slate-500 mt-0.5">Recommended: Invoicing and wages in both currencies.</div>
                                            </label>

                                            <label className={`p-3 border rounded-xl cursor-pointer transition-colors ${currencyMix === 'USD_ONLY' ? 'bg-[#e2f4f2] border-[#a5dcd6]' : 'bg-white border-slate-200'}`}>
                                                <input
                                                    type="radio"
                                                    name="currency_mix"
                                                    checked={currencyMix === 'USD_ONLY'}
                                                    onChange={() => setCurrencyMix('USD_ONLY')}
                                                    className="sr-only"
                                                />
                                                <div className="text-xs font-bold text-slate-900">Exclusive USD</div>
                                                <div className="text-[10px] text-slate-500 mt-0.5">Export or foreign customer operations.</div>
                                            </label>

                                            <label className={`p-3 border rounded-xl cursor-pointer transition-colors ${currencyMix === 'ZIG_ONLY' ? 'bg-[#e2f4f2] border-[#a5dcd6]' : 'bg-white border-slate-200'}`}>
                                                <input
                                                    type="radio"
                                                    name="currency_mix"
                                                    checked={currencyMix === 'ZIG_ONLY'}
                                                    onChange={() => setCurrencyMix('ZIG_ONLY')}
                                                    className="sr-only"
                                                />
                                                <div className="text-xs font-bold text-slate-900">Exclusive ZiG</div>
                                                <div className="text-[10px] text-slate-500 mt-0.5">Local statutory currency only.</div>
                                            </label>
                                        </div>
                                    </div>

                                    <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900 flex items-start gap-2">
                                        <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
                                        <div className="leading-relaxed">
                                            <strong>ZIMRA VAT Rule:</strong> Statutory Instrument 41 of 2024 established the unified VAT threshold at <strong>US$25,000</strong> per annum. Operating above this threshold without registration carries civil penalties and backdated assessments.
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* STEP 3: Workforce & Payroll */}
                            {currentStep === 3 && (
                                <div className="space-y-5">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Total Employee Headcount (Excluding Equity Directors)
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                value={headcount}
                                                onChange={(e) => setHeadcount(e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            />
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Employs 1 or more staff $\implies$ Mandatory NSSA, ZIMDEF & NEC registrations.
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Existing NSSA Employer Code
                                            </label>
                                            <input
                                                type="text"
                                                value={nssaNumber}
                                                onChange={(e) => setNssaNumber(e.target.value)}
                                                placeholder="e.g. NSSA-1294812"
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            />
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Leave blank if not yet registered with NSSA.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                                        <div className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                            Statutory Payroll Levies Triggered:
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                            <div className="p-3 bg-white border border-slate-200 rounded-lg">
                                                <div className="font-bold text-slate-900">NSSA POBS & APWCS</div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">4.5% Employee + 4.5% Employer, capped at monthly statutory ceilings.</div>
                                            </div>
                                            <div className="p-3 bg-white border border-slate-200 rounded-lg">
                                                <div className="font-bold text-slate-900">ZIMDEF Training Levy</div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">1% statutory assessment on gross monthly wage bill.</div>
                                            </div>
                                            <div className="p-3 bg-white border border-slate-200 rounded-lg">
                                                <div className="font-bold text-slate-900">ZIMRA PAYE & AIDS Levy</div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">Monthly withholding based on gazetted USD & ZiG tax tables.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* STEP 4: Premises & Location */}
                            {currentStep === 4 && (
                                <div className="space-y-5">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Local Authority / Council Jurisdiction
                                            </label>
                                            <select
                                                value={selectedJurisdiction}
                                                onChange={(e) => setSelectedJurisdiction(e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            >
                                                {jurisdictions.map((j) => (
                                                    <option key={j.code} value={j.code}>{j.name}</option>
                                                ))}
                                            </select>
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Licensing requirements governed by Urban Councils Act [Chapter 29:15].
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                                Premises Classification
                                            </label>
                                            <select
                                                value={premisesType}
                                                onChange={(e) => setPremisesType(e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#19766F]"
                                            >
                                                <option value="COMMERCIAL_WORKSHOP">Commercial Workshop / Factory</option>
                                                <option value="COMMERCIAL_OFFICE">Commercial Office Suite</option>
                                                <option value="RETAIL_SHOP">Retail Shopfront / Supermarket</option>
                                                <option value="RESIDENTIAL_HOME_OFFICE">Home Office / Residential Variance</option>
                                                <option value="MINING_SITE">Mining Claim / Remote Site</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl">
                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={hasCouncilLicence}
                                                onChange={(e) => setHasCouncilLicence(e.target.checked)}
                                                className="mt-0.5 rounded text-[#19766F] focus:ring-[#19766F]"
                                            />
                                            <div>
                                                <div className="text-xs font-bold text-slate-900">
                                                    Our business holds a current Municipal Operating Licence / Shop Licence
                                                </div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">
                                                    Check this if you already hold an active licence disc or permit from the local authority. You will be prompted to upload evidence in the Document Vault.
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            )}

                            {/* STEP 5: Public Procurement & Industry */}
                            {currentStep === 5 && (
                                <div className="space-y-5">
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-4">
                                        <div className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                            Public Procurement & Tendership (PPDPA Act)
                                        </div>

                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={bidOnTenders}
                                                onChange={(e) => setBidOnTenders(e.target.checked)}
                                                className="mt-0.5 rounded text-[#19766F] focus:ring-[#19766F]"
                                            />
                                            <div>
                                                <div className="text-xs font-bold text-slate-900">
                                                    We intend to bid on Zimbabwean Government, Municipal, or Parastatal Tenders
                                                </div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">
                                                    Triggers mandatory <strong>PRAZ Supplier Registration</strong> under the Public Procurement and Disposal of Public Assets Act [Chapter 22:23].
                                                </div>
                                            </div>
                                        </label>

                                        {bidOnTenders && (
                                            <div className="p-3 bg-purple-50 border border-purple-200 rounded-lg text-xs text-purple-900">
                                                <strong>PRAZ Pre-requisites:</strong> Bidding requires active ZIMRA ITF263 Tax Clearance, NSSA Compliance Clearance, and registered CR14/CR6 forms.
                                            </div>
                                        )}
                                    </div>

                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-4">
                                        <div className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                            Professional & Industry Associations
                                        </div>

                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={isEngineeringFirm}
                                                onChange={(e) => setIsEngineeringFirm(e.target.checked)}
                                                className="mt-0.5 rounded text-[#19766F] focus:ring-[#19766F]"
                                            />
                                            <div>
                                                <div className="text-xs font-bold text-slate-900">
                                                    Engineering Council of Zimbabwe (ECZ) Firm Practising Licence
                                                </div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">
                                                    Mandatory under Engineering Council Act [Chapter 27:22] for firms offering professional engineering consulting.
                                                </div>
                                            </div>
                                        </label>

                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={joinCzi}
                                                onChange={(e) => setJoinCzi(e.target.checked)}
                                                className="mt-0.5 rounded text-[#19766F] focus:ring-[#19766F]"
                                            />
                                            <div>
                                                <div className="text-xs font-bold text-slate-900">
                                                    Confederation of Zimbabwe Industries (CZI) Membership
                                                </div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">
                                                    Voluntary private industrial association (optional advocacy membership).
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            )}
                        </CardContent>

                        <CardFooter className="flex items-center justify-between">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentStep(Math.max(1, currentStep - 1))}
                                disabled={currentStep === 1}
                                icon={<ArrowLeft className="h-3.5 w-3.5" />}
                            >
                                Previous Step
                            </Button>

                            <div className="flex items-center gap-2">
                                {currentStep < 5 ? (
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        onClick={() => setCurrentStep(currentStep + 1)}
                                        icon={<ArrowRight className="h-3.5 w-3.5" />}
                                    >
                                        Next Step
                                    </Button>
                                ) : (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => setSubmitted(true)}
                                        icon={<FileCheck className="h-3.5 w-3.5" />}
                                    >
                                        Review & Generate Statutory Roadmap
                                    </Button>
                                )}
                            </div>
                        </CardFooter>
                    </Card>
                ) : (
                    /* GENERATED STATUTORY ROADMAP SCREEN */
                    <div className="space-y-6">
                        {/* Summary Header */}
                        <div className="bg-[#14263D] text-white p-6 rounded-xl border border-[#1C3554] flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Badge variant="teal" size="sm">
                                        Deterministic Rules Engine Output
                                    </Badge>
                                    <span className="text-xs text-slate-300">
                                        Evaluated across 11 Zimbabwean Statutory Authorities
                                    </span>
                                </div>
                                <h2 className="font-heading font-extrabold text-xl text-white">
                                    Official Statutory Roadmap & Compliance Matrix
                                </h2>
                                <p className="text-xs text-slate-300 max-w-2xl leading-relaxed">
                                    Derived from the Companies Act, Income Tax Act, VAT Act (SI 41/2024), NSSA Act, ZIMDEF Act, and Urban Councils Act.
                                </p>
                            </div>

                            <div className="flex items-center gap-2 shrink-0">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSubmitted(false)}
                                    icon={<RotateCcw className="h-3.5 w-3.5" />}
                                    className="!bg-white/10 !text-white !border-white/30 hover:!bg-white/20"
                                >
                                    Modify Answers
                                </Button>
                            </div>
                        </div>

                        {/* Roadmap Matrix Table */}
                        <Card>
                            <CardHeader>
                                <div>
                                    <CardTitle>
                                        <Layers className="h-4 w-4 text-[#19766F]" />
                                        Evaluated Statutory Obligations ({roadmapItems.length} Mapped)
                                    </CardTitle>
                                    <CardDescription>
                                        Notice: Declaring an existing certificate sets evidence status to User Declared. Supporting certificates must be verified in the Document Vault.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                            <tr>
                                                <th className="py-3 px-4">Authority & Obligation</th>
                                                <th className="py-3 px-4">Legal Citation</th>
                                                <th className="py-3 px-4">Classification</th>
                                                <th className="py-3 px-4">Evidence Status</th>
                                                <th className="py-3 px-4">Fee Estimate</th>
                                                <th className="py-3 px-4 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {roadmapItems.map((item, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/60 transition-colors">
                                                    <td className="py-3 px-4">
                                                        <div className="font-bold text-slate-900">{item.name}</div>
                                                        <div className="text-[10px] text-slate-500 font-semibold">{item.authority}</div>
                                                    </td>
                                                    <td className="py-3 px-4 font-mono text-[11px] text-slate-600 max-w-xs">
                                                        {item.citation}
                                                    </td>
                                                    <td className="py-3 px-4 whitespace-nowrap">
                                                        <Badge variant="neutral" size="sm">
                                                            {item.type.replace(/_/g, ' ')}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4 whitespace-nowrap">
                                                        <Badge variant={item.statusVariant} size="sm">
                                                            {item.statusLabel}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4 text-slate-700 whitespace-nowrap">
                                                        {item.cost}
                                                    </td>
                                                    <td className="py-3 px-4 text-right whitespace-nowrap">
                                                        <Button
                                                            href="/documents"
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            Upload Evidence
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                            <CardFooter className="flex items-center justify-between text-xs text-slate-500">
                                <div>
                                    Showing {roadmapItems.length} evaluated requirements for {entityType.replace(/_/g, ' ')}.
                                </div>
                                <Button
                                    href="/documents"
                                    variant="primary"
                                    size="sm"
                                    icon={<FileCheck className="h-3.5 w-3.5" />}
                                >
                                    Proceed to Document Vault
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
