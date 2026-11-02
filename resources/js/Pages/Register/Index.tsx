import React, { useState, useEffect } from 'react';
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
    Plus,
    Trash2,
    RefreshCw,
    Lock,
    History,
    Calendar,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/UI/Card';
import { Badge, BadgeVariant } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import axios from 'axios';

interface Authority {
    code?: string;
    name?: string;
    category?: string;
    portal_url?: string;
}

interface RuleVersionInfo {
    id?: string;
    version_code?: string;
    effective_from?: string;
    effective_to?: string;
    status?: string;
    verification_status?: string;
}

interface DeclaredRegistration {
    declared_number?: string;
    declared_date?: string;
    verification_state?: string;
}

interface RoadmapItem {
    sequence_number: number;
    priority: number;
    obligation_code: string;
    title: string;
    authority?: Authority;
    legal_classification: string;
    applicability_decision: string;
    required_prerequisites: string[];
    procedural_recommendations: string[];
    missing_information: string[];
    source_citation?: string;
    evidence_requirements?: {
        required_documents: string[];
        is_entity_level: boolean;
        renewal_frequency?: string;
    };
    verification_status: string;
    applicable_rule_version?: RuleVersionInfo | null;
    trigger_reasons: string[];
    declared_registration?: DeclaredRegistration | null;
    estimated_processing_days?: number;
}

interface AssessmentSummary {
    total_evaluated: number;
    mandatory_count: number;
    conditional_count: number;
    exempt_count: number;
    not_applicable_count: number;
    unverified_hold_count: number;
    requires_information_count: number;
}

interface AssessmentData {
    assessment_id: string;
    tenant_id: string;
    organisation_id: string;
    assessment_type?: string;
    assessed_at: string;
    summary: AssessmentSummary;
    roadmap: RoadmapItem[];
    exemptions: any[];
    not_applicable: any[];
    unresolved: any[];
    is_idempotent_replay?: boolean;
}

interface HistoricalAssessment {
    id: string;
    assessment_type: string;
    generated_at: string;
    formatted_date: string;
    total_evaluated: number;
    mandatory_count: number;
    idempotency_key?: string;
}

interface QuestionnaireData {
    id: string;
    status: string;
    current_step: number;
    answers: Record<string, any>;
    completed_at?: string;
}

interface BranchItem {
    name: string;
    jurisdiction_code: string;
    city: string;
    physical_address: string;
    activities?: string;
    is_headquarters?: boolean;
}

interface RegisterIndexProps {
    sectors: { code: string; name: string }[];
    jurisdictions: { code: string; name: string; province?: string; council_type?: string }[];
    questionnaire?: QuestionnaireData | null;
    latest_assessment?: AssessmentData | null;
    assessment_history?: HistoricalAssessment[];
    active_organisation?: {
        id: string;
        legal_name: string;
        trading_name?: string;
        entity_type: string;
    } | null;
}

