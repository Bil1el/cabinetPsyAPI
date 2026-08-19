# Backend Architecture — Psychology Cabinet

## 1. Objective

This document defines the target architecture of the Laravel backend.

The backend already contains an existing implementation.

The objective is NOT to rebuild everything blindly.

For each existing domain:

1. inspect the current implementation;
2. identify correct behavior;
3. identify architectural problems;
4. identify security and data-integrity risks;
5. preserve correct behavior;
6. refactor only where justified;
7. verify behavior with tests.

Architecture exists to improve correctness, maintainability, security and testability.

Do not add abstraction solely to make the project look complex.

---

## 2. Technology

Primary backend:

- Laravel
- PHP
- MySQL
- Laravel authentication/Sanctum where appropriate
- Laravel validation
- Eloquent ORM
- Laravel Policies/Gates
- API Resources
- PHPUnit/Pest according to the existing project configuration

Use the versions already installed in the repository.

Do not downgrade or replace framework components without a demonstrated reason.

---

## 3. Target structure

The target architecture may use:

app/
├── DTO/
├── Enums/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   ├── Public/
│   │   └── Professional/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Models/
├── Policies/
├── Repositories/
│   ├── Contracts/
│   └── Eloquent/
├── Services/
├── Actions/
├── Support/
└── Providers/

Not every directory must exist.

Create a layer only when the implementation actually benefits from it.

Do not reorganize working code merely to match this tree visually.

---

## 4. Architectural dependency flow

For a complex write operation, prefer:

HTTP
↓
Route
↓
Controller
↓
Form Request
↓
DTO when useful
↓
Application/Domain Service
↓
Repository or Eloquent
↓
Database

Then:

Service result
↓
Controller
↓
API Resource
↓
JSON response

Authorization and business invariants apply at the appropriate layers.

---

## 5. HTTP layer

The HTTP layer is responsible for:

- receiving requests;
- authentication middleware;
- request validation;
- invoking application behavior;
- returning HTTP responses.

It should not become the location of the core booking algorithm.

HTTP-specific concepts should not unnecessarily leak into domain services.

---

## 6. Routes

Routes must be explicit and grouped by responsibility.

Conceptual separation:

Public API
- public psychologist information;
- public availability;
- public appointment booking.

Professional API
- authentication-required dashboard operations;
- appointments;
- patients;
- schedules;
- absences;
- settings.

Do not expose private resources through public route groups.

Route names and URI contracts should become stable before frontend integration.

---

## 7. Controllers

Controllers must remain thin.

A controller may:

- receive an already validated Form Request;
- authorize an operation;
- construct/use a DTO when appropriate;
- call a service;
- return a Resource or HTTP response.

Controllers should NOT contain:

- availability algorithms;
- large database workflows;
- duplicated validation;
- complex appointment transitions;
- patient deduplication algorithms;
- concurrency control;
- raw response mapping scattered across methods.

---

## 8. Form Requests

Use Laravel Form Requests for meaningful input validation.

Examples:

- LoginRequest
- StorePublicAppointmentRequest
- UpdateAppointmentRequest
- StoreScheduleRequest
- StoreAbsenceRequest
- UpdatePatientRequest

Names must ultimately follow the actual domain terminology used by the project.

Form Requests should define input-level validity.

Examples:

- required fields;
- string lengths;
- formats;
- dates;
- allowed enum values;
- basic relational existence where appropriate.

Complex business rules should not be duplicated inside validation rules when they belong to services.

---

## 9. DTOs

DTOs are appropriate when they create a useful boundary between HTTP input and business logic.

Strong candidates:

- public booking;
- appointment creation/update;
- schedule configuration;
- complex filtering.

Example:

StorePublicAppointmentRequest
↓
CreateAppointmentDTO
↓
AppointmentService

A service should not need to understand Laravel Request objects.

Do not create DTOs for every trivial read operation.

---

## 10. DTO immutability

Prefer DTOs that represent validated input and are not casually mutated during a workflow.

DTOs should contain the data required for the operation, not arbitrary request metadata.

---

## 11. Hydrators and factories

A Hydrator/Factory may convert validated data into a DTO when transformation is meaningful.

Use one when:

- conversion is reused;
- normalization is non-trivial;
- multiple fields require mapping;
- request structure differs meaningfully from service input.

Do not introduce a Hydrator merely to copy a few identical fields.

---

## 12. Services

