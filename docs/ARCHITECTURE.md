# ZimComply AI — System Architecture Specification

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Verified & Authoritative

---

## 1. Architectural Vision & Principles

ZimComply AI is engineered as an enterprise-grade, mobile-first, multi-tenant compliance orchestration platform tailored specifically to Zimbabwean statutory and commercial realities. It is designed around seven immutable architectural tenets:

1. **Modular Monolith First:** Built as a unified, domain-partitioned Laravel monolith with strict internal API boundaries. This eliminates distributed systems overhead while ensuring future microservice extractability.
2. **Registration-First Journey:** Architecture prioritizes business discovery and initial regulatory registration roadmaps before recurring filing tracking.
3. **Deterministic Statutory Engine:** All legal obligations, fee calculations, deadline rules, and tax thresholds are calculated exclusively by deterministic code backed by verified, effective-dated database records. Generative AI is strictly forbidden from executing arithmetic calculations or establishing statutory legal facts.
4. **5-Tier Evidence Trust Architecture:** No compliance status is binary without provenance. Every claim is tagged with an immutable evidence status (`verified_by_issuer`, `document_supported`, `user_declared`, `inferred`, or `unknown`).
5. **Zero External Automation Dependency:** Operates independently of third-party workflow automation engines (e.g., n8n is strictly prohibited). Workflow orchestration is powered entirely by native Laravel Queues, Jobs, and the Scheduler.
6. **ERP & Accounting System Agnostic:** Zero direct schema coupling or hard operational database dependencies with external ERPs (Pastel, Sage, Xero, QuickBooks, ERPNext, or Smart ERP). All integrations interface via typed DTOs and versioned adapters.
7. **Strict Multi-Tenant Isolation:** Complete logical tenant separation enforced at the database layer (PostgreSQL schema / tenant foreign key with automatic global query scopes) and verified through automated cross-tenant security test suites.

---

## 2. Technology Stack & Runtime Topology

```
+-------------------------------------------------------------------------+
|                              CLIENT TIER                                |
|  - Desktop / Tablet / Mobile Browser (PWA)                              |
|  - React 18 + TypeScript + Tailwind CSS                                 |
|  - Inertia.js (Single-Page App user experience without REST/GraphQL API |
|    boilerplate overhead)                                                |
+-------------------------------------------------------------------------+
                                    | HTTPS / WSS
                                    v
+-------------------------------------------------------------------------+
|                             GATEWAY / WEB                               |
|  - Nginx / Caddy Reverse Proxy (TLS Termination, HTTP/2, Gzip/Brotli)   |
|  - Security Headers (CSP, HSTS, X-Frame-Options, Permissions-Policy)   |
+-------------------------------------------------------------------------+
                                    |
                                    v
+-------------------------------------------------------------------------+
|                        APPLICATION CORE (LARAVEL)                       |
|  - PHP 8.2+ (OPcache, JIT enabled)                                      |
|  - Laravel 11 Framework                                                 |
|  - Laravel Sanctum / Session Auth + MFA (TOTP)                          |
|  - Middleware Pipeline (TenantContext, Subscriptions, AuditLogger)      |
|  - Domain Service Layer (M01 - M14)                                     |
|  - Deterministic Rule Engine                                            |
|  - AI Provider Abstraction (Schema-Enforced Tooling)                    |
+-------------------------------------------------------------------------+
          |                       |                       |
          v                       v                       v
+-------------------+   +-------------------+   +-------------------+
|  PRIMARY DATABASE |   |  QUEUE & CACHE    |   |  OBJECT STORAGE   |
|  - PostgreSQL 16  |   |  - Redis / DB     |   |  - Encrypted S3 / |
|  - Strict Foreign |   |  - Laravel Horizon|   |    Local Storage  |
|    Keys & UUIDs   |   |  - Native Task    |   |  - Signed URLs    |
|  - Decimal(18,4)  |   |    Scheduler      |   |  - Virus Scanning |
+-------------------+   +-------------------+   +-------------------+
```

---

## 3. Modular Monolith Directory Structure

The codebase adheres to Domain-Driven Design (DDD) principles within the standard Laravel structure, ensuring modular cohesion:

```
app/
├── Domains/
│   ├── AuthAndTenant/            # M01: Multi-tenancy, Users, Roles, Permissions, Orgs, Branches
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Repositories/
│   │   └── Services/
│   ├── RegistrationEngine/       # M02: Applicability Questionnaire, Discovery, Roadmaps
│   ├── CorporateTax/             # M03: ZIMRA, TIN, VAT, ITF263 readiness, TaRMS preparation
│   ├── EmployerPayroll/          # M04: NSSA, ZIMDEF, PAYE, AIDS Levy, Payroll Capture
│   ├── SectorAgreements/         # M05: NECs, Collective Bargaining, Engineering Medical Fund
│   ├── LocalLicensing/           # M06: City Councils (Harare, Bulawayo, etc.), Premises permits
│   ├── ProfessionalBodies/       # M07: ECZ, CIFOZ, ZBCA, Sector Registrations
│   ├── FleetCompliance/          # M08: Vehicles, ZINARA, Radio Licences, Roadworthiness
│   ├── ComplianceWallet/         # M09: Document Vault, OCR Queue, Extraction & Verification
│   ├── WorkflowCalendar/         # M10: Task Orchestration, Deadlines, Escalation, State Machine
│   ├── TenderIntelligence/       # M11: PRAZ/e-GP, Bid Matrix, Requirement Extraction
│   ├── RegulatoryAI/             # M12: Grounded Assistant, Statutory SIs, Change Review Queue
│   ├── ComplianceFinance/        # M13: Levies, Cash Flow Forecasting, Multi-Currency Ledger
│   └── IntegrationHub/           # M14: CSV/Excel Mappers, ERP Connectors (Pastel, Sage, etc.)
├── Shared/
│   ├── Contracts/                # Domain Interfaces, DTOs, Enums
│   ├── RuleEngine/               # Deterministic Rule Evaluation, Math & Date calculations
│   ├── Currency/                 # CurrencyConverter, MonetaryValue (USD/ZiG precision)
│   ├── Scopes/                   # TenantScope, BranchScope, ActiveScope
│   └── Traits/                   # HasTenant, HasAuditTrail, HasUuid, ValidatesEvidence
└── Http/
    ├── Controllers/              # Thin controllers dispatching to Domain Actions
    ├── Middleware/               # EnforceTenant, RequireMfa, VerifySubscription
    └── Requests/                 # Strict FormRequest Validation
```

---

## 4. Multi-Tenancy Architecture

### 4.1 Tenant Scoping Model
- Every tenant represents an independent operational account (e.g., an individual SME, an enterprise with multiple subsidiaries, or an accounting practice managing multiple client organisations).
- Key hierarchy:
  $$\text{Tenant} \longrightarrow \text{Organisations} \longrightarrow \text{Branches / Sites} \longrightarrow \text{Assets / Employees / Obligations}$$
- Data records carry an indexed `tenant_id` (UUID).
- The `TenantScope` global Eloquent scope automatically appends `WHERE tenant_id = ?` to all database queries in runtime contexts, completely neutralizing accidental cross-tenant data leakage.

### 4.2 Consultancy & Accounting Practice Multi-Client Model
- An accounting firm owns a single Tenant account with an `accountant_firm` entitlement.
- The firm can be granted delegated, revocable access to client `Organisations` with explicit permission levels (e.g., `Reviewer`, `Preparer`, `Approver`).
- Audit logs capture both the acting user ID and the client organisation ID for complete forensic compliance.

---

## 5. Deterministic Statutory Rule Engine (Milestone 2.2 Implemented)

### 5.1 Service Structure
The Registration Engine is strictly deterministic, decoupled from UI controllers and completely independent of generative AI.
```
app/Domains/RegistrationEngine/
├── Services/
│   ├── RuleConditionEvaluator.php      # Restricted, validated condition evaluation engine (equals, not_equals, gt, gte, lt, lte, in, contains, all, any)
│   ├── DependencyGraph.php             # Directed Acyclic Graph (DAG) with Kahn's topological sort and cycle detection
│   ├── ApplicabilityEvaluator.php      # Resolves active, approved, verified rule versions and evaluates statutory outcomes
│   └── RegistrationRoadmapGenerator.php # Orchestrates evaluation, DAG sort, tenant isolation, and idempotent persistence
├── Exceptions/
│   ├── CircularDependencyException.php # Identifies exact cycle path (e.g. A -> B -> C -> A)
│   ├── MissingPrerequisiteException.php# Identifies missing prerequisite code in catalog
│   ├── InvalidConditionException.php   # Rejects malformed condition nodes and unknown operators
│   └── UnresolvedInputException.php    # Surfaces contradictory profile inputs requiring resolution
├── DTOs/
│   └── ApplicabilityDecision.php       # Auditable statutory decision value object
└── Models/
    ├── RegulatoryAuthority.php
    ├── RegulatorySource.php
    ├── RulePack.php
    ├── RuleVersion.php
    ├── ObligationDefinition.php
    ├── RegistrationQuestionnaire.php
    ├── ApplicabilityAssessment.php
    ├── AssessmentResult.php
    └── ObligationInstance.php
```

