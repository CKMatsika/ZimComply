# ZimComply AI — Security, Governance & Data Protection Specification

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative

---

## 1. Security Architecture & Threat Model

ZimComply AI manages sensitive corporate, financial, and personal data, including Tax Identification Numbers (TINs), payroll remuneration schedules, national identity numbers, director records, and proprietary procurement strategies. The security architecture implements defense-in-depth across five distinct tiers:

```
+-------------------------------------------------------------------+
| 1. PERIMETER & NETWORK: TLS 1.3, CSP, HSTS, WAF, IP Rate Limiting |
+-------------------------------------------------------------------+
                                  |
+-------------------------------------------------------------------+
| 2. IDENTITY & ACCESS: Sanctum Sessions, TOTP MFA, Granular RBAC   |
+-------------------------------------------------------------------+
                                  |
+-------------------------------------------------------------------+
| 3. APPLICATION & LOGIC: TenantScope, Laravel Policies, Segregation|
+-------------------------------------------------------------------+
                                  |
+-------------------------------------------------------------------+
| 4. DATA AT REST & TRANSIT: AES-256-GCM Field Encryption, KMS      |
+-------------------------------------------------------------------+
                                  |
+-------------------------------------------------------------------+
| 5. FORENSICS & AUDITING: Immutable Append-Only Audit Trail Ledger |
+-------------------------------------------------------------------+
```

---

## 2. Multi-Tenant Data Isolation Strategy

1. **Logical Separation via PostgreSQL Scopes:**
   - Every tenant-scoped database table includes an indexed `tenant_id` foreign key referencing `tenants.id`.
   - Global Eloquent Scope (`TenantScope`) automatically applies `WHERE tenant_id = ?` to all `SELECT`, `UPDATE`, and `DELETE` statements at the ORM layer.
2. **PostgreSQL Row-Level Security (RLS) Defense-in-Depth:**
   - For direct database queries or raw SQL operations, PostgreSQL RLS policies enforce `tenant_id = current_setting('app.current_tenant_id')`.
3. **Automated Cross-Tenant Isolation Test Suite:**
   - Automated CI tests explicitly simulate Tenant A attempting to access Tenant B's documents, employees, vehicles, and filings by ID, asserting immediate `403 Forbidden` or `404 Not Found` responses.

---

## 3. Identity, Authentication & MFA

1. **Session & Token Management:**
   - Stateful web authentication utilizes encrypted, HTTP-only, SameSite=Lax cookies via Laravel Sanctum.
   - Session lifetime is capped at 120 minutes of inactivity; privileged financial sessions require re-authentication after 15 minutes.
2. **Multi-Factor Authentication (MFA):**
   - Mandatory TOTP (Time-based One-Time Password) MFA via Google Authenticator or Microsoft Authenticator for the following roles:
     - `ADMIN`
     - `ACCOUNTANT`
     - `COMPLIANCE_OFFICER`
     - `APPROVER`
   - Encrypted backup recovery codes generated upon initial enrollment.
3. **Password Security:**
   - Passwords hashed using Argon2id or Bcrypt with a work factor of 12.
   - Enforced minimum length of 12 characters, requiring uppercase, lowercase, numbers, and symbols. Checked against compromised password databases.

---

## 4. Field-Level Encryption & Cryptography

Personally Identifiable Information (PII) and sensitive corporate secrets are encrypted at rest using AES-256-GCM authenticated encryption:

| Entity | Field | Encryption Standard | Rationale |
|---|---|---|---|
| `organisations` | `tin` | AES-256-GCM | Prevents disclosure of Tax Identification Number in raw DB dumps |
| `employees` | `national_id` | AES-256-GCM | Statutory personal identifier protection |
| `employees` | `nssa_number` | AES-256-GCM | Social security identifier protection |
| `users` | `mfa_secret` | AES-256-GCM | Prevents credential theft from database leaks |
| `integration_connections` | `api_keys_encrypted` | AES-256-GCM | Protects third-party ERP access credentials |

*Key Management:* Encryption keys are managed via environment variables (`APP_KEY`) with support for external KMS (AWS KMS or HashiCorp Vault) in enterprise deployments.

---

## 5. Document Vault & Object Storage Security

1. **Private Buckets:**
   - All uploaded PDFs, scans, and spreadsheets reside in private object storage (S3 / MinIO). Public bucket access is disabled at the bucket policy level.
2. **Time-Limited Presigned URLs:**
   - File downloads and previews are served exclusively through temporary, cryptographically signed URLs with a maximum lifespan of 15 minutes.
3. **Malware & Antivirus Pipeline:**
   - Uploaded files pass through an asynchronous ClamAV virus scanner before being marked as clean in `document_versions`. Any infected file is quarantined immediately.
4. **Content-Type & Extension Sanitization:**
   - Files are validated against strict MIME-type allowlists (PDF, PNG, JPEG, XLSX, CSV). File contents are validated against magic bytes, not merely client-reported file extensions.

---

## 6. Zimbabwe Cyber & Data Protection Act [Chapter 12:07] Compliance

ZimComply AI implements privacy-by-design to ensure compliance with Zimbabwean data protection regulations:

1. **Lawful Basis & Explicit Consent:**
   - The platform records explicit user consent during onboarding, detailing the lawful statutory basis for processing employee and director data.
2. **Data Minimization:**
   - Medical aid tracking is strictly limited to membership numbers, scheme tiers, and contribution splits. The collection or storage of clinical diagnostic records is strictly forbidden.
3. **Data Subject Rights:**
   - **Right to Access:** Tenants can export their complete compliance archive (documents, payroll runs, licence history) as a structured ZIP/JSON archive at any time.
   - **Right to Rectification:** Users can submit correction requests for any inaccurate identity or employment record.
   - **Retention Policies:** Statutory documents are retained according to Zimbabwean statutory periods (e.g. 6 years under the Companies and Other Business Entities Act and Income Tax Act).

---

## 7. Regulator Credential Protection & Portal Guidelines

1. **Prohibition of Plaintext Password Storage:**
   - The platform strictly forbids storing user passwords for regulator portals (TaRMS, PRAZ, NSSA).
2. **Authorized Integration Standards:**
   - If an official government API becomes available, authentication must use OAuth 2.0 tokens or client-side certificates, stored encrypted in `integration_connections`.
3. **Zero Scraping / Credential Bypass:**
   - The platform does not deploy headless browser scrapers, CAPTCHA bypass scripts, or reverse-engineered private portal APIs. All filings rely on user-uploaded official receipts, pre-formatted upload files, or accredited public endpoints.

---

## 8. Segregation of Duties & Approval Controls

To prevent fraud and unauthorized corporate commitments:

1. **Four-Eyes Principle (Dual Authorization):**
   - A filing or payment record cannot be submitted or marked as paid by the same user who prepared it if segregation of duties is enabled for that organisation.
   - **Preparer:** Compiles the payroll return or renewal application.
   - **Approver:** Authorizes the filing and releases payment evidence.
2. **Immutable Audit Trail:**
   - Every state transition, document verification, permission change, and rule activation records an immutable entry in `audit_events`.
   - Audit records are append-only; `DELETE` and `UPDATE` operations are blocked by database trigger rules.
