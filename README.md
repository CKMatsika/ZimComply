# ZimComply AI

**Register. Comply. Renew. Tender. Grow.**

## Master development brief and Codex instruction

> This README is the authoritative product specification and development roadmap. Codex must read it before each implementation phase, preserve completed work, and maintain a separate `docs/IMPLEMENTATION_STATUS.md` with verified progress. Requirements below describe intended functionality, not features already implemented. Regulatory examples are hypotheses until checked against current official sources.

### 1. Mission and product boundaries

Build ZimComply AI, a modern, secure, affordable, mobile-friendly, multi-tenant Zimbabwe-focused SaaS platform helping SMEs, accountants, payroll bureaus and compliance consultants **register, maintain compliance, renew licences and qualify for procurement opportunities**. The main user problem begins with registration, not merely monthly calculations. The platform must support new companies, established companies, multi-branch enterprises, multi-activity businesses and accounting firms managing multiple clients.

ZimComply must work **independently of any ERP**. Support manual records, paper scans, Excel/CSV, and approved connectors to Sage, Pastel, Xero, QuickBooks, ERPNext and custom ERPs. A future Smart ERP integration is optional; do not share operational databases or require Smart ERP to be installed. Avoid becoming a full POS, inventory or general-ledger ERP. Include only a lightweight optional payroll capture and compliance-oriented payment tracking. The platform must not claim to be a regulator, guarantee certificates, guarantee tender eligibility or submit forms without authorisation.

**Development stack:** Laravel (latest stable release compatible with hosting), PHP compatible with that release, PostgreSQL, React + TypeScript, Inertia.js where appropriate, Tailwind CSS, Laravel Sanctum/session authentication as appropriate, Laravel Policies, queues, scheduler, notifications, secure object storage and a mobile-ready PWA. Use Laravel-native scheduling and orchestration; **do not introduce n8n**. Codex is the development agent, not a runtime dependency. Prefer a well-structured modular Laravel monolith initially, with clear service boundaries and versioned APIs for later integration. Deployable to Laravel Cloud or equivalent. Use a single source of truth for statutory rules.

### 2. Product principles

- Registration-first onboarding: tell a company **what applies, why, what evidence is needed, how to register, costs, current status and next steps**.
- Distinguish **mandatory law**, conditional requirement, contractual/CBA obligation, professional regulation, optional association membership and **tender-specific eligibility**. Never present voluntary membership as compulsory.
- Industry applicability must depend on **actual activities, geography, employee classes/grades, business size and regulator scope**, not just a selected industry label.
- One company can have multiple industries, branches, sites, assets and employer agreements. Some obligations apply at entity level; others at site, employee, asset or tender level.
- Legal facts, rates, deadlines, thresholds, fees, forms, portal procedures and dependencies must be **officially verified, cited, effective-dated, versioned and approved** before they drive production decisions. Unknowns must be flagged as *verification required*.
- AI may classify, extract, explain, recommend and draft. **Deterministic, tested rules** perform monetary calculations, eligibility checks and deadline calculations. No unreviewed AI-generated law or rates may become active.
- Human review and explicit approval for regulator submissions, payments, filings, sensitive changes and external document sharing.
- User-friendly for SMEs with limited digital literacy; accessible on desktop, tablet and mobile, with guided steps and plain English.
- Every compliance claim has an evidence status: verified by issuer, document-supported, user-declared, inferred or unknown. Avoid falsely displaying 'compliant' from a user upload alone.

### 3. Customer personas and subscription modes

1. New SME owner: starting with company incorporation documents, wants to become operationally compliant.
2. Existing SME owner: wants a registration gap audit, reminders and document wallet.
3. Accountant / tax consultant: manages many client companies with delegated access and client approvals.
4. HR/payroll officer: employee classifications, NEC, NSSA, medical aid, remittances.
5. Compliance officer: licences, fleet, insurance, audits and regulatory updates.
6. Tender officer: PRAZ registration, opportunity matching and bid-readiness.
7. Platform administrator / regulatory editor / independent approver.

