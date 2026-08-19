# Backend Product Specification — Psychology Cabinet


## 1. Purpose


This document defines what the psychology cabinet backend must support.


It describes the product and business scope.


It does NOT define implementation details. Technical architecture is defined in:


- `docs/BACKEND_ARCHITECTURE.md`
- `docs/DATABASE.md`
- `docs/API_CONTRACTS.md`
- `docs/BACKEND_SECURITY.md`


The existing backend is partially implemented.


Existing code must be audited against this specification before being considered correct.


---


## 2. Product


The application is a web platform for a real psychology cabinet.


It contains two main sides:


### Public side


Visitors can:


- discover the cabinet;
- discover the psychologists;
- view bookable availability;
- select a psychologist;
- select a date;
- select an available time slot;
- provide the required patient information;
- submit an appointment request.


Patients do NOT need an account for the normal booking flow.


### Professional side


Authorized psychologists can access a private workspace used to manage:


- dashboard;
- agenda;
- appointments;
- patients;
- working schedules;
- absences;
- professional settings.


---


## 3. Cabinet model


The current real cabinet contains two psychologists.


The backend must correctly support both psychologists independently.


Conceptually:


```text
Cabinet
├── Psychologist A
│   ├── Profile
│   ├── Working schedules
│   ├── Absences
│   ├── Appointments
│   └── Availability
│
└── Psychologist B
    ├── Profile
    ├── Working schedules
    ├── Absences
    ├── Appointments
    └── Availability

The current business requirement is two psychologists.

However, core backend architecture must not depend on:

psychologist 1
psychologist 2

as hardcoded application concepts.

Adding another psychologist later should not require rewriting the booking engine.

4. Psychologists

A psychologist is a professional who may receive appointments.

A psychologist may have:

professional identity;
public profile information;
working schedules;
absences;
appointments;
calculated availability;
private professional account.

The exact fields must be based on actual product requirements and database design.

Do not invent medical qualifications, specialties, credentials or other real-world information.

5. Professional accounts

The private application is reserved for authorized professionals.

Professional accounts are separate from patients.

Patients do not receive professional dashboard access.

The backend must distinguish authentication identity from public psychologist profile information when appropriate.

Authentication and authorization details are defined separately in:

docs/AUTHENTICATION.md

6. Patients

A patient is a person associated with one or more appointments.

Patients do not need application accounts.

The public booking flow may collect only information actually required by the product.

Potential required information includes:

first name;
last name;
email;
phone.

The final fields must match the actual backend requirements.

Do not collect additional sensitive information without a real business requirement.

7. Patient reuse

A returning patient should not necessarily require creation of an entirely unrelated duplicate record for every booking.

The backend must define a deterministic patient lookup/creation strategy.

The strategy must:

avoid unsafe merging;
avoid exposing whether another patient exists;
avoid using weak identity assumptions;
preserve data integrity.

The exact deduplication rule will be documented in the business/database specifications.

8. Patient privacy

Patient information is private.

Public endpoints must never provide patient listings or patient details.

Private patient access requires authenticated and authorized professional access.

Only information necessary for the requested operation should be returned.

9. Appointments

An appointment represents a scheduled interaction between:

Patient
+
Psychologist
+
Date/time

Every appointment must belong to a psychologist.

Every appointment must be associated with the required patient information.

Appointments must have explicit lifecycle/state rules.

10. Appointment source

Appointments may originate from the public booking flow.

The architecture may also support professional-side appointment creation if this is part of the existing product/backend.

Codex must inspect the current implementation before assuming this capability exists.

Do not invent unsupported appointment sources.

11. Appointment status

The backend must use a controlled set of appointment statuses.

Candidate statuses may include:

pending;
confirmed;
completed;
cancelled;
no-show only if required.

The final status set must be explicitly documented after auditing the existing backend.

Do not introduce unnecessary statuses.

Status transitions must be controlled by backend business rules.

12. Pending appointments

If public appointments require professional validation, a newly submitted appointment may enter a pending state.

The professional dashboard must then allow the appropriate psychologist to process it according to the final business rules.

The exact workflow must be confirmed against the existing implementation before finalization.

13. Appointment confirmation

If confirmation is part of the product, the backend must determine:

who may confirm;
which statuses may transition to confirmed;
whether the slot remains reserved while pending;
what happens if the appointment is no longer valid.

These rules must not be decided by the frontend.

14. Appointment cancellation

Appointment cancellation must be an explicit state transition.

Do not silently delete appointments merely because they are cancelled.

Cancellation behavior must preserve operational/history requirements defined by the product.

Hard deletion should not be used as a substitute for appointment lifecycle management.

15. Appointment completion

If the product supports marking an appointment completed, the backend controls when and by whom this can occur.

The frontend must not determine completion authorization from the clock alone.

16. No-show

no_show must only exist if explicitly retained as a product requirement.

Do not add it simply because scheduling applications commonly have this status.

17. Working schedules

Each psychologist has their own working schedule.

Example concept:

Psychologist A


Monday
09:00 → 12:00
13:00 → 17:00


Tuesday
...

Psychologist B may have completely different hours.

Do not use one global schedule for both psychologists unless explicitly required.

18. Schedule rules

Working schedules define when a psychologist would normally be available.

They do NOT alone determine final bookable availability.

Availability must also consider:

absences;
existing appointments;
consultation duration;
booking rules.
19. Multiple working intervals

The backend should not assume that every working day necessarily consists of one uninterrupted interval if existing product requirements support multiple intervals.

Example:

09:00 → 12:00
14:00 → 18:00

The final schedule model must be based on the database/business design.

20. Closed days

A psychologist may have days with no working schedule.

No public availability should be generated outside valid working periods.

21. Absences

An absence represents a period during which a psychologist is unavailable despite their normal working schedule.

An absence belongs to a psychologist.

Examples:

one day;
several days;
part of a day, if supported by the final model.
22. Absence effect

An absence removes otherwise available time from public availability.

Conceptually:

Working schedule
-
Absence
=
Potential working time

before appointments and other booking rules are considered.

23. Absence conflicts

Creating an absence may conflict with existing appointments.

The backend must not silently:

delete;
cancel;
move;

existing appointments.

Conflict behavior must be explicit.

24. Availability

Availability is calculated by the backend.

It is not a manually trusted value received from the frontend.

Conceptually:

Psychologist
+
Working schedule
-
Absences
-
Occupied appointment time
+
Booking rules
=
Bookable slots

Availability must be centralized so public booking and professional operations use coherent rules.

25. Availability endpoint

The public frontend needs a safe way to request availability for a selected psychologist and date/range.

The response should expose bookable slots, not private appointment information.

For example, the public API may know:

10:00 available
10:30 unavailable

but it must never reveal:

10:30 occupied by Patient X
26. Consultation duration

Slot generation requires a defined consultation duration or equivalent appointment duration rule.

The final source of this duration must be explicitly defined during backend design.

Do not scatter hardcoded values such as:

60

through controllers and services.

27. Booking flow

The intended public flow is:

Choose psychologist
↓
Choose date
↓
Backend calculates availability
↓
Choose available slot
↓
Enter patient information
↓
Submit booking
↓
Backend revalidates everything
↓
Appointment created or conflict returned

The availability previously shown in the browser is never authoritative.

28. Booking submission

At final submission, the backend must verify again:

psychologist exists;
psychologist can receive appointments;
date/time is valid;
selected slot fits scheduling rules;
selected slot is not covered by absence;
selected slot is not already occupied;
patient input is valid;
request satisfies abuse/rate-limit rules.

Only then may the appointment be persisted.

29. Concurrent booking

Two users may attempt to book the same psychologist and same time concurrently.

The backend must guarantee that this cannot create two conflicting appointments.

This is a core correctness requirement.

The solution must be enforced through backend/database concurrency strategy, not frontend state.

30. Booking conflict

If a slot becomes unavailable between availability display and booking submission, the API must return an explicit conflict.

Preferred semantic status:

409 Conflict

with a stable business code such as:

SLOT_UNAVAILABLE

when appropriate.

31. Public API privacy

Public APIs may expose only information required for the public website and booking flow.

They must not expose:

patient information;
private professional account information;
internal IDs unnecessarily;
internal security metadata;
private appointment details.
32. Dashboard

The private dashboard gives professionals an operational overview.

It may include real metrics such as:

appointments today;
appointments requiring action;
upcoming appointments;
relevant status counts.

Only metrics backed by actual data should exist.

No fabricated dashboard statistics.

33. Agenda

The agenda provides a time-oriented representation of appointments.

The backend should support efficient retrieval for relevant date ranges.

Do not require the frontend to download the complete appointment database to build a week view.

34. Appointment management

Authorized professionals need to inspect and manage appointments according to business rules.

Possible operations depend on final status workflow and permissions.

The backend is authoritative for every transition.

35. Patient management

The private workspace may provide:

patient list;
patient search;
patient details;
appointment history.

This is operational patient management.

It is NOT automatically a full electronic medical record.

36. Patient search

Search should support useful identifiers required by the product.

Potential examples:

name;
phone;
email.

The final implementation should be efficient and privacy-conscious.

37. Professional settings

The private area may allow a psychologist to manage supported profile/settings information.

Only settings actually persisted by the backend should be exposed.

Do not invent settings simply because admin dashboards commonly contain them.

38. Public profile

If psychologist public profile data is editable, the backend must distinguish:

public profile information;
authentication/account information;
internal fields.

Public profile editing must not allow privilege escalation.

39. Authorization model

The final backend must explicitly define whether:

Model A

Each psychologist accesses only their own appointments/patients/schedules/absences.

or:

Model B

Authorized professionals in the same cabinet have some shared cabinet-wide visibility.

This must NOT be guessed.

Codex must inspect the existing backend and project requirements and report the current behavior before final implementation.

Until explicitly resolved, use the least-privilege interpretation for security decisions.

40. Current cabinet vs future scalability

The current project is for one real cabinet with two psychologists.

Do not prematurely turn the backend into a full multi-tenant SaaS.

However, avoid architecture that hardcodes the two professionals into application logic.

Current scope:

one cabinet
+
two current psychologists

Not current scope:

multi-cabinet SaaS
super-admin platform
billing/subscriptions
tenant provisioning
41. Authentication

Only professional/private access requires authentication in the current product.

Public visitors and patients can use public booking without accounts.

Authentication details will be defined in:

docs/AUTHENTICATION.md

42. Security

The backend must protect:

professional accounts;
patient information;
appointment integrity;
availability integrity;
private API resources;
application secrets.

Security requirements are defined in:

docs/BACKEND_SECURITY.md

43. Validation

Every write operation must be validated by the backend.

Frontend validation improves UX but is never authoritative.

44. Error behavior

The API should expose predictable errors suitable for frontend integration.

Important classes include:

401 unauthenticated;
403 forbidden;
404 not found;
409 business conflict;
422 validation failure;
429 rate limited;
safe 5xx responses.
45. Pagination

Potentially large private collections should support bounded retrieval.

Important examples:

appointments;
patients.

The frontend should not depend on unbounded datasets.

46. Auditability and history

Do not hard-delete operational records merely to simplify UI behavior when status/history is required.

Appointment lifecycle should preserve meaningful history.

Additional audit logging should only be added where justified by product/security requirements.

47. Email and notifications

Email and notification delivery are intentionally NOT part of the current implementation phase.

Do not implement:

appointment confirmation email;
cancellation email;
reminder email;
email verification;
SMTP provider;
transactional email provider;
email queues/templates.

The core backend must work correctly without email infrastructure.

Email integration will be handled in a later dedicated phase.

48. Payments

Payment processing is NOT part of the current product scope.

Do not add:

payment providers;
invoices;
subscriptions;
checkout;
payment status;

unless the product scope explicitly changes.

49. Teleconsultation

Teleconsultation/video calling is NOT part of the current backend scope unless explicitly added later.

Do not introduce video meeting infrastructure.

50. Clinical records

The current product is not intended to become a complete clinical information system.

Do not add without explicit approval:

diagnoses;
prescriptions;
clinical notes;
medical documents;
treatment records.
51. AI features

AI functionality is NOT required for the current backend.

Do not add AI assistants, automatic clinical analysis or similar functionality.

52. File/document uploads

Do not introduce patient medical-document uploads unless explicitly required.

Professional profile image handling may be supported only if it is part of the actual product/backend requirements.

53. Product truth

When existing code and this specification disagree:

identify the disagreement;
do not silently choose one;
determine whether existing behavior is intentional;
report the conflict during audit;
resolve it explicitly before major refactoring.
54. Backend completion objective

The backend is considered functionally complete only when the required domains work together coherently:

Psychologists
      ↓
Schedules
      ↓
Absences
      ↓
Availability
      ↓
Public booking
      ↓
Patients + Appointments
      ↓
Professional dashboard / agenda

Security, authorization, database integrity and tests apply across the entire chain.

55. Final product principle

This backend is not a generic CRUD API.

Its central responsibility is to safely coordinate:

WHO
the appointment belongs to


WHEN
the psychologist can actually receive the patient


WHETHER
the slot is still available


WHO
may access or modify private information


HOW
the database remains correct under concurrent requests

Product simplicity must not come at the expense of privacy, security or appointment integrity.
