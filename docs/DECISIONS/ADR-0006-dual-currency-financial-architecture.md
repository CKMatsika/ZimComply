# ADR-0006: Dual-Currency Financial Architecture (USD & ZiG)

## Status
Accepted

## Context
Zimbabwe operates a multi-currency monetary system where transactions, wages, and taxes are settled in United States Dollars (USD) and Zimbabwe Gold (ZiG). Floating exchange rates and statutory multi-currency tax rules require rigorous handling to avoid rounding errors and reconciliation discrepancies.

## Decision
1. **High-Precision Storage:** All monetary values are stored as `DECIMAL(18, 4)` in PostgreSQL.
2. **Arbitrary Precision Arithmetic:** Internal calculations utilize PHP's `bcmath` functions (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) with 4-decimal scale, rounded to 2 decimals for display.
3. **Currency Tagging:** Every financial field is explicitly accompanied by a 3-character ISO currency code column (e.g. `base_currency`, `payment_currency`).
4. **Isolated Dual-Currency Payroll:** For employees earning split wages (e.g., USD allowance + ZiG base), calculations are evaluated separately against respective currency tax tables to generate compliant dual-currency ZIMRA P2 schedules.
5. **Zero Implicit Conversion:** The system never implicitly converts currencies without a recorded transaction exchange rate and timestamp.

## Consequences
### Positive
- Prevents floating-point rounding inaccuracies.
- Fully aligns with official ZIMRA and NSSA multi-currency reporting mandates.

### Negative
- Requires passing currency parameters through all financial calculation methods.
