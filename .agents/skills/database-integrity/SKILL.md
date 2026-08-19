---
name: database-integrity
description: Safely audit and harden Laravel/MySQL schema, models, migrations, and concurrency invariants.
---

# Database integrity

Read `AGENTS.md`, `docs/DATABASE.md`, and `docs/BACKEND_BUSINESS_RULES.md`. Inspect migration history, current schema, production-data assumptions, model relationships/casts/fillable, query patterns, factories, and test DB before proposing schema work.

## Safe schema work

- Do not rewrite historical migrations blindly. Prefer additive/corrective migrations and explain destructive changes, data impact, and rollback limits.
- Prevent orphan appointments with intentional foreign keys; choose `restrict`, `cascade`, or `set null` consciously. Do not casually cascade appointment/patient/psychologist history.
- Make nullability intentional. Avoid duplicate conceptual tables and do not create a second psychologist/schedule/availability representation without proving the current one is insufficient.
- Index actual access paths: appointments by psychologist/time and relevant status, patients by real search fields, schedules by psychologist/day, absences by psychologist/time. Check existing FK and composite indexes before adding redundant ones.

## Domain persistence

- Patient identity/deduplication must be explicit, normalized where relied upon, and must not merge people by name alone. Do not store clinical data or create patient accounts.
- Appointment schema needs psychologist, patient, consistent absolute times, controlled status, valid interval, and intentional business timestamps. Weekly schedules use appropriate weekday/time types and support multiple intervals when required. Absences use valid datetime intervals.
- Store/compare times consistently with documented cabinet/app/API timezone handling. Use casts matching schema and explicit fillable/guarded boundaries; never map raw request payloads wholesale.

## Concurrency and tests

MySQL has no generic exclusion constraint for arbitrary overlapping intervals. A unique start time is insufficient unless fixed alignment is proven. Use `lockForUpdate()` only after identifying the row that serializes competing bookings; keep transactions short and revalidate under the lock. Use factories and isolated synthetic test databases only; never real patient data. Test migration safety, FK/index behavior, overlap, and concurrent booking on the actual database engine where possible.
