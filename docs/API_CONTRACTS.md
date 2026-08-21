# API Contracts — Psychology Cabinet

## 1. Purpose

This document defines the rules governing the backend API contract.

The Laravel backend already exists partially.

Therefore this document does NOT invent the final endpoint list.

The final API contract must be produced from:

- existing Laravel routes;
- Controllers;
- Form Requests;
- API Resources;
- authentication configuration;
- Policies;
- Services;
- business rules;
- database model.

Before frontend integration, every required API contract must be verified and documented here.

---

## 2. Backend is authoritative

The frontend will be adapted to the finalized backend.

Do not change backend business rules merely to accommodate temporary frontend assumptions.

The backend defines:

- accepted request payloads;
- response structures;
- authentication requirements;
- authorization;
- status codes;
- business errors;
- pagination;
- filters;
- date formats.

---

## 3. Audit before contract changes

Before modifying an existing endpoint, inspect:

1. route;
2. middleware;
3. Controller;
4. Form Request;
5. Policy/authorization;
6. Service;
7. Resource;
8. tests;
9. known frontend dependency if any.

Classify the endpoint as:

- KEEP;
- FIX;
- REFACTOR;
- REMOVE;
- MISSING;
- UNKNOWN.

Do not remove an endpoint only because another design looks cleaner.

---

## 4. API domains

The final API is expected to cover the required domains:

### Public

- public psychologist information;
- public availability;
- public appointment booking.

### Professional/private

- authentication/current professional;
- dashboard;
- agenda;
- appointments;
- patients;
- working schedules;
- absences;
- professional settings/profile.

Only expose endpoints actually required by the final product.

---

## 5. Public/private separation

Public endpoints must not require professional authentication when their product purpose is public.

Private endpoints must use the final authentication middleware.

Never expose private patient or appointment information through a public route for frontend convenience.

---

## 6. Public psychologists

The public frontend needs enough information to allow the visitor to identify/select one of the cabinet's psychologists.

Expose only intentionally public profile information.

Do not expose:

- authentication fields;
- password-related fields;
- internal permissions;
- private account metadata.

The exact endpoint and response must be derived from the implemented backend.

---

## 7. Public psychologists

### `GET /api/public/psychologists`

- Authentication: public.
- Returns active psychologists only, ordered by last name, first name, then ID.
- Each item contains: `id`, `firstName`, `lastName`, `speciality`, `bio`, and `photo`.
- The returned `id` is the identifier accepted by the public availability endpoint and public booking request.
- No user/account, contact, schedule, absence, appointment, patient, authorization, timestamp, or internal booking configuration data is returned.

Success response:

```json
{
  "data": [
    {
      "id": 1,
      "firstName": "Claire",
      "lastName": "Martin",
      "speciality": "Psychologie clinique",
      "bio": null,
      "photo": null
    }
  ]
}
```

## 8. Public availability

The public frontend requires availability for a psychologist and relevant date/range.

Availability is calculated by the backend.

A request must provide `date=YYYY-MM-DD` and `type=in_person|online`. A public availability response may expose:

- date;
- available start time;
- end time when useful;
- other non-sensitive booking metadata.

It must NOT expose:

- patient data;
- private appointment details;
- reason a specific occupied slot is unavailable;
- private professional information.

---

Working-hour modes are `in_person`, `online`, and `both`. Only compatible ranges contribute slots. Absences and blocking `pending`/`confirmed` appointments remove overlapping slots for both consultation types.

## 9. Public booking

The public booking endpoint is unauthenticated but protected.

It must:

- validate input;
- resolve the selected psychologist;
- revalidate availability;
- protect against concurrent booking;
- resolve/create patient according to final rules;
- create the appointment transactionally;
- return a safe response.

Do not trust availability previously returned to the frontend.

---

The request must reuse the selected consultation `type` (`in_person` or `online`). The backend recalculates the appointment end from the psychologist consultation duration and revalidates the slot and working-hour mode inside the booking transaction.

## 10. Booking response

A successful booking response should contain only information required for the public UX.

Do not return an entire Patient model.

Do not expose unrelated private appointment fields.

The exact final Resource must be documented after implementation.

---

## 11. Authentication API

Professional authentication must use one coherent Laravel authentication strategy.

The final contract must document:

