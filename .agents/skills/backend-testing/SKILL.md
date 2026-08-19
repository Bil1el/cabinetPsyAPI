---
name: backend-testing
description: Verify Laravel backend behavior by risk with isolated, privacy-safe, regression-focused tests.
---

# Backend testing

Read `AGENTS.md`, `docs/TESTING.md`, and `docs/DEFINITION_OF_DONE.md`. Inspect existing tests, factories, PHPUnit/Pest setup, test DB, and current failures before adding coverage. Never delete or weaken tests merely to obtain green output.

## Test strategy

- Prefer Feature tests for API, authentication, authorization, validation, persistence, Resources, and conflict behavior. Use Unit tests for genuinely isolated interval, transition, or slot-generation logic.
- Use isolated database rollback/refresh facilities and synthetic factories only; never production or real patient data.
- Create Psychologist A and B through factories, never fixed IDs. Verify their schedules, absences, appointments, availability, and private data remain isolated.
- Cover login/logout/current user; 401/403/404/409/422/429; IDOR for every sensitive resource; public privacy; pagination/filter bounds; Resource shape; appointment transitions; schedules/multiple ranges; absences; patient handling; dashboard/agenda range behavior.
- Booking tests must cover valid creation, malformed/past/outside-hours/absence/occupied failures, safe status assignment, cancelled-slot behavior, cross-psychologist same-time bookings, and deterministic `SLOT_UNAVAILABLE` conflict.
- Mandatory: prove anti-double-booking with a realistic concurrent/integration test against the actual selected MySQL strategy, not two sequential HTTP requests.

Run `php artisan test` and project quality checks; run `composer audit` when relevant. Report pre-existing failures separately and classify verification PASS, PASS WITH NON-BLOCKING ISSUES, FAIL, or BLOCKED / NOT VERIFIED.
