# Backend Testing Strategy — Psychology Cabinet

## 1. Purpose

This document defines the testing strategy for the Laravel backend.

The backend is not considered complete because endpoints appear to work manually.

Critical business rules, authorization rules, database integrity and booking behavior must be verified automatically where practical.

The existing test suite must be inspected before creating new tests.

Do not duplicate existing tests unnecessarily.

---

## 2. Testing priorities

Testing priority follows business risk.

Highest priority:

1. authentication;
2. authorization;
3. public booking;
4. double-booking prevention;
5. availability;
6. appointment state transitions;
7. schedules;
8. absences;
9. patient privacy;
10. API validation.

Lower-risk CRUD behavior may receive proportionally simpler coverage.

---

## 3. Test types

Use the appropriate test level.

### Feature tests

Preferred for:

- API endpoints;
- authentication;
- authorization;
- validation;
- booking workflows;
- database persistence;
- API Resources;
- business conflicts.

### Unit tests

Useful for isolated logic such as:

- interval overlap;
- appointment transition rules;
- slot generation;
- other pure business rules.

Do not convert every Laravel feature into a mocked unit test.

---

## 4. Existing tests

Before adding tests:

1. inspect `tests/`;
2. inspect PHPUnit/Pest configuration;
3. identify existing factories;
4. identify existing database testing strategy;
5. run the current suite;
6. record existing failures.

Do not hide pre-existing failures.

---

## 5. Test environment

Automated tests must use an isolated test environment.

Never run destructive tests against production data.

Verify:

- `APP_ENV=testing`;
- test database configuration;
- cache configuration;
- queue configuration if relevant later;
- safe environment values.

---

## 6. Synthetic data

Use factories and synthetic data.

Never commit real patient information into:

- tests;
- factories;
- seeders;
- fixtures.

---

## 7. Two psychologists

Tests must explicitly verify independence between the two psychologists.

Create at least:

- Psychologist A;
- Psychologist B.

Do not depend on fixed IDs such as `1` and `2`.

---

## 8. Authentication tests

Test at minimum:

- valid professional login;
- invalid credentials rejected;
- invalid login payload rejected;
- unauthenticated private access rejected;
- current professional returned when authenticated;
- logout succeeds;
- private access after logout rejected.

Test rate limiting where practical.

---

## 9. Authorization tests

Authorization tests must include negative cases.

Do not only test:

"Psychologist A can access A's appointment."

Also test:

"Psychologist B cannot access A's appointment"

when the final authorization model requires isolation.

If cabinet-wide shared visibility is intentionally selected later, tests must represent that explicit policy.

---

## 10. IDOR tests

For every sensitive resource type, attempt access using another resource ID.

Important domains:

- appointments;
- patients;
- schedules;
- absences.

Changing a route ID must not bypass authorization.

---

## 11. Public privacy tests

Public API tests must verify that responses do NOT expose:

- patient names where not required;
- patient emails;
- patient phone numbers;
- private appointment details;
- authentication information.

Privacy should be tested as an explicit contract.

---

## 12. Availability tests

Availability tests must cover:

- normal working day;
- closed day;
- multiple working intervals if supported;
- absence;
- partial absence if supported;
- pending appointment;
- confirmed appointment;
- cancelled appointment;
- past slots;
- boundary conditions;
- Psychologist A vs Psychologist B independence.

---

## 13. Schedule boundary tests

Example:

Working interval:

09:00–12:00

Duration:

60 minutes

Potential valid slots should never extend beyond 12:00.

A slot starting at 11:30 with a 60-minute duration must not be returned.

---

## 14. Appointment overlap tests

Test interval behavior explicitly.

Conflict:

09:00–10:00
09:30–10:30

No conflict:

09:00–10:00
10:00–11:00

unless a buffer rule is later introduced.

---

## 15. Booking success test

A valid public booking should verify:

- correct psychologist;
- patient resolution/creation;
- correct appointment interval;
- correct initial status;
- persisted database state;
- safe response structure.

---

## 16. Booking validation tests

Test invalid:

- missing psychologist;
- malformed patient information;
- invalid date/time;
- start in past;
- unsupported values;
- oversized fields.

Expected validation errors should use the final 422 contract.

---

## 17. Booking business conflict tests

Test:

- outside working schedule;
- absence conflict;
- occupied slot;
- invalid psychologist availability;
- other finalized booking restrictions.

Business-state conflicts should use the documented API semantics.

---

## 18. Double-booking tests

This is mandatory.

The final test/integration verification must demonstrate that two competing booking attempts cannot create overlapping blocking appointments for the same psychologist.

Do not mark this requirement complete based solely on sequential requests.

The verification must exercise the actual concurrency strategy as realistically as the test environment permits.

---

## 19. Cross-psychologist concurrency

An appointment for Psychologist A must not block the same valid time for Psychologist B.

Test this explicitly.

---

## 20. Appointment transition tests

For every final appointment status, define allowed transitions.

Test allowed transitions.

Test forbidden transitions.

Example expected rules may include:

pending → confirmed
pending → cancelled
confirmed → completed
confirmed → cancelled

Do not finalize these examples until the status model is audited.

---

## 21. Public status tampering

Test that a public booking request cannot create an appointment with arbitrary privileged status.

For example, a public client must not force:

`confirmed`

or:

`completed`

unless explicitly supported by business rules.

---

## 22. Schedule tests

Test:

- valid schedule creation/update;
- start >= end rejected;
- invalid weekday rejected;
- overlapping working intervals handled according to final rule;
- schedule belongs to correct psychologist;
- unauthorized modification rejected.

---

## 23. Schedule impact tests

Changing a schedule must affect future calculated availability correctly.

