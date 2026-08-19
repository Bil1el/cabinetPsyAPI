---
name: backend-audit
description: Conduct a read-only, evidence-based final audit of the existing Laravel cabinet backend before implementation.
---

# Backend audit

Read `AGENTS.md` and all backend documents: `BACKEND_PRODUCT.md`, `BACKEND_ARCHITECTURE.md`, `BACKEND_BUSINESS_RULES.md`, `DATABASE.md`, `API_CONTRACTS.md`, `BACKEND_SECURITY.md`, `AUTHENTICATION.md`, `AVAILABILITY_AND_BOOKING.md`, `TESTING.md`, and `DEFINITION_OF_DONE.md`.

## Read-only audit mode

Do not modify code, migrations, configuration, tests, or docs. Inventory Laravel/PHP/Composer versions and dependencies; routes/middleware; controllers/requests/resources; DTOs/mappers; services/repositories; models/relationships/casts/fillable; policies/enums/exceptions; providers/config/auth/Sanctum/CORS/CSRF; migrations/indexes/constraints; factories/seeders; tests and quality tools.

Audit product flows end-to-end: public profile/availability/booking; private auth/dashboard/agenda/appointments/patients/schedules/absences/profile. Check patient privacy, two-psychologist isolation without hardcoded IDs, patient-without-account policy, status transitions/blocking statuses, booking revalidation, overlap/concurrency, timezone, pagination/filter/sorting limits, Resources, N+1/performance, dead/duplicate/debug code, secret leakage, accidental email/SMTP code, and migration-history safety.

## Deliverable

For each domain/rule classify COMPLIANT/PARTIAL/MISSING/CONFLICTING/UNKNOWN. Report findings by P0/P1/P2/P3 with evidence and affected files, distinguish facts from uncertainty, identify behavior to preserve, and propose an ordered implementation/test plan. Do not make changes until audit approval.
