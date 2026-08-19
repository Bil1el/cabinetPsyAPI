# Backend Security — Psychology Cabinet

## 1. Purpose

This document defines the security requirements of the psychology cabinet Laravel backend.

The application handles private patient and appointment information.

Security must be enforced by the backend.

The frontend is an untrusted client.

Existing code must not be considered secure until audited.

---

## 2. Security priorities

The highest-priority assets are:

1. patient information;
2. professional accounts;
3. appointments;
4. schedules and absences;
5. authentication state;
6. application secrets;
7. database integrity.

Security fixes take priority over architectural cleanup.

---

## 3. Trust boundary

Never trust data because it comes from the official frontend.

Treat as attacker-controlled:

- request bodies;
- query parameters;
- route parameters;
- IDs;
- headers;
- cookies;
- dates;
- appointment statuses;
- psychologist IDs;
- patient IDs;
- sorting/filtering parameters.

Every sensitive operation must be validated and authorized server-side.

---

## 4. Authentication

Private professional endpoints require the final approved Laravel authentication mechanism.

Prefer the existing Laravel/Sanctum architecture when appropriate.

Do not create multiple competing authentication systems.

Never implement custom password/token cryptography.

---

## 5. Passwords

Passwords must:

- use Laravel-supported secure hashing;
- never be stored in plaintext;
- never be logged;
- never be returned through API Resources;
- never be committed as production credentials.

Review existing seeders and development accounts for accidental production assumptions.

---

## 6. Authentication responses

Login responses must not expose:

- password hashes;
- remember tokens;
- internal authentication metadata;
- secrets.

Authentication failure messages should not reveal unnecessary account information.

---

## 7. Brute-force protection

Login must be rate-limited.

Repeated authentication failures must not allow unlimited password guessing.

Use Laravel's rate-limiting facilities.

Do not implement home-made IP blocking without a demonstrated need.

---

## 8. Authorization

Authentication alone is insufficient.

Every private resource operation must verify whether the authenticated professional may perform that operation.

Use:

- Policies;
- Gates;
- explicit scoped authorization;

where appropriate.

---

## 9. Two psychologists

The cabinet currently has two psychologists.

This creates an important authorization boundary.

A professional must not gain unauthorized access to another psychologist's:

- appointments;
- schedules;
- absences;
- private profile/settings;

merely by modifying an ID.

The final shared-vs-private visibility policy must be explicitly resolved.

Until then, prefer least privilege.

---

## 10. IDOR

Insecure Direct Object Reference is a critical audit target.

Audit endpoints such as conceptually:

```text
/patients/{id}
/appointments/{id}
/schedules/{id}
/absences/{id}
Changing an ID must never bypass authorization.

Do not assume UUIDs or non-sequential IDs solve authorization.

11. Public/private route separation

Public routes must expose only public information.

Public APIs must never expose:

patient listings;
patient details;
private appointment details;
private professional settings;
authentication metadata.

Review route middleware carefully.

12. Public booking

Public booking is intentionally unauthenticated.

Therefore it requires additional protection:

strict validation;
rate limiting;
minimal information disclosure;
availability revalidation;
concurrency protection;
deterministic safe errors.

Public booking must not become a route into private patient data.

13. Booking enumeration

A booking response must not reveal whether a patient already exists in the database.

Avoid responses such as:

Patient already registered.

when this exposes private information.

Patient resolution should be an internal backend concern.

14. Availability privacy

Public availability must expose only bookable information.

Never return private appointment records to explain unavailable slots.

Bad:

{
  "time": "10:00",
  "patient": "..."
}

Public consumers only need to know what they may book.

15. Rate limiting

Audit rate limits for:

professional login;
public appointment creation;
public availability if necessary;
other abuse-sensitive public writes.

Limits should be configurable and reasonable.

Do not rely solely on frontend debouncing.

16. Spam booking protection

Rate limiting is the first protection against automated booking abuse.

Do not immediately introduce CAPTCHA or external anti-bot services unless required.

The architecture should allow stronger abuse protection later if real abuse occurs.

17. Input validation

Every write operation requires server-side validation.

Use Form Requests where appropriate.

Validate:

required fields;
maximum lengths;
formats;
enums;
dates;
identifiers;
arrays;
allowed values.

Do not pass arbitrary request payloads directly into models.

18. Mass assignment

Audit every Eloquent model for:

$fillable;
$guarded;
direct create($request->all());
direct update($request->all()).

Avoid patterns such as:

Model::create($request->all());

for sensitive models.

Map validated fields intentionally.

Clients must not be able to assign:

roles;
ownership;
internal IDs;
privileged statuses;
security fields.
19. SQL injection

Use Eloquent and Laravel Query Builder parameterization.

Never concatenate untrusted input into raw SQL.

Audit:

DB::raw;
whereRaw;
orderByRaw;
raw expressions;
dynamic column names.

If dynamic sorting exists, use an allowlist.

20. XSS and stored content

The API must not encourage storage/rendering of arbitrary unsafe HTML.

Do not introduce HTML fields without requirement.

The frontend must escape rendered user data.

Do not treat backend HTML sanitization as unnecessary if rich text is intentionally introduced later.

21. CSRF

If professional authentication uses cookies/session/Sanctum SPA authentication, CSRF protection is mandatory.

Do not disable CSRF to fix frontend integration.

The frontend must adapt to the correct CSRF flow.

22. CORS

CORS must be intentionally configured.

Development may allow the local frontend origin.

Production should allow only intended production origins.

Do not use credentialed:

Access-Control-Allow-Origin: *

Production CORS configuration must be reviewed before deployment.

23. Cookies

If authentication uses cookies, production cookies should use appropriate:

HttpOnly;
Secure;
SameSite;

configuration based on deployment architecture.

Do not expose session cookies to JavaScript unnecessarily.

24. Tokens

If the existing backend uses API tokens, audit:

where they are created;
scope/abilities;
expiration/revocation strategy;
how they are transmitted;
whether they are exposed in logs.

Do not introduce localStorage token authentication merely for frontend convenience.

25. Secrets

Secrets belong in environment variables/configuration.

Never commit:

APP_KEY;
production DB passwords;
SMTP credentials;
private service keys;
production tokens.

Audit repository history/current tracked files for obvious exposed secrets where practical.

Do not print secret values in audit reports.

26. .env

.env must not be committed.

Provide .env.example with safe placeholders where appropriate.

Never copy real production secrets into documentation.

27. Laravel configuration

Application code should use Laravel configuration.

Avoid calling:

env(...)

directly throughout application/service code.

Use config(...) outside configuration files.

This also prevents configuration-cache inconsistencies.

28. Production debug

Production must use:

APP_DEBUG=false

Never expose Laravel exception/debug pages publicly.

Production errors must return safe API responses.

29. Exception leakage

API responses must not expose:

stack traces;
SQLSTATE messages;
raw SQL;
filesystem paths;
environment configuration;
credentials;
internal exception details.

Expected business conflicts should be converted to safe documented responses.

30. Logging

Logs must be useful without leaking private data.

Never intentionally log:

passwords;
authentication tokens;
cookies;
secrets;
full patient request payloads;
sensitive headers.

Audit existing debug logs.

31. Patient privacy

Patient information must be returned only to authorized private consumers.

Use API Resources to control representation.

Do not return raw Patient models from public or unrelated endpoints.

32. Data minimization

Do not collect or expose information that the appointment workflow does not need.

This application is not currently a clinical records system.

Do not add:

diagnoses;
therapy notes;
prescriptions;
clinical documents.
33. Appointment privacy

Public consumers must not be able to inspect the private appointment calendar.

An unavailable time slot does not justify exposing appointment details.

Private appointment endpoints require authorization.

34. Database authorization scope

Queries for private resources should be scoped appropriately.

Avoid:

Appointment::findOrFail($id);

followed by no ownership/policy check.

Resolve authorization explicitly.

35. Appointment integrity

Security includes integrity, not only confidentiality.

An attacker must not be able to:

book outside valid working hours;
book during absence;
create overlapping appointments;
manipulate psychologist ownership;
assign unauthorized status;
create appointments in the past when prohibited.

Backend business rules must enforce these invariants.

36. Concurrency attacks

Repeated concurrent requests must not bypass booking conflict checks.

Anti-double-booking behavior must remain correct under concurrent requests.

This must be tested, not assumed.

37. Appointment status tampering

Do not allow clients to send arbitrary statuses during public booking.

Public clients must not create:

confirmed
completed

appointments unless explicitly allowed by business rules.

Professional transitions must also be authorized and validated.

38. Psychologist ID tampering

A client-supplied psychologist ID must be validated.

For private writes, authorization must ensure the authenticated professional may act on the selected psychologist.

For public booking, the psychologist must be a valid publicly bookable professional.

39. Patient ID tampering

Public booking should not blindly trust a client-provided patient ID.

Otherwise an attacker may associate bookings with another patient.

Patient resolution must be controlled by backend logic.

40. Resource overexposure

Audit every Resource/JSON response.

Avoid accidental exposure caused by:

raw models;
toArray();
loaded relationships;
hidden fields not configured;
debugging fields.

Explicit Resources are preferred for sensitive domains.

41. Pagination abuse

Bound collection sizes.

Do not allow:

per_page=1000000

to cause excessive DB/memory load.

If configurable page size exists, enforce a maximum.

42. Date-range abuse

Agenda/availability endpoints must validate requested date ranges.

Do not allow arbitrarily huge ranges that trigger expensive queries.

Limits should follow real UX requirements and remain documented.

43. Query abuse

Search/filter endpoints must:

validate filters;
bound result sizes;
allowlist sorting;
avoid expensive uncontrolled query patterns.

Do not expose arbitrary SQL-like filtering.

44. N+1 and resource exhaustion

Performance weaknesses can become availability/security problems.

Audit:

N+1 queries;
unbounded collections;
expensive availability loops;
repeated DB queries;
missing indexes.

Do not optimize prematurely, but eliminate obvious resource exhaustion risks.

45. File uploads

No patient document upload is currently required.

Do not implement generic upload endpoints.

If professional profile images exist later, validate:

MIME/type;
size;
storage path;
generated filenames;
authorization.

Never trust original filenames as storage paths.

46. Path traversal

Any future file access must prevent user-controlled filesystem paths.

Never concatenate request values directly into local filesystem paths.

47. Deserialization

Do not use unsafe PHP deserialization on untrusted input.

Avoid unserialize() for client-controlled values.

Use JSON and validated structured input.

48. Command execution

Never pass request values into shell/system commands.

No current product feature requires arbitrary command execution.

49. SSRF

No current feature should fetch arbitrary user-provided URLs server-side.

If external HTTP integrations are added later, validate destinations and threat model them separately.

50. Email security

Email infrastructure is currently out of scope.

Do not add SMTP/provider credentials or email verification logic during this phase.

When email is implemented later, it will receive a separate security review.

51. Dependencies

Audit Composer dependencies for:

abandoned packages;
unnecessary packages;
known security advisories where tooling supports it.

Use:

composer audit

when available.

Do not upgrade major dependencies blindly during a security audit.

52. Laravel version

Use the repository's supported Laravel version.

Do not disable framework security features.

Review security-relevant framework configuration rather than replacing Laravel mechanisms with custom implementations.

53. Database credentials

Database users should follow least privilege in deployment where practical.

Application credentials must not be exposed through errors or source control.

Do not embed DB credentials in PHP source.

54. Test data

Never place real patient data in:

factories;
seeders;
fixtures;
committed database dumps.

Use synthetic data.

55. Security tests

High-value security tests include:

unauthenticated private access rejected;
unauthorized cross-psychologist access rejected;
patient IDOR rejected;
appointment IDOR rejected;
invalid status manipulation rejected;
public patient data exposure absent;
booking outside availability rejected;
double booking rejected;
malformed payload rejected;
excessive public requests rate-limited where configured.
56. Security audit classifications

Security findings should use:

P0 — Critical
P1 — High
P2 — Medium
P3 — Low

Examples of potentially P0/P1 findings:

public patient database exposure;
authentication bypass;
unrestricted private-resource modification;
plaintext passwords;
committed production secrets;
reliable double-booking/data-integrity bypass with serious operational impact.

Severity must reflect actual exploitability and impact.

57. Security fix strategy

For every security finding:

describe the vulnerability;
identify affected code;
explain impact;
identify attack preconditions;
propose minimal safe correction;
add regression test;
verify no related endpoint remains vulnerable.

Do not merely hide the UI element.

58. Security and backward compatibility

Do not preserve insecure behavior solely because the old frontend depends on it.

If a security correction changes an API contract:

document the change;
update API contract documentation;
later adapt the frontend.

Security takes priority over preserving unsafe legacy behavior.

59. No security theatre

Do not add complexity that does not mitigate a real threat.

Examples:

encoding IDs instead of authorizing resources;
custom encryption instead of protecting access;
random middleware with no defined threat;
unnecessary CAPTCHA before evidence/need.

Prefer framework-supported, testable controls.

60. Deployment security checklist

Before production deployment verify at minimum:

APP_ENV correct;
APP_DEBUG=false;
secrets not committed;
production CORS restricted;
authentication cookie settings correct;
HTTPS used;
DB credentials protected;
migrations reviewed;
rate limits active;
private routes authenticated;
authorization tests passing;
composer audit reviewed;
logs do not expose sensitive data.

Deployment configuration will be reviewed separately before delivery.

61. Final security principle

For every backend endpoint ask:

Can an unauthenticated attacker call it?
Can an authenticated psychologist access another protected resource?
Can input manipulate ownership or status?
Can the request expose patient information?
Can repeated/concurrent requests break data integrity?
Can the request cause excessive resource usage?
Can an error reveal internal information?
Is the expected security behavior tested?

Security is a backend property, not a frontend feature.
