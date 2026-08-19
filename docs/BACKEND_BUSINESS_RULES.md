# Backend Business Rules — Psychology Cabinet

## 1. Purpose

This document defines the authoritative business rules of the psychology cabinet backend.

These rules take precedence over assumptions made from UI screens or existing legacy code.

If existing backend behavior conflicts with these rules, the conflict must be reported during the audit before refactoring.

---

## 2. Cabinet

The current product manages one psychology cabinet.

The cabinet currently contains two psychologists.

The system must work correctly for both psychologists independently.

The number two is a current business fact, not an architectural limitation.

Core business logic must therefore operate using psychologist records and relationships, not hardcoded psychologist IDs.

---

## 3. Psychologist independence

Each psychologist has their own:

- professional profile;
- working schedule;
- absences;
- appointments;
- calculated availability.

An operation affecting Psychologist A must not accidentally modify Psychologist B's:

- schedule;
- absences;
- appointments;
- availability.

---

## 4. Professional access between the two psychologists

The exact private visibility model between the two psychologists must be verified against the existing backend and final cabinet requirement.

Do NOT guess whether:

- each psychologist sees only their own patients and appointments;

or:

- both psychologists share some cabinet-wide operational visibility.

Until this is explicitly resolved, security-sensitive implementation must follow least privilege.

The audit must identify the current behavior.

---

## 5. Patients

Patients do not require accounts.

A patient exists as business data associated with appointments.

Do not create:

- patient login;
- patient password;
- patient dashboard;
- patient authentication tokens;

unless the product scope changes later.

---

## 6. Patient data minimization

Only collect patient information required for appointment management.

Expected basic information may include:

- first name;
- last name;
- email;
- phone.

The exact required fields must be reconciled with the existing database before finalization.

Do not introduce clinical information.

---

## 7. Patient deduplication

The backend should avoid uncontrolled duplicate patient creation.

However, it must also avoid incorrectly merging two different people.

Do NOT use name alone as a unique identity.

The existing backend must be audited to determine its current patient matching strategy.

The final strategy must be documented in `DATABASE.md`.

---

## 8. Appointment ownership

Every appointment belongs to exactly one psychologist.

An appointment cannot exist without a valid psychologist.

An appointment must also reference the appropriate patient record according to the final database model.

---

## 9. Appointment interval

An appointment must have an unambiguous time interval.

Conceptually:

start_at < end_at

An appointment with:

- invalid date;
- invalid start time;
- zero duration;
- negative duration;

must not be accepted.

---

## 10. Appointment duration

The backend must have one authoritative rule for appointment duration.

The duration may eventually be:

- globally configured;
- configured per psychologist;
- derived from appointment type;

depending on what the existing project actually requires.

Do not hardcode duration independently across controllers.

The audit must determine the current implementation before the final rule is selected.

---

## 11. Appointment statuses

The final set of statuses must be small and explicit.

Expected candidates:

- pending;
- confirmed;
- completed;
- cancelled.

`no_show` may exist only if confirmed as required.

The audit must inspect existing migrations, enums, controllers, services and frontend expectations before finalizing the enum.

---

## 12. Public booking initial status

The intended product behavior is that a patient submits a request and a psychologist can validate or cancel it.

Therefore the expected initial public-booking status is:

pending

However, Codex must verify whether the existing backend already implements a different intentional workflow.

Any disagreement must be reported before changing persisted status behavior.

---

## 13. Pending appointments reserve their slot

Unless explicitly changed after audit, a valid pending appointment must be treated as occupying its time interval.

Otherwise several patients could request the same slot while waiting for confirmation.

Therefore availability calculations should normally exclude appointments whose statuses reserve a slot.

The exact set of blocking statuses must be centralized.

---

## 14. Blocking appointment statuses

The final implementation must explicitly define which statuses occupy availability.

Expected behavior:

- pending → blocks slot;
- confirmed → blocks slot;
- completed → historical appointment, normally represents an already occupied past interval;
- cancelled → does not block future availability.

Do not scatter these decisions across multiple queries.

---

## 15. Appointment transitions

Status transitions must be explicit.

Expected conceptual transitions may include:

pending
→ confirmed

pending
→ cancelled

confirmed
→ completed

confirmed
→ cancelled

Other transitions must be justified.

Do not permit generic unrestricted:

PATCH status=<anything>

---

## 16. Invalid transitions

Examples that should normally be rejected:

cancelled
→ confirmed

completed
→ pending

completed
→ confirmed

The final transition matrix must be implemented centrally and tested.

---

## 17. Cancellation

Cancellation changes appointment state.

It must not normally hard-delete the appointment.

A cancelled appointment remains useful as operational history.

