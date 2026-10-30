# ADR-0003: Deterministic Statutory Rule Engine & Effective-Dated Immutability

## Status
Accepted

## Context
Zimbabwean tax, labour, and commercial regulations (ZIMRA PAYE brackets, NSSA insurable earnings caps, NEC collective bargaining agreements, City Council fee structures) evolve frequently through gazetted Statutory Instruments and Finance Acts. Furthermore, statutory calculations must frequently be performed for past historical periods (e.g. late tax filings or retrospective audits) without using newly gazetted rates.

## Decision
1. Zero hardcoded statutory rates, thresholds, or deadlines in PHP or React code.
2. All rules are managed through database entities: `rule_packs` and `rule_versions`.
3. Every rule version specifies an explicit `effective_from` date and optional `effective_to` date, alongside a gazetted citation.
4. When performing calculations (e.g. monthly payroll), the engine queries the exact rule version whose effective range covers the filing period date.
5. Once a payroll or return is finalized, its snapshot and calculation line items are frozen. Future rule activations can never mutate past finalized records.
6. Generative AI is strictly excluded from executing mathematical calculations or determining statutory liabilities.

## Consequences
### Positive
- Auditable single source of truth for all statutory obligations.
- Complete historical fidelity: past tax returns remain 100% compliant with the laws in effect at that time.
- Rule updates can be scheduled in advance before their legal effective date.

### Negative
- Requires maintaining rule versioning database schemas and continuous validation against gazetted SIs.
