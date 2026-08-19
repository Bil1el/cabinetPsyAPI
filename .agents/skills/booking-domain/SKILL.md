---
name: booking-domain
description: Protect the critical appointment, schedule, absence, availability, and public booking domain.
---

# Booking and availability domain

Read `AGENTS.md`, `docs/BACKEND_BUSINESS_RULES.md`, `docs/AVAILABILITY_AND_BOOKING.md`, and `docs/DATABASE.md`. This is a critical domain: audit existing behavior before refactoring and explicitly classify every unknown rule.

## Invariants

- The cabinet currently has two psychologists, each independently owning schedules, absences, appointments, and availability. Never hardcode IDs or the count; scope every query by psychologist relationship.
- Patients have no accounts. Public booking resolves/creates a patient safely without allowing public `patient_id` control or revealing whether one already exists.
- Appointments have valid intervals, a controlled status enum, an explicit initial public status, centralized blocking-status set, and a centrally enforced transition matrix. Cancellation preserves history and releases future availability when applicable.
- Multiple working intervals per day may be required; no slot may extend outside an interval. Closed days return no slots. Absences remove overlapping time and must not silently cancel/move appointments.

## Authoritative availability

One shared implementation calculates: working periods minus absences minus blocking appointments, then applies duration and slot-step rules. It must use the cabinet timezone consistently, reject past slots, load only bounded relevant data, and never reveal private reasons, patient data, or appointment IDs. The frontend displays results only; it is never authoritative.

## Booking workflow

Validated public payload → DTO → short transaction → resolve eligible psychologist → acquire justified shared lock/concurrency protection → revalidate schedule, absence, blocking overlap, duration and time → resolve/create patient → create backend-controlled pending appointment → commit → safe response. Revalidate even if the frontend received availability earlier.

Use overlap semantics `existing.start < requested.end && existing.end > requested.start`; boundary-touching intervals are valid absent a documented buffer. Same time for different psychologists is valid. Slot loss returns `409` with `SLOT_UNAVAILABLE`; invalid payload is `422`. Transaction alone is insufficient: choose and document a MySQL-compatible strategy that makes competing bookings contend on a relevant row/resource, and prove it with realistic concurrent verification.

Do not add email/SMTP/notifications or make booking depend on them.