Cancellation should release future availability when appropriate.

---

## 18. Completion

A completed appointment represents an appointment that occurred.

Completion must be performed only by an authorized professional.

Do not automatically mark appointments completed merely because their end time has passed unless such automation is explicitly approved.

---

## 19. Working schedules

Each psychologist has independent working schedules.

A schedule defines normal working periods.

Example:

Monday:
09:00–12:00
14:00–18:00

Psychologist B may have different periods.

---

## 20. Invalid schedule intervals

A working interval is invalid when:

- start >= end;
- weekday/date representation is invalid;
- required psychologist does not exist;
- interval violates final overlap rules.

Invalid schedules must be rejected by the backend.

---

## 21. Schedule overlaps

Two working intervals for the same psychologist must not create ambiguous overlapping availability.

For example:

09:00–13:00
12:00–17:00

should not exist as two independent overlapping periods unless the model intentionally normalizes them.

The final implementation should reject or normalize overlap deterministically.

Do not leave ambiguous behavior.

---

## 22. Schedule changes

Changing a psychologist's working schedule changes generated future availability.

It must NOT silently:

- cancel existing appointments;
- move existing appointments;
- delete existing appointments.

If a new schedule conflicts with existing future appointments, the backend must use an explicit business rule.

The audit must determine whether such conflicts are currently handled.

---

## 23. Absences

An absence belongs to one psychologist.

It defines a period where that psychologist cannot receive new appointments.

An absence must have a valid interval:

start < end

---

## 24. Partial absences

The architecture should support partial-day absences if the existing database/product already models datetime intervals.

Example:

14:00–16:00

Do not unnecessarily restrict absences to full days if the current model supports intervals.

---

## 25. Absence effect on availability

An absence removes overlapping potential slots from availability.

Example:

Working:
09:00–17:00

Absence:
13:00–15:00

No generated bookable slot may overlap 13:00–15:00.

---

## 26. Absence and existing appointments

Creating an absence that overlaps an existing blocking appointment must NOT silently modify that appointment.

The backend must return explicit conflict behavior or follow another explicitly approved workflow.

Expected safe default:

reject the conflicting absence with a business conflict.

---

## 27. Availability authority

Only the backend determines whether a slot is bookable.

The frontend may display availability but cannot reserve or guarantee it.

Every booking submission requires authoritative backend revalidation.

---

## 28. Availability inputs

Availability must consider at least:

- selected psychologist;
- working schedule;
- absences;
- blocking appointments;
- appointment duration;
- requested date/time;
- applicable booking constraints.

The final algorithm must be centralized.

---

## 29. Availability privacy

Public availability responses must never reveal:

- patient names;
- patient emails;
- patient phone numbers;
- appointment reasons;
- private appointment IDs merely to explain occupancy.

The public API returns available slots, not the private calendar.

---

## 30. Slot alignment

Generated slots must follow one deterministic alignment strategy.

For example, if appointment duration is 60 minutes:

09:00
10:00
11:00

or another explicitly configured interval.

Do not generate different slot boundaries in different endpoints.

The final rule must be documented after inspecting the existing backend.

---

## 31. Appointment overlap

For the same psychologist, two blocking appointments must not overlap.

General interval overlap rule:

existing.start < requested.end
AND
existing.end > requested.start

If this condition is true for a blocking appointment, the requested interval conflicts.

Boundary-touching intervals may be valid:

10:00–11:00
11:00–12:00

assuming no configured buffer exists.

---

## 32. Double booking

Double booking is prohibited.

The following must never result in two valid blocking appointments for the same psychologist/time:

Request A
+
Request B
arriving concurrently.

Checking availability before the transaction is not sufficient.

The final persistence strategy must protect against race conditions.

---

## 33. Booking transaction

Public booking must behave atomically.

Conceptually:

BEGIN
↓
revalidate psychologist
↓
revalidate slot
↓
protect against concurrency
↓
resolve/create patient
↓
create appointment
↓
COMMIT

Failure:

ROLLBACK

No partially created booking state should remain.

---

## 34. Slot unavailable response

When a requested slot is no longer available, return a deterministic business conflict.

Preferred HTTP status:

409

Preferred machine-readable code:

SLOT_UNAVAILABLE

The frontend should be able to refresh availability after receiving this error.

---

## 35. Public booking validation

Public booking input must be validated server-side.

At minimum, validate the final required fields for:

- psychologist;
- date/time;
- patient identity/contact information.

Never trust frontend Zod validation as the security boundary.

---

## 36. Booking abuse

Public booking is unauthenticated and therefore abuse-sensitive.

Apply appropriate rate limiting.

Do not reveal excessive information through validation or patient lookup behavior.

---

## 37. Past booking

