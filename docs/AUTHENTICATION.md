# Authentication — Psychology Cabinet Backend

## 1. Purpose

This document defines the authentication principles for the private professional area.

The backend already contains an authentication implementation that must be audited before being changed.

Do not replace the existing authentication system until its current behavior, security and frontend requirements are understood.

---

## 2. Who authenticates

Authentication is currently required only for authorized professionals.

The cabinet currently contains two psychologists.

Both psychologists must be able to access the professional application using their own professional account.

Patients do NOT authenticate.

Public visitors do NOT authenticate to book an appointment.

---

## 3. Separate professional identities

Each psychologist must have their own authentication identity.

Do not implement:

- one shared account for both psychologists;
- shared passwords;
- hardcoded professional identity;
- automatic psychologist selection based on a fixed user ID.

The authenticated user must be deterministically associated with the appropriate professional/psychologist identity.

## Account lifecycle

Professional accounts use `users.status`: `invited`, `active`, or `suspended`. An `active`, email-verified psychologist profile can authenticate and use its private workspace. An `active`, email-verified admin may authenticate for cabinet administration without a psychologist profile; it cannot thereby access psychologist-owned operational data. Public availability and booking require both an active user account and `psychologists.is_active=true`; the latter is the profile-level public visibility/bookability switch and does not grant dashboard access.

There is no public professional registration endpoint. An authenticated admin creates an invitation; it creates or refreshes an invited psychologist account and an inactive public profile. Invitation tokens are cryptographically random, stored only as SHA-256 hashes, expire after 48 hours, can be revoked, and are single use. Acceptance sets a Laravel-hashed password, `email_verified_at`, account status to `active`, and the invitation acceptance timestamp.

Password reset uses Laravel’s password broker and its hashed, expiring, single-use token storage. The request response is deliberately identical for unknown and ineligible email addresses. A signed-in professional can change their password by supplying the current password; existing database sessions and Sanctum personal tokens are invalidated. Email changes retain the old address until a random, hashed, 24-hour confirmation token sent to the new address is accepted.

Admins may suspend or reactivate accounts. Suspension invalidates database sessions and Sanctum tokens immediately; the active-professional middleware also denies any already-authenticated suspended account. Account mail links are built from `FRONTEND_URL`; mail transport remains entirely environment-configured through Laravel’s standard mail configuration.

---

## 4. Authentication vs psychologist profile

The architecture must distinguish when appropriate between:

- authentication account;
- psychologist business identity;
- public psychologist profile.

Codex must inspect the current schema before deciding whether these concepts use one table or related tables.

Do not create duplicate models unnecessarily.

---

## 5. Authentication strategy

Audit the current Laravel authentication configuration.

Inspect at minimum:

- `config/auth.php`;
- `config/sanctum.php` if present;
- User model;
- authentication routes;
- authentication controllers;
- middleware;
- session configuration;
- CORS;
- CSRF;
- existing frontend expectations;
- tests.

Prefer one coherent Laravel-supported authentication strategy.

---

## 6. SPA authentication

If the frontend and Laravel backend use first-party SPA authentication, Laravel Sanctum cookie/session authentication should be evaluated as the preferred architecture when compatible with the existing deployment.

Do not automatically switch architecture before auditing the current implementation.

---

## 7. No insecure token storage requirement

Do not design authentication around storing long-lived sensitive authentication tokens in browser `localStorage`.

If the existing backend currently requires this, report it during audit and evaluate the safer architecture.

The frontend will later adapt to the finalized backend authentication contract.

---

## 8. Login

Login must:

1. validate credentials;
2. apply rate limiting;
3. authenticate through Laravel-supported mechanisms;
4. establish the appropriate authenticated state;
5. return only safe professional information when required.

Do not implement custom password comparison.

---

## 9. Login input

The final login identifier must be based on the actual existing product.

Likely input:

- email;
- password.

Do not invent username/phone authentication without requirement.

Use a dedicated Form Request when appropriate.

---

## 10. Login failure

Invalid credentials must produce a predictable authentication failure.

Do not return:

- password-specific debugging information;
- password hash;
- SQL errors;
- stack traces.

Avoid unnecessary account enumeration.

---

## 11. Login rate limiting

Login must have protection against repeated credential guessing.

Use Laravel rate limiting.

The final limit should be documented/configurable rather than scattered as magic values.

---

## 12. Logout

Logout must invalidate the relevant authenticated state according to the selected authentication architecture.

After logout, private endpoints must no longer accept the previous session/authentication state.

---

## 13. Current professional endpoint

The frontend requires a reliable way to retrieve the currently authenticated professional.

The response may contain required information such as:

- ID;
- name;
- email;
- professional/profile information required by the UI;
- safe authorization-related information if genuinely required.

Never expose:

- password;
- password hash;
- remember token;
- session identifier;
- private secrets.

---

## 14. Authentication does not equal authorization

A successfully authenticated psychologist does NOT automatically have unrestricted access to all private resources.

Authorization remains responsible for:

- appointments;
- patients;
- schedules;
- absences;
- settings;
- psychologist-specific operations.

---

