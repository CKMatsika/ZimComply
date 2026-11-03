# ZimComply AI — Implementation Status & Verification Ledger

*Last updated: 2026-11-03*  
*Status: Phase 0, Phase 1, Phase 2 (Milestones 2.1 - 2.6) & Phase 3 (Milestones 3.1 - 3.2) Completed (142/142 Automated Tests Passing, 804 Assertions)*

---

## 1. Executive Summary

ZimComply AI is a Zimbabwe-focused SaaS platform designed to assist SMEs, accountants, payroll bureaus, and compliance consultants in registering, maintaining compliance, renewing licences, and qualifying for procurement opportunities.

Following the mandatory **Codex Operating Instructions** (README Section 12 & 13) and user conditional approval safeguards, this ledger tracks the implementation progress, runtime configuration, verified tests, and AI governance integration.

| Phase / Milestone | Description | Status | Verification Gate |
|---|---|---|---|
| **Phase 0** | Discovery, Environment & Architecture Baseline | **Completed** | Architecture specifications & ADRs verified |
| **Phase 1** | SaaS Foundation, Multi-Tenancy, Auth & UI Shell | **Completed** | 11/11 automated tests passing, PostgreSQL 16 migrations executed, Vite build passing |
| **Milestone 1.5** | PHP 8.4 Runtime & Official Laravel AI SDK Foundation | **Completed** | 20/20 automated tests passing (including 9 AI feature tests), side-by-side PHP 8.4 verified |
| **Milestone 2.1** | Regulatory Catalogue, Rule Governance, Schema & Seeders | **Completed** | 25/25 automated tests passing (129 assertions), PostgreSQL 16 migrations executed |
| **Milestone 2.2** | Deterministic Rules Engine (Applicability & DAG Graph) | **Completed** | 54/54 automated tests passing (230 assertions), Vite production build passing |
| **Milestone 2.3** | Discovery Questionnaire Service & Persistence | **Completed** | 73/73 automated tests passing (380 assertions), draft save/resume verified |
| **Milestone 2.4** | Document Storage, AI Extraction Pipeline & Human Verification | **Completed** | 91/91 automated tests passing (465 assertions), private vault & verification verified |
| **Milestone 2.5** | Registration Workspace UI (React + Inertia) | **Completed** | 5-step wizard, prerequisite roadmap & historical audit trail verified |
| **Milestone 2.6** | Security, Data Integrity & Evidence Lifecycle Remediation | **Completed** | 103/103 automated tests passing (513 assertions), all 5 audit findings remediated |
| **Milestone 3.1** | Ongoing Compliance Obligation Register & Lifecycle Engine | **Completed** | 118/118 automated tests passing (653 assertions), state machine & E2E browser tests verified |
| **Milestone 3.2** | Statutory Filing Calendar & Deterministic Deadline Engine | **Completed** | 142/142 automated tests passing (804 assertions), deadline engine, recurrence generator & E2E browser tests verified |


---

## 2. Runtime & Environment Audit