- login flow;
- logout flow;
- current authenticated professional;
- CSRF flow if applicable;
- authentication middleware;
- expected unauthenticated response.

Do not introduce multiple competing authentication methods.

---

## 12. Current professional

The private frontend needs a safe way to know the current authenticated professional.

The response should expose only information required by the application.

Never return:

- password hash;
- remember token;
- internal secrets;
- unnecessary security metadata.

---

## 13. Dashboard API

Dashboard endpoints should return actual backend-derived operational data.

Possible information:

- appointments today;
- pending/action-required appointments;
- upcoming appointments;
- relevant status counts.

Do not return fake values.

Avoid forcing the frontend to retrieve all appointments to calculate simple metrics.

---

## 14. Agenda API

Agenda retrieval should accept a bounded date range.

The backend should efficiently return appointments relevant to that range and authorized scope.

Do not expose unlimited appointment history for every agenda request.

---

## 15. Appointment API

Private appointment endpoints must support only required operations.

Potential operations include:

- list;
- show;
- confirm;
- cancel;
- complete;
- create professionally if confirmed as required.

Do not implement unrestricted generic status mutation.

Business transitions must remain explicit.

---

## 16. Patient API

Private patient endpoints may support:

- paginated listing;
- search;
- detail;
- allowed updates;
- appointment history when appropriate.

Every patient endpoint requires authorization.

No public patient directory may exist.

---

## 17. Schedule API

Working schedule endpoints operate on the appropriate psychologist.

The API must support the final schedule representation.

Do not allow arbitrary psychologist IDs to bypass authorization.

Schedule writes must use backend validation and conflict rules.

---

## 18. Absence API

Absence endpoints must support required:

- listing;
- creation;
- update;
- deletion if allowed.

Writes must enforce:

- valid interval;
- ownership/authorization;
- conflict behavior with appointments.

---

## 19. Settings/profile API

Only expose settings that actually exist in the product.

Distinguish:

- public psychologist profile;
- authentication account;
- private settings.

Never allow a profile update endpoint to modify role/permissions accidentally.

`PATCH /api/psychologist/profile` rejects `photo`. The only supported photo write is authenticated multipart upload through `POST /api/psychologist/profile/photo` with a required JPEG, PNG, or WebP image of at most 5 MiB. Both private and public psychologist resources return the resolved public URL in `photo`.

---

## 20. API Resources

Prefer Laravel API Resources for stable responses.

Do not expose raw models by default.

## 21. Finalized route summary

Public routes: `GET /api/public/psychologists`, `GET /api/psychologists/{psychologist}/availability?date=YYYY-MM-DD&type=in_person|online`, and `POST /api/public/appointments`. Public availability and booking use the psychologist primary key returned by the public list. A public psychologist has an active (`is_active=true`) profile and an active professional account. Unknown, inactive, invited, or suspended psychologist IDs return `404` from availability and public booking; an eligible psychologist with no available slot returns `200` with an empty `data` array. Booking returns `201`; slot conflicts return `409` with `SLOT_UNAVAILABLE` and never disclose patient resolution.

Private Sanctum routes require an active, verified professional account associated with a psychologist. `users.status` (`invited`, `active`, `suspended`) controls dashboard access and public eligibility; `psychologists.is_active` is the separate profile-level public visibility/bookability switch. There is no public registration route. Admins create invitations, which create an invited user and an inactive psychologist profile. Invitation tokens are random, SHA-256 hashed at rest, single use and expire after 48 hours.

Account lifecycle endpoints: `POST /api/account/invitations/accept` accepts an invitation token and password confirmation; `POST /api/account/password/forgot` always returns a generic response; `POST /api/account/password/reset` uses Laravel’s one-time, expiring password broker token; `PUT /api/account/password` changes a signed-in user’s password; `POST /api/account/email-change` requests a change while keeping the old email active; `POST /api/account/email/confirm` applies the new address only after confirmation. Email links use `FRONTEND_URL`; invitations target `/invitation/accept`, reset links `/mot-de-passe/reinitialiser`, and email confirmation `/email/confirm`. Admin-only endpoints are `POST /api/admin/psychologists/invitations`, `DELETE /api/admin/psychologists/invitations/{invitation}`, `PATCH /api/admin/users/{user}/suspend`, and `PATCH /api/admin/users/{user}/reactivate`.

