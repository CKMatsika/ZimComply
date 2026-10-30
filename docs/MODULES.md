# ZimComply AI — Modular Specification (M01 – M14)

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative

---

## Module Index & Responsibility Matrix

| Module ID | Module Title | Primary Domain Responsibility | Key Entities Owned | External Touchpoints |
|---|---|---|---|---|
| **M01** | Multi-Tenant Foundation & Identity | Tenant isolation, org identity, branches, users, RBAC, subscription tiers | `Tenant`, `Organisation`, `Branch`, `User`, `Membership`, `Subscription` | Auth, Email / SMS MFA |
| **M02** | Registration Discovery & Engine | Interactive questionnaire, legal assessment, dynamic registration roadmap | `OrganisationActivity`, `ApplicabilityAssessment`, `ObligationDefinition`, `ObligationInstance` | Regulatory Knowledge Base |
| **M03** | Corporate & Tax Registration | Company registration, ZIMRA TIN, VAT status, ITF263 tax clearance readiness | `RegistrationCase`, `ObligationInstance` (ZIMRA) | TaRMS Portal (prep only) |
| **M04** | Employer & Payroll Compliance | NSSA registration, POBS/APWCS, ZIMDEF, PAYE, AIDS Levy, payroll capture | `Employee`, `PayrollPeriod`, `ContributionCalculation`, `Filing` | NSSA Portal, ZIMDEF |
| **M05** | Sector Agreements & NEC | Collective Bargaining Agreements, wage grades, NEC levies, Engineering Medical Fund | `CollectiveAgreement`, `EmployeeClassification`, `MedicalAidScheme` | NEC Councils, Medical Funds |
| **M06** | Local Authority Licensing | Municipal shop licences, health inspections, fire certificates, City of Harare | `Licence`, `Permit`, `Jurisdiction`, `Branch` | City Councils (Harare, Bulawayo) |
| **M07** | Professional & Sector Bodies | Engineering Council of Zimbabwe (ECZ), contractor bodies (CIFOZ, ZBCA) | `ObligationInstance`, `Certificate` | ECZ, Construction Registries |
| **M08** | Fleet, Insurance & Radio Licences | Vehicle registry, ZINARA licensing, motor insurance policies, ZBC radio discs | `Vehicle`, `InsurancePolicy`, `Certificate` | ZINARA, Insurance, ZBC |
| **M09** | Compliance Wallet & Documents | Encrypted document vault, OCR processing, 5-tier verification, expiry tracking | `Document`, `DocumentVersion`, `DocumentVerification`, `Certificate` | S3 Storage, OCR Engines |
| **M10** | Compliance Calendar & Workflow | Multi-step state machine, automated reminders (90/60/30/14/7), escalations | `WorkflowTemplate`, `WorkflowStep`, `Task`, `Deadline` | Queue Workers, Mail, SMS |
| **M11** | PRAZ & Tender Intelligence | PRAZ supplier categories, public procurement notice parsing, bid matrix | `ProcurementCategory`, `SupplierRegistration`, `TenderNotice`, `TenderAssessment` | PRAZ e-GP Portal |
| **M12** | AI Assistant & Regulatory Radar | Grounded contextual Q&A, statutory change proposals, citation tracing | `RegulatorySource`, `RegulatoryChangeProposal` | Gemini API / LLM Providers |
| **M13** | Compliance Finance & Reporting | Forecasts of statutory liabilities, multi-currency cash flows, audit dossiers | `FilingAcknowledgement`, `PaymentEvidence` | PDF Export, Excel/CSV |
| **M14** | Integration Hub | CSV/Excel batch imports, versioned ERP adapters (Sage, Pastel, Xero, QuickBooks) | `IntegrationConnection`, `ImportJob`, `AuditEvent` | External ERPs, Webhooks |

---

## Detailed Module Specifications

### M01: Multi-Tenant Foundation & Business Identity
- **Scope:** Complete tenant lifecycle, corporate legal identities, subsidiary organizations, branches/sites, currencies (USD & ZiG), and user role memberships.
- **Access Control:** Role-Based Access Control (RBAC) with granular permissions:
  - `admin.manage_tenants`
  - `org.manage_identity`
  - `compliance.verify_documents`
  - `payroll.calculate`
  - `tender.manage_bids`
- **Security:** Session-based authentication via Laravel Sanctum, mandatory TOTP MFA for administrative and financial roles, immutable tenant audit logging.

### M02: Registration Discovery & Applicability Engine
- **Scope:** Replaces static compliance checklists with an intelligent discovery questionnaire. Evaluates sector activities, geographic operating branches, employee headcount, annual revenue, and assets.
- **Output:** Generates a **Personalized Registration Roadmap** detailing:
  - Applicable statutory obligation name and issuing regulator.
  - Legal basis (Act citation and gazetted SI).
  - Exact prerequisites and required evidence documents.
  - Estimated official government fees and typical processing timelines.
  - Confidence and evidence classification.
- **Non-Linear Execution:** Roadmaps allow parallel tasks and conditional branches (e.g., NSSA and Local Council can proceed concurrently; ITF263 requires prior TIN and tax return filings).

### M03: Corporate & Tax Registration (ZIMRA)
- **Scope:** Tracks incorporation records (CR6, CR14/CR28, Memorandum of Association) and tax registrations.
- **ITF263 Tax Clearance Readiness:** Tracks real-time eligibility for ITF263 certificate issuance. Audits outstanding return filings (ITF12, VAT7, P2) and payment balances.
- **TaRMS Assistance:** Prepares structured dossiers and pre-formatted data for ZIMRA's Tax and Revenue Management System (TaRMS). Zero speculative direct submissions without authorized API access.

