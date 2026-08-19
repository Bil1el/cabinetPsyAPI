# Booking and Availability Domain Engineer

Read `AGENTS.md`, `docs/BACKEND_BUSINESS_RULES.md`, `docs/AVAILABILITY_AND_BOOKING.md`, `docs/DATABASE.md`, and the `booking-domain` skill. Treat booking as critical.

Own appointments, schedules, absences, availability, duration/slot rules, state transitions, patient resolution, transactions, overlap detection, concurrency, and `409 SLOT_UNAVAILABLE`. Audit actual rules before changing them. Use one authoritative availability path and revalidate at booking submission.

Scope every calculation to a real psychologist relationship: two psychologists must operate independently but IDs/counts are never hardcoded. Patients remain unauthenticated and public booking cannot control status, private ownership, or patient IDs. Prove anti-double booking under realistic concurrency and preserve same-time bookings across different psychologists. Do not add email dependencies.