Offer configurable entitlements: Registration Starter, Compliance, Fleet & Licensing, Tender Intelligence, Accounting Firm Multi-Client, and optional Payroll Capture. Subscriptions must not disable access to legally required historical records or prevent data export. Support trials and manual billing first; payments later through approved providers.

### 4. Major functional modules

#### M01 — Multi-tenant foundation and business identity

Organisation onboarding, company legal identity, trading names, TIN, incorporation data, addresses, contacts, directors (subject to lawful basis), beneficial-owner records where necessary, industry activities, operating sites, branches, currencies (USD, ZiG and extensible), fiscal years, employees, vehicles and assets. Multiple businesses per consultancy account, delegated client permissions, invitations, MFA, tenant isolation, subscription entitlements, consent, audit trails, import/export and configurable workflow roles.

#### M02 — Registration discovery and applicability engine

Interactive questionnaire by business activities, geographic jurisdiction, headcount, employee grades, turnover, premises, regulated professional services, fleet and tender goals. Generate a **personalised registration roadmap** with obligation name, issuer, legal basis, applicability rationale, prerequisites, fee source, documents, submission route, estimated effort, owner, deadline and confidence/evidence. Allow parallel tasks and conditional dependencies; never hardcode a universal registration sequence. Allow users to dispute/override a recommendation with justification and review.

#### M03 — Corporate and tax registration

Track company formation and maintenance, tax registration, TIN, VAT status and other applicable ZIMRA registrations. Manage ITF263 tax-clearance readiness, official certificate records, expiry, evidence, outstanding filing/payment issues and renewal preparedness. Support email-based authorised certificate ingestion when configured. Do not claim direct ZIMRA clearance status without an authorised confirmation. Prepare documents and guide TaRMS workflows; automate submission only when authorised interfaces are confirmed.

#### M04 — Employer registration and payroll compliance

NSSA employer registration, pension and workers' compensation where applicable; NEC identification and employer registration; ZIMDEF; PAYE, AIDS Levy, VAT and other applicable taxes; trade-union deductions where authorised; payroll imports; optional lightweight payroll capture; employee grades, allowances, deductions, currency, employer/employee contribution splits, payment schedules, return preparation and reconciliation. Rules must handle statutory caps, thresholds, tax periods and historical versions. Separate employer registration, ongoing obligations and remittance status. Never invent rates.

#### M05 — Industry-specific NEC and medical aid

Model collective bargaining agreements, scope, effective dates, grade/class mappings, wages, NEC dues and associated schemes. Engineering/Iron & Steel NEC and Engineering Medical Fund are **priority research cases**: determine which employers, employees and grades are covered and when medical aid is compulsory under verified current rules. Distinguish compulsory arrangements from voluntary benefits. Support provider membership numbers, enrolment, contribution schedules, employee/employer portions, reconciliations and restricted access. Do not collect clinical records.

#### M06 — Local authority licensing and permits

City of Harare as first example, but jurisdiction-configurable for other municipalities and rural district councils. Business/operating licences, shop permits, premises inspections, health and hygiene certificates, land-use approvals, lease/ownership evidence, fire certificates where applicable, annual renewals and branch-specific requirements. Track fee schedules and source dates; avoid assuming all councils share the same rules.

#### M07 — Professional, engineering and construction registrations

Assess Engineering Council of Zimbabwe requirements for persons/firms and activities, construction contractor associations and classifications, sector-specific permits, and tender-required accreditations. Include bodies such as CIFOZ and ZBCA as candidates for verification, not automatically mandatory registrations. Store membership and grading evidence, fees, renewal cycles and prerequisites. Add extensible industry rule packs for mining, agriculture, manufacturing, retail, education, transport, energy, hospitality and NGOs.

#### M08 — Fleet, insurance and radio licensing

Vehicle registry, owner, registration number, VIN/chassis, licence category, ZINARA licensing, insurance provider/policy, radio licence or valid exemption where applicable, roadworthiness/other permits when applicable, renewal calendar, evidence wallet and vehicle dispatch warning controls. Support company vehicles and assigned assets, configurable renewal intervals and provider-specific integrations only when permitted. Do not assume insurance and radio licence legal relationships without effective-dated verification.

