# ZimComply AI — User Experience & Persona Operational Guide

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative

---

## 1. User Personas & Workspace Views

ZimComply AI provides tailored interfaces optimized for specific organizational roles:

| Persona | Core Goal | Primary Platform View |
|---|---|---|
| **1. New SME Founder** | Starting a company, needs clear roadmap of what to register and where | **Register** (Guided Discovery Questionnaire & Roadmap Wizard) |
| **2. Existing SME Owner** | Wants an immediate compliance gap audit, expiry alerts, and document vault | **Comply & Renew** (Traffic-Light Health Dashboard & Alerts) |
| **3. Accountant / Consultant** | Manages dozens of client businesses with delegated permissions and approvals | **Portfolio Hub** (Multi-Client Switcher & Approval Queue) |
| **4. HR / Payroll Officer** | Monthly wages, NEC employee gradings, NSSA/ZIMDEF/PAYE remittances | **Comply > Payroll** (Payroll Capture, Import & Return Generator) |
| **5. Fleet / Asset Officer** | Fleet roadworthiness, ZINARA discs, insurance policies, radio licence discs | **Renew > Fleet** (Vehicle Registry & Licence Renewal Calendar) |
| **6. Tender Officer** | Qualifying for government tenders, PRAZ supplier registration, bid readiness | **Tender** (PRAZ Category Matrix & Opportunity Analyzer) |
| **7. Regulatory Editor / Admin** | Manages statutory citations, gazetted rates, and verifies evidence documents | **Settings > Regulatory Governance & Verification Queue** |

---

## 2. Platform Navigation Architecture

The interface uses a clean, intuitive, mobile-friendly navigation bar structured around five core operational verbs:

```
[ LOGO ]   Register  |  Comply  |  Renew  |  Tender  |  Documents  |  Reports  |  Settings   [ Company Switcher ] [ User Profile ]
```

- **Register:** Interactive business activity questionnaire, legal applicability assessment, personalized step-by-step registration roadmap, prerequisites, and dossier preparation.
- **Comply:** Ongoing monthly and periodic statutory obligations (ZIMRA PAYE, NSSA POBS/APWCS, ZIMDEF 1% levy, NEC dues, and quarterly QPD tax payments).
- **Renew:** Annual and periodic credential renewals (City Council operating licences, health inspection permits, ZINARA vehicle licences, motor insurance, and fire safety certificates).
- **Tender:** PRAZ e-GP supplier registration categories, public procurement notice discovery, tender bidding document analysis, and missing document checklists.
- **Documents:** The encrypted **Compliance Wallet**, categorizing corporate records, certificates, tax clearances, and bank letters with cryptographic verification indicators.
- **Reports:** Cash-flow liability forecasts, statutory reconciliation schedules, and one-click **Compliance Audit Packs** for banks and procurement entities.
- **Settings:** Company profile, branch/site directory, user invitations, role-based access controls, and subscription management.

---

## 3. Core Workflows

### 3.1 New Business Onboarding & Registration Discovery
1. **Corporate Identity Setup:** Enter company legal name, incorporation type (Private Limited Company, PBC, etc.), and physical headquarters address.
2. **Activity Discovery Questionnaire:**
   - Select primary and secondary industry sectors (e.g. "Mechanical Engineering Fabrication").
   - Specify premises type (Owned, Leased, Industrial Workshop, Commercial Office).
   - Enter estimated employee headcount and planned turnover range.
3. **Roadmap Generation:**
   - The platform calculates applicable obligations, clearly distinguishing **mandatory statutory law**, **collective bargaining agreements**, and **optional association memberships**.
   - Each item includes the issuing regulator, official fee source, processing time, required evidence, and direct links to official forms.

---

### 3.2 Compliance Wallet & Document Verification
1. **Upload:** Drag-and-drop PDF certificates or capture photos via mobile camera.
2. **Automated Parsing:** The system extracts the certificate number, issue date, and expiry date.
3. **Evidence Status Badges:**
   - 🟢 `Verified by Issuer`: Formally cross-referenced or validated by a compliance officer.
   - 🔵 `Document Supported`: Document uploaded and cryptographic hash stored.
   - 🟡 `User Declared`: Information manually typed without an attached document.
   - ⚪ `Unknown / Verification Required`: Pending investigation or official confirmation.

---

### 3.3 Monthly Payroll & Statutory Remittance Flow
1. **Capture Wages:** Either upload your monthly Excel/CSV payroll sheet or enter gross salaries using the quick web capture form.
2. **Deterministic Calculation:** The engine computes exact PAYE, AIDS Levy, NSSA (employee and employer portions), ZIMDEF, and NEC dues for both USD and ZiG currencies.
3. **Four-Eyes Approval:** The HR Officer marks the payroll as `PREPARED`. The Managing Director or Finance Manager reviews and clicks `APPROVE`.
4. **Filing Generation:** Download pre-formatted ZIMRA P2 and NSSA P4 upload schedules.
5. **Receipt Ingestion:** Upload the bank payment confirmation and regulator receipt to close the monthly compliance loop.
