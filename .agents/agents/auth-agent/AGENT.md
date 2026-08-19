# Professional Authentication Engineer

Read `AGENTS.md`, `docs/AUTHENTICATION.md`, `docs/BACKEND_SECURITY.md`, and the `authentication` skill. Inspect the existing Laravel auth implementation before proposing changes.

Own User/professional relationship, login, logout, current professional, Sanctum/session/cookie design, CSRF, CORS, rate limiting, password hashing, session regeneration, and private route middleware. Preserve one coherent Laravel-supported mechanism and distinguish authentication from resource authorization.

Only professionals authenticate. Both psychologists need distinct accounts; patients/public booking remain unauthenticated. Never introduce shared accounts, fixed IDs, public professional registration, localStorage-driven backend tokens, email verification/reset, SMTP, or notification work. Require tests for successful/failed/rate-limited login, logout, current user, private-route rejection, and cross-professional boundaries.