- **Operating System:** Windows 10/11 x64 (PowerShell 7 / pwsh)
- **Primary Development PHP Runtime:** PHP 8.4.25 (`C:\php84\php.exe`, ZTS Visual C++ 2022 x64)
- **Preserved Legacy Runtime:** PHP 8.2.12 (`C:\xampp\php`, completely untouched and unmodified)
- **Verified PHP 8.4 Extensions:** `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `pgsql`, `pdo_sqlite`, `sqlite3`, `zip`, `Zend OPcache`
- **Composer Executable:** Composer 2.10.2 via PHP 8.4 (`C:\php84\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar`)
- **Database Engine:** PostgreSQL 16 (Port 5433, database `zimcomply` active and fully migrated) & SQLite (in-memory for automated testing)
- **Frontend Stack:** React 18, TypeScript, Inertia.js (v3.5), Tailwind CSS v4, Lucide Icons, Vite 7
- **AI SDK:** Official `laravel/ai` (v1.3.0) with provider configuration in `config/ai.php`

---

## 3. Milestone 2.2 Deliverables (Deterministic Registration Engine)

1. **Domain Services (`app/Domains/RegistrationEngine/Services`):**
   - `RuleConditionEvaluator`:
     - Safe deterministic condition matching engine without `eval()` or dynamic SQL.
     - Supports validated operators: `equals`, `not_equals`, `greater_than`, `greater_than_or_equal`, `less_than`, `less_than_or_equal`, `in`, `contains`, `all` (AND), `any` (OR).
     - Missing or `null` profile fields flag explicit `unresolved_fields` and return `REQUIRES_INFORMATION`.
     - Detects contradictory inputs (e.g., 0 employees with wage payments declared) and returns `REQUIRES_INFORMATION`.
     - Resolves dynamic rule version parameters (e.g. `compulsory_turnover_threshold_usd`).
   - `DependencyGraph`:
     - Directed Acyclic Graph (DAG) using Kahn's topological sorting algorithm.
     - Deterministic tie-breaking prioritizing statutory classifications (`MANDATORY_STATUTORY` -> `CONDITIONAL_STATUTORY` -> `CBA` -> `PROFESSIONAL` -> `TENDER` -> `OPTIONAL`), then alphabetical by obligation code.
     - Traces and detects circular dependencies, throwing `CircularDependencyException`.
     - Validates prerequisites against known catalog, throwing `MissingPrerequisiteException`.
     - Distinguishes hard legal prerequisites (`HARD_LEGAL`) from procedural recommendations (`PROCEDURAL_RECOMMENDATION`).
   - `ApplicabilityEvaluator`:
     - Resolves approved, verified, effective-dated `RuleVersion` records valid on `$assessmentDate`.
     - Excludes draft, unreviewed, unverified, superseded, or out-of-date rules with `UNVERIFIED_HOLD`.
     - Evaluates statutory exemptions (diplomatic missions, religious/charitable bodies, educational institutions) returning `EXEMPT`.
     - Preserves user-declared existing registrations without erroneously marking them `COMPLIANT`.
     - Asserts zero AI calls during deterministic evaluation.
   - `RegistrationRoadmapGenerator`:
     - Orchestrates applicability evaluation, topological sorting, and response formatting.
     - Handles tenant-scoped transaction persistence into `applicability_assessments` and `assessment_results`.
     - Enforces safe idempotency via SHA-256 evaluation fingerprints or provided keys, returning `is_idempotent_replay: true`.
     - Guarantees historical assessment immutability (historical rows are never updated or deleted).

2. **Domain Exceptions (`app/Domains/RegistrationEngine/Exceptions`):**
   - `CircularDependencyException`: Captures the exact cycle path (`A -> B -> C -> A`).
   - `MissingPrerequisiteException`: Identifies missing prerequisite code and calling obligation.
   - `InvalidConditionException`: Rejects malformed condition nodes and unsupported operators.
   - `UnresolvedInputException`: Surfaces contradictory profile inputs requiring resolution.

3. **Data Transfer Objects (`app/Domains/RegistrationEngine/DTOs`):**
   - `ApplicabilityDecision`: Type-safe value object encapsulating obligation, exact rule version, decision, evaluated conditions, trigger reasons, source citations, and declared registration details.

4. **Milestone 2.3 & 2.5 Deliverables (Questionnaire Persistence & Registration Workspace UI):**
   - `QuestionnaireProfileMapper`:
     - Standardized translation of 5-step questionnaire responses into normalized business profile attributes.
     - Robust sanitation of contradictions (zero employees vs declared payroll) and null handling.
   - `RegistrationController`:
     - `index`: Loads questionnaire draft, active roadmap, audit history, sectors, and jurisdictions.
     - `saveDraft`: Persists partial questionnaire progress with tenant-scoped isolation.
     - `submit`: Validates required fields, runs deterministic roadmap engine, creates immutable assessment snapshot.
     - `reassess`: Evaluates rule changes against profile, persisting auditable historical assessment snapshots.
     - `showAssessment`: Historical snapshot viewer.
   - `Registration Workspace UI (`resources/js/Pages/Register/Index.tsx`)`:
     - 5-step wizard (Business Identity, Operations & Premises, Workforce & Payroll, Revenue & Tax, Existing Registrations).
     - Save draft & resume functionality with optimistic state management.
     - Tabbed interactive roadmap: Prerequisite Sequence (topological order), All Obligations, Excluded & Exempt, and Historical Audit Trail.
     - Real-time estimated statutory fees and evidence status badges.

---

## 4. Milestone 2.4 Deliverables (Secure Document Vault, AI Extraction & Human Verification)

1. **Storage Infrastructure & Security Safeguards:**
   - Dedicated private filesystem disk (`config/filesystems.php`) pointing to `storage_path('app/private')`. Sensitive documents are strictly excluded from the public disk and are never exposed via public URLs.
   - Randomized internal storage identifiers: `tenants/{tenant_id}/documents/{uuid}.{ext}`.
   - Strict content validation: Magic bytes inspection via `finfo_file`, matching declared extensions (`pdf`, `jpg`, `jpeg`, `png`), and size limit checks (<= 10MB).
   - SHA-256 cryptographic hashing calculated from uploaded byte stream to guarantee evidence immutability.
   - Safe streaming preview and download endpoints with tenant-scoped ownership validation, preventing path traversal and cross-tenant leakage.

2. **Database Schema Enhancements:**
   - Additive migration `2026_11_02_100001_add_metadata_columns_to_compliance_documents_table.php` added:
     - `uploaded_by_user_id` (foreign key to `users`)
     - `processing_status` (`PENDING`, `PROCESSING`, `EXTRACTED`, `FAILED`, `UNAVAILABLE`)
     - `extracted_metadata` (jsonb/json for flexible structured output storage)
     - `expiry_date` & `issue_date` (nullable dates)
     - `issuing_authority` (string)
     - Compound index `comp_docs_tenant_proc_idx` (`tenant_id`, `processing_status`)

3. **AI Extraction Pipeline & Governance:**
   - Integrated with existing `RegistrationDocumentExtractorAgent` using official Laravel AI SDK (`laravel/ai` v1.3.0).
   - Data privacy sanitization (`DataPrivacySanitizer`) applied to document payloads before sending to AI providers.
   - Extracted fields: Registered company name, registration/reference number, issuing authority, document type, issue date, expiry date, registered address, taxpayer identifier, and director list.
   - Provenance retained in `ai_verification_items.metadata` with extraction timestamp and agent identifier.
   - Tenant-scoped usage metering and rate limiting enforced via `AiUsageLedger`.
   - Asynchronous execution via `ExtractDocumentJob` queue worker with automatic retry handling and tenant context restoration.

4. **Human Verification Workflow (HITL):**
   - Mandatory human-in-the-loop verification gate via `ai_verification_items`.
   - Strict reviewer authorization: only users with `tenant_admin` or `compliance_officer` roles can approve or reject evidence (`ComplianceDocumentService::authorizeReviewer`).
   - Audit trail recorded in `audit_events` on verification, rejection, and obligation linking.
   - Document rejection requires an explicit, mandatory rejection reason.
   - Extracted values can be corrected inline by authorised reviewers prior to approval.

5. **Evidence-to-Obligation Linking & Statutory Compliance Safeguard:**
   - Documents can be linked and unlinked from `ObligationInstance` records.
   - Crucial Statutory Safeguard (Section 7): Linking verified evidence updates `obligation_instances.evidence_status` to `'DOCUMENT_SUPPORTED'` without automatically marking the obligation `'COMPLIANT'`. Formal compliance remains governed by deterministic rules and official regulatory processes.

6. **Interactive Enterprise Workspace (`resources/js/Pages/Documents/Index.tsx`):**
   - Category filtering (Certificate of Incorporation, CR6, CR14/CR5, ZIMRA ITF263, NSSA, Municipal Licences, Other).
   - Search by filename or document title with status filtering pills.
   - One-click SHA-256 hash copying, inline secure PDF/image preview modal, and authorized download.
   - Interactive AI extraction inspection drawer with confidence metrics and side-by-side field correction.
   - Rejection modal with required reason prompt.
   - Obligation linking modal showing all active tenant obligations and current evidence status.

---

## 5. Test Verification Ledger (91/91 Passing, 465 Assertions)

Executed using `C:\php84\php.exe artisan test`:

```
PASS  Tests\Unit\ExampleTest
✓ that true is true

