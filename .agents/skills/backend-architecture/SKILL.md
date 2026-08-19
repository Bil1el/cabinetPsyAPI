---
name: backend-architecture
description: Audit and incrementally improve the Laravel backend architecture without unnecessary abstraction.
---

# Backend architecture

Read `AGENTS.md`, `docs/BACKEND_ARCHITECTURE.md`, `docs/BACKEND_BUSINESS_RULES.md`, and `docs/DEFINITION_OF_DONE.md` before proposing changes. Inspect the existing implementation and tests first; classify behavior as correct, partial, missing, conflicting, or unknown. Preserve correct behavior and prefer a corrective, incremental refactor over a rewrite.

## Boundaries

- Keep controllers thin: receive validated input, authorize, call an application service/action, and return a Resource/response.
- Use Form Requests for meaningful HTTP validation. Do not put `$request->validate()` or booking workflows in controllers.
- Use immutable DTOs for complex writes, booking payloads, schedule changes, or complex filters when they stop services depending on HTTP objects. Do not create DTOs for trivial reads merely for appearances.
- Add Hydrators/Mappers only for reused or non-trivial normalization; direct DTO construction is preferable for a few unchanged fields.
- Put multi-model workflows, state transitions, availability, locking, and transactions in Services or a focused Action. Services never return HTTP responses.
- Use Eloquent directly for simple, local operations. Repositories are optional: introduce contracts only for reusable complex queries or a real persistence boundary. Bind justified contracts in a provider.
- Shape every API result through intentional Resources; use separate public/private representations where privacy differs.
- Use Policies/Gates for private-resource access, backed enums for closed business values, and typed business exceptions with safe rendering.

## Dependency direction

Prefer `HTTP → application/domain service → persistence/database`. Controllers and Requests must not leak into domain services; repositories must not call controllers. Avoid service cycles; introduce a clear orchestration action instead of `A → B → A`. Use constructor injection and the Laravel container, not service locators.

## Legacy and migration discipline

Inventory routes, models, queries, configuration, tests, and migration history before moving classes or tables. Do not rewrite executed migrations blindly; add corrective migrations when environments may contain data. Preserve stable public contracts unless a documented security/integrity correction requires change. Add regression coverage before extracting high-risk legacy logic.

## Review checklist

- Is the layer justified by complexity and used consistently?
- Is booking/availability centralized and testable?
- Are authorization, transactions, error semantics, and Resources intentional?
- Are names domain-specific (`AppointmentService`, not `CommonService`)?
- Did the change preserve valid behavior, avoid hardcoded psychologist IDs, avoid patient accounts, and avoid email/notification work?
