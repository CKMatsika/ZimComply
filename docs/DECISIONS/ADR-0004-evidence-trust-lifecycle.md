# ADR-0004: 5-Tier Evidence Trust Lifecycle

## Status
Accepted

## Context
In compliance management, user-declared assertions often diverge from verified legal reality. Displaying a company as "Compliant" merely because a user uploaded a scanned PDF or typed a certificate number creates severe false-assurance risks, potentially leading to tender disqualification or regulatory penalties.

## Decision
We implement a mandatory 5-tier evidence trust taxonomy across all compliance claims and obligation instances:

1. **`verified_by_issuer`**: Provenance confirmed directly via regulator QR code verification, portal confirmation, or manual sign-off by a certified compliance officer.
2. **`document_supported`**: An official document (PDF/image) has been uploaded and cryptographically hashed, but formal inspection is pending.
3. **`user_declared`**: Data entered manually by a user without any supporting document attachment.
4. **`inferred`**: Deduced programmatically by the rule engine based on verified subsidiary facts (e.g. branch is deemed covered under an entity-level headquarters registration).
5. **`unknown`**: Insufficient or unverified data; visually marked as "Verification Required".

## Consequences
### Positive
- Prevents misleading "compliant" green badges based on unchecked uploads.
- Clear audit trails for procurement tenders and banking reviews.
- Highlights exact verification bottlenecks for business owners and consultants.

### Negative
- Requires user understanding of status distinctions; mitigated by plain-English UI tooltips and visual status badges.