export default function RegisterIndex({
    sectors,
    jurisdictions,
    questionnaire,
    latest_assessment,
    assessment_history = [],
    active_organisation,
}: RegisterIndexProps) {
    // Mode state: 'QUESTIONNAIRE' or 'ROADMAP'
    const [viewMode, setViewMode] = useState<'QUESTIONNAIRE' | 'ROADMAP'>(
        latest_assessment ? 'ROADMAP' : 'QUESTIONNAIRE'
    );

    // Active assessment being viewed
    const [activeAssessment, setActiveAssessment] = useState<AssessmentData | null>(latest_assessment ?? null);

    // Roadmap active tab: 'ROADMAP' | 'EXEMPTIONS' | 'NOT_APPLICABLE' | 'HISTORY'
    const [roadmapTab, setRoadmapTab] = useState<'ROADMAP' | 'EXEMPTIONS' | 'NOT_APPLICABLE' | 'HISTORY'>('ROADMAP');

    // Stepper state (1-5)
    const [currentStep, setCurrentStep] = useState<number>(
        questionnaire?.current_step ? Math.min(5, Math.max(1, questionnaire.current_step)) : 1
    );

    // Save and submission loading & error states
    const [isSavingDraft, setIsSavingDraft] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [saveMessage, setSaveMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // Form Answers State initialized from saved questionnaire or defaults
    const initialAnswers = questionnaire?.answers ?? {};

    // STEP 1: Business Identity
    const [businessMode, setBusinessMode] = useState<'NEW' | 'EXISTING'>(
        initialAnswers.business_mode ?? (initialAnswers.is_already_incorporated ? 'EXISTING' : 'NEW')
    );
    const [entityType, setEntityType] = useState<string>(
        initialAnswers.entity_type ?? active_organisation?.entity_type ?? 'PRIVATE_LIMITED_COMPANY'
    );
    const [registeredName, setRegisteredName] = useState<string>(
        initialAnswers.registered_name ?? active_organisation?.legal_name ?? ''
    );
    const [tradingName, setTradingName] = useState<string>(
        initialAnswers.trading_name ?? active_organisation?.trading_name ?? ''
    );
    const [isAlreadyIncorporated, setIsAlreadyIncorporated] = useState<boolean>(
        Boolean(initialAnswers.is_already_incorporated ?? (businessMode === 'EXISTING'))
    );
    const [companyRegNumber, setCompanyRegNumber] = useState<string>(
        initialAnswers.company_registration_number ?? ''
    );
    const [incorporationDate, setIncorporationDate] = useState<string>(
        initialAnswers.incorporation_date ?? ''
    );
    const [bpNumber, setBpNumber] = useState<string>(
        initialAnswers.bp_number ?? ''
    );

    // STEP 2: Activities & Scale
    const [industrySector, setIndustrySector] = useState<string>(
        initialAnswers.industry_sector ?? 'ENGINEERING'
    );
    const [secondaryActivities, setSecondaryActivities] = useState<string[]>(
        Array.isArray(initialAnswers.secondary_activities) ? initialAnswers.secondary_activities : []
    );
    const [goodsAndServices, setGoodsAndServices] = useState<string>(
        initialAnswers.goods_and_services ?? ''
    );
    const [licensedProfessionInvolved, setLicensedProfessionInvolved] = useState<boolean>(
        Boolean(initialAnswers.licensed_profession_involved)
    );
    const [annualTurnoverUsd, setAnnualTurnoverUsd] = useState<string>(
        initialAnswers.annual_turnover_usd ? String(initialAnswers.annual_turnover_usd) : '45000'
    );
    const [turnoverCurrency, setTurnoverCurrency] = useState<'USD' | 'ZIG'>(
        initialAnswers.turnover_currency ?? 'USD'
    );
    const [wantsVoluntaryVat, setWantsVoluntaryVat] = useState<boolean>(
        Boolean(initialAnswers.wants_voluntary_vat)
    );
    const [hasVatRegistration, setHasVatRegistration] = useState<boolean>(
        Boolean(initialAnswers.has_vat_registration || initialAnswers.vat_number)
    );
    const [vatRegistrationNumber, setVatRegistrationNumber] = useState<string>(
        initialAnswers.vat_registration_number ?? initialAnswers.vat_number ?? ''
    );
    const [hasItf263, setHasItf263] = useState<boolean>(
        Boolean(initialAnswers.has_itf263)
    );

    // STEP 3: Workforce & Payroll
    const [employeeCount, setEmployeeCount] = useState<string>(
        initialAnswers.employee_count !== undefined ? String(initialAnswers.employee_count) : '6'
    );
    const [hasEmployees, setHasEmployees] = useState<boolean>(
        initialAnswers.has_employees !== undefined ? Boolean(initialAnswers.has_employees) : true
    );
    const [employeeCategories, setEmployeeCategories] = useState<string[]>(
        Array.isArray(initialAnswers.employee_categories)
            ? initialAnswers.employee_categories
            : ['TECHNICAL_SKILLED', 'CLERICAL']
    );
    const [paysWages, setPaysWages] = useState<boolean>(
        initialAnswers.pays_wages !== undefined ? Boolean(initialAnswers.pays_wages) : true
    );
    const [grossMonthlyWageBill, setGrossMonthlyWageBill] = useState<string>(
        initialAnswers.gross_monthly_wage_bill_usd ? String(initialAnswers.gross_monthly_wage_bill_usd) : '3500'
    );
    const [hasNssaRegistration, setHasNssaRegistration] = useState<boolean>(
        Boolean(initialAnswers.has_nssa_registration || initialAnswers.nssa_employer_number)
    );
    const [nssaEmployerNumber, setNssaEmployerNumber] = useState<string>(
        initialAnswers.nssa_employer_number ?? initialAnswers.nssa_number ?? ''
    );
    const [necIndustry, setNecIndustry] = useState<string>(
        initialAnswers.nec_industry ?? (industrySector === 'ENGINEERING' ? 'NEC_ENG' : 'NEC_COMM')
    );
    const [hasNecRegistration, setHasNecRegistration] = useState<boolean>(
        Boolean(initialAnswers.has_nec_registration || initialAnswers.nec_registration_number)
    );
    const [necRegistrationNumber, setNecRegistrationNumber] = useState<string>(
        initialAnswers.nec_registration_number ?? ''
    );
    const [isExemptDiplomaticOrReligious, setIsExemptDiplomaticOrReligious] = useState<boolean>(
        Boolean(initialAnswers.is_exempt_diplomatic_or_religious)
    );

    // STEP 4: Premises & Jurisdictions
    const [hasPhysicalPremises, setHasPhysicalPremises] = useState<boolean>(
        initialAnswers.has_physical_premises !== undefined ? Boolean(initialAnswers.has_physical_premises) : true
    );
    const [premisesType, setPremisesType] = useState<string>(
        initialAnswers.premises_type ?? 'INDUSTRIAL_WORKSHOP'
    );
    const [jurisdictionCode, setJurisdictionCode] = useState<string>(
        initialAnswers.jurisdiction_code ?? 'ZW-HARARE'
    );
    const [physicalAddress, setPhysicalAddress] = useState<string>(
        initialAnswers.physical_address ?? '14 Kelvin Road North, Graniteside, Harare'
    );
    const [branches, setBranches] = useState<BranchItem[]>(
        Array.isArray(initialAnswers.branches) ? initialAnswers.branches : []
    );
    const [hasCouncilLicence, setHasCouncilLicence] = useState<boolean>(
        Boolean(initialAnswers.has_council_licence || initialAnswers.council_licence_number)
    );
    const [councilLicenceNumber, setCouncilLicenceNumber] = useState<string>(
        initialAnswers.council_licence_number ?? ''
    );

    // STEP 5: Procurement & Review
    const [bidsOnGovernmentTenders, setBidsOnGovernmentTenders] = useState<boolean>(
        Boolean(initialAnswers.bids_on_government_tenders ?? initialAnswers.bid_on_tenders)
    );
    const [hasPrazRegistration, setHasPrazRegistration] = useState<boolean>(
        Boolean(initialAnswers.has_praz_registration || initialAnswers.praz_supplier_number)
    );
    const [prazSupplierNumber, setPrazSupplierNumber] = useState<string>(
        initialAnswers.praz_supplier_number ?? ''
    );
    const [tenderCategories, setTenderCategories] = useState<string[]>(
        Array.isArray(initialAnswers.tender_categories) ? initialAnswers.tender_categories : ['GOODS', 'SERVICES']
    );
    const [wantsCziMembership, setWantsCziMembership] = useState<boolean>(
        Boolean(initialAnswers.wants_czi_membership ?? initialAnswers.join_czi)
    );
    const [confirmationAccurate, setConfirmationAccurate] = useState<boolean>(false);

    // Sync businessMode and isAlreadyIncorporated
    useEffect(() => {
        if (businessMode === 'NEW') {
            setIsAlreadyIncorporated(false);
            setCompanyRegNumber('');
        } else {
            setIsAlreadyIncorporated(true);
        }
    }, [businessMode]);

    // Construct the answers payload object
    const collectAnswers = () => ({
        // Step 1
        business_mode: businessMode,
        entity_type: entityType,
        registered_name: registeredName,
        trading_name: tradingName,
        is_already_incorporated: isAlreadyIncorporated,
        company_registration_number: isAlreadyIncorporated ? companyRegNumber : null,
        incorporation_date: isAlreadyIncorporated ? incorporationDate : null,
        bp_number: bpNumber || null,

        // Step 2
        industry_sector: industrySector,
        secondary_activities: secondaryActivities,
        goods_and_services: goodsAndServices,
        licensed_profession_involved: licensedProfessionInvolved,
        annual_turnover_usd: parseFloat(annualTurnoverUsd) || 0,
        turnover_currency: turnoverCurrency,
        wants_voluntary_vat: wantsVoluntaryVat,
        has_vat_registration: hasVatRegistration,
        vat_registration_number: hasVatRegistration ? vatRegistrationNumber : null,
        has_itf263: hasItf263,

        // Step 3
        employee_count: parseInt(employeeCount, 10) || 0,
        has_employees: parseInt(employeeCount, 10) > 0,
        employee_categories: employeeCategories,
        pays_wages: paysWages,
        gross_monthly_wage_bill_usd: paysWages ? (parseFloat(grossMonthlyWageBill) || 0) : null,
        has_nssa_registration: hasNssaRegistration,
        nssa_employer_number: hasNssaRegistration ? nssaEmployerNumber : null,
        nec_industry: necIndustry,
        has_nec_registration: hasNecRegistration,
        nec_registration_number: hasNecRegistration ? necRegistrationNumber : null,
        is_exempt_diplomatic_or_religious: isExemptDiplomaticOrReligious,

        // Step 4
        has_physical_premises: hasPhysicalPremises,
        premises_type: premisesType,
        jurisdiction_code: jurisdictionCode,
        physical_address: physicalAddress,
        branches: branches,
        has_council_licence: hasCouncilLicence,
        council_licence_number: hasCouncilLicence ? councilLicenceNumber : null,

        // Step 5
        bids_on_government_tenders: bidsOnGovernmentTenders,
        has_praz_registration: hasPrazRegistration,
        praz_supplier_number: hasPrazRegistration ? prazSupplierNumber : null,
        tender_categories: tenderCategories,
        wants_czi_membership: wantsCziMembership,
        confirmation_accurate: confirmationAccurate,
    });

    // Save Draft to PostgreSQL
    const handleSaveDraft = async (targetStep?: number) => {
        setIsSavingDraft(true);
        setSaveMessage(null);
        setErrorMessage(null);

        try {
            const stepToSave = targetStep ?? currentStep;
            const payload = {
                current_step: stepToSave,
                answers: collectAnswers(),
            };

            const response = await axios.post('/registration/questionnaire/save', payload);

            if (response.data.success) {
                const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                setSaveMessage(`Draft saved at ${now}`);
                setTimeout(() => setSaveMessage(null), 4000);
            }
        } catch (err: any) {
            setErrorMessage(err.response?.data?.message || err.response?.data?.error || 'Failed to save draft.');
        } finally {
            setIsSavingDraft(false);
        }
    };

    // Final Submission & Deterministic Assessment Generation
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!confirmationAccurate) {
            setErrorMessage('Please confirm accuracy declaration before generating statutory assessment.');
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);

        try {
            const payload = {
                answers: collectAnswers(),
            };

            const response = await axios.post('/registration/questionnaire/submit', payload);

            if (response.data.success && response.data.assessment) {
                setActiveAssessment(response.data.assessment);
                setViewMode('ROADMAP');
                setRoadmapTab('ROADMAP');
            }
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                const errors = Object.values(err.response.data.errors).flat();
                setErrorMessage(errors.join(' '));
            } else {
                setErrorMessage(err.response?.data?.message || err.response?.data?.error || 'Failed to submit questionnaire.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    // Reassessment
    const handleReassess = async () => {
        setIsSubmitting(true);
        setErrorMessage(null);

        try {
            const payload = {
                answers: collectAnswers(),
            };

            const response = await axios.post('/registration/reassess', payload);

            if (response.data.success && response.data.assessment) {
                setActiveAssessment(response.data.assessment);
                setViewMode('ROADMAP');
                setRoadmapTab('ROADMAP');
            }
        } catch (err: any) {
            setErrorMessage(err.response?.data?.message || err.response?.data?.error || 'Failed to generate reassessment.');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Load a historical snapshot
    const handleLoadHistorical = async (assessmentId: string) => {
        try {
            const res = await axios.get(`/registration/assessments/${assessmentId}`);
            if (res.data.success && res.data.assessment) {
                setActiveAssessment(res.data.assessment);
                setRoadmapTab('ROADMAP');
            }
        } catch (err: any) {
            setErrorMessage('Failed to load historical assessment.');
        }
    };

    // Branch management helpers
    const addBranch = () => {
        setBranches([
            ...branches,
            {
                name: `Branch #${branches.length + 1}`,
                jurisdiction_code: 'ZW-BULAWAYO',
                city: 'Bulawayo',
                physical_address: '',
                activities: 'Retail & Distribution',
            },
        ]);
    };

    const updateBranch = (index: number, field: keyof BranchItem, value: any) => {
        const updated = [...branches];
        updated[index] = { ...updated[index], [field]: value };
        setBranches(updated);
    };

    const removeBranch = (index: number) => {
        setBranches(branches.filter((_, i) => i !== index));
    };

    const steps = [
        { number: 1, title: 'Entity Identity', desc: 'Structure & incorporation' },
        { number: 2, title: 'Activities & Scale', desc: 'Sector, VAT & revenue' },
        { number: 3, title: 'Workforce & Payroll', desc: 'NSSA, ZIMDEF & NEC' },
        { number: 4, title: 'Premises & Branches', desc: 'Local council licences' },
        { number: 5, title: 'Procurement & Review', desc: 'PRAZ tenders & confirmation' },
    ];

    const turnoverNumber = parseFloat(annualTurnoverUsd) || 0;
    const isVatThresholdExceeded = turnoverNumber >= 25000;

    // Helper for Decision Badge variant
    const getDecisionBadge = (decision: string) => {
        switch (decision) {
            case 'MANDATORY':
                return <Badge variant="danger" size="sm" dot>MANDATORY</Badge>;
            case 'CONDITIONAL':
                return <Badge variant="warning" size="sm" dot>CONDITIONAL</Badge>;
            case 'EXEMPT':
                return <Badge variant="success" size="sm" dot>EXEMPT</Badge>;
            case 'NOT_APPLICABLE':
                return <Badge variant="neutral" size="sm">NOT APPLICABLE</Badge>;
            case 'UNVERIFIED_HOLD':
                return <Badge variant="warning" size="sm" dot>UNVERIFIED HOLD</Badge>;
            case 'REQUIRES_INFORMATION':
                return <Badge variant="info" size="sm" dot>REQUIRES INFO</Badge>;
            default:
                return <Badge variant="neutral" size="sm">{decision}</Badge>;
        }
    };

    return (
        <AppLayout
            title="Registration Engine"
            subtitle="Zimbabwe Statutory Discovery & Deterministic Registration Roadmap"
        >
            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                {/* Header Card */}
                <div className="bg-[#14263D] text-white p-6 rounded-xl border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2">
                            <span className="h-2.5 w-2.5 rounded-full bg-teal-400 animate-pulse" />
                            <span className="text-xs font-semibold tracking-wider uppercase text-teal-300">
                                Zimbabwe Statutory Engine • Milestone 2.3
                            </span>
                        </div>
                        <h1 className="font-heading font-extrabold text-2xl text-white">
                            Business Discovery & Regulatory Roadmap
                        </h1>
                        <p className="text-xs text-slate-300 max-w-3xl leading-relaxed">
                            Deterministic regulatory assessment across CIPZ, ZIMRA, NSSA, ZIMDEF, PRAZ, City of Harare, and National Employment Councils.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {viewMode === 'ROADMAP' ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setViewMode('QUESTIONNAIRE')}
                                icon={<RotateCcw className="h-3.5 w-3.5" />}
                                className="!bg-white/10 !text-white !border-white/30 hover:!bg-white/20"
                            >
                                Edit Circumstances & Reassess
                            </Button>
                        ) : activeAssessment ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setViewMode('ROADMAP')}
                                icon={<Layers className="h-3.5 w-3.5" />}
                                className="!bg-white/10 !text-white !border-white/30 hover:!bg-white/20"
                            >
                                View Current Roadmap
                            </Button>
                        ) : null}
                    </div>
                </div>

                {/* Notifications & Error Banners */}
                {errorMessage && (
                    <div className="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 text-xs">
                        <AlertCircle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <span className="font-bold">Validation Notice:</span>
                            <p>{errorMessage}</p>
                        </div>
                    </div>
                )}

                {saveMessage && (
                    <div className="p-3 rounded-xl bg-teal-50 border border-teal-200 text-teal-800 flex items-center gap-2 text-xs">
                        <CheckCircle2 className="w-4 h-4 text-teal-600 shrink-0" />
                        <span>{saveMessage}</span>
                    </div>
                )}

                {viewMode === 'QUESTIONNAIRE' ? (
                    /* ------------------------------------------------------------- */
                    /* 5-STEP QUESTIONNAIRE WIZARD (Phase B & C)                     */
                    /* ------------------------------------------------------------- */
                    <Card>
                        {/* Stepper Progress Bar */}
                        <div className="border-b border-slate-100 bg-slate-50/70 p-4 sm:p-5 rounded-t-xl">
                            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                {steps.map((step) => {
                                    const isDone = currentStep > step.number;
                                    const isCurrent = currentStep === step.number;
                                    return (
                                        <button
                                            key={step.number}
                                            type="button"
                                            onClick={() => {
                                                handleSaveDraft(step.number);
                                                setCurrentStep(step.number);
                                            }}
                                            className={`text-left p-2.5 rounded-lg border transition-all ${
                                                isCurrent
                                                    ? 'bg-white border-[#19766F] shadow-xs ring-1 ring-[#19766F]'
                                                    : isDone
                                                    ? 'bg-emerald-50/70 border-emerald-200 hover:bg-emerald-50'
                                                    : 'bg-white/80 border-slate-200 hover:bg-white'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between mb-1">
                                                <span
                                                    className={`text-[10px] font-bold uppercase tracking-wider ${
                                                        isCurrent
                                                            ? 'text-[#19766F]'
                                                            : isDone
                                                            ? 'text-emerald-700'
                                                            : 'text-slate-500'
                                                    }`}
                                                >
                                                    Step {step.number}
                                                </span>
                                                {isDone && <Check className="w-3.5 h-3.5 text-emerald-600" />}
                                            </div>
                                            <div className="font-heading text-xs font-bold text-slate-800 truncate">
                                                {step.title}
                                            </div>
                                            <div className="text-[10px] text-slate-500 truncate hidden sm:block">
                                                {step.desc}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <CardContent className="p-6 space-y-6">
                            {/* STEP 1: BUSINESS IDENTITY */}
                            {currentStep === 1 && (
                                <div className="space-y-6">
                                    <div className="border-b border-slate-100 pb-3">
                                        <h2 className="text-base font-bold text-slate-900 font-heading">
                                            Step 1: Business Identity & Legal Structure
                                        </h2>
                                        <p className="text-xs text-slate-500">
                                            Specify business lifecycle status, legal personality under the Companies and Other Business Entities Act [Chapter 24:31], and registration identifiers.
                                        </p>
                                    </div>

                                    {/* Business Mode Toggle */}
                                    <div className="space-y-2">
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                            Business Formation Mode
                                        </label>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div
                                                onClick={() => setBusinessMode('NEW')}
                                                className={`p-4 rounded-xl border cursor-pointer transition-all ${
                                                    businessMode === 'NEW'
                                                        ? 'bg-teal-50/60 border-[#19766F] ring-1 ring-[#19766F]'
                                                        : 'bg-white border-slate-200 hover:border-slate-300'
                                                }`}
                                            >
                                                <div className="flex items-center gap-2 mb-1">
                                                    <Compass className="w-4 h-4 text-[#19766F]" />
                                                    <span className="font-bold text-sm text-slate-900">New Business Formation</span>
                                                </div>
                                                <p className="text-xs text-slate-500">
                                                    Entity not yet incorporated with CIPZ. Roadmap will sequence foundational company incorporation first.
                                                </p>
                                            </div>

                                            <div
                                                onClick={() => setBusinessMode('EXISTING')}
                                                className={`p-4 rounded-xl border cursor-pointer transition-all ${
                                                    businessMode === 'EXISTING'
                                                        ? 'bg-teal-50/60 border-[#19766F] ring-1 ring-[#19766F]'
                                                        : 'bg-white border-slate-200 hover:border-slate-300'
                                                }`}
                                            >
                                                <div className="flex items-center gap-2 mb-1">
                                                    <Building2 className="w-4 h-4 text-[#19766F]" />
                                                    <span className="font-bold text-sm text-slate-900">Existing Operating Entity</span>
                                                </div>
                                                <p className="text-xs text-slate-500">
                                                    Already incorporated with CIPZ / Registrar of Companies. Map ongoing tax, NSSA, local council, and tender obligations.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Legal Entity Type */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Legal Entity Structure <span className="text-rose-500">*</span>
                                            </label>
                                            <select
                                                value={entityType}
                                                onChange={(e) => setEntityType(e.target.value)}
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            >
                                                <option value="PRIVATE_LIMITED_COMPANY">Private Limited Company ((Pvt) Ltd - COBE Act)</option>
                                                <option value="PBC">Private Business Corporation (PBC - COBE Act)</option>
                                                <option value="PARTNERSHIP">Partnership / Joint Venture</option>
                                                <option value="SOLE_PROPRIETORSHIP">Sole Proprietorship</option>
                                                <option value="TRUST">Registered Business Trust</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Registered Legal Name <span className="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                value={registeredName}
                                                onChange={(e) => setRegisteredName(e.target.value)}
                                                placeholder="e.g. Apex Industrial Solutions (Pvt) Ltd"
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Trading Name (if different)
                                            </label>
                                            <input
                                                type="text"
                                                value={tradingName}
                                                onChange={(e) => setTradingName(e.target.value)}
                                                placeholder="e.g. Apex Engineering"
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            />
                                        </div>

                                        {isAlreadyIncorporated && (
                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                    CIPZ Company Registration Number
                                                </label>
                                                <input
                                                    type="text"
                                                    value={companyRegNumber}
                                                    onChange={(e) => setCompanyRegNumber(e.target.value)}
                                                    placeholder="e.g. COBE-2023-48192 or 1234/2022"
                                                    className="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {isAlreadyIncorporated && (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                    Date of Incorporation
                                                </label>
                                                <input
                                                    type="date"
                                                    value={incorporationDate}
                                                    onChange={(e) => setIncorporationDate(e.target.value)}
                                                    className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                    Existing ZIMRA Business Partner (BP/TIN)
                                                </label>
                                                <input
                                                    type="text"
                                                    value={bpNumber}
                                                    onChange={(e) => setBpNumber(e.target.value)}
                                                    placeholder="e.g. BP-020038914"
                                                    className="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* STEP 2: ACTIVITIES & REVENUE */}
                            {currentStep === 2 && (
                                <div className="space-y-6">
                                    <div className="border-b border-slate-100 pb-3">
                                        <h2 className="text-base font-bold text-slate-900 font-heading">
                                            Step 2: Business Activities, Revenue & Taxation
                                        </h2>
                                        <p className="text-xs text-slate-500">
                                            Determine VAT thresholds (VAT Act [Chapter 23:12]), professional licensing, and sector-specific collective agreements.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Primary Industry Sector <span className="text-rose-500">*</span>
                                            </label>
                                            <select
                                                value={industrySector}
                                                onChange={(e) => setIndustrySector(e.target.value)}
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            >
                                                {sectors.map((s) => (
                                                    <option key={s.code} value={s.code}>
                                                        {s.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Goods & Services Traded
                                            </label>
                                            <input
                                                type="text"
                                                value={goodsAndServices}
                                                onChange={(e) => setGoodsAndServices(e.target.value)}
                                                placeholder="e.g. Steel fabrication, structural tanks, equipment hire"
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            />
                                        </div>
                                    </div>

                                    {/* Annual Turnover & VAT statutory threshold check */}
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                                        <div className="flex items-center justify-between">
                                            <label className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                                Annual Taxable Turnover Estimate
                                            </label>
                                            <span className="text-[11px] font-semibold text-teal-800 bg-teal-100 px-2 py-0.5 rounded-full">
                                                Statutory VAT Threshold: US$25,000
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div className="sm:col-span-2">
                                                <div className="relative">
                                                    <span className="absolute left-3 top-2 text-xs font-bold text-slate-400">
                                                        $
                                                    </span>
                                                    <input
                                                        type="number"
                                                        value={annualTurnoverUsd}
                                                        onChange={(e) => setAnnualTurnoverUsd(e.target.value)}
                                                        min="0"
                                                        step="1000"
                                                        className="w-full text-xs pl-7 rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3 font-mono"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <select
                                                    value={turnoverCurrency}
                                                    onChange={(e) => setTurnoverCurrency(e.target.value as any)}
                                                    className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                                >
                                                    <option value="USD">USD (United States Dollar)</option>
                                                    <option value="ZIG">ZiG (Zimbabwe Gold)</option>
                                                </select>
                                            </div>
                                        </div>

                                        {isVatThresholdExceeded ? (
                                            <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900 flex items-start gap-2">
                                                <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                                <div>
                                                    <span className="font-bold">Compulsory VAT Registration Triggered:</span> Because turnover meets or exceeds the US$25,000 threshold under Section 23 of the Value Added Tax Act [Chapter 23:12], registration for VAT is mandatory.
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="space-y-2">
                                                <p className="text-xs text-slate-500">
                                                    Turnover is below the compulsory statutory threshold of US$25,000.
                                                </p>
                                                <label className="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-800">
                                                    <input
                                                        type="checkbox"
                                                        checked={wantsVoluntaryVat}
                                                        onChange={(e) => setWantsVoluntaryVat(e.target.checked)}
                                                        className="rounded text-[#19766F] focus:ring-[#19766F]"
                                                    />
                                                    <span>Opt for Voluntary VAT Registration (permitted under VAT regulations for input credit claiming)</span>
                                                </label>
                                            </div>
                                        )}
                                    </div>

                                    {/* Regulated Professional Activities */}
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={licensedProfessionInvolved}
                                                onChange={(e) => setLicensedProfessionInvolved(e.target.checked)}
                                                className="rounded text-[#19766F] focus:ring-[#19766F] mt-0.5"
                                            />
                                            <div>
                                                <span className="text-xs font-bold text-slate-900 block">
                                                    Practicing Regulated Professional Activities (e.g., Engineering Council, Legal, Healthcare)
                                                </span>
                                                <span className="text-[11px] text-slate-500 block mt-0.5">
                                                    Triggers statutory professional council registration under the Engineering Council Act [Chapter 27:22] or relevant professional enactments.
                                                </span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            )}

                            {/* STEP 3: WORKFORCE & PAYROLL */}
                            {currentStep === 3 && (
                                <div className="space-y-6">
                                    <div className="border-b border-slate-100 pb-3">
                                        <h2 className="text-base font-bold text-slate-900 font-heading">
                                            Step 3: Workforce, Payroll & Social Security
                                        </h2>
                                        <p className="text-xs text-slate-500">
                                            Evaluates mandatory NSSA employer registration (NSSA Act [Chapter 17:04]), 1% ZIMDEF training levy, and National Employment Council (NEC) coverage.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Employee Headcount <span className="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                value={employeeCount}
                                                onChange={(e) => setEmployeeCount(e.target.value)}
                                                min="0"
                                                className="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            />
                                            <p className="text-[11px] text-slate-500 mt-1">
                                                Engaging 1 or more employees triggers mandatory NSSA and ZIMDEF registration within 30 days.
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                National Employment Council (NEC) Industry Coverage
                                            </label>
                                            <select
                                                value={necIndustry}
                                                onChange={(e) => setNecIndustry(e.target.value)}
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            >
                                                <option value="NEC_ENG">NEC for Engineering, Iron & Steel (SI 152/2017)</option>
                                                <option value="NEC_COMM">NEC for Commercial Sectors of Zimbabwe (SI 135/2011)</option>
                                                <option value="OTHER">Other Sectoral NEC</option>
                                                <option value="NONE">None / Owner-Managed Only</option>
                                            </select>
                                        </div>
                                    </div>

                                    {/* Payroll Remuneration & ZIMDEF */}
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={paysWages}
                                                onChange={(e) => setPaysWages(e.target.checked)}
                                                className="rounded text-[#19766F] focus:ring-[#19766F] mt-0.5"
                                            />
                                            <div>
                                                <span className="text-xs font-bold text-slate-900 block">
                                                    Business pays regular employee wages or executive salaries
                                                </span>
                                                <span className="text-[11px] text-slate-500 block mt-0.5">
                                                    Triggers mandatory 1% monthly gross wage bill training levy under Section 53 of the Manpower Planning and Development Act [Chapter 28:02].
                                                </span>
                                            </div>
                                        </label>

                                        {paysWages && (
                                            <div className="pt-2">
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                    Estimated Gross Monthly Wage Bill (USD)
                                                </label>
                                                <div className="relative max-w-xs">
                                                    <span className="absolute left-3 top-2 text-xs font-bold text-slate-400">$</span>
                                                    <input
                                                        type="number"
                                                        value={grossMonthlyWageBill}
                                                        onChange={(e) => setGrossMonthlyWageBill(e.target.value)}
                                                        min="0"
                                                        className="w-full text-xs pl-7 rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3 font-mono"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Existing NSSA and NEC identifiers */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Existing NSSA Employer Registration Number
                                            </label>
                                            <input
                                                type="text"
                                                value={nssaEmployerNumber}
                                                onChange={(e) => {
                                                    setNssaEmployerNumber(e.target.value);
                                                    setHasNssaRegistration(Boolean(e.target.value.trim()));
                                                }}
                                                placeholder="e.g. NSSA-ER-89412"
                                                className="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Existing NEC Registration Number
                                            </label>
                                            <input
                                                type="text"
                                                value={necRegistrationNumber}
                                                onChange={(e) => {
                                                    setNecRegistrationNumber(e.target.value);
                                                    setHasNecRegistration(Boolean(e.target.value.trim()));
                                                }}
                                                placeholder="e.g. NEC-ENG-2024-091"
                                                className="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            />
                                        </div>
                                    </div>

                                    {/* Statutory Exemption declaration */}
                                    <div className="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                                        <label className="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={isExemptDiplomaticOrReligious}
                                                onChange={(e) => setIsExemptDiplomaticOrReligious(e.target.checked)}
                                                className="rounded text-[#19766F] focus:ring-[#19766F]"
                                            />
                                            <span>Registered diplomatic mission, religious or charitable institution (statutory exemption from ZIMDEF levy)</span>
                                        </label>
                                    </div>
                                </div>
                            )}

                            {/* STEP 4: PREMISES & JURISDICTIONS */}
                            {currentStep === 4 && (
                                <div className="space-y-6">
                                    <div className="border-b border-slate-100 pb-3">
                                        <h2 className="text-base font-bold text-slate-900 font-heading">
                                            Step 4: Business Premises & Municipal Jurisdictions
                                        </h2>
                                        <p className="text-xs text-slate-500">
                                            Local authority licensing, health inspections, and municipal permits under the Urban Councils Act [Chapter 29:15].
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Primary Municipal Jurisdiction <span className="text-rose-500">*</span>
                                            </label>
                                            <select
                                                value={jurisdictionCode}
                                                onChange={(e) => setJurisdictionCode(e.target.value)}
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            >
                                                {jurisdictions.map((j) => (
                                                    <option key={j.code} value={j.code}>
                                                        {j.name} ({j.province ?? j.council_type})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                                Premises Operational Type
                                            </label>
                                            <select
                                                value={premisesType}
                                                onChange={(e) => setPremisesType(e.target.value)}
                                                className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                            >
                                                <option value="COMMERCIAL_OFFICE">Commercial Office Suite</option>
                                                <option value="RETAIL_SHOP">Retail Commercial Shopfront</option>
                                                <option value="INDUSTRIAL_WORKSHOP">Industrial Workshop / Factory</option>
                                                <option value="WAREHOUSE">Warehouse / Logistics Yard</option>
                                                <option value="HOME_OFFICE">Home Office / Residential</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                            Physical Operating Address
                                        </label>
                                        <input
                                            type="text"
                                            value={physicalAddress}
                                            onChange={(e) => setPhysicalAddress(e.target.value)}
                                            placeholder="e.g. 14 Kelvin Road North, Graniteside, Harare"
                                            className="w-full text-xs rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                        />
                                    </div>

                                    {/* Existing Council Operating Licence */}
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={hasCouncilLicence}
                                                onChange={(e) => setHasCouncilLicence(e.target.checked)}
                                                className="rounded text-[#19766F] focus:ring-[#19766F] mt-0.5"
                                            />
                                            <div>
                                                <span className="text-xs font-bold text-slate-900 block">
                                                    We already hold a valid Municipal Operating Licence or Health Permit
                                                </span>
                                                <span className="text-[11px] text-slate-500 block mt-0.5">
                                                    Annual licence issued by City of Harare or relevant local council.
                                                </span>
                                            </div>
                                        </label>

                                        {hasCouncilLicence && (
                                            <div className="pt-1">
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                    Council Licence / Permit Reference Number
                                                </label>
                                                <input
                                                    type="text"
                                                    value={councilLicenceNumber}
                                                    onChange={(e) => setCouncilLicenceNumber(e.target.value)}
                                                    placeholder="e.g. COH-LIC-2025-9921"
                                                    className="w-full max-w-sm text-xs font-mono rounded-lg border-slate-300 focus:border-[#19766F] focus:ring-[#19766F] py-2 px-3"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {/* Multi-Branch Locations Manager */}
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">
                                                    Additional Branch Locations ({branches.length})
                                                </h3>
                                                <p className="text-[11px] text-slate-500">
                                                    Add branches operating in other municipal jurisdictions.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addBranch}
                                                icon={<Plus className="w-3.5 h-3.5" />}
                                            >
                                                Add Branch
                                            </Button>
                                        </div>

                                        {branches.length === 0 ? (
                                            <div className="p-4 rounded-xl border border-dashed border-slate-300 text-center text-xs text-slate-500">
                                                No additional branches added. Operating from primary jurisdiction.
                                            </div>
                                        ) : (
                                            <div className="space-y-3">
                                                {branches.map((branch, idx) => (
                                                    <div key={idx} className="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-bold text-xs text-slate-800">
                                                                Branch #{idx + 1}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => removeBranch(idx)}
                                                                className="text-rose-600 hover:text-rose-800 text-xs flex items-center gap-1 cursor-pointer"
                                                            >
                                                                <Trash2 className="w-3.5 h-3.5" /> Remove
                                                            </button>
                                                        </div>

                                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                            <div>
                                                                <label className="block text-[11px] font-semibold text-slate-600 mb-1">
                                                                    Branch Name
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    value={branch.name}
                                                                    onChange={(e) => updateBranch(idx, 'name', e.target.value)}
                                                                    placeholder="e.g. Bulawayo Depot"
                                                                    className="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2.5"
                                                                />
                                                            </div>

                                                            <div>
                                                                <label className="block text-[11px] font-semibold text-slate-600 mb-1">
                                                                    Jurisdiction
                                                                </label>
                                                                <select
                                                                    value={branch.jurisdiction_code}
                                                                    onChange={(e) => updateBranch(idx, 'jurisdiction_code', e.target.value)}
                                                                    className="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2.5"
                                                                >
                                                                    {jurisdictions.map((j) => (
                                                                        <option key={j.code} value={j.code}>
                                                                            {j.name}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>

                                                            <div>
                                                                <label className="block text-[11px] font-semibold text-slate-600 mb-1">
                                                                    Physical Address
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    value={branch.physical_address}
                                                                    onChange={(e) => updateBranch(idx, 'physical_address', e.target.value)}
                                                                    placeholder="e.g. 88 Fife Street, Bulawayo"
                                                                    className="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2.5"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* STEP 5: PROCUREMENT & REVIEW */}
                            {currentStep === 5 && (
                                <div className="space-y-6">
                                    <div className="border-b border-slate-100 pb-3">
                                        <h2 className="text-base font-bold text-slate-900 font-heading">
                                            Step 5: Public Procurement, Tenders & Final Review
                                        </h2>
                                        <p className="text-xs text-slate-500">
                                            PRAZ supplier registration under the Public Procurement and Disposal of Public Assets Act [Chapter 22:23] and review of all entered details.
                                        </p>
                                    </div>

                                    {/* PRAZ Tenders Section */}
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-4">
                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={bidsOnGovernmentTenders}
                                                onChange={(e) => setBidsOnGovernmentTenders(e.target.checked)}
                                                className="mt-0.5 rounded text-[#19766F] focus:ring-[#19766F]"
                                            />
                                            <div>
                                                <div className="text-xs font-bold text-slate-900">
                                                    We intend to bid on Zimbabwean Government, Municipal, or Parastatal Tenders
                                                </div>
                                                <div className="text-[11px] text-slate-500 mt-0.5">
                                                    Triggers mandatory <strong>PRAZ Supplier Registration</strong> on the e-GP portal under the PPDPA Act [Chapter 22:23].
                                                </div>
                                            </div>
                                        </label>

                                        {bidsOnGovernmentTenders && (
                                            <div className="space-y-3 pt-2 border-t border-slate-200/80">
                                                <div>
                                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                        Existing PRAZ Registration Number (if already registered)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={prazSupplierNumber}
                                                        onChange={(e) => setPrazSupplierNumber(e.target.value)}
                                                        placeholder="e.g. PRAZ-2026-8912"
                                                        className="w-full max-w-sm text-xs font-mono rounded-lg border-slate-300 py-2 px-3"
                                                    />
                                                </div>

                                                <div className="p-3 bg-teal-50 border border-teal-200 rounded-lg text-xs text-teal-900">
                                                    <strong>PRAZ Hard Prerequisites:</strong> Participating in public tenders requires foundational Certificate of Incorporation (CIPZ), ZIMRA ITF263 Tax Clearance, and NSSA Compliance Clearance.
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Voluntary Association: CZI */}
                                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl">
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={wantsCziMembership}
                                                onChange={(e) => setWantsCziMembership(e.target.checked)}
                                                className="rounded text-[#19766F] focus:ring-[#19766F] mt-0.5"
                                            />
                                            <div>
                                                <span className="text-xs font-bold text-slate-900 block">
                                                    Confederation of Zimbabwe Industries (CZI) Membership
                                                </span>
                                                <span className="text-[11px] text-slate-500 block mt-0.5">
                                                    Voluntary industrial advocacy & networking body (non-statutory).
                                                </span>
                                            </div>
                                        </label>
                                    </div>

                                    {/* Comprehensive Summary Box */}
                                    <div className="p-5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-4">
                                        <div className="flex items-center justify-between border-b border-slate-200 pb-2">
                                            <h3 className="font-bold text-xs uppercase tracking-wider text-slate-900">
                                                Discovery Profile Summary
                                            </h3>
                                            <span className="text-[11px] text-slate-500 font-medium">
                                                Ready for Deterministic Evaluation
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                                            <div>
                                                <span className="text-slate-400 block text-[10px] uppercase font-bold">Entity Structure</span>
                                                <span className="font-bold text-slate-900">{entityType.replace(/_/g, ' ')}</span>
                                                <span className="text-[11px] text-slate-600 block">{businessMode === 'NEW' ? 'New Business' : 'Existing Company'}</span>
                                            </div>

                                            <div>
                                                <span className="text-slate-400 block text-[10px] uppercase font-bold">Turnover & VAT</span>
                                                <span className="font-bold text-slate-900">${parseFloat(annualTurnoverUsd).toLocaleString()} USD</span>
                                                <span className="text-[11px] text-slate-600 block">
                                                    {isVatThresholdExceeded ? 'Compulsory VAT Triggered' : (wantsVoluntaryVat ? 'Voluntary VAT Opted' : 'Under Threshold')}
                                                </span>
                                            </div>

                                            <div>
                                                <span className="text-slate-400 block text-[10px] uppercase font-bold">Workforce & NEC</span>
                                                <span className="font-bold text-slate-900">{employeeCount} Employees</span>
                                                <span className="text-[11px] text-slate-600 block">{necIndustry.replace(/_/g, ' ')}</span>
                                            </div>

                                            <div>
                                                <span className="text-slate-400 block text-[10px] uppercase font-bold">Location & PRAZ</span>
                                                <span className="font-bold text-slate-900">{jurisdictionCode}</span>
                                                <span className="text-[11px] text-slate-600 block">
                                                    {bidsOnGovernmentTenders ? 'Public Tenders (PRAZ Active)' : 'No Public Tenders'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Confirmation Checkbox */}
                                    <div className="p-4 rounded-xl border border-teal-200 bg-teal-50/50 space-y-2">
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={confirmationAccurate}
                                                onChange={(e) => setConfirmationAccurate(e.target.checked)}
                                                className="rounded text-[#19766F] focus:ring-[#19766F] mt-0.5"
                                            />
                                            <span className="text-xs text-teal-950 font-medium">
                                                I confirm that the entered business discovery information is accurate and reflects our current operating structure. I authorize the deterministic evaluation of applicable Zimbabwean statutory obligations.
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            )}
                        </CardContent>

                        {/* Navigation & Action Footer */}
                        <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 bg-slate-50 border-t border-slate-100 rounded-b-xl">
                            <div className="flex items-center gap-2 w-full sm:w-auto">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        const prevStep = Math.max(1, currentStep - 1);
                                        handleSaveDraft(prevStep);
                                        setCurrentStep(prevStep);
                                    }}
                                    disabled={currentStep === 1 || isSubmitting}
                                    icon={<ArrowLeft className="h-3.5 w-3.5" />}
                                >
                                    Previous
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleSaveDraft()}
                                    disabled={isSavingDraft || isSubmitting}
                                    icon={<Save className="h-3.5 w-3.5" />}
                                >
                                    {isSavingDraft ? 'Saving Draft...' : 'Save Draft'}
                                </Button>
                            </div>

                            <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                                {currentStep < 5 ? (
                                    <Button
                                        type="button"
                                        variant="primary"
                                        size="sm"
                                        onClick={() => {
                                            const nextStep = currentStep + 1;
                                            handleSaveDraft(nextStep);
                                            setCurrentStep(nextStep);
                                        }}
                                        icon={<ArrowRight className="h-3.5 w-3.5" />}
                                    >
                                        Next Step
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="primary"
                                        size="md"
                                        onClick={handleSubmit}
                                        disabled={isSubmitting || !confirmationAccurate}
                                        icon={isSubmitting ? <RefreshCw className="h-4 w-4 animate-spin" /> : <FileCheck className="h-4 w-4" />}
                                    >
                                        {isSubmitting ? 'Evaluating Statutory Rules...' : 'Submit & Generate Statutory Roadmap'}
                                    </Button>
                                )}
                            </div>
                        </CardFooter>
                    </Card>
                ) : (
                    /* ------------------------------------------------------------- */
                    /* DETERMINISTIC STATUTORY ROADMAP (Phase D, E, F)              */
                    /* ------------------------------------------------------------- */
                    <div className="space-y-6">
                        {/* Assessment KPI Summary Header */}
                        {activeAssessment && (
                            <div className="bg-white rounded-xl border border-slate-200/90 p-5 shadow-xs space-y-4">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                    <div>
                                        <div className="flex items-center gap-2 mb-1">
                                            <Badge variant="teal" size="sm">
                                                {activeAssessment.assessment_type === 'REASSESSMENT' ? 'Statutory Reassessment' : 'Initial Registration Roadmap'}
                                            </Badge>
                                            <span className="text-[11px] text-slate-500 font-mono">
                                                ID: {activeAssessment.assessment_id.slice(0, 13)}...
                                            </span>
                                            {activeAssessment.is_idempotent_replay && (
                                                <Badge variant="neutral" size="sm">Idempotent Snapshot</Badge>
                                            )}
                                        </div>
                                        <h2 className="font-heading font-extrabold text-lg text-slate-900">
                                            Deterministic Regulatory Applicability Roadmap
                                        </h2>
                                        <p className="text-xs text-slate-500">
                                            Evaluated on {new Date(activeAssessment.assessed_at).toLocaleDateString([], { day: '2-digit', month: 'long', year: 'numeric' })} under active Zimbabwean statutory acts and official gazettes.
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setViewMode('QUESTIONNAIRE')}
                                            icon={<RotateCcw className="h-3.5 w-3.5" />}
                                        >
                                            Update Circumstances
                                        </Button>
                                    </div>
                                </div>

                                {/* Metric Badges / KPIs */}
                                <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                                    <div className="p-3 rounded-lg bg-rose-50/70 border border-rose-200">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-rose-700 block">Mandatory</span>
                                        <span className="text-xl font-extrabold text-rose-900">{activeAssessment.summary.mandatory_count}</span>
                                    </div>

                                    <div className="p-3 rounded-lg bg-amber-50/70 border border-amber-200">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-amber-700 block">Conditional</span>
                                        <span className="text-xl font-extrabold text-amber-900">{activeAssessment.summary.conditional_count}</span>
                                    </div>

                                    <div className="p-3 rounded-lg bg-emerald-50/70 border border-emerald-200">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">Exempt</span>
                                        <span className="text-xl font-extrabold text-emerald-900">{activeAssessment.summary.exempt_count}</span>
                                    </div>

                                    <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-600 block">Not Applicable</span>
                                        <span className="text-xl font-extrabold text-slate-800">{activeAssessment.summary.not_applicable_count}</span>
                                    </div>

                                    <div className="p-3 rounded-lg bg-purple-50/70 border border-purple-200">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-purple-700 block">Unverified Holds</span>
                                        <span className="text-xl font-extrabold text-purple-900">{activeAssessment.summary.unverified_hold_count}</span>
                                    </div>

                                    <div className="p-3 rounded-lg bg-sky-50/70 border border-sky-200">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-sky-700 block">Total Evaluated</span>
                                        <span className="text-xl font-extrabold text-sky-900">{activeAssessment.summary.total_evaluated}</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Roadmap Tabs */}
                        <div className="flex items-center gap-2 border-b border-slate-200 text-xs font-semibold">
                            <button
                                onClick={() => setRoadmapTab('ROADMAP')}
                                className={`pb-2.5 px-3 border-b-2 cursor-pointer transition-all ${
                                    roadmapTab === 'ROADMAP'
                                        ? 'border-[#19766F] text-[#19766F]'
                                        : 'border-transparent text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                Active Roadmap ({activeAssessment?.roadmap.length ?? 0})
                            </button>

                            <button
                                onClick={() => setRoadmapTab('EXEMPTIONS')}
                                className={`pb-2.5 px-3 border-b-2 cursor-pointer transition-all ${
                                    roadmapTab === 'EXEMPTIONS'
                                        ? 'border-[#19766F] text-[#19766F]'
                                        : 'border-transparent text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                Statutory Exemptions ({activeAssessment?.exemptions.length ?? 0})
                            </button>

                            <button
                                onClick={() => setRoadmapTab('NOT_APPLICABLE')}
                                className={`pb-2.5 px-3 border-b-2 cursor-pointer transition-all ${
                                    roadmapTab === 'NOT_APPLICABLE'
                                        ? 'border-[#19766F] text-[#19766F]'
                                        : 'border-transparent text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                Not Applicable ({activeAssessment?.not_applicable.length ?? 0})
                            </button>

                            {assessment_history.length > 0 && (
                                <button
                                    onClick={() => setRoadmapTab('HISTORY')}
                                    className={`pb-2.5 px-3 border-b-2 cursor-pointer transition-all ${
                                        roadmapTab === 'HISTORY'
                                            ? 'border-[#19766F] text-[#19766F]'
                                            : 'border-transparent text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    Assessment History ({assessment_history.length})
                                </button>
                            )}
                        </div>

                        {/* TAB 1: ACTIVE ROADMAP */}
                        {roadmapTab === 'ROADMAP' && (
                            <div className="space-y-4">
                                <div className="p-3 rounded-lg bg-teal-50/70 border border-teal-200 text-xs text-teal-900 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Scale className="w-4 h-4 text-teal-700 shrink-0" />
                                        <span>
                                            Topologically sequenced in strict prerequisite dependency order (Kahn's DAG algorithm).
                                        </span>
                                    </div>
                                    <span className="text-[11px] font-semibold text-teal-800">
                                        Rule Versions Gazetted & Verified
                                    </span>
                                </div>

                                <div className="space-y-3">
                                    {activeAssessment?.roadmap.map((item) => {
                                        const isDeclared = item.declared_registration && item.declared_registration.declared_number;
                                        return (
                                            <Card key={item.obligation_code} className="overflow-hidden">
                                                <div className="p-5 space-y-4">
                                                    {/* Card Header Row */}
                                                    <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                                        <div className="flex items-start gap-3">
                                                            <div className="h-8 w-8 rounded-lg bg-slate-100 text-slate-800 font-extrabold flex items-center justify-center text-xs shrink-0 border border-slate-200">
                                                                #{item.sequence_number}
                                                            </div>

                                                            <div className="space-y-1">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span className="font-extrabold text-sm text-slate-900 font-heading">
                                                                        {item.title}
                                                                    </span>
                                                                    <Badge variant="neutral" size="sm">
                                                                        {item.authority?.code ?? 'AUTHORITY'}
                                                                    </Badge>
                                                                    {getDecisionBadge(item.applicability_decision)}
                                                                </div>

                                                                <p className="text-xs text-slate-500 font-mono">
                                                                    {item.source_citation ?? 'Statutory Law of Zimbabwe'}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        {/* Status & Portal */}
                                                        <div className="flex items-center gap-2 self-start">
                                                            {item.authority?.portal_url && (
                                                                <a
                                                                    href={item.authority.portal_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex items-center gap-1 text-[11px] font-semibold text-[#19766F] hover:underline"
                                                                >
                                                                    <span>{item.authority.code} Portal</span>
                                                                    <ExternalLink className="w-3 h-3" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Legal Classification & Rule Version Grid */}
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs bg-slate-50 p-3 rounded-lg border border-slate-100">
                                                        <div>
                                                            <span className="text-[10px] text-slate-400 font-bold uppercase block">Classification</span>
                                                            <span className="font-semibold text-slate-800">
                                                                {item.legal_classification.replace(/_/g, ' ')}
                                                            </span>
                                                        </div>

                                                        <div>
                                                            <span className="text-[10px] text-slate-400 font-bold uppercase block">Rule Version</span>
                                                            <span className="font-mono text-slate-800">
                                                                {item.applicable_rule_version?.version_code ?? 'Draft/Pending'}
                                                            </span>
                                                            <span className="text-[10px] text-emerald-700 block">
                                                                {item.verification_status}
                                                            </span>
                                                        </div>

                                                        <div>
                                                            <span className="text-[10px] text-slate-400 font-bold uppercase block">Prerequisites</span>
                                                            {item.required_prerequisites.length > 0 ? (
                                                                <div className="flex flex-wrap gap-1 mt-0.5">
                                                                    {item.required_prerequisites.map((prereq) => (
                                                                        <span key={prereq} className="bg-slate-200 text-slate-700 text-[10px] px-1.5 py-0.5 rounded font-mono font-medium">
                                                                            {prereq}
                                                                        </span>
                                                                    ))}
                                                                </div>
                                                            ) : (
                                                                <span className="text-slate-500 text-[11px]">None (Foundational)</span>
                                                            )}
                                                        </div>

                                                        <div>
                                                            <span className="text-[10px] text-slate-400 font-bold uppercase block">Processing Estimate</span>
                                                            <span className="font-medium text-slate-800">
                                                                ~{item.estimated_processing_days ?? 5} Working Days
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Evidence / Declared Status Disclaimer */}
                                                    {isDeclared ? (
                                                        <div className="p-3 rounded-lg bg-teal-50 border border-teal-200 text-xs text-teal-950 flex items-start gap-2.5">
                                                            <Info className="w-4 h-4 text-teal-700 shrink-0 mt-0.5" />
                                                            <div>
                                                                <span className="font-bold">Declared Active: </span>
                                                                <span className="font-mono">Reg No: {item.declared_registration?.declared_number}</span>
                                                                <p className="text-[11px] text-teal-800 mt-0.5">
                                                                    Status: <strong>USER DECLARED (UNVERIFIED)</strong>. In accordance with ZimComply regulatory governance, declaring a certificate does not confer verified compliance until supporting documentation is uploaded and verified.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 flex items-start gap-2.5">
                                                            <FileText className="w-4 h-4 text-slate-500 shrink-0 mt-0.5" />
                                                            <div>
                                                                <span className="font-bold">Required Documents for Filing: </span>
                                                                <span>{item.evidence_requirements?.required_documents.join(', ') || 'Statutory Forms & IDs'}</span>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {/* Trigger Reasons */}
                                                    {item.trigger_reasons && item.trigger_reasons.length > 0 && (
                                                        <div className="text-xs space-y-1">
                                                            <span className="text-[10px] uppercase font-bold text-slate-400">Deterministic Evaluation Trigger:</span>
                                                            <ul className="list-disc list-inside text-slate-600 text-[11px] space-y-0.5">
                                                                {item.trigger_reasons.map((reason, i) => (
                                                                    <li key={i}>{reason}</li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="px-5 py-3 bg-slate-50/60 border-t border-slate-100 flex items-center justify-between text-xs">
                                                    <span className="text-[11px] text-slate-500">
                                                        Renewal: {item.evidence_requirements?.renewal_frequency ?? 'ONCE_OFF'}
                                                    </span>
                                                    <Button
                                                        href="/documents"
                                                        variant="outline"
                                                        size="sm"
                                                        icon={<FileCheck className="w-3.5 h-3.5" />}
                                                    >
                                                        Upload Supporting Certificate
                                                    </Button>
                                                </div>
                                            </Card>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* TAB 2: EXEMPTIONS */}
                        {roadmapTab === 'EXEMPTIONS' && (
                            <div className="space-y-3">
                                {activeAssessment?.exemptions.length === 0 ? (
                                    <div className="p-8 rounded-xl border border-slate-200 text-center text-xs text-slate-500 bg-white">
                                        No statutory exemptions triggered for this business profile.
                                    </div>
                                ) : (
                                    activeAssessment?.exemptions.map((ex: any, idx: number) => (
                                        <Card key={idx} className="p-4 space-y-2">
                                            <div className="flex items-center justify-between">
                                                <div className="font-bold text-sm text-slate-900">
                                                    {ex.title ?? ex.obligation_code}
                                                </div>
                                                <Badge variant="success" size="sm">EXEMPT</Badge>
                                            </div>
                                            <p className="text-xs text-slate-600">
                                                {ex.trigger_reasons?.[0] ?? 'Qualifies for statutory exemption under relevant Act.'}
                                            </p>
                                        </Card>
                                    ))
                                )}
                            </div>
                        )}

                        {/* TAB 3: NOT APPLICABLE */}
                        {roadmapTab === 'NOT_APPLICABLE' && (
                            <div className="space-y-3">
                                {activeAssessment?.not_applicable.length === 0 ? (
                                    <div className="p-8 rounded-xl border border-slate-200 text-center text-xs text-slate-500 bg-white">
                                        All catalogued obligations were either triggered or exempted.
                                    </div>
                                ) : (
                                    activeAssessment?.not_applicable.map((na: any, idx: number) => (
                                        <Card key={idx} className="p-4 space-y-1">
                                            <div className="flex items-center justify-between">
                                                <div className="font-bold text-sm text-slate-900">
                                                    {na.title ?? na.obligation_code}
                                                </div>
                                                <Badge variant="neutral" size="sm">NOT APPLICABLE</Badge>
                                            </div>
                                            <p className="text-xs text-slate-500">
                                                Threshold or criteria condition not met by business profile.
                                            </p>
                                        </Card>
                                    ))
                                )}
                            </div>
                        )}

                        {/* TAB 4: HISTORY & AUDIT TRAIL */}
                        {roadmapTab === 'HISTORY' && (
                            <Card>
                                <CardHeader>
                                    <div>
                                        <CardTitle>Historical Assessment Audit Trail</CardTitle>
                                        <CardDescription>
                                            Immutable snapshots of prior statutory assessments preserved for regulatory audit compliance.
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                                            <tr>
                                                <th className="py-3 px-4">Generated Date</th>
                                                <th className="py-3 px-4">Type</th>
                                                <th className="py-3 px-4">Total Evaluated</th>
                                                <th className="py-3 px-4">Mandatory Obligations</th>
                                                <th className="py-3 px-4 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {assessment_history.map((hist) => (
                                                <tr key={hist.id} className="hover:bg-slate-50/60">
                                                    <td className="py-3 px-4 font-medium text-slate-900">
                                                        {hist.formatted_date}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <Badge variant={hist.assessment_type === 'REASSESSMENT' ? 'teal' : 'neutral'} size="sm">
                                                            {hist.assessment_type}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4 text-slate-600">
                                                        {hist.total_evaluated} Requirements
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <span className="font-bold text-rose-700">{hist.mandatory_count} Mandatory</span>
                                                    </td>
                                                    <td className="py-3 px-4 text-right">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => handleLoadHistorical(hist.id)}
                                                        >
                                                            View Snapshot
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