Collection routes use the standard Laravel Resource envelope (`data` and, when paginated, `links` and `meta`). Patients and appointments accept validated `per_page` from 1 to 100; appointments additionally accept validated `date` (`YYYY-MM-DD`), `from`, `to`, `status`, and `patient_id` filters.

Appointment `from`/`to` ranges must not exceed 366 days. Patient detail embeds the 20 most recent appointments; complete history remains available through the authenticated, scoped, paginated appointment list filtered by `patient_id`.

Datetime fields are ISO-8601 UTC strings (`startsAt`, `endsAt`, lifecycle timestamps and absence intervals). Working-hour values are `HH:MM` and each range requires `mode=in_person|online|both`; availability requires `date` in `YYYY-MM-DD` plus `type=in_person|online` and returns ISO-8601 slots. Validation failures return `422`; unauthenticated access `401`; forbidden access `403`; unresolved route models `404`; state conflicts `409`; throttling `429`. Finalized business codes are `SLOT_UNAVAILABLE`, `INVALID_APPOINTMENT_TRANSITION`, `SCHEDULE_CONFLICT`, `ABSENCE_CONFLICT`, and `PATIENT_IDENTITY_CONFLICT`.

Resources must intentionally select fields.

Separate public/private Resources when their privacy requirements differ substantially.

## Implemented endpoint matrix

This matrix records the routes currently implemented. Unless noted otherwise, API
Resources use Laravel's `data` envelope; paginated collections also include
`links` and `meta`.

