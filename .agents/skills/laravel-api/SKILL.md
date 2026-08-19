---
name: laravel-api
description: Design, audit, and stabilize Laravel JSON API contracts for the cabinet backend.
---

# Laravel API contract

Read `AGENTS.md`, `docs/API_CONTRACTS.md`, and `docs/BACKEND_SECURITY.md`. Start with a route inventory: URI, method, middleware, controller, request, policy, service, resource, tests, and current consumers. Classify each endpoint KEEP/FIX/REFACTOR/REMOVE/MISSING/UNKNOWN. Never invent an endpoint or remove one before inspecting current behavior.

## Contract rules

- Separate public routes (public profiles, availability, booking) from authenticated professional routes. Public endpoints never expose patients or private calendars.
- Controllers receive Form Requests, authorize, invoke services, and return API Resources; never expose raw Eloquent models.
- Use one documented JSON naming convention, Resource envelope, pagination format, ISO-8601 datetime strategy, and null/boolean semantics.
- Private lists are paginated and bounded. Validate filters and date ranges. Allowlist sort fields; never pass client field names to SQL ordering.
- Document method, URI, access, authorization, request/query, success status/resource, validation errors, business errors, and tests before contract freeze for frontend work.

## HTTP semantics

- `401` unauthenticated, `403` authenticated but forbidden, `404` absent/unresolvable resource without leakage.
- `409` current valid request conflicts with state, e.g. `SLOT_UNAVAILABLE`; `422` malformed/invalid input; `429` rate limited.
- Return safe, consistent 5xx responses; never leak traces, SQLSTATE, paths, secrets, or model internals.
- Use stable machine-readable business codes for frontend-actionable conflicts. Do not make clients parse exception text.

## Finalization

Freeze contracts only after route/controller/request/resource/policy/service behavior is verified and covered by tests. Record intentional changes and frontend impact. Do not add email, patient login, or unrelated APIs.