Services contain meaningful application/business workflows.

Potential services:

- AppointmentService
- AvailabilityService
- ScheduleService
- AbsenceService
- PatientService
- DashboardService

The final service set must follow actual needs found during audit.

Services may:

- coordinate multiple models/repositories;
- enforce business rules;
- manage transactions;
- perform conflict checks;
- coordinate state transitions.

They should not return HTTP responses.

---

## 13. AvailabilityService

Availability should have one authoritative implementation.

Conceptually:

AvailabilityService
↓
load psychologist
↓
load relevant working schedule
↓
subtract absences
↓
subtract occupied appointment intervals
↓
apply consultation duration
↓
apply booking constraints
↓
produce available slots

Do not implement separate availability algorithms for:

- public booking;
- dashboard;
- appointment creation.

They must use coherent shared business rules.

---

## 14. AppointmentService

Appointment workflows should be centralized where complexity justifies it.

Potential responsibilities:

- public appointment creation;
- professional appointment creation if supported;
- confirmation;
- cancellation;
- completion;
- conflict detection;
- state-transition enforcement.

Do not allow arbitrary status mutation through generic model updates.

---

## 15. Booking creation

Public booking is a critical transaction.

Conceptually:

validated public request
↓
DTO
↓
AppointmentService
↓
begin transaction
↓
resolve psychologist
↓
validate/revalidate requested interval
↓
protect against concurrent booking
↓
resolve/create patient
↓
create appointment
↓
commit
↓
return result

If any critical step fails:

rollback
↓
return safe deterministic error

The exact concurrency implementation will be documented after database analysis.

---

## 16. ScheduleService

Schedule behavior should centralize rules concerning psychologist working periods.

It must prevent invalid schedule definitions.

Potential rules include:

- start before end;
- no invalid overlap;
- correct psychologist ownership;
- valid weekday/date representation.

Schedule modifications must not silently rewrite existing appointments.

---

## 17. AbsenceService

Absence behavior should centralize:

- creation;
- update;
- conflict detection;
- ownership;
- effect on availability.

Absence operations must not silently move/delete existing appointments.

---

## 18. PatientService

Patient logic may centralize:

- safe lookup;
- creation;
- updates;
- deduplication rules.

Patient matching must not use unsafe assumptions.

Do not merge two people solely because they share a common name.

---

## 19. DashboardService

Dashboard aggregation should happen efficiently on the backend.

It may calculate:

- today's appointment count;
- pending/action-required count;
- upcoming appointments;
- status counts.

Do not load all appointment rows into PHP merely to count them when the database can aggregate efficiently.

---

## 20. Repositories

Repositories are optional architectural boundaries.

Use repositories for:

- reusable complex queries;
- persistence behavior that should be isolated;
- queries used by multiple services;
- easier testing where genuinely valuable.

Do not create repository interfaces mechanically for every model.

---

## 21. Repository contracts

When a repository abstraction is justified:

Repositories/Contracts/AppointmentRepository.php

and implementation:

Repositories/Eloquent/EloquentAppointmentRepository.php

may be used.

Bind contracts to implementations through Laravel's service container.

Only introduce interfaces where substitution/boundary value exists.

---

## 22. Eloquent

Direct Eloquent usage is acceptable for simple operations.

Example:

A simple authenticated profile lookup does not require six architectural layers.

Architecture should remain proportional to domain complexity.

---

## 23. Models

Models should primarily contain:

- relationships;
- casts;
- scopes;
- simple domain predicates/helpers;
- model configuration.

Examples of relationships:

Psychologist
- appointments
- schedules
- absences

Patient
- appointments

Appointment
- patient
- psychologist

The actual model names must be based on the existing schema.

---

## 24. Avoid fat models

Do not put entire workflows such as public booking inside:

Appointment::book(...)

if this makes the model coordinate validation, patient creation, locking and persistence.

Use a dedicated service for complex workflows.

---

## 25. Enums

Use PHP backed enums for stable business values where appropriate.

Example:

AppointmentStatus::Pending
AppointmentStatus::Confirmed
AppointmentStatus::Completed
AppointmentStatus::Cancelled

Only use statuses confirmed by the final business rules.

Use Eloquent enum casts where useful.

---

## 26. Policies

Private resources require authorization.

Potential policies:

- AppointmentPolicy
- PatientPolicy
- SchedulePolicy
- AbsencePolicy

Policies should answer questions such as:

- can this professional view this record?
- can this professional update it?
- can this professional perform this transition?

Never trust resource IDs from the frontend.

---

## 27. Ownership model

The exact visibility model between the two psychologists must be resolved before final policy implementation.

Until resolved:

- inspect existing behavior;
- document it;
- prefer least privilege;
- do not silently introduce broad cross-professional access.

---

## 28. API Resources

Use Laravel API Resources to stabilize frontend-facing responses.

Potential resources:

- PsychologistResource
- AppointmentResource
- PatientResource
- AvailabilityResource
- ScheduleResource
- AbsenceResource
- DashboardResource

Only create Resources actually required by the API.

---

## 29. Public vs private resources

Public representations should contain less information than professional representations where appropriate.

Never expose patient information through a public appointment/availability Resource.

Consider separate Resources if public/private representations differ significantly.

---

## 30. Exceptions

Business conflicts should use explicit exceptions or equivalent typed domain/application errors.

Potential examples:

- SlotUnavailableException
- InvalidAppointmentTransitionException
- ScheduleConflictException
- AbsenceConflictException

Exception rendering should convert them into stable safe API responses.

Do not make controllers parse exception strings.

---

## 31. Error format

Final API errors should be predictable.

A business conflict may conceptually return:

{
"message": "The selected slot is no longer available.",
"code": "SLOT_UNAVAILABLE"
}

with HTTP 409.

Validation errors should use Laravel's established 422 structure unless a documented API convention replaces it.

---

## 32. Transactions

Use `DB::transaction()` for atomic multi-step write workflows.

Critical candidate:

public booking
→ patient resolution/creation
→ appointment creation

Transactions must be as short as practical.

Avoid slow unrelated work inside transactions.

---

## 33. Concurrency

Transactions alone do not automatically solve every race condition.

Booking correctness must consider concurrent requests.

The final implementation may require:

- row locking;
- unique/exclusion strategy compatible with MySQL;
- deterministic transactional conflict checks;
- database constraints.

The exact solution must be selected after inspecting the current appointment schema.

Never claim double-booking is solved without testing concurrent behavior.

---

## 34. Database constraints

Business rules that can safely be enforced by MySQL should have database support.

Examples:

- foreign keys;
- unique values where truly unique;
- non-null constraints;
- indexes.

Do not rely exclusively on controller checks.

---

## 35. Database migrations

Never casually rewrite historical migrations that may already have been executed in deployed environments.

For schema corrections, prefer new migrations unless the project is explicitly confirmed to have no deployed database requiring migration compatibility.

Audit before modifying migration history.

---

## 36. Database indexes

Indexes should follow actual query patterns.

Expected important query dimensions include:

- psychologist;
- appointment start/end;
- status;
- date/range;
- patient lookup.

Composite indexes may be appropriate for common combined filters.

Validate with actual queries/schema.

---

## 37. Time representation

All date/time logic must use one documented strategy.

The architecture must define:

- database storage timezone;
- application timezone;
- cabinet timezone;
- API serialization format.

Do not mix local strings and UTC timestamps unpredictably.

Use Carbon consistently.

---

## 38. Authentication architecture

Authentication is defined in:

`docs/AUTHENTICATION.md`

Do not implement a second competing auth mechanism inside domain code.

Controllers/services should use the authenticated identity supplied by Laravel's authentication layer.

---

## 39. Authorization architecture

Authentication identifies the professional.

Policies/authorization determine what that professional may do.

Do not replace Policies with frontend filtering.

---

## 40. Security boundary

All incoming data is untrusted.

This includes:

- JSON bodies;
- query parameters;
- route parameters;
- headers;
- IDs;
- dates;
- status values.

Validate and authorize at backend boundaries.

---

## 41. Rate limiting

Use Laravel rate limiting for abuse-sensitive routes.

Separate limits may be appropriate for:

- login;
- public booking;
- availability.

Rate-limit architecture must remain configurable.

---

## 42. Configuration

Environment-specific values belong in configuration/environment variables.

Application code should read configuration using Laravel config.

Avoid direct `env()` usage outside configuration files.

Never commit production secrets.

---

## 43. Logging

Use structured useful logs where needed.

Never intentionally log:

- passwords;
- tokens;
- secrets;
- full sensitive patient payloads.

Business errors expected during normal use should not flood logs as critical server failures.

---

## 44. API versioning