### 5.2 Rule Packs, Versioning & Effective Dates
- Statutory rules are maintained in database tables (`rule_packs`, `rule_versions`, `obligation_definitions`).
- Each rule version specifies:
  - `effective_from` (Date)
  - `effective_to` (Date or NULL for active rules)
  - `verification_status` (`VERIFIED_OFFICIAL`, `REQUIRES_REVIEW`, `DRAFT`)
  - `status` (`DRAFT`, `PENDING_REVIEW`, `APPROVED`, `ACTIVE`, `SUPERSEDED`, `REJECTED`)
  - `parameters` (JSON schema containing thresholds, percentage rates, exemption flags)
  - Dual-key governance (`proposed_by_user_id`, `approved_by_user_id`, `approved_at`)
- **Verification Guarantee:** Only approved, verified, and effective-dated rules generate definitive statutory obligations (`MANDATORY`, `CONDITIONAL`, `EXEMPT`). Unverified, draft, or out-of-date rules are placed on `UNVERIFIED_HOLD`.
- **Topological Sequence Guarantee:** Prerequisites are ordered via Kahn's algorithm with deterministic tie-breaking (classification priority, then alphabetical by code).
- **Historical Immutability & Idempotency:** Assessments are saved as immutable records with SHA-256 fingerprinting or explicit idempotency keys; re-evaluating or changing business circumstances creates a new historical record without mutating past assessments.

---

## 6. AI Boundary & Human-in-the-Loop Architecture

Generative AI (e.g. Gemini / LLMs) is isolated as an assistive co-pilot:

1. **Permitted AI Functions:**
   - Document classification & OCR field extraction (e.g., reading certificate number and expiry from an uploaded PDF).
   - Natural language explanation of statutory terms and plain-English regulatory summaries.
   - Requirement extraction from uploaded tender bidding documents (PRAZ e-GP PDFs).
   - Semantic drafting of inquiry emails, cover letters, or clarification requests.
2. **Strictly Prohibited AI Functions:**
   - Statutory contribution arithmetic (PAYE, NSSA, NEC, ZIMDEF).
   - Autonomous submission of filings or certificates to government portals.
   - Autonomous payment execution or debit authorization.
   - Overriding human-configured exemptions or disputes.
3. **Execution Guardrails:**
   - **Strict Schemas:** AI endpoints must output strictly validated JSON schemas.
   - **Verification Queue:** Extracted dates and figures are placed in a `PENDING_VERIFICATION` human queue before being committed to active compliance records.
   - **Prompt Logging:** Prompts, responses, token usage, and model versions are immutably logged for regulatory auditability.

---

## 7. Evidence Trust Pipeline & Verification Lifecycle

Every piece of evidence and compliance status adheres to a deterministic 5-tier classification:

```
[ Upload / Input ]
        |
        v
+------------------+
|  user_declared   | ---> User entered data without supporting official document
+------------------+
        |
   (File Uploaded)
        v
+---------------------+
| document_supported  | ---> PDF/Image attached, pending formal validation
+---------------------+
        |
  (Manual Admin Review or Cryptographic QR/Digital Signature Verified)
        v
+--------------------+
| verified_by_issuer | ---> Confirmed via official QR/portal receipt or verified auditor
+--------------------+

Alternative States:
- inferred: Derived programmatically from related validated facts (e.g., branch has operating licence because master premises permit is active).
- unknown: Insufficient data; flagged with "Verification Required".
```

---

## 8. Workflow Engine & Dependency State Machine

Registration and renewal cases move through an explicit state machine:

$$\text{NOT\_STARTED} \longrightarrow \text{INFO\_REQUIRED} \longrightarrow \text{DRAFT} \longrightarrow \text{AWAITING\_APPROVAL} \longrightarrow \text{SUBMITTED} \longrightarrow \text{ACKNOWLEDGED} \longrightarrow \text{ISSUED}$$
$$\text{Rejection / Dispute Branch:} \quad \text{SUBMITTED} \longrightarrow \text{REJECTED} \longrightarrow \text{DISPUTED} \longrightarrow \text{REOPENED}$$

