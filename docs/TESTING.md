# ZimComply AI — Testing Strategy & Quality Assurance Framework

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative

---

## 1. Testing Philosophy & Standards

To guarantee enterprise compliance and prevent regulatory liability, ZimComply AI employs a zero-regression, multi-layer verification strategy. All code merges require 100% pass rates across unit, integration, tenant-isolation, and statutory-math test suites.

```
+-------------------------------------------------------------+
| TIER 5: Acceptance Scenarios & E2E Workflows (12 Scenarios) |
+-------------------------------------------------------------+
                              |
+-------------------------------------------------------------+
| TIER 4: Tenant Data Isolation & RBAC Security Tests        |
+-------------------------------------------------------------+
                              |
+-------------------------------------------------------------+
| TIER 3: Feature & Integration Workflows (APIs & Actions)    |
+-------------------------------------------------------------+
                              |
+-------------------------------------------------------------+
| TIER 2: Statutory Rule Regressions (PAYE, NSSA, NEC Math)  |
+-------------------------------------------------------------+
                              |
+-------------------------------------------------------------+
| TIER 1: Unit Tests (Domain Math, DTOs, bcmath Precision)   |
+-------------------------------------------------------------+
```

---

## 2. Test Execution Tiers

### 2.1 Tier 1: Unit Testing (PHPUnit / Pest)
- **Scope:** Pure functions, domain value objects, currency arithmetic, and date calculators.
- **Rules:**
  - High-precision monetary math must execute via `bcmath`.
  - Zero database I/O; fast in-memory execution.
- **Example Test Targets:**
  - `CurrencyConverterTest`: Verifies USD and ZiG precision without floating-point drift.
  - `ObligationPrerequisiteGraphTest`: Verifies topological sort and circular dependency detection.

### 2.2 Tier 2: Statutory Rule Regression Benchmarks
- **Scope:** Validates the calculation engine against official ZIMRA, NSSA, and ZIMDEF benchmark matrices.
- **Regression Invariance:**
  - A regression suite with 100+ simulated employee salary profiles across various salary brackets (USD $100 to $10,000 and ZiG equivalents).
  - Asserts exact penny/cent matching against manual official tax schedules.
  - Asserts that creating or updating a future rule pack does not alter historical calculation outputs.

### 2.3 Tier 3: Feature & Integration Tests
- **Scope:** End-to-end HTTP requests, FormRequest validations, Domain Actions, and Database migrations.
- **Execution:** Uses Laravel's built-in HTTP test assertions against PostgreSQL/SQLite test databases.
- **Coverage:**
  - Questionnaire submission producing correct `obligation_instances`.
  - Document upload pipeline creating `document_versions` and dispatching OCR jobs.
  - Payroll CSV import creating `contribution_calculations` with duplicate prevention.

### 2.4 Tier 4: Cross-Tenant Isolation & Security Suite
- **Mandatory Security Requirement:**
  - Every tenant-scoped entity (`Organisation`, `Branch`, `Employee`, `Document`, `Filing`, `Vehicle`) must be tested for cross-tenant leakage.
  - Automated tests authenticate as `Tenant A`, query endpoints using `Tenant B` UUIDs, and assert `403 Forbidden` or `404 Not Found`.
  - Tests verify that global scopes cannot be inadvertently bypassed by unauthenticated requests.

### 2.5 Tier 5: 12 Master Acceptance Scenarios (README Section 10)

| Scenario ID | Test Scenario Description | Expected Verification Outcome |
|---|---|---|
| **SC-01** | Engineering company registration discovery | Receives cited roadmap with ZIMRA, NSSA, NEC Engineering, EMF, ECZ, and City Council. |
| **SC-02** | Construction company contractor associations | CIFOZ/ZBCA classified as tender prerequisites; statutory building permits marked as mandatory law. |
| **SC-03** | Retail SME Excel payroll import | Validates spreadsheet rows, flags anomalies, outputs draft P2 schedule, requires human review. |
| **SC-04** | Document upload with expiry extraction | Ingests ITF263 & Council licence, extracts valid dates into verification queue, schedules alerts. |
| **SC-05** | Multi-branch commercial business | Entity-level tax (ZIMRA) isolated from branch-level municipal licences (Harare vs Bulawayo). |
| **SC-06** | Fleet asset compliance | Vehicle dashboard displays synced status for ZINARA licence, insurance cover, and ZBC radio disc. |
| **SC-07** | PRAZ tender bidding document upload | Parses tender requirements; outputs compliance matrix highlighting missing tax clearance. |
| **SC-08** | Accounting firm 50-client portfolio | Accountant switches client companies seamlessly with zero cross-client data leakage. |
| **SC-09** | Segregation of duties on submission | User without `APPROVER` permission is blocked from submitting official filings or confirming payments. |
| **SC-10** | Historical rule immutability | Activating a new statutory rule does not alter finalized historical calculations or returns. |
| **SC-11** | Idempotent import & job execution | Re-running a payroll import or notification job produces zero duplicate records. |
| **SC-12** | Standalone operational autonomy | Platform operates fully without Smart ERP; future adapter syncs data cleanly via standard DTOs. |

---

## 3. Automated Test Commands

```bash
# Run all unit and feature tests
php artisan test

# Run statutory mathematical regression suite
php artisan test --filter=StatutoryRuleRegressionTest

# Run cross-tenant isolation security suite
php artisan test --filter=TenantIsolationTest

# Run code coverage report
php artisan test --coverage-html=tests/reports/coverage
```