| Method and URI | Access / middleware | Validated input | Success response |
| --- | --- | --- | --- |
| `POST /api/login` | Public; `throttle:login` | `email`, `password`, optional boolean `remember` | `200` `{ message, user }`, where `user` has `id`, `name`, `email`, `role` |
| `GET /api/public/psychologists` | Public; `throttle:60,1` | None | Active profiles only, ordered by last name, first name, ID; each has `id`, `firstName`, `lastName`, `speciality`, `bio`, `photo` |
| `GET /api/psychologists/{psychologist}/availability` | Public; `throttle:60,1` | Query `date` (`YYYY-MM-DD`, today or later) | `data` array of `{ startsAt, endsAt }` ISO-8601 slots; `404` for unknown/inactive psychologist |
| `POST /api/public/appointments` | Public; `throttle:public-booking` | `psychologist_id`; `starts_at`; appointment `type`; required nested `patient` (`first_name`, `last_name`, `email`, `phone`, optional `birth_date`); optional `patient_message`; `patient_id` prohibited | `201` public appointment with only `id`, `startsAt`, `endsAt`, `status`, `type`; `404` for unknown/inactive psychologist |
| `GET /api/me` | Sanctum | None | Current professional user: `id`, `name`, `email`, `role` |
| `POST /api/logout` | Sanctum | None | `200` `{ message }`; invalidates the session |
| `GET /api/dashboard` | Sanctum; current psychologist policy | None | `appointmentsToday`, `appointmentsThisWeek`, `appointmentsPending`, `appointmentsConfirmed`, `patientsCount`, `nextAppointments` |
| `GET /api/psychologist/profile` | Sanctum; current psychologist policy | None | Private psychologist profile: `id`, names, `email`, `phone`, `speciality`, `bio`, `photo`, `consultationDuration`, `isActive` |
| `PATCH /api/psychologist/profile` | Sanctum; current psychologist policy | Optional profile fields: names, `phone`, `speciality`, `bio`, `consultation_duration` (15–240), `is_active`; `email` and `photo` are prohibited | Updated private psychologist profile |
| `GET /api/working-hours` | Sanctum; current psychologist policy | None | Working-hour collection: `id`, `dayOfWeek`, `startsAt`, `endsAt`, `mode`, `isActive` |
| `PUT /api/working-hours` | Sanctum; current psychologist policy | Required `ranges` array (maximum 28); each range has weekday enum, `starts_at`, `ends_at` (`HH:MM`, end after start), required `mode`, optional `is_active` | Replaced working-hour collection; `409 SCHEDULE_CONFLICT` when protected future appointments would no longer fit |
| `GET /api/absences` | Sanctum; absence policy | Optional `per_page` 1–100 | Paginated absence collection: `id`, `startsAt`, `endsAt`, `reason` |
| `POST /api/absences` | Sanctum; absence policy | `starts_at`, `ends_at` (end after start), optional `reason` (max 500) | Created absence; `409 ABSENCE_CONFLICT` for protected appointment or absence conflicts |
| `GET /api/absences/{absence}` | Sanctum; ownership policy | None | One absence |
| `PATCH /api/absences/{absence}` | Sanctum; ownership policy | Optional absence fields, with interval validation | Updated absence; `409 ABSENCE_CONFLICT` when applicable |
| `DELETE /api/absences/{absence}` | Sanctum; ownership policy | None | `204 No Content` |
| `GET /api/patients` | Sanctum; patient policy | Optional `search` (max 100), `per_page` 1–100 | Paginated private patient collection |
| `POST /api/patients` | Sanctum; patient policy | `first_name`, `last_name`, `email`, `phone`; optional past `birth_date` | Created private patient |
| `GET /api/patients/{patient}` | Sanctum; ownership policy | None | Private patient plus up to 20 latest appointments |
| `PATCH /api/patients/{patient}` | Sanctum; ownership policy | Optional patient fields with the same validation | Updated private patient |
| `GET /api/appointments` | Sanctum; appointment policy | Optional `date`, `from`, `to` (maximum 366-day range), status enum, `patient_id`, `per_page` 1–100 | Paginated private appointment collection, scoped to current psychologist |
| `POST /api/appointments` | Sanctum; appointment policy | `starts_at`, appointment `type`, optional `patient_message`, and either an owned `patient_id` or nested patient details | Created private appointment; every structurally valid but unavailable slot returns `409 SLOT_UNAVAILABLE` |
| `GET /api/appointments/{appointment}` | Sanctum; ownership policy | None | One private appointment |
| `PATCH /api/appointments/{appointment}` | Sanctum; ownership policy | Optional owned `patient_id`, `starts_at`, type, `patient_message` | Updated private appointment; unavailable replacement slot returns `409 SLOT_UNAVAILABLE`; terminal appointments return `422 INVALID_APPOINTMENT_TRANSITION` |
| `PATCH /api/appointments/{appointment}/confirm` | Sanctum; ownership policy | None | Confirmed appointment; invalid transition returns `422 INVALID_APPOINTMENT_TRANSITION` |
| `PATCH /api/appointments/{appointment}/cancel` | Sanctum; ownership policy | Optional `cancellation_reason` (max 1000) | Cancelled appointment; invalid transition returns `422 INVALID_APPOINTMENT_TRANSITION` |
| `PATCH /api/appointments/{appointment}/complete` | Sanctum; ownership policy | None | Completed appointment; invalid transition returns `422 INVALID_APPOINTMENT_TRANSITION` |

Private patient fields are returned only through routes protected by the patient
or appointment ownership policy. The private patient representation is `id`,
`firstName`, `lastName`, `email`, `phone`, `birthDate`, `createdAt`, and (only
when loaded for patient detail) `appointments`. A private appointment contains
`psychologistId` and, when loaded, a compact `psychologist` with only `id`,
`firstName`, `lastName`, and `speciality`; it never contains the professional
account email or phone. Complete patient history remains the scoped,
paginated appointment list filtered by `patient_id`.

All Form Request validation failures use Laravel's `422` JSON validation shape.
Missing authentication is `401`; policy denial is `403`; unresolved route models
are `404`; throttled requests are `429`. Public booking maps every current-state
unavailability outcome to the same safe `409` body:

```json
{
  "message": "Le créneau sélectionné n’est plus disponible.",
  "code": "SLOT_UNAVAILABLE"
}
```

## Endpoint implementation map