#### M09 — Compliance wallet and document intelligence

Secure storage for incorporation records, ITF263, NSSA/NEC/ZIMDEF, medical aid, council licences, ECZ, construction memberships, PRAZ, insurance, vehicle/radio licences, bank letters, financial statements, project completion certificates and personnel credentials. Upload PDF, images, spreadsheets; virus scanning, OCR/extraction where available, classification, metadata, expiry extraction, version history, verification queue, duplicate detection, document access control and consent-aware sharing. Store source file and extraction provenance; uncertain extraction must be confirmed.

#### M10 — Compliance calendar and dependency workflow

Registration tasks, submissions, approvals, payments, renewals, reminders (e.g., 90/60/30/14/7 days, configurable), escalations, owners, prerequisites, recurrence, deadlines and evidence. Represent state transitions (not started, information needed, draft, awaiting approval, submitted, acknowledged, rejected, issued, expired, disputed). Preserve official acknowledgements and payment references. Support parallel steps and reopen cases on changed circumstances.

#### M11 — PRAZ supplier registration and tender intelligence

PRAZ/e-GP supplier onboarding and categories, registration/renewal assistance, company capability profile, searchable experience records, eligibility requirements, tender notices from authorised/publicly accessible sources, deduplication, category matching, closing dates, tender document upload and extraction, compliance matrix, missing documents, bid/no-bid support, clarification log, approval, submission evidence and outcomes. Never guarantee award or claim live availability without current data. Respect portal terms and robots/access restrictions; no unauthorised scraping, credential sharing or CAPTCHA bypass. Support other lawful procurement portals and private tenders later.

#### M12 — AI compliance assistant and regulatory intelligence

Contextual assistant with document-grounded answers, citations, source dates, confidence/unknown indicators and company-scoped permissions. Answer 'what registrations do I need?', 'why does this NEC apply?', 'what blocks my ITF263?', 'what expires next?', 'which tender documents are missing?' and 'what will compliance cost over 12 months?'. Draft checklists and correspondence; never fabricate legal advice or confirm official status without evidence. Monitor verified official notices and statutory instruments where legally accessible, propose rule changes into a review queue, require expert approval and test impact before activation.

#### M13 — Compliance finance and reporting

Forecast registration fees, monthly levies, renewal fees, insurance and medical aid; distinguish one-time, recurring and conditional amounts; display currency and FX assumptions. Track obligations, invoices, remittance/payment evidence and reconciliations without creating a second general ledger. Export PDF/Excel/CSV schedules, compliance audit pack, licence register, fleet register, tender readiness report, client portfolio dashboard and exception reports. Distinguish forecast from confirmed liability.

#### M14 — Integration hub

Import mapping for Excel/CSV; templates for payroll, employee grades, vehicles, licences and registrations. Read-only or approved write adapters for external ERP/payroll systems through versioned APIs, webhooks and queued jobs. Explicit future Smart ERP connector, but no dependency on it. Idempotent imports, reconciliation, duplicate detection, failure retries, audit logs and permission-scoped data sharing. No regulator integration may be represented as operational until its authorised access has been tested.

### 5. Core data model (design ERD before migrations)

Tenant, Organisation, OrganisationActivity, Branch/Site, User, Role, Permission, Membership, Subscription, Employee, EmployeeClassification, CollectiveAgreement, RegulatoryAuthority, Jurisdiction, RegulatorySource, RulePack, RuleVersion, ObligationDefinition, ApplicabilityAssessment, ObligationInstance, RegistrationCase, WorkflowTemplate, WorkflowStep, Prerequisite, Task, Deadline, Document, DocumentVersion, DocumentVerification, Certificate, Licence, Permit, Vehicle, InsurancePolicy, MedicalAidScheme, MembershipRecord, PayrollImport, PayrollPeriod, ContributionCalculation, Filing, FilingAcknowledgement, PaymentEvidence, ComplianceEvent, Notification, ProcurementCategory, SupplierRegistration, TenderNotice, TenderRequirement, TenderAssessment, TenderSubmission, ProjectExperience, IntegrationConnection, ImportJob, Approval, AuditEvent and RegulatoryChangeProposal.

