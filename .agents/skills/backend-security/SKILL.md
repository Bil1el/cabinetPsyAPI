---
name: backend-security
description: Perform a practical Laravel backend security review focused on privacy, authorization, abuse, and integrity.
---

# Backend security

Read `AGENTS.md`, `docs/BACKEND_SECURITY.md`, `docs/AUTHENTICATION.md`, and `docs/DEFINITION_OF_DONE.md`. Treat the frontend as untrusted. Report P0/P1/P2/P3 with affected code, exploit preconditions, impact, minimal remediation, and regression test.

## Review areas

- Verify professional authentication and backend authorization separately. Test IDOR/BOLA by changing patient, appointment, schedule, absence, and profile IDs across the two psychologists; apply least privilege until shared visibility is explicitly decided.
- Enforce public/private route separation and patient privacy. Audit Resources, loaded relationships, pagination, date ranges, sorting, dashboard aggregation, and public booking responses for overexposure and exhaustion.
- Review Form Requests, `$fillable`/`$guarded`, explicit mapping, raw SQL/dynamic sorting, SQL injection, status/ownership tampering, unsafe deserialization, command execution, SSRF, and future file-upload paths.
- Protect login and public booking with Laravel rate limiting; inspect CSRF, CORS credential origins, secure HttpOnly/SameSite cookies, token lifecycle, session regeneration, and no localStorage-driven backend design.
- Inspect `.env.example`, tracked secrets without printing values, config cache safety, `APP_DEBUG`, exception rendering, logs, dependency exposure (`composer audit`), and unsafe debug code.

## Scope

Do not add patient accounts, email/SMTP/notifications, CAPTCHA, custom crypto, opaque IDs as a substitute for authorization, or unrelated infrastructure. Security fixes must preserve valid behavior where possible, stabilize contracts, and include tests for 401/403/409/422/429 and privacy regressions.
