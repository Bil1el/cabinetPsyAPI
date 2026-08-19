# AGENTS.md — Psychology Cabinet Backend


## 1. Purpose


This repository contains the backend of a real psychology cabinet platform.


The backend is the authoritative source for:


- authentication;
- authorization;
- patients;
- psychologists;
- appointments;
- schedules;
- absences;
- availability;
- dashboard data;
- business rules;
- validation;
- concurrency protection;
- security;
- API contracts.


The backend already exists partially.


Do NOT assume the current implementation is correct.


Before modifying an existing domain:


1. inspect it;
2. understand its current behavior;
3. compare it with project documentation;
4. preserve correct behavior;
5. fix incorrect architecture or business logic;
6. avoid unnecessary rewrites.


---


## 2. Product model


The current cabinet contains two psychologists.


Each psychologist may have their own:


- working schedule;
- absences;
- appointments;
- availability;
- professional profile.


The backend must NOT hardcode the application around exactly two psychologists.


Never spread logic such as:


```php
$psychologistId = 1;

through the application.

Use database relationships and authenticated/business context.

The architecture must remain compatible with adding more psychologists later without rewriting core appointment logic.

3. Patients

Patients do NOT require application accounts for the normal public booking flow.

A patient may provide the information required to request an appointment.

The backend is responsible for the rules around:

patient creation;
patient lookup;
duplicate handling;
data access;
patient privacy.

Do not introduce patient authentication unless explicitly requested later.

4. Current backend scope

The backend currently covers or must eventually cover:

professional authentication;
psychologists;
patients;
appointments;
public appointment booking;
working schedules;
absences;
availability calculation;
dashboard data;
professional profile/settings;
API validation;
authorization;
rate limiting;
database integrity;
tests.
5. Out of scope for the current phase

Email and notification infrastructure is intentionally OUT OF SCOPE.

Do NOT implement yet:

SMTP;
Brevo;
Resend;
Mailgun;
email verification;
appointment confirmation emails;
cancellation emails;
reminders;
notification queues;
email templates.

The architecture should remain extensible so these features can be added later.

Core appointment operations must not depend on email delivery.

6. Backend authority

The frontend is an untrusted client.

Never rely on frontend validation or hidden UI controls for backend security.

The backend must independently enforce:

authentication;
authorization;
ownership;
validation;
appointment state transitions;
availability;
conflict detection;
duplicate protection;
rate limits.
7. Architecture objective

Prefer clear responsibilities.

Typical complex business flow:

HTTP Request
↓
Form Request
↓
DTO / validated input object when useful
↓
Service / application business logic
↓
Repository / Eloquent persistence
↓
Database
↓
Resource
↓
JSON response

Do not force every request through every layer.

Use abstractions only when they provide value.

8. Controllers

Controllers should remain thin.

Controllers may:

receive validated input;
invoke application/domain services;
authorize when appropriate;
return Resources/responses.

Controllers should not contain large business workflows.

Avoid:

Controller
→ manual business calculations
→ multiple unrelated queries
→ direct state transitions
→ raw response transformation

when the behavior belongs in a service or domain layer.

9. Form Requests

Use Form Requests for meaningful request validation and authorization where appropriate.

Validation belongs at the application boundary.

Examples include:

login;
appointment creation;
schedule updates;
absence creation;
patient updates.

Do not duplicate the same validation rules in controllers.

Backend validation remains authoritative even if the frontend uses Zod.

10. DTOs

Use DTOs where they make complex input clearer and prevent controllers/services from depending directly on raw request arrays.

Good candidates:

appointment creation;
schedule updates;
complex filters;
public booking payload.

Do NOT create DTOs for every trivial endpoint solely for architecture aesthetics.

11. Hydrators / Mappers

Use hydrators or mappers only when transformation logic is significant or reused.

Do not add a Hydrator layer that merely copies 3 request fields into a DTO with no benefit.

12. Services

Business workflows belong in services when they coordinate meaningful rules.

Critical service candidates include:

AvailabilityService;
AppointmentService;
ScheduleService;
AbsenceService;
DashboardService.

Services may coordinate:

business rules;
transactions;
repositories/models;
conflict detection;
related state changes.

Services must not depend on HTTP-specific concepts when avoidable.

13. Repository layer

Use repositories when they provide a real boundary around complex persistence/query behavior.

Potential candidates:

appointments;
availability-related queries;
patients;
complex dashboard queries.

Do not create repositories that only wrap:

Model::find($id);

without any architectural value.

Eloquent may be used directly for simple persistence when appropriate.

14. Models

Eloquent models define:

relationships;
casts;
scopes;
simple domain helpers.

Do not place large application workflows inside models.

Avoid fat models containing:

booking orchestration;
HTTP behavior;
notifications;
complex authorization decisions.
15. Policies

Use Policies/Gates for private resource authorization.

Examples:

viewing patient data;
modifying appointments;
accessing another psychologist's resources;
modifying schedules;
modifying absences.

Never rely solely on frontend route protection.

16. Resources

API Resources define the public API representation.

Do not return raw Eloquent models unnecessarily.

Resources should prevent accidental exposure of:

private account fields;
internal database fields;
sensitive patient data;
implementation details.

Frontend types must eventually align with these Resources.

17. Enums

Use Enums for stable business concepts when useful.

Examples:

appointment status;
professional role;
consultation type if the product actually supports it.

Do not scatter status strings throughout the codebase.

18. Appointment status

Appointment statuses must have explicit semantics.

Only statuses actually required by the product should exist.

Potential statuses may include:

pending;
confirmed;
completed;
cancelled;
no_show if explicitly supported.

Do not add statuses because they are common in other systems.

Transitions must be validated by backend rules.

19. Availability

Availability is a backend business calculation.

The frontend must never become the source of truth.

Availability may depend on:

psychologist
+
working schedule
+
absence
+
existing appointments
+
consultation duration
+
booking rules
+
date/time
=
available slots

This logic should be centralized.

Do not calculate availability differently in multiple controllers.

20. Double-booking protection

This is a critical requirement.

The backend must remain correct even when two booking requests arrive at nearly the same time.

Do not rely on:

frontend disabled buttons;
previously fetched availability;
browser state.

Booking creation must revalidate the selected slot at submission time.

Use:

transactions;
database constraints where appropriate;
locking/atomic logic where required;
deterministic conflict handling.

Return a business conflict response when the slot is no longer available.

21. Database integrity

Prefer enforcing important invariants at the database level where practical.

Use:

foreign keys;
indexes;
uniqueness constraints where valid;
appropriate nullable rules;
appropriate cascade/restrict behavior.

Do not rely only on application code to protect relational integrity.

22. Transactions

Use database transactions around workflows that must succeed or fail atomically.

Potential examples:

public appointment creation;
patient creation + appointment creation;
complex appointment transition affecting multiple records.

Do not use transactions around every read-only request.

23. Patients

Patient data is private.

Apply data minimization.

The backend must not expose patient information through public endpoints.

Professional access must be authorized.

Do not turn the patient module into a medical record system unless explicitly requested later.

24. Clinical data

The current project does NOT automatically include:

clinical notes;
diagnosis;
therapy notes;
prescriptions;
medical documents.

Do not introduce these fields or endpoints without explicit product approval.

25. Working schedules

Working schedules belong to a psychologist.

Each psychologist may have independent schedule rules.

Do not implement one global cabinet schedule unless explicitly required.

Schedule changes affect future availability.

They must not silently modify existing appointments.

26. Absences

Absences belong to a psychologist.

An absence may make future slots unavailable.

Creating or modifying an absence must not silently delete or move appointments.

If existing appointments conflict, return explicit business behavior.

27. Public booking

Public booking does not require patient authentication.

The backend must safely accept public appointment requests.

Public booking requires particular attention to:

validation;
rate limiting;
abuse prevention;
availability revalidation;
concurrency;
minimal patient data;
safe error responses.
28. Authentication

Professional authentication must follow the real Laravel authentication strategy.

Potential stack may include Laravel Sanctum/session authentication.

Do not invent multiple competing authentication mechanisms.

The final strategy must be documented in:

docs/AUTHENTICATION.md
29. Authorization

Authentication and authorization are different.

A logged-in psychologist must not automatically gain access to every private resource.

Authorization rules must define who may access:

patients;
appointments;
schedules;
absences;
settings.
30. IDOR protection

Every endpoint accepting a resource ID must be considered attacker-controlled.

Examples:

/api/patients/{id}
/api/appointments/{id}
/api/absences/{id}

Policies or equivalent backend rules must prevent unauthorized cross-resource access.

31. Mass assignment

Review all Eloquent mass assignment behavior.

Do not allow user input to assign privileged or internal fields such as:

role;
ownership;
status when not allowed;
internal identifiers.

Use:

$fillable;
DTOs;
explicit field mapping;

appropriately.

32. SQL injection

Use Laravel Query Builder/Eloquent parameterization.

Avoid raw SQL with concatenated user input.

Any raw query requires review.

33. API errors

The API should return predictable safe errors.

Important classes include:

401 unauthenticated
403 forbidden
404 not found
409 business conflict
422 validation
429 rate limited
5xx server error

Do not leak:

SQLSTATE;
stack traces;
filesystem paths;
secrets;
raw database messages.
34. Business error codes

For important business conflicts, prefer stable machine-readable codes when useful.

Examples:

SLOT_UNAVAILABLE
INVALID_APPOINTMENT_TRANSITION
SCHEDULE_CONFLICT
ABSENCE_CONFLICT

Frontend should not need to parse exception text.

35. Rate limiting

Review public and authentication endpoints for abuse.

Important candidates:

login;
public appointment creation;
public availability if abuse becomes significant.

Use sensible Laravel rate limiting.

Do not use arbitrary extreme limits.

36. CORS

Production CORS must allow only intended frontend origins.

Do not use:

*

for credentialed private APIs.

Development and production origins must remain distinct.

37. CSRF

If authentication uses cookies/session/Sanctum, preserve CSRF protection.

Do not disable CSRF to solve frontend integration problems.

38. Password security

Passwords must use Laravel-supported secure hashing.

Never:

log passwords;
store plaintext passwords;
return password hashes through Resources;
hardcode production credentials.
39. Secrets

Secrets belong in environment configuration.

Never commit:

database passwords;
APP_KEY;
private API keys;
production credentials.

Do not expose secrets through API responses.

40. Logging

Avoid logging sensitive patient data.

Do not log:

passwords;
full patient payloads;
auth tokens;
secrets.

Operational logs should remain useful without exposing private information.

41. Debug configuration

Production must not expose debug output.

APP_DEBUG=true is not acceptable for production.

Errors must be transformed into safe responses.

42. Dashboard

Dashboard statistics should be calculated by backend queries/services.

Do not expect the frontend to download all appointments and derive authoritative metrics.

Only implement useful operational metrics.

No fake KPI data.

43. API pagination

Large collections should use appropriate pagination.

Important domains:

appointments;
patients.

Avoid returning unbounded historical datasets.

44. Query performance

Review:

N+1 queries;
missing eager loading;
missing indexes;
unnecessary queries;
large date ranges.

Use database indexes based on actual query patterns.

45. Database indexes

Potential index candidates include fields frequently used for:

foreign keys;
appointment date/time;
psychologist/date queries;
status filtering;
patient search identifiers.

Indexes must be justified by real queries.

Do not add random indexes everywhere.

46. Time and timezone

Date/time handling must be explicit.

Document:

storage timezone;
API datetime format;
cabinet timezone;
date-only fields;
appointment start/end semantics.

Availability, appointments, schedules and absences must use the same strategy.

47. API contracts

The frontend will eventually be adapted to the final backend.

Therefore backend API Resources, paths, validation and status codes must be stabilized before frontend integration.

Document final contracts in:

docs/API_CONTRACTS.md

Do not change verified contracts casually after frontend integration begins.

48. Existing code

The backend already contains code.

Do not automatically rewrite existing implementations.

For each domain:

Inspect
↓
Understand
↓
Compare with target architecture
↓
Preserve what is correct
↓
Refactor only what is necessary
↓
Test
49. Legacy code

If legacy code conflicts with the target architecture:

identify the actual risk;
migrate incrementally;
preserve API behavior when intended;
avoid uncontrolled rewrites.

Do not keep insecure legacy behavior solely for compatibility.

50. No fake functionality

Never expose API operations that pretend to work.

No endpoint should return fake success while persistence failed.

No dashboard endpoint should return fabricated numbers.

51. Testing

Critical backend behavior must have tests.

Priorities include:

authentication;
authorization;
booking;
availability;
double-booking protection;
appointment transitions;
schedules;
absences;
patient access;
validation;
conflicts.
52. Test database

Tests must not depend on production data.

Use appropriate test database configuration.

Never run destructive test operations against production.

53. Booking tests

Booking tests must include:

available slot
→ appointment created
occupied slot
→ conflict
two competing booking requests
→ no double booking
absence
→ affected availability removed
schedule changes
→ availability changes
54. Authorization tests

Test that unauthorized users cannot access resources merely by changing IDs.

Do not only test happy-path authorized access.

55. Definition of done

A backend feature is not complete simply because an endpoint returns 200.

A feature must satisfy relevant criteria from:

docs/DEFINITION_OF_DONE.md

including:

architecture;
business rules;
security;
database integrity;
errors;
tests;
performance;
documentation.
56. Agent behavior

AI agents must not:

invent product requirements;
invent API contracts;
remove working functionality without reason;
disable security;
bypass tests;
silence static analysis through unsafe casts/workarounds;
modify unrelated domains during focused tasks.
57. Audit-first rule

Before large backend changes, perform a read-only audit.

The audit must identify:

current implementation;
architecture violations;
security risks;
missing business rules;
database problems;
API inconsistencies;
missing tests;
duplicate code;
dead code.

Do not begin a full rewrite without audit evidence.

58. Priority order

When fixing findings, prioritize:

1. Security / data exposure
2. Data integrity
3. Booking correctness
4. Authentication / authorization
5. Database constraints
6. API correctness
7. Architecture
8. Performance
9. Tests
10. Cleanup
59. Severity

Use:

P0 — Critical
P1 — High
P2 — Medium
P3 — Low

Do not inflate severity.

60. Completion reporting

For meaningful backend work, report:

Implemented
Architecture
Business rules
Database changes
Security
API contracts
Tests
Remaining dependencies

Be explicit about unverified areas.

61. Final backend principle

The backend exists to protect the integrity and privacy of the cabinet's data.

For every operation ask:

Who may perform this action?
Is the input valid?
Does the business rule allow it?
Can concurrent requests break it?
Can the database enforce part of the invariant?
What data should be returned?
What happens on failure?
Is the behavior tested?

Correctness and security take priority over architectural decoration.