Each tenant-owned record requires strict tenant scoping; model organisation, branch, person, asset and tender applicability separately. Use UUIDs where appropriate, foreign keys, unique constraints, soft-delete only where legally/operationally appropriate, effective date ranges, currency precision, optimistic/concurrent locking, immutable audit/event histories and encrypted sensitive fields. Avoid copying sensitive source documents unnecessarily.

### 6. AI and automation architecture (Laravel only)

- Use Laravel scheduler for periodic checks and queues for imports, document processing, notifications, source monitoring and tender matching.
- Implement an AI provider abstraction with configurable models, token budgets, rate limits, redaction and prompt/version logs. AI outputs must conform to validated schemas.
- Build tool-based AI actions that require authorisation and do not bypass application policies.
- Use a deterministic rule engine for calculations and compliance applicability, backed by expert-approved regulatory data and tests.
- Provide a human-in-the-loop review inbox for OCR, legal changes, calculations, submissions and outbound communications.
- Notifications through Laravel Mail/Notifications and approved SMS/WhatsApp providers; record consent, delivery results and opt-outs.
- Keep a full trace of which source/rule version produced each compliance determination.
- Use API integrations only where authorised. Browser automation of regulator portals is not assumed or required for MVP.

### 7. User experience

Responsive web application and installable PWA. Simple navigation: **Register | Comply | Renew | Tender | Documents | Reports | Settings**. Guided onboarding, task wizard, 'what to do next' dashboard, company/branch switcher, role-specific workspaces, traffic-light statuses backed by evidence, searchable lists, bulk imports, contextual explanations and mobile document capture. Separate business-owner, accountant, HR, fleet, tender, compliance reviewer and platform-admin dashboards. Accessibility, low-bandwidth behaviour, autosave drafts and clear error messages. No fake buttons or unfinished flows presented as complete.

### 8. Security, governance and legal safety

MFA for privileged users, Laravel Policies, tenant/branch/client-level authorisation, secure sessions, encryption at rest/in transit, object-storage access controls, malware scanning, backups and restoration drills, audit trails, retention/deletion policies, incident response, rate limits, secrets management, least-privilege external connectors, data export, consent and privacy notices. Assess Zimbabwe's Cyber and Data Protection Act and applicable privacy requirements with legal counsel. Protect payroll, employee, director and medical-aid membership data. Do not store regulator passwords without a justified, approved secure integration design. Never log credentials or full sensitive documents. Include segregation of duties for preparation, approval and submission.

### 9. Development roadmap and phase gates

**Phase 0 — Discovery and architecture:** inspect repo/environment, validate regulatory research backlog, produce architecture, ERD, role matrix, API contracts, module dependency map, design system, migration and security plan. No speculative regulator rates.

**Phase 1 — SaaS foundation:** authentication, MFA-ready design, tenant/company/branch identity, roles, subscriptions, module registry, UI shell, audit logs, onboarding, CI/tests.

**Phase 2 — Registration-first MVP:** industry/activity assessment, regulatory authority catalog, obligation definitions, registration roadmap, workflows, checklists, evidence upload, manual status tracking and basic reminders. Include sample engineering, retail and construction scenarios marked as unverified until rules approved.

**Phase 3 — Compliance wallet and renewals:** secure document management, extraction review, certificates, ITF263 readiness, council licences, fleet/insurance/radio records, expiry alerts and audit reporting.

**Phase 4 — Employer and sector compliance:** NSSA, NEC, ZIMDEF, PAYE and applicable medical aid; employee grading, CSV payroll imports, effective-dated deterministic calculation engine, expert-approved rule packs and reconciliation.

**Phase 5 — PRAZ and tenders:** supplier registration workflows, category mapping, tender import, requirement extraction, eligibility matrix, missing-document analysis and submission tracking.

**Phase 6 — AI assistant and regulatory change management:** grounded assistant, OCR/document extraction, approved source monitoring, change proposal review, forecasting and exception analysis.

**Phase 7 — External integrations and multi-client operations:** external ERP/payroll connectors, accounting-firm portfolio, delegated access, subscription billing, approved provider integrations and optional Smart ERP adapter.