| Domain | Controller | Request(s) | Service | Resource / policy |
| --- | --- | --- | --- | --- |
| Authentication | `Api\\AuthController` | `LoginRequest` | `AuthService` | `UserResource`; professional eligibility is enforced by the service |
| Public directory | `Public\\PublicPsychologistController` | — | `PsychologistService` | `PublicPsychologistResource` |
| Public availability | `Public\\AvailabilityController` | `AvailabilityRequest` | `AvailabilityService` | controlled `{data: slots}` response |
| Public booking | `Public\\PublicAppointmentController` | `PublicStoreAppointmentRequest` | `AppointmentService` | `PublicAppointmentResource` |
| Dashboard / profile | `Dashboard\\DashboardController`, `Dashboard\\PsychologistController` | `UpdatePsychologistRequest` | `DashboardService`, `PsychologistService` | `DashboardResource`, `PsychologistResource`; current-professional policy |
| Working hours | `Dashboard\\WorkingHoursController` | `UpdateWorkingHoursRequest` | `WorkingHoursService` | `WorkingHoursResource`; `PsychologistPolicy` |
| Absences | `Dashboard\\AbsenceController` | `AbsenceIndexRequest`, `StoreAbsenceRequest`, `UpdateAbsenceRequest` | `AbsenceService` | `AbsenceResource`; `AbsencePolicy` |
| Patients | `Dashboard\\PatientController` | `PatientIndexRequest`, `StorePatientRequest`, `UpdatePatientRequest` | `PatientService` | `PatientResource`; `PatientPolicy` |
| Appointments | `Dashboard\\AppointmentController` | `AppointmentIndexRequest`, `StoreAppointmentRequest`, `UpdateAppointmentRequest`, `CancelAppointmentRequest` | `AppointmentService` | `AppointmentResource`; `AppointmentPolicy` |

All private controllers derive the psychologist scope from the authenticated
professional; route or payload IDs are never proof of ownership. The common
error semantics are: `401` unauthenticated, `403` policy denial, `404` missing
or non-public resource, `409` documented business conflict, `422` Form Request
validation or invalid state transition, and `429` throttling. Business codes
are emitted where listed above; validation uses Laravel's `errors` object.

---

## 20. Naming convention

Use one consistent JSON naming convention.

Do not mix arbitrarily:

- psychologist_id;
- psychologistId;

across endpoints.

Audit the existing convention before choosing the final standard.

Once stabilized, document it and preserve it for frontend integration.

---

## 21. Response consistency

Successful API responses should use a predictable convention.

Do not return:

Endpoint A:
`{ "data": ... }`

Endpoint B:
raw model

Endpoint C:
`{ "result": ... }`

without intentional documented reason.

Prefer Laravel Resource conventions unless the existing project has a justified established contract.

---

## 22. Collection responses

Collections should use consistent Resource collections/pagination.

Potentially large private datasets must not be unbounded.

Important examples:

- appointments;
- patients.

---

## 23. Pagination

The final contract must document:

- pagination query parameters;
- default page size;
- maximum page size if applicable;
- response metadata.

Use Laravel pagination conventions unless there is a reason not to.

---

## 24. Filtering

Only documented filters should be supported.

Potential appointment filters:

- date range;
- status;
- psychologist when authorized;
- patient search.

Validate filter values.

Do not pass arbitrary client parameters directly into database queries.

---

## 25. Sorting

Sorting fields must be allowlisted.

Never use arbitrary client-provided SQL column names.

The final API contract must list supported sort fields if sorting is exposed.

---

## 26. Validation errors

Use HTTP:

`422 Unprocessable Entity`

for request validation failures.

Prefer Laravel's established validation error format unless a consistent project-wide API format is intentionally implemented.

Frontend integration must map field errors from this contract.

---

## 27. Authentication errors

Use:

`401 Unauthorized`

when professional authentication is required but missing/invalid.

Do not return 500 for normal unauthenticated requests.

---

## 28. Authorization errors

Use:

`403 Forbidden`

when an authenticated professional is not permitted to perform the operation.

Authorization must be enforced server-side.

---

## 29. Not found

Use:

`404 Not Found`

for missing resources according to the final resource visibility policy.

Responses must not leak private information.

---

## 30. Business conflicts

Use:

`409 Conflict`

for valid requests that cannot be executed because current business state conflicts.

Examples:

- requested slot became unavailable;
- absence conflicts with protected appointment;
- invalid state transition where conflict semantics are appropriate.

---

## 31. Rate limiting

Use:

`429 Too Many Requests`

when a configured rate limit is exceeded.

Public booking and login are important abuse-sensitive endpoints.

---

## 32. Server errors

Unexpected server failures return safe 5xx responses.

Production responses must not expose:

- stack traces;
- SQLSTATE;
- database credentials;
- filesystem paths;
- environment values.

---

## 33. Business error codes