### M04: Employer Registration & Payroll Compliance
- **Scope:** End-to-end employer statutory registration and monthly compliance for NSSA (POBS & APWCS), ZIMDEF (1% levy), PAYE, and AIDS Levy (3%).
- **Payroll Ingestion:** Supports lightweight manual web capture and high-throughput Excel/CSV batch imports.
- **Calculation Precision:** High-precision evaluation of progressive PAYE brackets and quarterly-adjusted NSSA insurable earning caps in both USD and ZiG.
- **Filing Generation:** Produces official P2, NSSA P4, and ZIMDEF return schedules and CSV upload files.

### M05: Industry-Specific NEC & Medical Aid
- **Scope:** Models Collective Bargaining Agreements across economic sectors.
- **Engineering / Iron & Steel Priority:** Models grade-specific wage floors (Grade 1 to 10), artisan classifications, and council administration dues.
- **Medical Aid Governance:** Captures designated industry schemes (e.g. Engineering Medical Fund). Separates statutory compulsory arrangements from voluntary employer fringe benefits.

### M06: Local Authority Licensing & Permits
- **Scope:** Municipality-specific business licensing for premises, workshops, and commercial outlets.
- **Jurisdiction Configuration:** Defaults to City of Harare (Urban Councils Act [Chapter 29:15]), but supports City of Bulawayo, Chitungwiza, and Rural District Councils (RDCs).
- **Inspection Tracking:** Tracks multi-stage municipal approvals: Town Planning land-use permits, Health & Hygiene inspection certificates, and Fire Brigade clearances.

### M07: Professional, Engineering & Construction Registrations
- **Scope:** Assesses firm-level vs individual practitioner accreditations for technical sectors.
- **Engineering Council of Zimbabwe (ECZ):** Evaluates corporate compliance with Engineering Council Act [Chapter 27:22].
- **Construction Contractors (CIFOZ & ZBCA):** Classifies contractor categories (Category A through G) and annual subscription renewals. Explicitly documents that association membership is a tender prerequisite, not a compulsory criminal statute.

### M08: Fleet, Insurance & Radio Licensing
- **Scope:** Asset-level compliance for corporate vehicles, delivery trucks, and heavy equipment.
- **Tripartite Renewal Calendar:** Synchronizes ZINARA vehicle licence discs, mandatory third-party or comprehensive motor insurance policies, and ZBC car radio licence discs.
- **Dispatch Safety Warnings:** Flags vehicles with expiring licences on the fleet dashboard to prevent roadside impoundment and traffic penalties.

### M09: Compliance Wallet & Document Intelligence
- **Scope:** Encrypted vault storing official certificates, tax clearances, licences, and bank letters.
- **Document Pipeline:**
  1. Malware / Antivirus scan on upload.
  2. OCR text extraction and automated metadata parsing (certificate number, issue date, expiry date).
  3. Placement in `PENDING_VERIFICATION` queue for human officer verification.
  4. Generation of active `Certificate` / `Licence` records upon verification.
- **Access Controls:** Private object storage with time-limited presigned URLs (15-minute expiration) and role-scoped download permissions.

### M10: Compliance Calendar & Workflow Engine
- **Scope:** Unified task orchestration, deadline notifications, and escalation matrix.
- **Alert Cadence:** Configurable multi-stage notifications: 90 days, 60 days, 30 days, 14 days, 7 days, and 1 day prior to statutory expiry.
- **Multi-Channel Dispatch:** Dispatched via email and SMS/WhatsApp using native Laravel Notification channels.

### M11: PRAZ Supplier Registration & Tender Intelligence
- **Scope:** Public procurement readiness and tender tracking under the PPDPA Act [Chapter 22:23].
- **Supplier Categories:** Maps corporate capabilities to official PRAZ e-GP category codes.
- **Tender Dossier Analysis:** Parses uploaded tender bidding documents, extracts mandatory compliance criteria, and produces an instant **Bid Readiness Compliance Matrix** highlighting missing documents.

### M12: AI Compliance Assistant & Regulatory Radar
- **Scope:** Context-aware, citation-grounded assistant for regulatory inquiries.
- **Grounding Standards:** Every answer links directly to verified statutory instruments and gazette citations. Answers explicitly disclose confidence ratings and flag legal ambiguities with "Verification Required".
- **Regulatory Radar:** Ingests official government gazettes, identifies statutory changes, and drafts change proposals for expert legal review.

### M13: Compliance Finance & Reporting
- **Scope:** Cash-flow forecasting for upcoming regulatory liabilities across 30, 90, and 365 days.
- **Audit Packs:** One-click generation of comprehensive **Compliance Audit Packs** (PDF dossiers containing corporate certificates, ITF263, NSSA clearances, and council licences) suitable for banking reviews and tender submissions.

### M14: Integration Hub
- **Scope:** Data interchange layer connecting ZimComply AI with third-party business systems.
- **Supported Adapters:** Structured CSV/Excel import mappers and REST API adapters for Sage Pastel, Xero, QuickBooks, ERPNext, and future Smart ERP.
- **Idempotency & Safety:** All import jobs use unique hash keys to guarantee that repeated imports never duplicate employees, payroll periods, or statutory obligations.