## 15. Two-psychologist authorization question

The final product must explicitly resolve whether:

### Option A

Each psychologist sees/manages only their own operational data.

or:

### Option B

The two authorized psychologists share some cabinet-wide operational data.

Authentication implementation must not silently decide this question.

The backend audit must report current behavior.

Until resolved, least privilege is the security default.

---

## 16. Route protection

All private API routes must use the appropriate authentication middleware.

Public routes must remain separate.

Audit route groups for accidentally unprotected professional endpoints.

---

## 17. CSRF

If session/cookie-based SPA authentication is used, CSRF protection must remain enabled.

Do not add CSRF exceptions merely because frontend requests currently fail.

Fix the client/server integration correctly.

---

## 18. CORS

Authentication architecture and CORS must be compatible.

Development frontend origins and production frontend origins must be explicitly configured.

Do not enable unrestricted credentialed CORS.

---

## 19. Session security

If sessions/cookies are used, production configuration must appropriately consider:

- HTTPS;
- secure cookies;
- HttpOnly;
- SameSite;
- session domain;
- frontend/backend domain relationship.

Final values depend on deployment architecture.

---

## 20. Password hashing

Use Laravel's supported hashing system.

Never:

- store plaintext passwords;
- implement MD5/SHA password storage;
- compare password strings manually;
- return password hashes.

---

## 21. Password creation

The two professional accounts must be created through a controlled process.

Do not expose unrestricted public professional registration unless explicitly required.

The current product does not require public psychologist registration.

---

## 22. Registration

Public professional registration is NOT part of the current product.

Do not create a `/register` workflow for psychologists unless explicitly approved.

Professional accounts may be provisioned administratively during setup.

The final provisioning process can be decided separately.

---

## 23. Password reset

Do not implement password-reset email infrastructure during the current phase because email functionality is intentionally deferred.

If password reset already exists, audit it and report its dependency on email.

Do not delete working secure functionality without review.

---

## 24. Email verification

Professional email verification is deferred with the email phase unless an existing authentication requirement makes it necessary.

Do not block current backend finalization on email verification.

---

## 25. Roles

Do not introduce a complex role/permission system unless the product requires it.

The current product has two psychologists.

If an existing `role` field exists, audit:

- why it exists;
- allowed values;
- authorization usage;
- mass-assignment protection.

Do not add admin/super-admin roles without product requirement.

---

## 26. Privilege escalation

Clients must never be able to assign or modify their own privileged role/permissions through generic profile updates.

Sensitive authorization fields must not be mass assignable from ordinary requests.

---

## 27. Authentication logging

Do not log:

- passwords;
- full credentials;
- session cookies;
- auth tokens.

Security-relevant events may be logged safely where useful without exposing secrets.

---

## 28. Account status

Do not invent account-status workflows unless the existing project requires them.

If fields such as `active`, `disabled`, or `status` already exist, audit their semantics and enforcement.

---

## 29. Session fixation

Use Laravel-supported session regeneration behavior after successful session authentication.

Do not implement custom session management that weakens framework protections.

---

## 30. Error semantics

Private API authentication errors should consistently use:

- `401` when unauthenticated;
- `403` when authenticated but unauthorized.

Do not use `500` for normal authentication/authorization failures.

---

## 31. Authentication tests

The final test suite should cover at minimum:

- valid professional login;
- invalid credentials;
- login validation;
- login rate limiting where practical;
- current-user endpoint authenticated;
- current-user endpoint unauthenticated;
- private route rejection when unauthenticated;
- logout;
- access after logout rejected;
- authorization boundary between professionals according to final policy.

---

## 32. No patient authentication

Do not create authentication middleware or credentials for patients.

The public booking workflow operates without patient accounts.

Patient identity in appointment records is not an authentication identity.

---

## 33. No frontend security assumptions

Frontend behavior such as:

- hiding dashboard links;
- hiding buttons;
- protected React routes;

is UX only.

Laravel must independently protect every private operation.

---

## 34. Audit checklist

Codex must determine:

1. What authentication system currently exists?
2. Is Sanctum installed/configured?
3. Session cookies or bearer tokens?
4. Are private routes protected?
5. Is CSRF correctly configured?
6. Is CORS compatible and restricted?
7. Is login rate-limited?
8. How is a user associated with a psychologist?
9. Are both psychologists separate accounts?
10. Can one account access another professional's data?
11. Are passwords safely hashed?
12. Is public professional registration exposed?
13. Are sensitive account fields mass assignable?
14. Are authentication tests present?
15. Does any email dependency currently exist?

Classify findings before refactoring.

---

## 35. Frontend handoff

After backend authentication is finalized, document the exact frontend contract in `API_CONTRACTS.md`.

The frontend must then adapt to:

- CSRF requirements;
- credential behavior;
- login endpoint;
- logout endpoint;
- current-user endpoint;
- `401` behavior;
- `403` behavior.

Do not make the frontend guess the authentication mechanism.

---

## 36. Final principle

Authentication answers:

"Who is making this request?"

Authorization separately answers:

"Is this professional allowed to perform this action on this resource?"

Both must be enforced by Laravel.