Public users must not be able to create appointments in the past.

The exact minimum booking lead time, if any, must be determined from product requirements.

Do not invent a 24h/48h rule without explicit requirement.

---

## 38. Maximum booking horizon

Do not invent an arbitrary maximum booking horizon.

If the existing project has a rule such as booking only within the next N days/months, audit and document it.

Otherwise leave it configurable/undefined until explicitly decided.

---

## 39. Psychologist availability isolation

Availability for Psychologist A must be calculated from A's:

- schedule;
- absences;
- blocking appointments.

Psychologist B's appointments must not make A unavailable.

---

## 40. Same-time appointments across psychologists

Psychologist A and Psychologist B may have appointments at the same time.

This is not a conflict because they are different professionals.

Conflict uniqueness is scoped to the relevant psychologist.

---

## 41. Professional appointment access

Private appointment access requires authentication and authorization.

Route parameters must not be trusted for ownership.

The exact shared-vs-private visibility rule between the two psychologists remains to be explicitly resolved.

---

## 42. Patient access

Patient listings/details are private.

No public endpoint may provide a searchable patient directory.

Authorization must be applied to professional patient access.

---

## 43. Dashboard metrics

Dashboard values must be derived from actual database data.

Do not cache or hardcode fake counts.

Metrics must use the same appointment status semantics as the rest of the backend.

---

## 44. Agenda queries

Agenda retrieval should be bounded by a requested date/range.

Do not retrieve all historical appointments for every calendar render.

The API should support the date ranges needed by the frontend agenda.

---

## 45. Schedule and absence authorization

A professional may only modify schedules/absences permitted by the final authorization model.

Never accept a psychologist ID from the frontend and automatically assume the authenticated user may manage it.

---

## 46. Resource deletion

Hard deletion must be treated carefully.

Appointments should normally use lifecycle status rather than deletion.

Patients with appointment history should not be casually deleted.

Schedules and absences may use actual deletion where business history does not require retention, subject to final design.

---

## 47. Timezone

All business comparisons must use a consistent timezone strategy.

The cabinet's operational timezone must be documented.

Do not compare naive frontend date strings against differently interpreted server timestamps.

The final timezone strategy belongs in `DATABASE.md` and `API_CONTRACTS.md`.

---

## 48. Validation vs business conflict

Use the appropriate distinction:

422:
input structurally/semantically invalid.

409:
valid request cannot be completed because current business state conflicts.

Examples:

invalid email
→ 422

end before start
→ 422

slot already occupied
→ 409

absence overlaps protected appointment
→ 409

---

## 49. Authorization failures

Use:

401
when authentication is required but absent/invalid.

403
when authenticated but not permitted.

Do not convert authorization failures into generic 404/500 responses without an explicit security/API convention.

---

## 50. Not found

Use 404 for resources that do not exist or cannot be resolved under the API's final resource visibility convention.

Never leak private details in error messages.

---

## 51. Business codes

Important frontend-actionable conflicts should use stable codes.

Potential codes:

SLOT_UNAVAILABLE
INVALID_APPOINTMENT_TRANSITION
SCHEDULE_CONFLICT
ABSENCE_CONFLICT

Only introduce codes that correspond to real business conditions.

---

## 52. No email dependency

Appointment creation, confirmation, cancellation and completion must work without email infrastructure.

Email integration will be added later.

An unavailable email provider must never become part of current booking correctness.

---

## 53. No payments

Appointment validity does not depend on payment.

Payments are outside the current scope.

---

## 54. No clinical workflow

Appointments and patients are administrative/operational data for the current application.

Do not infer clinical workflows from the word "psychology".

No diagnosis, prescription, therapy notes or clinical documents are part of this phase.

---

## 55. Existing backend audit

For every rule in this document, the audit must classify existing behavior as:

COMPLIANT
PARTIAL
MISSING
CONFLICTING
UNKNOWN

Do not modify UNKNOWN behavior until the relevant requirement or implementation is understood.

---

## 56. Critical invariants

The following are critical:

1. Every appointment belongs to a valid psychologist.
2. Appointment intervals are valid.
3. Blocking appointments for the same psychologist do not overlap.
4. Public booking is revalidated server-side.
5. Concurrent requests cannot produce double booking.
6. Absences affect only their psychologist.
7. Schedules affect only their psychologist.
8. Public APIs never expose patient information.
9. Private operations require authorization.
10. Cancelled appointments do not incorrectly block future availability.

Violations of these invariants should receive high audit priority.

---

## 57. Final rule

The central business question for every booking is:

"Can this psychologist receive this patient during this exact interval according to the authoritative current backend state?"

The answer must come from backend business logic and database state, never from assumptions made by the frontend.
