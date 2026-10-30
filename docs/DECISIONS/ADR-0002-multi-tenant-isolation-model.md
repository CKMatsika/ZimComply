# ADR-0002: Multi-Tenant Data Isolation Strategy

## Status
Accepted

## Context
ZimComply AI serves single SMEs, large enterprises with multiple branches, and accounting consultancies managing 50+ client organisations. Cross-tenant data leakage is an existential regulatory and legal liability under the Zimbabwe Cyber and Data Protection Act [Chapter 12:07].

Three primary multi-tenancy models were evaluated:
1. **Database-per-tenant:** High physical isolation, but massive operational complexity in running migrations, cross-tenant updates, and connection pool exhaustion.
2. **Schema-per-tenant (PostgreSQL schemas):** Moderate isolation, but still complex to maintain migrations and connection limits.
3. **Shared database with Tenant-Scoped Foreign Keys and Row-Level Security:** Highly cost-effective, straightforward migrations, and scalable to thousands of SME tenants, provided isolation is strictly enforced.

## Decision
1. We implement a **Shared Database with Indexed `tenant_id` Foreign Keys**.
2. Logical isolation is enforced at the application layer via an Eloquent Global Scope (`TenantScope`), which automatically appends `WHERE tenant_id = ?` to all ORM operations.
3. Middleware (`EnforceTenantContext`) binds the active tenant context from the authenticated user's session or subdomain.
4. As defense-in-depth, PostgreSQL Row-Level Security (RLS) policies are configured for direct database access.
5. Accounting firms are granted delegated access to client organisations via an explicit `memberships` join table with strict permission scoping.
6. Automated CI test suites execute dedicated cross-tenant penetration tests before every merge.

## Consequences
### Positive
- Rapid migration deployment and single database schema maintenance.
- Efficient connection pooling and low infrastructure operating costs for affordable Zimbabwean SME pricing.
- Seamless multi-client switching for accounting practices.

### Negative
- Requires strict adherence to Eloquent conventions; raw SQL queries must explicitly include tenant checks or rely on PostgreSQL RLS session variables.
