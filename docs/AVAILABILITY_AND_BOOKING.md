# Availability and Booking — Psychology Cabinet Backend

## 1. Purpose

This document defines the target booking and availability behavior.

Availability and booking are critical backend domains.

The existing implementation must be audited before being replaced.

The backend is the only authority for determining whether an appointment can be booked.

---

## 2. Current cabinet

The cabinet currently contains two psychologists.

Each psychologist has independent:

- working schedules;
- absences;
- appointments;
- availability.

Availability must always be calculated for a specific psychologist.

Do not hardcode the two psychologists in the algorithm.

---

## 3. Core availability equation

Conceptually:

Working periods
-
Absences
-
Blocking appointments
-
Other explicitly configured restrictions
=
Potential free periods

Then:

Potential free periods
+
Appointment duration / slot rules
=
Bookable slots

---

## 4. Authoritative inputs

Availability must be derived from authoritative backend/database state.

Inputs include:

- psychologist;
- requested date/range;
- working schedule;
- absences;
- blocking appointments;
- appointment duration;
- slot-generation rules;
- explicitly configured booking constraints.

Do not accept an `available=true` value from the frontend.

---

## 5. Psychologist selection

Every availability calculation must identify one valid psychologist.

The psychologist must exist and be eligible for public booking according to the final product model.

Psychologist A and Psychologist B are calculated independently.

---

## 6. Working periods

A psychologist is potentially available only inside their valid working periods.

Example:

09:00–12:00
14:00–18:00

No slot may start outside those periods.

No slot may extend beyond the end of a working period.

---

## 7. Closed periods

If a psychologist has no valid working interval for a date, public availability for that date is empty.

Do not interpret a missing schedule as all-day availability.

---

## 8. Absences

Absences remove time from otherwise valid working periods.

Example:

Working:
09:00–17:00

Absence:
13:00–15:00

Potential availability becomes:

09:00–13:00
15:00–17:00

subject to appointments and slot duration.

---

## 9. Existing appointments

Appointments whose statuses reserve time must remove overlapping intervals from availability.

The final set of blocking statuses must be centralized.

Expected candidates:

- pending;
- confirmed.

Cancelled appointments must not block future availability.

Historical completed appointments remain historical records but naturally concern past time.

---

## 10. Appointment overlap

Use standard interval-overlap semantics:

existing.start < requested.end
AND
existing.end > requested.start

If true, intervals overlap.

Boundary-touching intervals do not overlap:

10:00–11:00
11:00–12:00

unless a buffer rule is explicitly introduced.

---

## 11. Appointment duration

Slot generation requires one authoritative duration rule.

The audit must determine whether duration is currently:

- globally configured;
- psychologist-specific;
- stored per appointment;
- derived from another domain concept.

Do not scatter magic values such as `60` throughout the application.

---

## 12. Slot interval

Appointment duration and slot step are conceptually different.

Example:

duration = 60 minutes
slot step = 30 minutes

could theoretically produce:

09:00–10:00
09:30–10:30
10:00–11:00

if the business model intentionally supports it.

Do not assume duration equals slot step without verifying existing requirements.

---

## 13. Slot generation

Slot generation must be deterministic.

Conceptually:

for each valid working interval:
generate candidate starts
calculate candidate end
reject candidate outside working interval
reject candidate overlapping absence
reject candidate overlapping blocking appointment
retain valid candidate

The actual implementation should use clear domain code, not controller loops duplicated across endpoints.

---

## 14. No frontend slot generation authority

The frontend may format/display returned slots.

It must not independently decide authoritative availability.

Do not send only raw schedules to the public frontend and expect React to calculate booking safety.

---

## 15. Public availability response

The public response should contain only information necessary for booking.

Conceptually:

```json
{
  "date": "2026-08-20",
  "slots": [
    {
      "startsAt": "2026-08-20T09:00:00+01:00",
      "endsAt": "2026-08-20T10:00:00+01:00"
    }
  ]
}

This is illustrative only.

Final naming and datetime format must follow the verified API contract.

16. Availability privacy

Never return private reasons for unavailable time.

Do not expose:

patient identity;
patient contact information;
private appointment ID;
appointment notes;
private cancellation information.

The public consumer needs bookable slots only.

17. Date range

Availability queries must use bounded date ranges.

Do not allow an unauthenticated client to request years of availability in one expensive request.

The final maximum range should correspond to real frontend requirements.

Do not invent an arbitrary business booking horizon without approval.

18. Past availability

Public availability must not expose past slots as bookable.

The backend clock/timezone strategy is authoritative.

19. Minimum lead time

Do not invent a minimum lead time such as:

2 hours;
24 hours;
48 hours;

unless required by the cabinet.

If the existing backend has such a rule, the audit must identify it.

20. Maximum booking horizon

Do not invent a maximum booking horizon.

If a future-booking limit exists in the current implementation, document and verify it.

Otherwise treat it as an unresolved configurable product rule.

21. Booking request

A public booking request conceptually contains:

psychologist identifier;
selected appointment start/date-time;
required patient information;
other explicitly approved appointment information.

Do not allow the public client to control privileged fields.

22. Forbidden public fields

Public booking must not allow the client to arbitrarily assign:

appointment status;
ownership other than selected valid psychologist;
patient database ID without an explicitly safe design;
internal timestamps;
professional user ID;
privileged metadata.

The backend constructs these values.

23. Booking validation

Before persistence, validate:

payload structure;
psychologist identifier;
patient fields;
datetime;
allowed formats;
required values.

Then perform business validation separately.

24. Booking business validation

A structurally valid request is not automatically bookable.

The backend must verify:

psychologist exists;
requested time is not in the past;
requested interval fits a working period;
it does not overlap an absence;
it does not overlap a blocking appointment;
duration/slot rules are satisfied;
other approved booking constraints are satisfied.
25. Revalidation at submission

Availability returned earlier is informational.

When the booking request arrives, availability must be recalculated/revalidated using current database state.

Never trust:

"the frontend received this slot five minutes ago."

26. Concurrency problem

Consider:

Request A checks 10:00
→ free

Request B checks 10:00
→ free

A creates appointment
B creates appointment

Without concurrency protection, both may succeed.

This is prohibited.

27. Transaction boundary

Appointment creation should be atomic.

Conceptually:

BEGIN TRANSACTION

resolve authoritative booking context

acquire appropriate concurrency protection

revalidate slot

resolve/create patient

create appointment

COMMIT

On failure:

ROLLBACK

28. Concurrency strategy

Do not choose the locking strategy before inspecting the real database model.

The implementation must determine a shared database resource that competing requests contend on.

Potential approaches depend on the schema:

row-level locking;
psychologist/date serialization;
slot records;
unique constraints for fixed aligned slots;
another deterministic transactional design.

The chosen strategy must work with MySQL and be explained in the audit/final implementation report.

29. Transactions are not enough alone

Simply wrapping this in:

DB::transaction(function () {
    // check
    // create
});

does not automatically prevent two transactions from reading the same free state.

The implementation must explicitly address the race condition.

30. Fixed-slot optimization

If the audited product guarantees:

fixed appointment duration;
fixed aligned slot starts;

then a database uniqueness strategy may be possible.

Example concept:

unique(psychologist_id, starts_at)

But this is safe only if the business model guarantees that different start times cannot overlap.

Do not assume this without verification.

31. Arbitrary intervals

If appointments may have arbitrary durations/start times, simple start-time uniqueness is insufficient.

Example:

09:00–10:00
09:30–10:30

The final implementation must protect general interval overlap.

32. Patient resolution

Patient lookup/creation occurs inside the booking workflow according to the final patient identity strategy.

The public client should not decide whether a new patient record must be created.

Backend logic decides.

33. Patient resolution privacy

The public response must not reveal:

existing patient found;
new patient created;
patient internal matching details.

This avoids patient enumeration/privacy leaks.

34. Booking creation result

After successful persistence, return a safe booking representation.

Do not return raw Patient or User models.

The response should contain only information needed for the public success experience.

35. Initial status

Expected public booking behavior:

new booking
→ pending

because professional validation is expected.

However, the existing backend must be audited before the final status rule is changed.

Once finalized, the initial status must be assigned by backend code, not client input.

36. Pending slot reservation

Expected behavior:

pending appointments reserve the selected slot.

This prevents several patients from requesting the same slot while professional validation is pending.

If the existing backend behaves differently, report the conflict.

37. Confirmation

Confirmation must operate on an existing pending appointment according to the final transition matrix.

It requires professional authentication and authorization.

Do not create a second appointment when confirming.

38. Cancellation

Cancellation changes the appointment status.

For a future appointment, cancellation normally releases its time for future availability.

Do not hard-delete the appointment merely to release the slot.

39. Completion

Completion is a professional operation.

It should follow explicit state-transition rules.

Do not let public clients mark appointments completed.

40. Invalid state transition

Invalid transitions must fail predictably.

Potential machine-readable code:

INVALID_APPOINTMENT_TRANSITION

The final status/HTTP semantics must be documented in API_CONTRACTS.md.

41. Schedule modification impact

Changing working hours changes future calculated availability.

Existing appointments must not silently move or disappear.

If a schedule update makes existing future appointments fall outside working hours, the backend must apply an explicitly selected conflict policy.

The audit must report current behavior.

42. Absence creation impact

Before persisting an absence, check relevant existing blocking appointments.

Expected safe default:

absence overlaps appointment
→ reject
→ business conflict

Do not silently cancel the appointment.

43. Two psychologists

The same clock time may be valid for both psychologists.

Example:

Psychologist A:
10:00 appointment

Psychologist B:
10:00 appointment

This is valid.

All overlap/concurrency checks must be scoped to the correct psychologist.

44. Availability query efficiency

Avoid per-slot database queries.

Bad conceptual implementation:

for every 30-minute slot:
query appointments
query absences

Prefer loading relevant bounded scheduling state efficiently, then calculate slots.

45. Query boundaries

For availability date/range, load only relevant:

schedules;
absences;
appointments.

Do not load all historical appointments.

46. Index support

Database indexes should support queries such as:

appointments:
psychologist + time range + blocking status

absences:
psychologist + time range

schedules:
psychologist + weekday/date representation

Final indexes must follow the actual schema.

47. Availability caching

Do not cache availability until correctness is established.

If caching is introduced later, invalidation must occur after:

booking creation;
cancellation;
schedule update;
absence creation/update/delete.

Stale cache must never become authoritative during booking submission.

48. Timezone

Availability and booking must use one consistent timezone strategy.

The backend must correctly interpret:

selected date;
start time;
end time;
current time.

The API must not rely on browser-local ambiguous strings.

49. Daylight/time changes

Use proper date/time objects and documented timezone handling.

Do not perform scheduling calculations through manual string concatenation when Carbon/date-time operations are appropriate.

50. Error classification

Examples:

Malformed/invalid date:
→ 422

Unknown psychologist:
→ 404 or documented validation semantics

Slot unavailable:
→ 409

Unauthenticated private transition:
→ 401

Unauthorized professional:
→ 403

Unexpected server failure:
→ safe 5xx

51. Business error codes

Potential stable codes:

SLOT_UNAVAILABLE

INVALID_APPOINTMENT_TRANSITION

SCHEDULE_CONFLICT

ABSENCE_CONFLICT

Only implement codes for real conditions.

52. Rate limiting

Public booking must have an appropriate rate limit.

Availability may also require a reasonable limit to protect expensive calculations.

Do not use rate limiting as a substitute for database concurrency protection.

53. No email in transaction

Email is out of scope.

Even later, external email delivery should not be performed as slow network work inside the critical booking database transaction.

Current booking success depends only on core persistence/business correctness.

54. No payment dependency

Booking does not currently require payment.

Do not reserve slots based on payment states that do not exist in the product.

55. Availability tests

Tests must cover at least:

working day produces valid slots;
closed day produces no slots;
absence removes overlapping slots;
pending appointment removes slot;
confirmed appointment removes slot;
cancelled appointment does not block future slot;
appointment of Psychologist A does not block Psychologist B;
slots do not exceed working interval;
past slots are not bookable.
56. Booking tests

Tests must cover at least:

valid booking succeeds;
patient/appointment persisted correctly;
invalid payload rejected;
invalid psychologist rejected;
past booking rejected;
outside-working-hours booking rejected;
absence booking rejected;
occupied-slot booking rejected;
cancelled appointment permits appropriate rebooking;
public client cannot choose privileged status.
57. Concurrency tests

The final implementation must include a test or reproducible integration verification proving that competing booking requests cannot produce overlapping valid appointments for the same psychologist.

Do not mark booking complete without verifying this invariant.

58. Transition tests

Test all allowed appointment transitions.

Test representative forbidden transitions.

Do not only test controller HTTP success.

59. Audit requirements

Codex must inspect the current implementation for:

availability service/logic;
schedule representation;
absence representation;
appointment duration;
slot step;
blocking statuses;
patient resolution;
transactions;
locks;
overlap query;
booking endpoint;
public validation;
rate limiting;
timezone handling;
relevant tests.

For each area classify:

COMPLIANT;
PARTIAL;
MISSING;
CONFLICTING;
UNKNOWN.
60. Final booking invariant

At commit time, for a given psychologist and requested interval:

the psychologist is valid;
the interval is allowed by schedule;
no absence conflicts;
no blocking appointment conflicts;
patient data is valid;
concurrent requests cannot violate these conditions.

Only then may the appointment become persistent.

61. Final principle

Availability answers:

"When can this specific psychologist actually receive a new appointment?"

Booking answers:

"Can this exact requested interval still be safely committed right now?"

Both answers belong to the backend.
