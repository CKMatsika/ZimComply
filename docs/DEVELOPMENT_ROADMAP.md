# ZimComply AI — Development Roadmap & Phase Gate Specifications

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative

---

## 1. Roadmap Overview & Phase Gate Principles

Development proceeds through sequential, gate-controlled phases. In accordance with the Codex Operating Instructions (README Section 12), **no phase may commence until the deliverables, test suites, and documentation of the preceding phase have been completed and verified.**

```
+-------------------------------------------------------------------------+
| PHASE 0: Discovery & Architecture Specification             [COMPLETED] |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 1: SaaS Foundation, Multi-Tenancy & UI Shell        [READY / NEXT]|
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 2: Registration-First MVP & Roadmap Engine             [PLANNED]  |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 3: Compliance Wallet, OCR Extraction & Renewals        [PLANNED]  |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 4: Employer, Payroll & Sector Compliance (NSSA/NEC)    [PLANNED]  |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 5: PRAZ Supplier Registration & Tender Intelligence    [PLANNED]  |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 6: AI Assistant & Regulatory Change Review System      [PLANNED]  |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 7: External Integrations & Multi-Client Accounting     [PLANNED]  |
+-------------------------------------------------------------------------+
                                    |
+-------------------------------------------------------------------------+
| PHASE 8: Production Hardening, Security Audits & Rollout     [PLANNED]  |
+-------------------------------------------------------------------------+
```

---

## 2. Phase-by-Phase Execution Plan

### Phase 0: Discovery & Architecture Baseline (COMPLETED)
- **Deliverables:**
  - Complete repository and environment inspection (PHP 8.2, Composer 2.10, Node 20, PostgreSQL 16).
  - Git repository initialization.
  - Complete architectural specifications (`ARCHITECTURE.md`, `ERD.md`, `REGULATORY_SOURCE_REGISTER.md`, `RULE_GOVERNANCE.md`, `MODULES.md`, `SECURITY.md`, `API_INTEGRATIONS.md`, `DEVELOPMENT_ROADMAP.md`, `DEPLOYMENT.md`, `TESTING.md`, `USER_GUIDE.md`).
  - Architecture Decision Records (`ADR-0001` through `ADR-0006`).
- **Phase Gate Review:** Architectural documentation verified against master brief. Ready for product owner review and Phase 1 greenlight.

---

### Phase 1: SaaS Foundation, Multi-Tenancy & UI Shell (CURRENT / NEXT)
- **Primary Goal:** Establish the functional skeleton of ZimComply AI with secure authentication, multi-tenant isolation, database migrations, and a responsive application shell.
- **Key Deliverables:**
  1. Laravel 11 application scaffolding configured with PostgreSQL 16 and Vite.
  2. Inertia.js + React 18 + TypeScript + Tailwind CSS design system setup.
  3. Core database migrations: `tenants`, `organisations`, `branches`, `users`, `memberships`, `subscriptions`, `audit_events`.
  4. Authentication system: Login, registration, password reset, session security, and TOTP MFA readiness.
  5. Multi-tenant scoping middleware and automated isolation test suite.
  6. Application navigation shell (**Register | Comply | Renew | Tender | Documents | Reports | Settings**) with company switcher and mobile responsiveness.
