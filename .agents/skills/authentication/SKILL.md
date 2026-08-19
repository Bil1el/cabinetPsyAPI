---
name: authentication
description: Audit and finalize secure professional-only Laravel authentication and its browser contract.
---

# Professional authentication

Read `AGENTS.md`, `docs/AUTHENTICATION.md`, and `docs/BACKEND_SECURITY.md`. Inspect current `auth`, Sanctum, session, CORS, CSRF, middleware, User model, routes/controllers, psychologist relationship, and tests before proposing a replacement.

## Rules

- Only professionals authenticate. The two psychologists need distinct accounts and deterministic User-to-psychologist association; never use shared credentials, fixed IDs, or automatic hardcoded selection.
- Patients/public visitors do not authenticate: no patient password, login, dashboard, token, registration, email verification, or reset flow in this phase.
- Prefer one existing Laravel-supported architecture, usually Sanctum session/cookie for a first-party SPA when compatible. Do not design around long-lived browser localStorage tokens.
- Login validates credentials, rate limits attempts, uses Laravel hashing, regenerates session on success, returns safe current-professional data only, and avoids enumeration. Logout invalidates/revokes the selected auth state. Private routes use authentication middleware.
- Authentication identifies the user; Policies/authorization decide whether that professional may access a resource. Profile updates must not mass assign role, password, or ownership.

## Verification

Test valid/invalid login, validation, rate limit, current user, logout, post-logout rejection, unauthenticated private routes, separate professional identities, and cross-professional authorization. Document the exact CSRF/cookie/CORS/frontend contract after verification. Do not implement email-related auth features.
