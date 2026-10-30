# ZimComply AI — Regulatory Source Register & Statutory Catalog

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative Baseline with Legal Research Backlog

---

## 1. Statutory Register Principles

1. **No Speculative Rates:** The platform strictly prohibits fabricating tax brackets, levy percentages, penalty calculations, or deadline formulas.
2. **Citation Requirement:** Every active rule pack must cite its enabling Act of Parliament or Statutory Instrument (SI), including the gazette date.
3. **Verification Levels:**
   - **`VERIFIED_OFFICIAL`**: Grounded in an official Government Gazette, gazetted SI, or written ruling issued by the regulator.
   - **`PENDING_VERIFICATION`**: Derived from public notices, circulars, or industry standard practices; requires certified legal sign-off before driving production monetary outputs.
   - **`HYPOTHESIS_BENCHMARK`**: Working baseline models used strictly in non-production test suites to validate calculation algorithms.

---

## 2. Master Authority Catalog

| Code | Regulatory Authority | Legal Jurisdiction | Category | Official Portal / Registry |
|---|---|---|---|---|
| **ZIMRA** | Zimbabwe Revenue Authority | National | Revenue & Taxation | [TaRMS Portal](https://tarms.zimra.co.zw) |
| **NSSA** | National Social Security Authority | National | Social Security & Workers Comp | [Self-Service Portal](https://selfservice.nssa.org.zw) |
| **ZIMDEF** | Zimbabwe Manpower Development Fund | National | Skills Development Levy | [ZIMDEF Portal](https://www.zimdef.org.zw) |
| **PRAZ** | Procurement Regulatory Authority of Zimbabwe | National | Public Procurement & Disposal | [e-GP Portal](https://egp.praz.org.zw) |
| **ZINARA** | Zimbabwe National Road Administration | National | Transport & Vehicle Licensing | [ZINARA Portal](https://www.zinara.co.zw) |
| **ECZ** | Engineering Council of Zimbabwe | National | Professional Engineering Council | [ECZ Portal](https://www.ecz.co.zw) |
| **COH** | City of Harare | Municipal (Harare) | Local Authority / Health Permits | City of Harare Licensing Dept |
| **COB** | City of Bulawayo | Municipal (Bulawayo) | Local Authority / Health Permits | City of Bulawayo Licensing Dept |
| **NEC_ENG** | NEC for Engineering, Iron & Steel Industry | Sectoral | Collective Bargaining & Benefits | NEC Engineering Council |
| **NEC_COMM**| NEC for Commercial Sectors of Zimbabwe | Sectoral | Collective Bargaining & Dues | Commercial NEC Council |
| **ZBC** | Zimbabwe Broadcasting Corporation | National | Broadcasting & Radio Licences | ZBC Licensing Division |

---

## 3. Statutory Instruments & Legal Citations

### 3.1 Corporate & Tax Compliance (ZIMRA)

1. **Income Tax Act [Chapter 23:06]**
   - *Scope:* Mandatory for all incorporated entities (PBCs, Private Limited Companies) and individuals trading in Zimbabwe.
   - *Obligations:* Tax Identification Number (TIN) registration, annual tax return filing (ITF12), Quarterly Payment Dates (QPDs / Provisional Tax - ITF13B).
   - *Status:* `VERIFIED_OFFICIAL`.

2. **ITF263 Tax Clearance Certificate (Section 80 of Income Tax Act)**
   - *Scope:* Withholding Tax Exemption. Without an active ITF263, all commercial payors must withhold 30% from payments made to the entity.
   - *Prerequisites:* All income tax, PAYE, and VAT returns up to date, or an approved payment plan in place with ZIMRA.
   - *Status:* `VERIFIED_OFFICIAL`.

3. **Value Added Tax Act [Chapter 23:12]**
   - *Scope:* Compulsory registration if taxable annual turnover exceeds statutory threshold; voluntary registration permitted subject to criteria.
   - *Obligations:* Monthly VAT return (VAT7) and remittance by the 25th day of the following month.
   - *Status:* `VERIFIED_OFFICIAL`.

4. **Pay As You Earn (PAYE) & AIDS Levy Regulations**
   - *Statute:* Section 73 of Income Tax Act & National AIDS Council of Zimbabwe Act [Chapter 15:14].
   - *Rate Dynamics:* Progressive tax tables gazetted in Finance Acts for USD and ZiG. AIDS Levy fixed at 3% of assessed PAYE.
   - *Filing Deadline:* 10th day of the month following payroll.
   - *Status:* `VERIFIED_OFFICIAL`.

---

### 3.2 Social Security & Employer Levies (NSSA & ZIMDEF)

1. **National Social Security Authority Act [Chapter 17:04]**
   - **Pension and Other Benefits Scheme (POBS) - SI 393 of 1993:**
     - *Obligation:* Compulsory for every employer with 1 or more employees.
     - *Contribution Split:* Equal split between employer and employee (currently 4.5% + 4.5% = 9.0% total up to the gazetted national insurable earnings ceiling).
     - *Ceiling Volatility:* NSSA publishes quarterly insurable earnings ceilings in USD and ZiG based on the Total Consumption Poverty Line (TCPL).
     - *Status:* `VERIFIED_OFFICIAL` (Ceilings dynamically versioned).
   - **Accident Prevention and Workers' Compensation Scheme (APWCS) - SI 68 of 1990:**
     - *Obligation:* 100% employer-funded insurance against occupational hazards.
     - *Rate Determination:* Variable risk-based percentage determined by NSSA based on industry classification code.
     - *Status:* `VERIFIED_OFFICIAL`.

2. **Manpower Planning and Development Act [Chapter 28:02] (ZIMDEF)**
   - *Obligation:* 1% Training Levy on the total gross monthly wage bill of every employer.
   - *Exemptions:* Embassies, diplomatic missions, and verified charitable institutions.
   - *Filing Deadline:* 1st day of the following month (grace period up to 15th).
   - *Status:* `VERIFIED_OFFICIAL`.

---

### 3.3 Public Procurement & Supplier Compliance (PRAZ)

1. **Public Procurement and Disposal of Public Assets Act (PPDPA) [Chapter 22:23]**
   - *Statute:* Section 4 of PPDPA & SI 5 of 2018 (Public Procurement Regulations).
   - *Obligation:* Any entity bidding for public sector contracts or government tenders must register on the PRAZ e-GP portal under the relevant procurement category.
   - *Validity:* Annual calendar year validity; renewable every 12 months.
   - *Status:* `VERIFIED_OFFICIAL`.

2. **Special Procurement Category Classifications**
   - Goods, Works, Consultancy Services, and Non-Consultancy Services.
   - Requires valid ITF263, NSSA clearance, incorporation certificate, and CR6/CR14.
   - *Status:* `VERIFIED_OFFICIAL`.

---

### 3.4 Sectoral Agreements & Collective Bargaining (NECs)

1. **Labour Act [Chapter 28:01] & Registered CBAs**
   - Collective Bargaining Agreements negotiated between registered employer organisations and trade unions, gazetted as Statutory Instruments.
   - *Binding Legal Force:* Once gazetted, a CBA has statutory force and binds all employers and employees within the defined undertaking or industry, regardless of whether they belong to the employer association or union.
   - *Status:* `VERIFIED_OFFICIAL`.

2. **NEC for the Engineering, Iron and Steel Industry**
   - *Scope:* Metal fabrication, structural steelwork, machining, mechanical engineering, and foundry works.
   - *Obligations:* Mandatory grading of employees (Grades 1 through 10 / Artisan / Journeyman), minimum statutory wage scales, NEC administrative council levies (split employer/employee).
   - *Status:* `VERIFIED_OFFICIAL`.

3. **Engineering Medical Fund (Priority Research Case)**
   - *Hypothesis:* Certain collective agreements in the Engineering sector require employer and employee contributions to the Engineering Medical Fund for covered shop-floor grades.
   - *Research Status:* `PENDING_VERIFICATION`. System enforces explicit review before marking non-membership as a statutory violation.

---

### 3.5 Local Authority Licensing (City Councils)

1. **Urban Councils Act [Chapter 29:15] & Shop Licences Act [Chapter 14:17]**
   - *Scope:* Operating premises within municipal boundaries.
   - *Obligations:*
     - Annual Business / Trading Licence.
     - Health & Food Hygiene Inspection Certificate.
     - Fire Safety Clearance Certificate (City Fire Dept).
     - Approved Building / Land Use Town Planning Permit.
   - *City of Harare Specifics:* Annual licence cycles; inspections required upon initial application and renewal.
   - *Status:* `VERIFIED_OFFICIAL`.

---

### 3.6 Transport, Fleet & Broadcasting (ZINARA & ZBC)

1. **Vehicle Registration and Licensing Act [Chapter 13:14] (ZINARA)**
   - *Obligation:* Every motor vehicle operating on public roads must display an active ZINARA vehicle licence disc.
   - *Licence Cycles:* Quarterly, bi-annual, or annual renewals.
   - *Prerequisites:* Valid third-party or comprehensive motor insurance policy and active ZBC Radio Licence (or valid exemption certificate).
   - *Status:* `VERIFIED_OFFICIAL`.

2. **Broadcasting Services Act [Chapter 12:06] (ZBC Car Radio)**
   - *Obligation:* Every motor vehicle equipped with a radio receiver must hold a valid ZBC radio licence disc.
   - *Exemptions:* Vehicles certified without operational radio receiving apparatus.
   - *Status:* `VERIFIED_OFFICIAL`.

---

## 4. Regulatory Research Backlog & Uncertainties

The following statutory topics are assigned to the regulatory research queue:

1. **RRB-01: Engineering Medical Fund Scope:** Determine the exact gazetted SI number establishing the boundaries of compulsory vs voluntary medical aid contributions across artisan grades.
2. **RRB-02: ECZ Firm Registration vs Individual Engineer Registration:** Clarify under Engineering Council Act [Chapter 27:22] when an engineering contracting company requires corporate firm licensing versus employing registered individual engineers.
3. **RRB-03: Construction Association Membership (CIFOZ vs ZBCA):** Verify whether CIFOZ or ZBCA membership is a statutory legal mandate under Zimbabwean law, or strictly a client/tender-specified prerequisite. *(Preliminary finding: Tender-specified / commercial prerequisite, NOT a statutory legal mandate. Must not be flagged as compulsory law).*
4. **RRB-04: ZiG vs USD Statutory Conversion Rulings:** Document official Reserve Bank of Zimbabwe (RBZ) and ZIMRA guidelines regarding dual-currency invoicing, PAYE payment ratios, and exchange rate pegging rules.