- **Prerequisite Graphs:** Roadmaps support Directed Acyclic Graphs (DAGs). For example, obtaining an ITF263 Tax Clearance requires a verified TIN and filed returns, but NSSA registration and Local Authority licensing can proceed in parallel.
- **Rollback & Re-evaluation:** If a prerequisite is revoked or expired, downstream dependencies are flagged automatically with warning badges.

---

## 9. Data Storage & Security Controls

- **Database:** PostgreSQL 16 with Row-Level Security (RLS) policies as defense-in-depth behind Eloquent Global Scopes.
- **Field-Level Encryption:** Sensitive personal and corporate identifiers (Director National ID numbers, bank account numbers, TIN, employee medical aid numbers) use AES-256-GCM authenticated encryption (`encrypted` Eloquent casts).
- **Object Storage:** Document uploads stored in S3-compatible private buckets with private access only. File access is mediated strictly via temporary signed URLs with 15-minute expirations.
- **Audit Trails:** Immutable, append-only `audit_events` capturing: `id`, `tenant_id`, `user_id`, `event_type`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`.

---

## 10. AI Subsystem & Governance Architecture (Official Laravel AI SDK & ADR-0005)

Integrated under PHP 8.4 with the official Laravel AI SDK (`laravel/ai` v1.3.0), the AI architecture mediates all machine learning capabilities through strict statutory boundaries, multi-tenant isolation, and privacy safeguards:

```
+---------------------------------------------------------------------------------------+
|                                    USER & TENANT CONTEXT                              |
|   (Established via TenantContext singleton & EnforceTenantContext middleware)        |
+---------------------------------------------------------------------------------------+
                                           |
                                           v
+---------------------------------------------------------------------------------------+
|                          AI ORCHESTRATION LAYER (app/Shared/Ai)                       |
|  - AiOrchestrationService: Central gateway enforcing tenant validation & quota checks |
|  - DataPrivacySanitizer: Masks Zimbabwe National IDs, passwords, API keys & secrets  |
|  - AiUsageMonitor: High-resolution token metering, USD cost calculation, and limits   |
+---------------------------------------------------------------------------------------+
                                           |
                    +----------------------+----------------------+
                    |                                             |
                    v                                             v
+---------------------------------------+     +---------------------------------------+
|       RegistrationRoadmapAgent        |     |  RegistrationDocumentExtractorAgent   |
| - Implements HasStructuredOutput      |     | - Implements HasStructuredOutput      |
| - Strictly grounded in Zimbabwe Acts  |     | - Extracts company, registration no., |
|   (COBE Act 24:31, ZIMRA, NSSA, PRAZ) |     |   incorporation date, and directors   |
| - Zero hallucinated statutory rules   |     | - Computes objective confidence score |
+---------------------------------------+     +---------------------------------------+
                    |                                             |
                    | (Structured Response)                       | (Proposed Entities)
                    v                                             v
+---------------------------------------+     +---------------------------------------+
|            ai_usage_ledgers           |     |         ai_verification_items         |
|  - Tenant-isolated token consumption  |     |  - Mandatory Human-in-the-Loop review |
|  - Provider, model, cost attribution  |     |  - PENDING -> VERIFIED / REJECTED     |
|  - Immutable audit trail generation   |     |  - Prevents automated registry writes |
+---------------------------------------+     +---------------------------------------+
```

### Core AI Governance Tenets:
1. **Statutory Integrity:** The AI Assistant never independently calculates statutory tax rates, penalty formulas, or deadlines. All outputs cite specific Zimbabwean Acts.
2. **Mandatory Human-in-the-Loop Queue:** Extracted document parameters are queued as `PENDING` in `ai_verification_items`. They only commit to permanent company registries upon explicit human verification or rejection.
3. **Multi-Tenant Metering & Isolation:** Usage ledgers are partitioned by `tenant_id` with `TenantScope`. Runaway API usage is preemptively halted when subscription tier token limits are reached.
4. **Data Privacy Pre-processing:** Every prompt passes through `DataPrivacySanitizer` to redact sensitive identifiers before dispatch to external gateways.
5. **Mocked Automated Testing:** Test suites execute 100% offline via `Ai::fakeAgent()`, guaranteeing zero external API cost and predictable CI/CD execution.