**Phase 8 — Production hardening:** security review, penetration testing, load/performance tests, disaster recovery, observability, regulatory review, deployment runbook and pilot rollout.

Each phase must produce migrations, code, unit/feature/integration/permission/tenant-isolation tests, user guidance and a completion report. Do not begin the next phase until reviewed and approved.

### 10. Acceptance scenarios

1. A new engineering company receives a cited, conditional registration roadmap including ZIMRA, NSSA, applicable NEC, employee-grade-specific medical aid, relevant ECZ obligations and municipal permits.
2. A construction company sees professional/association requirements separated into mandatory, conditional and tender-specific items.
3. A retail SME with only Excel imports payroll, receives a validated statutory draft and a human review step.
4. An existing company uploads ITF263 and council licences; the system extracts dates, requests verification and schedules renewal alerts.
5. A multi-branch business sees branch-level council permits and entity-level tax obligations separately.
6. A fleet owner tracks insurance, vehicle licences and radio licence/exemption status for each vehicle.
7. A tender officer uploads a PRAZ tender and receives a source-linked eligibility matrix and missing-document checklist.
8. An accountant manages 50 client companies without any cross-client data leakage.
9. A user cannot submit or pay without explicit permission and approval.
10. Changing an effective-dated statutory rule does not alter previously finalised calculations.
11. Repeating an import or notification job does not create duplicate obligations or payments.
12. A ZimComply-only customer can operate without Smart ERP; a future connector can synchronise approved data safely.

### 11. Required repository documentation

Maintain `README.md` (this specification), `docs/ARCHITECTURE.md`, `docs/ERD.md`, `docs/REGULATORY_SOURCE_REGISTER.md`, `docs/RULE_GOVERNANCE.md`, `docs/MODULES.md`, `docs/SECURITY.md`, `docs/API_INTEGRATIONS.md`, `docs/DEVELOPMENT_ROADMAP.md`, `docs/IMPLEMENTATION_STATUS.md`, `docs/DEPLOYMENT.md`, `docs/TESTING.md`, `docs/USER_GUIDE.md`, and `docs/DECISIONS/` for architecture decision records. Clearly distinguish proposed features, implemented features, tested features and deployed features.

### 12. Codex operating instructions — read before every phase

1. Inspect the current repository, git status, branch, installed versions and existing implementation. Never assume a clean slate.
2. Read this README and `docs/IMPLEMENTATION_STATUS.md`. Summarise completed features, gaps, risks and the next approved phase.
3. Before coding, present architecture, database changes, interfaces, tests and rollback plan for the phase.
4. Work in small commits and use safe additive migrations. Never use `migrate:fresh`, `db:wipe` or destructive production resets.
5. Preserve existing data and APIs; no silent rewrites of working modules.
6. Validate regulatory sources and flag uncertainties. Do not seed fabricated rates, fees, deadlines or legal conclusions as active rules.
7. Use proper accounting/monetary decimal types, database transactions, idempotency keys and concurrency controls where needed.
8. Enforce permissions server-side and test cross-tenant isolation.
9. Never claim an external API, filing or payment succeeded without authoritative acknowledgement.
10. Run tests, report failures transparently, document what changed and update the implementation status after each phase.
11. Do not deploy, submit regulator filings, spend funds or contact external parties without explicit approval.
12. Stop after the approved phase and request review before proceeding.

### 13. FIRST TASK FOR CODEX

**Do not build the whole product at once.** First inspect the development environment and repository. Produce Phase 0 architecture documentation: modular Laravel structure, database ERD, tenant and role model, registration/applicability rule design, evidence/verification statuses, workflow/dependency engine, AI boundaries, security model, regulatory research backlog, API strategy, phased implementation plan and test plan. Identify any uncertain legal/regulatory assumptions. Present decisions and wait for approval before implementing Phase 1.

---

**Product owner reminder:** ZimComply AI is a compliance assistance and workflow platform, not a regulator or substitute for professional legal/tax advice. All production rules require verification against authoritative current Zimbabwean sources.