It must not silently modify existing appointments.

Test the selected conflict policy for schedule changes affecting future appointments.

---

## 24. Absence tests

Test:

- valid absence;
- invalid interval;
- correct psychologist ownership;
- effect on availability;
- unauthorized modification;
- conflict with existing blocking appointment.

---

## 25. Patient tests

Test:

- creation through booking;
- reuse/deduplication according to final strategy;
- private listing;
- private detail;
- authorized update if supported;
- unauthorized access rejected.

Do not assert unsafe deduplication behavior.

---

## 26. Patient privacy

Explicitly test that public endpoints cannot retrieve patient records.

Also test that booking responses do not leak internal patient information unnecessarily.

---

## 27. Dashboard tests

Dashboard tests should verify metrics against known database fixtures.

Example:

Given known appointments,
the dashboard count must equal the actual expected value.

Do not merely test that a numeric field exists.

---

## 28. Agenda tests

Test bounded date-range retrieval.

Appointments outside the requested range should not appear unless the final API explicitly defines otherwise.

Authorization scope must also be tested.

---

## 29. API Resource tests

Critical Resource responses should verify:

- expected fields present;
- sensitive fields absent;
- consistent naming;
- correct date serialization.

Do not snapshot huge unstable payloads without reason.

---

## 30. Pagination tests

For paginated domains test:

- pagination works;
- default size;
- maximum size if configured;
- metadata structure;
- unauthorized records do not leak across pages.

---

## 31. Filter tests

Validate supported filters.

Reject or safely ignore unsupported values according to the documented contract.

Do not allow arbitrary sorting/filtering to become SQL injection.

---

## 32. Error tests

Test important error classes:

- 401;
- 403;
- 404;
- 409;
- 422;
- 429 where practical.

Unexpected server exceptions should not expose sensitive debug information in production-style behavior.

---

## 33. Rate-limit tests

Where practical, verify:

- login throttling;
- public booking throttling.

Do not make the test suite unnecessarily slow.

Use Laravel facilities to test configured limits efficiently.

---

## 34. Database integrity tests

Where valuable, verify:

- foreign keys;
- required relationships;
- unique constraints;
- invalid orphan state prevented.

Do not duplicate every database constraint with meaningless tests.

Focus on critical invariants.

---

## 35. Transaction rollback tests

For multi-step booking workflows, verify that failure does not leave partial data.

Example:

patient operation succeeds
appointment operation fails
→ transaction rollback leaves no invalid partial booking state

according to the final patient-resolution design.

---

## 36. Timezone tests

Once timezone strategy is finalized, test representative serialization/calculation boundaries.

Do not rely entirely on the developer machine timezone.

---

## 37. Query performance

Tests are not a substitute for profiling.

However, obvious N+1 regressions on critical endpoints should be reviewed.

Availability must not perform one database query per generated slot.

---

## 38. Regression tests

Every important bug fixed during the backend audit should receive a regression test when practical.

The pattern is:

bug reproduced
↓
test fails
↓
fix
↓
test passes

This prevents the same issue returning during later refactoring.

---

## 39. Security regression tests

Security findings should receive regression coverage where practical.

Especially:

- IDOR;
- unauthorized status mutation;
- public patient exposure;
- authentication bypass;
- double booking;
- mass-assignment privilege changes.

---

## 40. Test naming

Test names should describe behavior.

Prefer:

`public_booking_rejects_an_already_occupied_slot`

over:

`test_booking_2`

Tests are executable documentation.

---

## 41. Test independence

Tests must not depend on execution order.

Each test should create the state it needs.

Avoid shared mutable database assumptions.

---

## 42. Factories

Use factories to express domain state clearly.

Useful factory states may include:

- pending appointment;
- confirmed appointment;
- cancelled appointment;
- psychologist with schedule.

Only create factory states that improve readability.

---

## 43. Mocking

Do not mock Eloquent/database behavior excessively in Feature tests.

Critical booking behavior should exercise real persistence and transaction behavior.

Mock external services only where appropriate.

Email is currently out of scope.

---

## 44. Test coverage philosophy

Do not chase an arbitrary 100% coverage number.

Prioritize:

- critical invariants;
- high-risk branches;
- authorization;
- security;
- business conflicts.

Coverage percentage is secondary to meaningful behavioral verification.

---

## 45. Commands

Codex must inspect the existing project before choosing exact commands.

Typical Laravel verification may include:

```bash
php artisan testand:

composer audit

plus any formatter/static-analysis commands already configured.

Do not assume Pest or PHPUnit syntax until the repository is inspected.

46. Failing tests

Do not delete or skip failing tests simply to obtain green CI.

Determine whether:

implementation is wrong;
test is obsolete;
requirement changed;
environment is misconfigured.

Document the reason before changing the test.

47. Definition of tested

A feature is not "tested" merely because:

controller returns 200;
one happy path exists.

Critical features need relevant:

success;
failure;
validation;
authorization;
conflict;

coverage.

48. Audit test report

During the initial backend audit, Codex must report:

current number/type of tests;
test framework;
whether suite currently passes;
failing tests;
untested critical domains;
unsafe test configuration;
missing factories.

Do not modify tests during the read-only audit.

49. Pre-frontend verification

Before backend API contracts are handed to the frontend:

critical test suite passes;
booking conflict behavior passes;
authorization tests pass;
Resources are verified;
authentication contract is verified;
API validation/error behavior is verified.

The frontend must not be integrated against knowingly unstable backend behavior.

50. Final testing principle

Tests must prove the backend protects the cabinet's critical invariants.

The most important question is not:

"Does the endpoint respond?"

It is:

"Does the system remain correct, private and secure when valid, invalid, unauthorized and competing requests occur?"