- **Testing & Verification Gate:**
  - 100% pass on authentication and session security tests.
  - Cross-tenant data isolation test suite passing (asserting Tenant A cannot view Tenant B's records).
  - Frontend responsiveness validated on desktop and mobile viewports.

---

### Phase 2: Registration-First MVP & Discovery Engine
- **Primary Goal:** Implement Module M02, delivering the interactive discovery questionnaire and dynamic registration roadmap.
- **Key Deliverables:**
  1. Regulatory catalog migrations: `regulatory_authorities`, `jurisdictions`, `regulatory_sources`, `rule_packs`, `rule_versions`, `obligation_definitions`.
  2. Seeders with verified baseline authorities (ZIMRA, NSSA, PRAZ, ZINARA, COH, NEC Engineering).
  3. Interactive applicability questionnaire UI evaluating business activities, turnover, premises, and employee count.
  4. Roadmap generator action producing cited, conditional registration roadmaps with prerequisites and fee sources.
  5. Task checklist UI with manual status tracking (`NOT_STARTED` -> `IN_PROGRESS` -> `COMPLIANT`).
- **Testing & Verification Gate:**
  - Automated acceptance scenario tests: Engineering company, Construction contractor, and Retail SME scenarios generating appropriate roadmaps.
  - Prerequisite dependency graph validated (preventing downstream tasks before prerequisites are satisfied).

---

### Phase 3: Compliance Wallet, OCR & Renewals
- **Primary Goal:** Implement Module M09 and operational licence tracking (M06, M08).
- **Key Deliverables:**
  1. Compliance wallet migrations: `documents`, `document_versions`, `document_verifications`, `certificates`, `licences`, `permits`, `vehicles`, `insurance_policies`.
  2. File upload interface with drag-and-drop, MIME validation, and S3-compatible private storage.
  3. Asynchronous OCR and metadata extraction pipeline.
  4. Human verification queue for compliance officers.
  5. ITF263 tax clearance readiness monitor and vehicle fleet renewal calendar (ZINARA + Insurance + Radio licence).
  6. Automated deadline reminder scheduler (90/60/30/14/7 days).
- **Testing & Verification Gate:**
  - Presigned URL security test (asserting private bucket isolation and URL expiration).
  - Expiry alert notification dispatch tests.

---

### Phase 4: Employer, Payroll & Sector Compliance
- **Primary Goal:** Implement Modules M04 and M05, enabling statutory payroll calculation and reconciliation.
- **Key Deliverables:**
  1. Migrations: `employees`, `employee_classifications`, `collective_agreements`, `payroll_periods`, `contribution_calculations`, `filings`, `payment_evidence`.
  2. Deterministic calculation engine for PAYE, AIDS Levy, NSSA POBS/APWCS, ZIMDEF, and NEC dues.
  3. High-throughput CSV/Excel payroll import engine with duplicate row detection.
  4. Dual-currency processing (USD and ZiG) with official return generation (ZIMRA P2, NSSA P4).
- **Testing & Verification Gate:**
  - 100% mathematical regression match against verified ZIMRA and NSSA tax tables.
  - Historical immutability test: modifying a 2026 rule does not alter 2025 calculation records.

---

### Phase 5: PRAZ Supplier Registration & Tender Intelligence
- **Primary Goal:** Implement Module M11 for public procurement and bid readiness.
- **Key Deliverables:**
  1. Migrations: `procurement_categories`, `supplier_registrations`, `tender_notices`, `tender_requirements`, `tender_assessments`, `tender_submissions`.
  2. PRAZ supplier category management and annual renewal tracking.
  3. Public procurement notice feed and keyword/category matching.
  4. Tender document parser and automated Bid Readiness Compliance Matrix generator.
- **Testing & Verification Gate:**
  - Tender gap analysis test: accurately identifies missing tax clearance or NSSA clearance from uploaded bid dossiers.

---

### Phase 6: AI Assistant & Regulatory Change Review System
- **Primary Goal:** Implement Module M12, integrating grounded AI assistance and regulatory intelligence.
- **Key Deliverables:**
  1. AI provider abstraction (Gemini / Anthropic / OpenAI) with strict token budgets and JSON schema validation.
  2. Contextual compliance assistant grounded in verified statutory sources.
  3. Regulatory change proposal review inbox with simulation diffs.
- **Testing & Verification Gate:**
  - AI guardrail tests: asserts AI cannot perform monetary calculations or confirm official filings without human sign-off.

---

### Phase 7: External Integrations & Multi-Client Accounting
- **Primary Goal:** Implement Module M14 and firm-level multi-client tenancy.
- **Key Deliverables:**
  1. External ERP connectors (Sage Pastel, Xero, QuickBooks, ERPNext, future Smart ERP).
  2. Accounting firm portfolio dashboard managing up to 100+ client companies with zero data leakage.
  3. Delegated client permissions and approval workflows.
- **Testing & Verification Gate:**
  - Cross-client authorization tests under accounting firm accounts.

---

### Phase 8: Production Hardening, Security Audits & Rollout
- **Primary Goal:** Enterprise deployment readiness, performance tuning, and launch.
- **Key Deliverables:**
  1. Security penetration testing and vulnerability remediation.
  2. Database indexing, query optimization, and Redis caching.
  3. Automated backup drills and disaster recovery runbooks.
  4. PWA offline caching and low-bandwidth optimizations.
  5. Production deployment to Laravel Cloud / Linux cloud infrastructure.
- **Testing & Verification Gate:**
  - Load testing under simulated multi-tenant concurrency.
  - Comprehensive deployment verification report.

---

## 3. Rollback & Migration Safety Strategy

In strict adherence to Codex operating instructions:
1. **Never use `migrate:fresh` or `db:wipe`:** All schema changes must be safe and additive.
2. **Reversible Migrations:** Every migration must implement a robust `down()` method.
3. **Data Preservation:** Dropping tables or altering columns containing production data is prohibited without verified data migration scripts.