Frontend-actionable business conflicts should use stable machine-readable codes where useful.

Potential examples:

- `SLOT_UNAVAILABLE`
- `INVALID_APPOINTMENT_TRANSITION`
- `SCHEDULE_CONFLICT`
- `ABSENCE_CONFLICT`

Do not require the frontend to parse human-readable exception messages.

---

## 34. Example conflict

Conceptual example:

```json
{
  "message": "The selected slot is no longer available.",
  "code": "SLOT_UNAVAILABLE"
}

HTTP:

409 Conflict

This is an example contract shape, not permission to implement it differently from an already established project-wide error convention without review.

35. Dates and times

The final API must use one documented date/time format.

Prefer an unambiguous ISO-8601-compatible representation.

The final contract must specify:

timezone semantics;
appointment datetime format;
absence datetime format;
schedule time format;
date-only query format.

Do not send locale-formatted strings as authoritative API values.

36. Boolean/null semantics

Use real JSON:

true;
false;
null;

rather than strings such as:

"true";
"false";
"null".
37. IDs

Treat IDs as opaque identifiers from the frontend perspective.

The frontend may send a resource ID but backend authorization remains mandatory.

Never rely on ID obscurity for security.

38. Mass assignment

Request payloads must not be mapped blindly to Eloquent models.

The API must not allow clients to arbitrarily control:

ownership;
role;
privileged status;
internal identifiers;
security fields.
39. Status changes

Do not expose a generic endpoint that allows arbitrary appointment status values unless all transitions are centrally validated.

Prefer explicit operations or a strongly validated transition service.

40. Idempotency/repeated requests

Audit behavior when a user submits the same booking request multiple times.

Do not claim full idempotency unless explicitly implemented.

At minimum, concurrent/repeated requests must not violate double-booking invariants.

41. CORS

The API contract includes browser-origin expectations.

Production CORS must be restricted to intended frontend origins.

Do not use wildcard credentialed CORS.

42. CSRF

If professional authentication uses cookie/session-based Sanctum authentication, frontend integration must follow the required CSRF flow.

Do not disable CSRF to simplify the API.

43. API secrets

No secret API credential may be embedded in frontend-consumable responses.

Frontend environment variables are public from a security perspective.

44. Email independence

No current endpoint contract should require email delivery for successful core appointment behavior.

Email integration is deferred.

45. API tests

Critical endpoints require Feature tests covering:

success;
validation;
authentication;
authorization;
conflict;
not-found behavior;
privacy-sensitive response shape.

Booking tests must include slot conflicts.

46. Contract freeze

Before frontend implementation begins, the backend team/agent must produce the final endpoint inventory.

For every endpoint document:

HTTP method;
URI;
authentication;
authorization;
request/query parameters;
validation;
success status;
response Resource;
error statuses;
business error codes.

Only then is that endpoint considered ready for frontend integration.

47. Final endpoint inventory template

Use this format after backend audit/finalization:

DOMAIN:
Appointment


METHOD:
POST


URI:
<verified URI>


ACCESS:
Public / Authenticated


REQUEST:
<verified payload>


SUCCESS:
<verified HTTP status>


RESPONSE:
<verified Resource/JSON>


ERRORS:
422 ...
409 ...


BUSINESS CODES:
SLOT_UNAVAILABLE


IMPLEMENTATION:
Controller:
Request:
Service:
Resource:
Policy:


TESTS:
<test references>

Do not fill <verified ...> values until they are confirmed from the actual backend implementation.

48. Frontend handoff

After the backend is complete, the frontend agent must read:

this document;
verified route inventory;
Resources;
Requests;
authentication contract;
business error codes.

Frontend implementation must not invent missing API behavior.

If the frontend needs an operation that the final API does not provide, report the missing contract instead of creating fake client-side behavior.

49. Contract change policy

After frontend integration starts, changes to verified contracts must be intentional.

A contract change should identify:

backend reason;
affected endpoint;
request/response change;
frontend impact;
tests requiring update.

Avoid casual API drift.

50. Final principle

An API contract is not merely a route that happens to return JSON.

A frontend-ready endpoint has a stable definition for:

access;
input;
validation;
business behavior;
response;
errors;
privacy;
authorization.

The backend must establish these contracts before the frontend is adapted to it.
