# ADR-0001: Adoption of Modular Monolith with Laravel 11, Inertia.js, and React

## Status
Accepted

## Context
ZimComply AI requires high developer velocity, strict domain boundaries, responsive mobile-friendly user interfaces, and enterprise multi-tenancy. Building a distributed microservices architecture at inception introduces significant network serialization, distributed transaction complexity, and deployment overhead. Conversely, a standard monolithic Rails/Laravel architecture without strict domain separation risks developing into an unmaintainable "spaghetti" codebase.

Furthermore, building a separate single-page application (SPA) with a standalone REST or GraphQL backend requires duplicate route definitions, serialization layers, and client-state synchronization boilerplate.

## Decision
1. We adopt a **Modular Monolith architecture** powered by **Laravel 11** on **PHP 8.2+**.
2. Domain logic is strictly partitioned into cohesive domains (`App\Domains\*`) reflecting modules M01 through M14, communicating via explicit interfaces and events.
3. The presentation tier uses **Inertia.js** with **React 18**, **TypeScript**, and **Tailwind CSS**.
4. Workflow automation and asynchronous queues are managed natively via Laravel Queues and the Scheduler. Third-party automation engines like **n8n are strictly prohibited**.

## Consequences
### Positive
- Unified codebase with single git repository and unified database migrations.
- Inertia.js provides single-page application user experience (instant page transitions, client-side reactivity) without REST/GraphQL boilerplate.
- Strict domain partitioning enables future extraction of specific modules (e.g., the statutory rule engine) into dedicated microservices if horizontal scaling demands it.
- Native Laravel queues eliminate external runtime dependencies and single points of failure.

### Negative
- Requires developer discipline to prevent direct cross-domain coupling without using designated service contracts.
- Long-running CPU-intensive tasks (e.g. large OCR batch processing) must be queued to avoid blocking web worker threads.
