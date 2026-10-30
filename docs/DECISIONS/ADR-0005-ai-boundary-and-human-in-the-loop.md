# ADR-0005: Safe AI Boundaries & Human-in-the-Loop Architecture

## Status
Accepted

## Context
Generative Large Language Models (LLMs) excel at semantic processing, OCR extraction, natural language translation, and drafting correspondence. However, LLMs are prone to hallucinations, non-deterministic outputs, and arithmetic inaccuracies, making them unsuitable for statutory legal decisions or financial computations.

## Decision
1. **Clear Functional Separation:**
   - **Allowed for AI:** Document classification, OCR metadata extraction (certificate number, expiry dates), conversational explanations grounded in cited statutory texts, parsing unstructured tender requirements, drafting clarification inquiries.
   - **Strictly Prohibited for AI:** Calculating statutory monetary obligations (PAYE, NSSA, NEC, ZIMDEF), autonomous submission of filings, executing financial payments, or overriding human exemptions.
2. **Strict Schema Validation:** All AI completions must conform to validated JSON schemas.
3. **Human-in-the-Loop Verification Queue:** Extracted dates, registration numbers, and proposed rule updates are staged in a `PENDING_VERIFICATION` review inbox. They do not drive production logic until approved by an authorized user.
4. **Auditability:** Every AI prompt, raw completion, token count, and model version is logged for governance review.

## Consequences
### Positive
- Zero risk of AI-hallucinated tax liabilities or incorrect legal filings.
- High efficiency through automated extraction while preserving complete human accountability.

### Negative
- Requires a review step for users before extracted documents are committed as active certificates.