Do not introduce API versioning solely for appearance.

If the existing API already uses a version convention, preserve/evaluate it.

If versioning becomes necessary before frontend contract freeze, document the decision explicitly.

---

## 45. Pagination

Use Laravel pagination for potentially large private collections.

Pagination response structure should be stable for frontend consumption.

Avoid manual ad-hoc pagination formats across controllers.

---

## 46. Filtering

Complex list filters should be explicit.

Potential appointment filters:

- date range;
- psychologist;
- status;
- patient search.

Validate filter parameters.

Avoid controllers with uncontrolled query-building duplication.

---

## 47. Sorting

Expose only supported sorting fields.

Never insert arbitrary client-provided column names directly into raw SQL.

Use allowlists.

---

## 48. N+1 prevention

Resources accessing relationships should use intentional eager loading.

Audit API endpoints for N+1 behavior.

Do not globally eager-load everything.

---

## 49. Service container

Use Laravel dependency injection.

When contracts/interfaces are justified, bind them through service providers.

Avoid service-locator patterns and excessive `app()` calls inside business logic.

---

## 50. Dependency direction

Preferred dependency direction:

HTTP
→ application/domain logic
→ persistence

Lower-level domain/business logic should not depend on controllers.

Repositories should not depend on HTTP Requests.

DTOs should not depend on controllers.

---

## 51. No circular architecture

Avoid patterns where:

Service A → Service B → Service A

or repositories call controllers/services in reverse.

If orchestration becomes complex, introduce a clear higher-level operation rather than circular dependencies.

---

## 52. Actions

Actions are optional.

Use an Action when one application use-case benefits from a dedicated orchestration object.

Example:

CreatePublicAppointment

may be appropriate if AppointmentService becomes an unrelated collection of many operations.

Do not create both an Action and Service for the same trivial logic without reason.

---

## 53. Naming

Names should communicate business intent.

Prefer:

CalculateAvailability
CreatePublicAppointment
CancelAppointment

over vague names such as:

Manager
Helper
Processor
CommonService

Avoid generic `Utils` for business logic.

---

## 54. Helpers

Generic helpers should not become a dumping ground for domain rules.

Availability and booking rules belong in explicit domain/application classes.

---

## 55. Response consistency

Do not manually invent different JSON envelopes in every controller.

Use:

- Resources;
- pagination conventions;
- documented error responses.

The final API contract will be recorded in:

`docs/API_CONTRACTS.md`

---

## 56. Frontend integration

After backend finalization, the frontend must adapt to the backend.

The frontend should derive its:

- endpoint definitions;
- request types;
- response types;
- status handling;
- validation mapping;

from the stabilized backend API contracts.

Do not distort backend business integrity merely to make frontend implementation easier.

---

## 57. Testing architecture

Feature tests should verify API behavior across real Laravel boundaries.

Unit tests should target isolated business rules where valuable.

High-value test areas:

- booking;
- availability;
- authorization;
- status transitions;
- schedule rules;
- absence rules;
- validation.

---

## 58. Static analysis and quality

Use existing project quality tooling.

Do not add a large toolchain before auditing what already exists.

Potential checks include:

- Laravel test suite;
- formatter/linter already configured;
- static analysis if already configured or later explicitly approved.

Do not silence real type/static-analysis problems with unsafe workarounds.

---

## 59. Refactoring strategy

Because the backend already exists, refactor incrementally.

Preferred sequence:

Audit
↓
Tests around important existing behavior
↓
Fix security/data-integrity risks
↓
Extract business logic where justified
↓
Stabilize database
↓
Stabilize API
↓
Complete missing behavior
↓
Regression tests

Avoid a large uncontrolled rewrite.

---

## 60. Architecture review questions

Before accepting a backend implementation, ask:

1. Is this logic in the correct layer?
2. Is authorization enforced server-side?
3. Is input validated?
4. Is database integrity protected?
5. Can concurrent requests break the invariant?
6. Is the API response intentionally shaped?
7. Is sensitive data minimized?
8. Is the behavior testable?
9. Is this abstraction providing real value?
10. Will the frontend have a stable contract?

---

## 61. Final architecture principle

Use professional architecture without architecture theatre.

Complex domains such as booking and availability deserve explicit boundaries.

Simple operations should remain simple.

The final backend should be:

- secure;
- predictable;
- testable;
- maintainable;
- efficient;
- understandable;
- difficult to misuse.