PASS  Tests\Feature\Ai\AiServiceIntegrationTest
✓ ai roadmap agent generates structured explanation with metered usage

PASS  Tests\Feature\Ai\DataPrivacySanitizerTest
✓ sanitizer masks zimbabwe national id numbers
✓ sanitizer masks api keys and passwords
✓ sanitizer cleans nested payloads

PASS  Tests\Feature\Ai\StructuredDocumentExtractionTest
✓ document extraction creates pending verification item and human approves
✓ document extraction can be rejected by human verifier

PASS  Tests\Feature\Ai\TenantAiIsolationTest
✓ tenant cannot see another tenants ai usage ledgers
✓ tenant cannot approve another tenants verification item
✓ ai request aborts when tenant exceeds monthly quota

PASS  Tests\Feature\AuthenticationTest
✓ login screen renders successfully
✓ user can login with valid credentials
✓ user cannot login with invalid credentials
✓ new sme registration initializes tenant hierarchy

PASS  Tests\Feature\Documents\DocumentVaultAndExtractionTest
✓ document upload succeeds with sha256 integrity and private storage
✓ invalid mime type rejected
✓ spoofed file extension rejected
✓ oversized file rejected
✓ authorized user can download and preview document
✓ cross tenant document download rejected
✓ cross tenant document preview rejected
✓ cross tenant extraction rejected
✓ uploader ownership and metadata persisted
✓ document categorization works correctly
✓ ai extraction succeeds with mocked agent and creates verification item
✓ ai extraction gracefully handles missing fields
✓ ai extraction fails gracefully on unreadable document
✓ non reviewer cannot verify document
✓ authorized reviewer can correct and approve document
✓ reviewer can reject document with mandatory reason
✓ evidence links to obligation without granting compliant status
✓ audit trail created for all document events

