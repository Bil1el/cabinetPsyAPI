# Backend Definition of Done — Psychology Cabinet

## 1. Purpose

This document defines when the psychology cabinet backend may be considered complete.

"Done" does NOT mean:

- the application starts;
- endpoints return 200;
- the frontend displays data;
- manual testing appears successful;
- Codex completed its assigned changes.

The backend is done only when architecture, business rules, database integrity, security, API contracts and tests have been verified together.

---

## 2. Existing backend

This project already contains a partially implemented backend.

Completion requires:

1. audit existing implementation;
2. identify correct behavior;
3. identify defects and missing functionality;
4. identify security risks;
5. identify database integrity risks;
6. correct justified issues;
7. add missing required behavior;
8. test critical behavior;
9. stabilize API contracts;
10. verify the complete backend again.

Do not rebuild working functionality unnecessarily.

---

## 3. Product scope

The backend must correctly support the current product:

- one psychology cabinet;
- two current psychologists;
- professional authentication;
- patients without accounts;
- public appointment booking;
- appointments;
- working schedules;
- absences;
- calculated availability;
- dashboard;
- agenda;
- patient management;
- professional settings required by the product.

The architecture must not hardcode exactly two psychologists.

---

## 4. Out of scope

The backend is not incomplete merely because it does not contain:

- email delivery;
- email verification;
- appointment reminder emails;
- payments;
- subscriptions;
- patient accounts;
- patient dashboard;
- teleconsultation;
- AI;
- clinical notes;
- diagnoses;
- prescriptions;
- medical document management;
- multi-cabinet SaaS;
- super-admin.

These require separate future decisions.

---

## 5. Architecture complete

Architecture is complete when:

- controllers are reasonably thin;
- meaningful validation uses Form Requests;
- complex business workflows are centralized;
- DTOs are used where they create useful boundaries;
- Resources intentionally shape API responses;
- Policies/authorization protect private resources;
- database access is organized and understandable;
- unnecessary abstraction has not been introduced;
- no major business logic is duplicated across controllers.

Architecture must remain proportional to complexity.

---

## 6. Authentication complete

Authentication is complete when:

- both professional accounts can authenticate independently;
- passwords use Laravel-supported hashing;
- login is validated;
- login abuse protection exists;
- logout works;
- current professional can be retrieved safely;
- private routes reject unauthenticated access;
- authentication responses do not expose sensitive fields;
- CSRF/CORS/session behavior matches the selected architecture;
- authentication tests pass.

Patients must not require accounts.

---

## 7. Authorization complete

Authorization is complete when:

- the final visibility model between the two psychologists is explicitly documented;
- private resources use backend authorization;
- route IDs cannot bypass authorization;
- appointment access is protected;
- patient access is protected;
- schedule access is protected;
- absence access is protected;
- settings/profile writes cannot escalate privileges;
- authorization tests pass.

Frontend route protection does not count as backend authorization.

---

## 8. Patient domain complete

Patient management is complete when:

- required patient fields are defined;
- public booking can safely resolve/create patients;
- deduplication strategy is explicit;
- unsafe patient merging is prevented;
- private patient access is authorized;
- public patient enumeration is prevented;
- patient lists are bounded/paginated where required;
- sensitive information is minimized;
- tests cover critical patient behavior.

---

## 9. Appointment domain complete

Appointments are complete when:

- every appointment belongs to a valid psychologist;
- every appointment is associated with the required patient;
- intervals are valid;
- statuses are controlled;
- initial public-booking status is explicit;
- blocking statuses are explicit;
- allowed state transitions are explicit;
- invalid transitions are rejected;
- cancellation does not incorrectly delete operational history;
- public clients cannot assign privileged status;
- tests pass.

---

## 10. Schedule domain complete

Working schedules are complete when:

- schedules belong to the correct psychologist;
- both psychologists may have different schedules;
- invalid intervals are rejected;
- overlap behavior is explicit;
- multiple intervals are supported if required;
- closed days work correctly;
- schedule changes affect availability correctly;
- schedule changes do not silently modify appointments;
- authorization is enforced;
- tests pass.

---

## 11. Absence domain complete

Absences are complete when:

- each absence belongs to a psychologist;
- interval validation exists;
- partial-day/multi-day behavior matches the final model;
- availability correctly excludes absences;
- existing appointments are not silently modified;
- appointment conflicts follow an explicit policy;
- authorization is enforced;
- tests pass.

---

## 12. Availability complete

Availability is complete when one authoritative backend implementation correctly considers:

- psychologist;
- working schedule;
- absences;
- blocking appointments;
- appointment duration;
- slot-generation rules;
- current date/time;
- approved booking constraints.

Availability must not be calculated independently by the frontend.

---

## 13. Availability privacy complete

Public availability must not expose:

- patient information;
- private appointment information;
- private appointment IDs unnecessarily;
- private professional metadata.

Only information required for booking may be returned.

---

## 14. Public booking complete

Public booking is complete when the backend:

1. validates input;
2. validates the psychologist;
3. validates the requested interval;
4. revalidates current availability;
5. applies concurrency protection;
6. resolves/creates the patient safely;
7. creates the appointment atomically;
8. assigns backend-controlled status;
9. returns a safe response.

Frontend state is never authoritative.

---

## 15. Double-booking protection complete

This is a mandatory completion requirement.

The backend must prevent two concurrent requests from creating conflicting blocking appointments for the same psychologist.

Completion requires:

- an explicit concurrency strategy;
- correct database/transaction behavior;
- documented reasoning;
- reproducible verification/test.

A simple pre-insert availability check is not sufficient.

`DB::transaction()` alone is not sufficient proof.

---

## 16. Cross-psychologist behavior complete

The system must allow:

Psychologist A
10:00

and:

Psychologist B
10:00

when both are independently available.

Availability and conflict logic must be scoped to the correct psychologist.

---

## 17. Database complete

Database work is complete when:

- relationships are correct;
- required foreign keys exist where appropriate;
- nullable fields are intentional;
- uniqueness rules are intentional;
- delete behavior is safe;
- indexes support critical queries;
- duplicate/redundant schema concepts are resolved;
- appointment concurrency strategy is supported;
- migrations are safe;
- model casts match schema;
- timezone/date representation is documented.

Do not declare database work complete based only on successful migrations.

---

## 18. Migration safety complete

Existing migration history must be understood.

Destructive schema changes must not be performed blindly.

If existing environments may contain data:

- use safe corrective migrations;
- avoid unnecessary data loss;
- document risky transformations.

---

## 19. API complete

Every frontend-required operation must have a verified API contract.

For each endpoint document:

- method;
- URI;
- authentication;
- authorization;
- request/query;
- validation;
- success status;
- response structure;
- error statuses;
- business codes where relevant.

The frontend must not need to guess backend behavior.

---

## 20. Error handling complete

Expected errors must be predictable.

Relevant semantics include:

- 401 unauthenticated;
- 403 forbidden;
- 404 not found;
- 409 business conflict;
- 422 validation;
- 429 rate limited;
- safe 5xx.

Expected business errors must not become generic server failures.

---

## 21. Security complete

Security is complete only after reviewing:

- authentication;
- authorization;
- IDOR;
- mass assignment;
- SQL injection risks;
- CORS;
- CSRF;
- rate limiting;
- patient privacy;
- resource overexposure;
- secret handling;
- production debug configuration;
- logging;
- concurrency integrity;
- dependency advisories.

Security is not complete because the frontend hides unauthorized buttons.

---

## 22. Secrets complete

Before delivery:

- no production secret is committed;
- `.env` is excluded from Git;
- `.env.example` contains safe placeholders;
- source code does not contain production credentials;
- responses/logs do not expose secrets.

If an exposed real secret is discovered, it must be rotated outside the codebase as appropriate.

---

## 23. Performance complete

Critical endpoints must avoid obvious scalability problems.

Review at minimum:

- N+1 queries;
- unbounded collections;
- availability query loops;
- missing important indexes;
- huge date ranges;
- excessive dashboard queries;
- patient search.

Do not perform premature micro-optimization.

---

## 24. Testing complete

Critical backend behavior must have meaningful automated coverage.

Required areas include:

- authentication;
- authorization;
- IDOR boundaries;
- availability;
- public booking;
- booking conflicts;
- concurrency/double booking;
- appointment transitions;
- schedules;
- absences;
- patient privacy;
- validation;
- critical API responses.

All relevant final tests must pass.

---

## 25. Regression coverage

Important defects fixed during audit/refactoring should receive regression tests when practical.

Security and data-integrity bugs have especially high priority for regression coverage.

---

## 26. Static/security tooling

Run and review the tools appropriate to the existing repository.

Examples may include:

```bash
php artisan test
composer audit plus any formatter/static-analysis tooling already configured.

Do not install a large toolchain merely to satisfy this checklist.

27. Production error safety

Production behavior must not expose:

Laravel debug pages;
stack traces;
SQL queries;
SQLSTATE details;
filesystem paths;
environment values;
secrets.

APP_DEBUG must be disabled in production.

28. Documentation complete

Final documentation must match implementation.

At minimum update as required:

BACKEND_PRODUCT.md;
BACKEND_ARCHITECTURE.md;
BACKEND_BUSINESS_RULES.md;
DATABASE.md;
API_CONTRACTS.md;
BACKEND_SECURITY.md;
AUTHENTICATION.md;
AVAILABILITY_AND_BOOKING.md;
TESTING.md;
DEFINITION_OF_DONE.md.

Do not leave documentation describing architecture that was never implemented.

29. Frontend handoff complete

Before adapting the frontend, provide a verified backend handoff containing:

endpoint inventory;
authentication flow;
request payloads;
response types;
pagination;
filters;
appointment statuses;
business error codes;
datetime/timezone format;
authorization expectations.

Only then should frontend API integration be finalized.

30. Email does not block completion

Email infrastructure is intentionally deferred.

The backend may be considered complete without email if all current core requirements are satisfied.

Email will be implemented and tested separately later.

31. No hidden TODOs

Before declaring completion, search for relevant:

TODO;
FIXME;
temporary bypasses;
debug code;
commented security checks;
fake responses;
mocks used in production paths;
hardcoded psychologist IDs.

Classify and resolve anything affecting the current scope.

32. No silent uncertainty

Codex must not report:

"Done"

when an important area was not verified.

Use explicit reporting such as:

VERIFIED;
IMPLEMENTED;
TESTED;
UNVERIFIED;
BLOCKED;
OUT OF SCOPE.

Unknown behavior must remain visible.

33. Severity

Use:

P0 — Critical

P1 — High

P2 — Medium

P3 — Low

Do not inflate severity.

34. P0 examples

Potential P0 findings include severe exploitable conditions such as:

authentication bypass exposing private cabinet data;
public access to sensitive patient database information;
committed active production secrets enabling major compromise.

Classification depends on actual exploitability and impact.

35. P1 examples

Potential P1 findings include:

cross-professional unauthorized modification;
serious IDOR;
reliable appointment integrity failure;
major mass-assignment privilege escalation;
concurrency flaw allowing operationally serious double booking.

Severity must reflect actual context.

36. Completion report

For meaningful backend work, report:

Implemented


Architecture
- ...


Business rules
- ...


Database changes
- ...


Security
- ...


API contracts
- ...


Tests
- ...


Remaining dependencies
- ...


Unverified
- ...

Never hide remaining work behind a generic success message.

37. Final audit

After implementation is believed complete, perform a final backend audit.

The final audit must compare the repository against:

product requirements;
business rules;
architecture;
database rules;
API contracts;
security requirements;
authentication requirements;
booking requirements;
testing requirements.

Do not rely only on the original audit.

38. Final backend state

The expected final chain is:

Professional authentication
        ↓
Psychologists
        ↓
Working schedules
        ↓
Absences
        ↓
Availability
        ↓
Public booking
        ↓
Patients + Appointments
        ↓
Agenda / Dashboard / Management

Authorization, security, database integrity and tests apply across the entire chain.

39. Definition of Done

The backend is DONE only when:

Correct business behavior
+
Secure access
+
Database integrity
+
Concurrency safety
+
Stable API contracts
+
Meaningful tests
+
Verified documentation
=
Frontend-ready backend

If one critical component remains unverified, the backend is not yet ready for final frontend integration.
