# Database Design — Psychology Cabinet


## 1. Purpose


This document defines the target database principles for the psychology cabinet backend.


The database already exists partially.


Do NOT recreate or rewrite the schema blindly.


Before any schema modification:


1. inspect all existing migrations;
2. inspect current models and relationships;
3. inspect indexes and foreign keys;
4. inspect existing production-relevant data assumptions;
5. compare the schema with the business rules;
6. propose safe migrations for required corrections.


Database integrity is part of application correctness.


---


## 2. Current product model


The current application manages:


- one cabinet;
- two current psychologists;
- patients without accounts;
- appointments;
- psychologist working schedules;
- psychologist absences;
- professional authentication.


The database must not hardcode exactly two psychologists.


---


## 3. Core entities


The expected conceptual model is:


```text
Professional/User
        ↕
Psychologist
        │
        ├── Working Schedules
        ├── Absences
        └── Appointments
                 │
                 └── Patient

The actual existing model names must be inspected before changes are made.

Do not create duplicate entities merely because this document uses different terminology.

4. Users / professional authentication

The authentication table represents professional accounts.

Audit the existing users table before modifying it.

It may contain fields such as:

id;
name;
email;
password;
role/status if actually required;
timestamps.

Never expose password hashes.

Do not put patient accounts into this table for the current product.

5. Psychologists

The database must have a deterministic way to represent the two psychologists.

A psychologist must be addressable through a stable primary key.

Depending on the existing implementation, psychologist profile information may:

live directly on the professional user;
use a dedicated psychologists table;
use another existing professional/profile model.

Do NOT create a duplicate psychologists table until the current schema has been audited.

The final design must clearly distinguish:

authentication identity;
public professional profile;
psychologist business identity.
6. No hardcoded psychologist IDs

Never encode assumptions such as:

if ($psychologistId === 1) { ... }

Business relationships must use database foreign keys and authenticated context.

The database should continue working if another psychologist record is added later.

7. Patients

The patient table should represent operational patient identity/contact data.

Expected fields may include:

id;
first_name;
last_name;
email;
phone;
timestamps.

These are conceptual fields.

The actual schema must be audited before renaming or adding columns.

Do not add clinical fields.

8. Patient sensitive data

Patient records are private data.

Store only what the application requires.

Do not add:

diagnosis;
therapy notes;
prescriptions;
clinical documents;
unnecessary personal attributes.

Database design must follow data minimization.

9. Patient identity and deduplication

Do not automatically make:

first_name + last_name

unique.

Different people may share names.

Do not automatically assume email alone or phone alone is a perfect real-world identity without reviewing product requirements.

The audit must inspect the existing patient lookup strategy.

The final deduplication strategy must be explicit and deterministic.

10. Normalization of patient contact fields

If email is used for lookup, normalize it consistently where appropriate.

If phone is used for lookup, define a consistent normalization strategy before relying on uniqueness.

Do not create a uniqueness constraint on unnormalized values and assume it solves identity.

11. Appointments

Appointments are a critical table.

Each appointment must reference:

one psychologist;
one patient;
an unambiguous start;
an unambiguous end or authoritative duration;
a controlled status.

Potential conceptual columns:

id
psychologist_id
patient_id
starts_at
ends_at
status
created_at
updated_at

Do not change the existing schema to this exact shape without auditing it.

12. Appointment foreign keys

Appointment relationships must use foreign keys where practical.

Conceptually:

appointments.psychologist_id
→ psychologists/users.id


appointments.patient_id
→ patients.id

The actual referenced table depends on the existing architecture.

Invalid orphan appointments must not be possible under the final design.

13. Appointment interval integrity

Every appointment must satisfy:

starts_at < ends_at

Laravel validation and service logic must enforce this.

Use database-level enforcement where supported and appropriate, but do not assume MySQL alone will enforce every scheduling invariant.

14. Appointment statuses

Status must use a controlled domain.

Expected candidates:

pending
confirmed
completed
cancelled

no_show only if explicitly retained.

The final database representation may use:

string + PHP Enum cast;
database enum only if justified;
another controlled representation already used by the project.

Do not scatter uncontrolled status strings.

15. Appointment overlap invariant

For the same psychologist, two appointments whose statuses reserve time must not overlap.

Overlap condition:

existing.starts_at < requested.ends_at
AND
existing.ends_at > requested.starts_at

This is scoped by psychologist.

Appointments for different psychologists may overlap in time.

16. MySQL and overlapping intervals

A simple unique index such as:

UNIQUE(psychologist_id, starts_at)

does NOT fully prevent interval overlap when arbitrary appointment durations are possible.

Example:

09:00–10:00
09:30–10:30

Different starts_at values, but still overlapping.

Therefore the final concurrency strategy must consider the actual duration/slot model.

Do not claim a simple unique index solves arbitrary interval overlap unless the business model guarantees fixed aligned slots.

17. Double-booking protection

Double booking must be prevented under concurrent requests.

The final solution must be selected after auditing:

appointment duration;
slot alignment;
existing schema;
MySQL version;
current transaction strategy.

Potential techniques include:

transactional locking;
locking an appropriate psychologist/schedule/slot row;
deterministic slot records if the architecture uses them;
unique constraints where the business model makes them sufficient.

The final solution must be tested.

18. Locking strategy

Do not add lockForUpdate() randomly.

A lock only protects correctness when concurrent transactions contend on the same relevant rows.

The audit must determine which row/resource can safely serialize competing bookings for one psychologist/time range.

Keep transactions short.

19. Working schedules

Working schedules belong to one psychologist.

Conceptual representation may include:

id
psychologist_id
weekday
starts_at/time
ends_at/time

or another existing representation.

The final model must support the actual schedule requirements.

20. Multiple schedule intervals

If psychologists may work:

09:00–12:00
14:00–18:00

on the same day, the schema must support multiple intervals per psychologist/day.

Do not force one row per weekday if it prevents required split schedules.

21. Schedule overlap

For one psychologist and relevant day, working intervals should not overlap ambiguously.

Example invalid state:

09:00–13:00
12:00–16:00

Application validation/business logic must prevent or normalize this according to final rules.

22. Schedule indexes

Likely query dimensions include:

psychologist_id
weekday/date

Indexes should support actual availability queries.

Do not add indexes without inspecting query patterns.

23. Absences

Absences belong to one psychologist.

Preferred conceptual representation:

id
psychologist_id
starts_at
ends_at
created_at
updated_at

if the existing model supports interval-based absences.

This naturally supports:

partial day;
full day;
multiple days.

Audit the existing schema first.

24. Absence interval

Every absence must satisfy:

starts_at < ends_at

Invalid absence intervals must not persist.

25. Absence overlap

Overlapping absences for the same psychologist may be:

rejected;
merged;
tolerated if queries handle them correctly.

The final behavior must be explicit.

Prefer avoiding ambiguous redundant records.

26. Absence vs appointment conflict

An absence must not silently modify existing appointments.

Before creating/updating an absence, application logic must check blocking future appointments according to the final business rule.

Expected safe behavior:

conflict
→ reject operation
→ HTTP 409

unless another workflow is explicitly approved.

27. Referential delete behavior

Foreign-key deletion behavior must be intentional.

Do not casually use cascadeOnDelete() everywhere.

Examples:

Deleting a psychologist must not silently destroy appointment history.

Deleting a patient must not silently destroy historical appointments.

For historical/operational records, restrict or application-controlled lifecycle may be safer.

Audit existing FK actions.

28. Hard deletion

Appointments should normally not be hard-deleted for cancellation.

Use status transitions.

Patient deletion must consider existing appointment history.

Schedule and absence deletion may be acceptable when business history is not required.

29. Soft deletes

Do not add Laravel SoftDeletes to every model automatically.

Use soft deletion only when there is a real restoration/history requirement.

Soft deletes complicate:

uniqueness;
queries;
authorization;
availability.

Audit before introducing them.

30. Timestamps

Use Laravel timestamps where useful.

Do not confuse:

created_at
updated_at

with business timestamps such as:

starts_at
ends_at
cancelled_at

Only add business timestamps actually required.

31. Timezone strategy

The final database must use one consistent datetime strategy.

Preferred direction for review:

store absolute appointment/absence datetimes consistently;
define the cabinet operational timezone explicitly;
serialize API datetimes consistently.

The actual strategy must account for the current application and deployment.

Do not mix timezone assumptions.

32. Date and time types

Use appropriate SQL column types.

Examples:

appointment absolute datetime → datetime/timestamp depending on chosen strategy;
recurring weekly schedule time → time may be appropriate;
weekday → small integer/string/enum depending on existing design.

Do not store structured dates as arbitrary display strings.

33. Index principles

Indexes should support actual frequent queries.

Likely candidates:

Appointments:

(psychologist_id, starts_at)
(psychologist_id, status, starts_at)
(patient_id, starts_at)

Schedules:

(psychologist_id, weekday)

Absences:

(psychologist_id, starts_at)

These are candidates, not mandatory exact indexes.

Inspect existing queries before finalizing.

34. Foreign-key indexes

Foreign-key columns used in joins/filters should be indexed appropriately.

Check what MySQL/Laravel already creates before adding duplicates.

35. Duplicate indexes

Audit migrations/schema for:

duplicate indexes;
redundant single-column indexes covered by useful composites;
indexes that are never useful.

Do not optimize blindly.

36. Appointment query performance

Availability frequently asks:

appointments for psychologist X
overlapping date/range Y
with blocking statuses

The final index strategy should support this query efficiently.

37. Agenda query performance

Agenda commonly queries:

psychologist/cabinet
+
date range
+
status

Do not require full table scans as appointment history grows.

38. Patient search performance

If professionals search patients by:

name;
email;
phone;

the schema/query strategy should support expected search behavior.

Do not create broad full-text infrastructure unless actually needed.

39. Dashboard queries

Dashboard metrics should use database aggregation.

Examples:

COUNT
GROUP BY status
date-range predicates

Do not retrieve every row into PHP for basic counts.

Indexes should support the actual dashboard filters.

40. Migrations

Because this backend already exists:

Do NOT automatically edit old migrations.

First determine whether they may already have been executed outside the local environment.

Prefer corrective migrations for established schema.

Historical migrations may only be rewritten when it is explicitly safe to reset the entire database history.

41. Migration safety

Before a destructive migration:

identify affected data;
determine whether data already exists;
determine rollback behavior;
avoid silent data loss.

Codex must report destructive schema operations before applying them when risk exists.

42. Seeders

Seeders may provide local/dev/test data.

Do not put real patient information into repository seeders.

Do not use seeders as hidden production configuration.

43. Two psychologists seed/setup

If local development needs two psychologists, factories/seeders may create sample professionals.

Do not rely on specific seeded IDs in application code.

Use lookup by deterministic dev attributes only inside dev/test setup where appropriate.

44. Factories

Use factories for tests.

Factories should make it easy to create:

professionals/psychologists;
patients;
appointments;
schedules;
absences.

Do not depend on fixed database IDs in tests.

45. Test isolation

Tests must use isolated test data.

Use Laravel database testing facilities according to existing configuration.

Never point automated tests at production.

46. Sensitive database data

Do not log database rows containing unnecessary patient information.

Do not expose database dumps containing real patient data.

Backups and production database operational security are deployment concerns and must be treated as sensitive.

47. Mass assignment

Models must explicitly protect assignment boundaries.

Do not allow request payloads to control:

id
role
ownership foreign keys
privileged status
internal timestamps

unless the specific operation intentionally maps them.

Prefer explicit validated mapping.

48. Database-generated IDs

Use the project's existing ID strategy.

Do not migrate integer IDs to UUID/ULID merely because they are fashionable.

Public security must come from authorization, not from assuming IDs are impossible to guess.

49. IDOR

Sequential IDs are acceptable when authorization is correct.

Never treat non-guessable IDs as a replacement for Policies/ownership checks.

50. Schema source of truth

After backend finalization, the source of truth consists of:

Laravel migrations
+
Eloquent relationships/casts
+
documented business invariants

Documentation must match the implemented schema.

51. No duplicate domain tables

Before creating a new table, inspect whether the existing backend already represents the same concept under another name.

Do not accidentally create:

psychologists
professionals
therapists

as three representations of the same entity.

The same applies to:

working_hours
schedules
availabilities

Understand existing semantics first.

52. Availability storage

Calculated availability should generally be derived from authoritative scheduling state rather than stored as a large set of permanently trusted available slots.

Conceptually:

Schedules
-
Absences
-
Appointments
=
Availability

If the existing backend stores generated slots, audit why and how they are invalidated before deciding whether to preserve that architecture.

53. Cache

Do not introduce caching for availability before correctness is established.

Stale availability can cause serious booking UX/correctness issues.

If caching is later used, invalidation must account for:

appointment creation/change;
cancellation;
schedule change;
absence change.
54. Database audit checklist

Codex must inspect:

all migrations;
schema relationships;
foreign keys;
indexes;
nullable columns;
unique constraints;
delete rules;
model $fillable/$guarded;
casts;
date handling;
appointment overlap protection;
schedule representation;
absence representation;
patient deduplication;
test factories/seeders.
55. Critical database questions

Before finalizing the database, answer:

What table represents a psychologist?
How is a psychologist linked to authentication?
What identifies a patient for reuse?
How are appointment start/end represented?
Which statuses reserve a slot?
How are working schedules represented?
Can one day contain multiple working intervals?
How are absences represented?
What prevents concurrent double booking?
Which indexes support availability queries?
What happens to history if a professional/patient is deleted?
What timezone strategy is used?

No critical answer should remain implicit.

56. Final database principle

The database is the final integrity boundary beneath Laravel.

Application validation improves behavior, but critical relational integrity should not depend entirely on controllers behaving correctly.

The final schema must make invalid states difficult to create and efficient valid queries easy to perform.