PASS  Tests\Feature\ExampleTest
✓ public homepage returns successful response
✓ guest accessing dashboard is redirected to login
✓ login page returns successful response

PASS  Tests\Feature\LandingPageTest
✓ public homepage is accessible to guests
✓ public homepage does not expose tenant sensitive data
✓ authenticated user accessing landing page receives auth user prop
✓ unauthenticated user accessing dashboard is redirected to login
✓ login and registration routes remain operational for guests
✓ authenticated user visiting login or register is redirected to dashboard

PASS  Tests\Feature\NavigationWorkspacesTest
✓ guest is redirected to login on protected routes
✓ authenticated user can access all core navigation workspaces

PASS  Tests\Feature\Registration\ApplicabilityEvaluatorTest
✓ evaluates mandatory statutory obligations for new business
✓ evaluates compulsory vat when turnover exceeds statutory threshold
✓ evaluates voluntary vat when declared under threshold
✓ evaluates statutory exemption diplomatic or religious
✓ unverified or draft rule versions yield unverified hold
✓ historical effective date selection
✓ missing required inputs yield requires information
✓ contradictory inputs yield requires information
✓ declared existing registration does not label compliant
✓ zero ai api calls made during rule evaluation

PASS  Tests\Feature\Registration\DependencyGraphTest
✓ topological sort returns deterministic sequence
✓ detects circular dependencies and throws exception
✓ detects missing prerequisite definition and throws exception
✓ distinguishes hard legal prerequisites from procedural recommendations
✓ deterministic ordering is repeatable

PASS  Tests\Feature\Registration\RegistrationDiscoveryAndAssessmentTest
✓ authenticated user can view registration workspace
✓ save draft persists incomplete questionnaire progress
✓ user can resume saved questionnaire draft
✓ submission validates required business profile fields
✓ submission executes deterministic engine and generates roadmap
✓ user declared registration does not confer compliant status
✓ reassessment creates new assessment snapshot and preserves history
✓ concurrent submissions are safeguarded
✓ cross tenant access is strictly prevented
✓ scenario 1 harare clothing retailer
✓ scenario 2 engineering and fabrication company
✓ scenario 3 multi branch computer accessories retailer

PASS  Tests\Feature\Registration\RegistrationRoadmapGeneratorTest
✓ generates and persists auditable registration roadmap
✓ enforces tenant isolation in roadmap generation
✓ cannot bind questionnaire from another tenant
✓ idempotent repeated requests return existing assessment
✓ historical assessment records are immutable
✓ zero ai api calls made during roadmap generation

PASS  Tests\Feature\Registration\RegulatoryCatalogTest
✓ regulatory authorities and categories are properly distinguished
✓ regulatory sources enforce verification status
✓ obligation definitions distinguish legal classifications
✓ rule version governance enforces dual key approval and superseding
✓ assessment results link exact rule versions and preserve tenant isolation

PASS  Tests\Feature\Registration\RuleConditionEvaluatorTest
✓ evaluates equality and inequality operators
✓ evaluates numeric comparisons
✓ evaluates in and contains operators
✓ evaluates all and any logical grouping
✓ missing required profile field produces requires information
✓ detects contradictory inputs and returns requires information
✓ resolves parameter from rule version
✓ throws exception on unsupported operator

PASS  Tests\Feature\TenantIsolationTest
✓ tenant scope prevents cross tenant data access
✓ branch creation automatically injects active tenant id

PASS  Tests\Feature\Documents\EvidenceLifecycleAndRemediationTest
✓ concurrent and simultaneous extraction requests handled idempotently
✓ repeated extraction after completed extraction returns existing unless forced
✓ extraction failure and retry tracking
✓ valid evidence retained after reassessment
✓ expired evidence excluded after reassessment
✓ same tenant cross organisation extraction and access denied
✓ rejected evidence remaining after valid evidence is unlinked
✓ multiple linked documents with mixed validity
✓ scheduled evidence expiry command processing
✓ queue worker processing and dispatch
✓ historical audit events preserved across lifecycle events
✓ verification never automatically declares statutory compliance

Tests:    103 passed (513 assertions)
Duration: 9.08s
```

---

## 6. Milestone 2.6 Security, Data Integrity and Evidence Lifecycle Remediation

Following the Phase 2 Acceptance Audit, all five High and Medium findings plus the Low finding have been remediated:

### 1. Durable AI Extraction Idempotency (HIGH)
- **Root Cause:** Repeated extraction triggers spawned redundant AI calls and generated orphan `AiVerificationItem` records.
- **Remediation:**
  - Implemented database row locking (`lockForUpdate()`) and cache locking (`Cache::lock("extract_lock_{$doc->id}", 60)`).
  - Extended `AiOrchestrationService::extractRegistrationDocument` with optional `existingItemId` parameter to update pending proposals in-place without generating orphan items.
  - Preserved completed/historical human reviewer decisions; approved/verified records are never overwritten silently.
  - Authorized deliberate re-extraction via explicit `force: true` parameter.

### 2. Evidence Preservation During Reassessment (HIGH)
- **Root Cause:** `RegistrationRoadmapGenerator::syncObligationInstance` previously hardcoded `$evidenceStatus = 'UNKNOWN'` during reassessments.
- **Remediation:**
  - Introduced `App\Domains\RegistrationEngine\Services\ObligationEvidenceStatusEvaluator` as the single authoritative evidence evaluator.
  - Injected evaluator into `RegistrationRoadmapGenerator::syncObligationInstance` to preserve existing valid evidence.
  - Eligible documents must match tenant and organisation, be `VERIFIED`, non-expired, non-rejected, and non-superseded.
  - Guaranteed statutory status is never automatically elevated to `COMPLIANT`.

### 3. Background AI Extraction Queue Worker (MEDIUM)
- **Root Cause:** Web controllers previously invoked external AI models synchronously, risking HTTP timeouts.
- **Remediation:**
  - Updated `DocumentVaultController::extract` and `ComplianceDocumentService::upload` (when `auto_extract: true`) to dispatch `ExtractDocumentJob` through Laravel queues.
  - Persisted processing states: `QUEUED`, `PROCESSING`, `EXTRACTED`, `FAILED`.
  - Configured retry limits (`$tries = 3`), backoff (`$backoff = 10`), and timeout (`$timeout = 60`).
  - Stored sanitized failure reasons in `compliance_documents.last_extraction_error` and tracked `extraction_attempts`.
  - **Local Queue Worker Command:** `C:\php84\php.exe artisan queue:work --tries=3 --timeout=90`.

### 4. Strict Organisation-Level Authorization (MEDIUM)
- **Root Cause:** Checks previously permitted same-tenant cross-organisation access if a user belonged to the tenant.
- **Remediation:**
  - Enforced strict organisation-level access checks on `upload`, `list`, `preview`, `download`, `extract`, `verify`, `reject`, `link`, and `unlink`.
  - Revalidated organisation membership inside queued jobs (`ExtractDocumentJob`).
  - Added test coverage ensuring same-tenant cross-organisation requests receive HTTP 403 Forbidden.

### 5. Document Evidence Lifecycle & Expiry (MEDIUM)
- **Root Cause:** Document validity lacked lifecycle transitions (`EXPIRED`, `SUPERSEDED`, `REVOKED`) and expiry did not update linked obligations.
- **Remediation:**
  - Introduced full verification state machine: `PENDING`, `VERIFIED`, `REJECTED`, `EXPIRED`, `SUPERSEDED`, `REVOKED`.
  - Created scheduled console command: `compliance:evaluate-evidence-lifecycle {--date=}` registered daily in `routes/console.php`.
  - Transitions expired documents and idempotently re-evaluates linked obligations to `EXPIRED_EVIDENCE`.
  - **Local Scheduler Command:** `C:\php84\php.exe artisan schedule:run` or manual run `C:\php84\php.exe artisan compliance:evaluate-evidence-lifecycle`.

### 6. Configurable Malware Scanning Abstraction (LOW)
- **Remediation:**
  - Defined `App\Shared\Contracts\MalwareScannerInterface` and `App\Shared\DTOs\MalwareScanResult`.
  - Implemented `NullMalwareScanner` returning `SKIPPED` (offline vault policy) without claiming unverified scans.
  - Registered binding in `AppServiceProvider`.

### 7. Frontend Documents Workspace
- Added 5-card metric strip (`Total Vault Evidence`, `Human Verified`, `Pending Review`, `Action Required`, `Expired Evidence`).
- Updated badges to support `QUEUED` (purple), `EXPIRED` (rose), and `SUPERSEDED` (slate).
- Added double-click protection and loading spinners during extraction queueing.
- Displayed expiry and error alerts with manual retry/re-extract actions.

---

## 7. Milestone 3.1 Deliverables (Ongoing Compliance Obligation Register & Lifecycle Engine)

1. **Additive Database Migrations & Models:**
   - Migration `2026_11_02_150001_add_ongoing_lifecycle_to_obligation_instances.php`:
     - Extended `obligation_instances` with `assigned_to_user_id`, `compliance_frequency`, `next_due_date`, `last_reviewed_at`, `last_reviewed_by_user_id`, `internal_notes`, and `lock_version` (optimistic concurrency versioning).
     - Created `obligation_activity_logs` table recording all lifecycle transitions, ownership reassignments, schedule changes, and audit notes with tenant and organisation isolation.
   - Updated `ObligationInstance` model with `assignedTo()`, `lastReviewedBy()`, and `activityLogs()` relationships, date casts, and status helper methods.
   - Added `ObligationActivityLog` model in `App\Domains\RegistrationEngine\Models`.

2. **Compliance Lifecycle State Machine (`App\Domains\ComplianceEngine\Services\ComplianceLifecycleStateMachine`):**
   - Controlled state machine with strict statuses:
     - `NOT_STARTED`
     - `IN_PROGRESS`
     - `AWAITING_EVIDENCE`
     - `UNDER_REVIEW`
     - `COMPLETED_INTERNAL`
     - `VERIFIED_COMPLETE`
     - `BLOCKED`
     - `NOT_APPLICABLE`
   - **Dual-Control Preconditions & Safeguards:**
     - `COMPLETED_INTERNAL`: Records that an internal user or admin finished compiling return lines, computations, or declarations. Internal task completion does NOT confer official statutory compliance.
     - `VERIFIED_COMPLETE`: Only allowed if:
       1) The actor is an Organisation Admin or Owner (`isOwnerOrAdmin()`).
       2) The obligation has valid, independently verified, non-expired supporting evidence from the document vault (`evidence_status === 'DOCUMENT_SUPPORTED'` with a non-expired `VERIFIED` document, or `VERIFIED_BY_ISSUER`).
       3) Unverified, missing, expired, or rejected evidence rejects `VERIFIED_COMPLETE` with `InvalidLifecycleTransitionException`.
     - `BLOCKED`: Requires a mandatory justification note detailing the blocking reason.
     - `NOT_APPLICABLE`: Permitted only if the assessment decision is `EXEMPT` / `NOT_APPLICABLE`, or by an admin with a documented legal justification note.

3. **Compliance Register Service (`App\Domains\ComplianceEngine\Services\ComplianceObligationRegisterService`):**
   - Multi-dimensional filtering by Regulatory Authority, Category, Lifecycle Status, Responsible Owner, and free-text search.
   - Real-time summary metrics calculation (`total`, `not_started`, `in_progress`, `awaiting_evidence`, `under_review`, `completed_internal`, `verified_complete`, `blocked`, `not_applicable`, `overdue`).
   - Tenant-scoped database transactions with pessimistic row locking (`lockForUpdate()`) and optimistic concurrency conflict detection (`lock_version`).
   - Integrated with `ObligationEvidenceStatusEvaluator` for authoritative derivation of document support.
   - Audit event logging into both `obligation_activity_logs` and enterprise `audit_events`.

4. **Compliance Controller & Endpoints (`App\Http\Controllers\ComplianceRegisterController`):**
   - `GET /comply`: Renders Inertia `Comply/Index` workspace with real organization obligations and assignable members.
   - `GET /compliance/obligations/{id}`: Returns JSON detail payload with linked documents, available transitions, and full audit timeline.
   - `POST /compliance/obligations/{id}/assign`: Handles assignment/reassignment with membership verification and concurrency protection.
   - `POST /compliance/obligations/{id}/transition`: Validates and transitions status according to state machine rules.
   - `POST /compliance/obligations/{id}/schedule`: Updates due dates and compliance frequencies.

5. **Compliance Workspace UI (`resources/js/Pages/Comply/Index.tsx`):**
   - Enterprise multi-card metrics strip displaying live obligation counts.
   - Dynamic filter toolbar with instant search, authority filter, classification filter, status filter, and owner filter.
   - Full obligations register data table with status badges distinguishing internal task completion from verified statutory compliance.
   - Interactive slide-over detail drawer displaying:
     - Authority, category, and citation badges.
     - Dual-control statutory guidance banner.
     - Owner assignment select form with instruction notes.
     - Lifecycle transition action buttons with precondition feedback.
     - Linked evidence section with verification status badges and direct Document Vault links.
     - Statutory legal basis and rule version provenance.
     - Chronological activity timeline and version lock indicator.
   - Fully responsive on Desktop, Tablet, and Mobile viewports.

6. **Automated Test Suite (`tests/Feature/Compliance/ComplianceObligationRegisterTest.php`):**
   - 15 comprehensive feature tests (140 assertions) verifying:
     - Register generation from approved rule versions.
     - Authority, category, status, and owner filtering.
     - Unverified rule version governance (`UNVERIFIED_HOLD` maintained).
     - Assignment authorization and cross-organisation assignment rejection.
     - Cross-tenant and same-tenant cross-organisation isolation.
     - Valid status transition sequences.
     - Prevention of unverified statutory completion declarations.
     - Dual-control non-admin verification restrictions.
     - Mandatory notes on blocked transitions.
     - Optimistic locking and concurrent update detection.
     - Preservation of activity logs and audit trails.
     - Obligation detail endpoint payload structure.
   - Full suite passes: **118 tests, 653 assertions** (100% passing).

---

## 8. Milestone 3.2 Deliverables (Statutory Filing Calendar & Deterministic Deadline Engine)

1. **Additive Database Migrations & Models:**
   - Migration `2026_11_03_090001_create_filing_occurrences_tables.php`:
     - Created `filing_occurrences` table storing tenant ID, organisation ID, parent `obligation_instance_id`, `obligation_definition_id`, `rule_version_id`, `schedule_identity` (`RETURN`, `PAYMENT`), `filing_frequency`, `filing_period_start`, `filing_period_end`, `filing_period_label`, `original_statutory_due_date`, `current_due_date`, `payment_due_date`, `deadline_calculation_basis` (JSON), `timezone` (`Africa/Harare`), `assigned_to_user_id`, `workflow_status`, `official_submission_status`, `submission_reference`, `official_receipt_number`, `internally_approved_at`, `submitted_at`, `acknowledged_at`, `closed_at`, `evidence_status`, `source_citation`, `verification_status`, and `lock_version`.
     - Enforced composite unique index `filing_occ_org_inst_period_sched_uniq` on `(organisation_id, obligation_instance_id, filing_period_start, filing_period_end, schedule_identity)`.
     - Created `filing_occurrence_activity_logs` table for immutable occurrence-level audit history.
     - Added nullable `filing_occurrence_id` foreign key on `compliance_documents` to bind supporting evidence to a specific filing period.

2. **Deterministic Deadline Calculator (`App\Domains\ComplianceEngine\Services\FilingDeadlineCalculator`):**
   - Supports verified schedule patterns: `MONTHLY`, `QUARTERLY`, `ANNUAL`, `FIXED_DATE`, and `EVENT_DRIVEN`.
   - Handles calendar month boundary clamping (e.g. `due_day: 31` in 30-day months or February), leap years (`2028-02-29`), year-end transitions (December 2026 due in January 2027), `Africa/Harare` timezone, mid-year rule amendments, and filing vs payment period distinctions.
   - Weekend and public holiday adjustments (`NEXT_BUSINESS_DAY`, `PREVIOUS_BUSINESS_DAY`) are applied **only** when explicitly authorised in the approved `RuleVersion` schedule parameters (`weekend_adjustment`).
   - **Rule-Governance Safeguard:** If an obligation lacks an active, `VERIFIED_OFFICIAL` rule version or explicit schedule definition, the occurrence is marked `UNVERIFIED_HOLD` or `DEADLINE_UNVERIFIED` with `current_due_date = null`. Statutory deadlines are never invented from memory.

3. **Recurrence Generator (`App\Domains\ComplianceEngine\Services\FilingScheduleGenerator`):**
   - Generates filing occurrences across a configurable future horizon (default 12 months).
   - Enforces tenant and organisation isolation, cache-locked concurrency protection, and transactional persistence.
   - Preserves historical immutability: completed (`SUBMITTED`, `ACKNOWLEDGED`, `CLOSED`), in-progress, or past filing occurrences are never overwritten when rules change.
   - Safely updates future `NOT_STARTED` occurrences when a newly approved `RuleVersion` takes effect, while preserving `original_statutory_due_date`.
   - Automatically closes future `NOT_STARTED` occurrences if the parent obligation becomes `NOT_APPLICABLE` or `EXEMPT`, while preserving completed historical filings.
   - Queued job `GenerateFilingScheduleJob` and console command `compliance:generate-filing-schedules` registered in `routes/console.php`.

4. **Filing Lifecycle & Dynamic Display Classification (`FilingLifecycleStateMachine` & `FilingDisplayClassifier`):**
   - Filing-specific internal workflow states: `NOT_STARTED`, `PREPARING`, `READY_FOR_REVIEW`, `INTERNALLY_APPROVED`, `SUBMITTED`, `ACKNOWLEDGED`, `REJECTED`, `CLOSED`.
   - Separate official submission statuses: `NOT_SUBMITTED`, `DECLARED_SUBMITTED`, `OFFICIALLY_ACKNOWLEDGED`, `OFFICIALLY_REJECTED`, `WAIVED_OR_EXEMPT`.
   - Entering a user-supplied `submission_reference` transitions to `SUBMITTED` / `DECLARED_SUBMITTED` only — it never automatically grants `OFFICIALLY_ACKNOWLEDGED`.
   - `ACKNOWLEDGED` requires organisation admin/compliance officer privileges AND a human-`VERIFIED`, non-expired document linked to that specific `filing_occurrence_id`.
   - Dynamic operational classifications (`UPCOMING`, `DUE_SOON`, `DUE_TODAY`, `OVERDUE`, `COMPLETED`, `DEADLINE_UNVERIFIED`) never classify an unverified due date as `OVERDUE`.

5. **Compliance Calendar Workspace UI (`resources/js/Pages/Comply/Calendar.tsx`):**
   - Integrated at `/comply/calendar` and `/calendar` with Monthly Calendar Grid, Filing List View, Upcoming Deadlines View, Overdue Filings View, multi-dimensional filters, and clickable Filing Detail Drawer.
   - Displays source citations, rule version governance, deterministic due-date calculation explanations, owner assignment, internal workflow transitions, period-specific evidence linking, and immutable audit history.

6. **Automated Test Suite (`tests/Feature/Compliance/StatutoryFilingCalendarTest.php`):**
   - 24 comprehensive feature tests covering all Milestone 3.2 requirements using clearly labelled `SYNTHETIC_FIXTURE` test rules.
   - Full suite passes: **142 tests, 804 assertions** (100% passing).

---

## 9. Regulatory Assumptions Requiring Legal Verification

1. **VAT Compulsory Threshold:**
   - Seeded parameter: `USD 25,000.00` per annum under Value Added Tax Act [Chapter 23:12], Section 23.
   - *Assumed note:* Historical threshold gazetted in statutory instruments; subject to multi-currency fluctuations (ZiG vs USD) and Finance Act amendments.
2. **NSSA POBS & APWCS Compulsory Employer Threshold:**
   - Seeded parameter: `1 employee` within 30 days of first hire under SI 393/1993 and SI 68/1990.
3. **ZIMDEF Manpower Training Levy:**
   - Seeded parameter: `1% gross monthly wage bill` on commercial employers with statutory exemptions for diplomatic, religious, and non-commercial educational entities under Chapter 28:02.
4. **ITF263 Tax Clearance:**
   - Seeded parameter: Requires clean tax record across Income Tax, PAYE, and VAT under Income Tax Act [Chapter 23:06], Section 80.
5. **PRAZ Annual Supplier Registration:**
   - Seeded parameter: Mandatory calendar-year supplier registration for public procurement tenders under Chapter 22:23.
6. **NEC and Local Authority Coverage:**
   - Municipal operating licences (Harare, Bulawayo) and sectoral NEC CBAs (Engineering SI 152/2017, Commercial SI 135/2011) currently lack published parameter rule packs in the seeder; the engine correctly places them on `UNVERIFIED_HOLD` until rule packs are legally audited and approved.

