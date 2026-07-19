# VanAssist Security Documentation

> **Migration notice:** The current Assist Platform security record is
> [`docs/SECURITY.md`](docs/SECURITY.md). This legacy document describes the
> original VanAssist intent and overstates several controls. In particular,
> immutable audit guarantees, complete upload scanning, administrative MFA,
> privacy workflows, and encrypted offsite backup recovery are not yet complete.

This document describes the threat model and the security controls implemented
in VanAssist. It is written for a shared cPanel hosting deployment.

## 1. Threat model

VanAssist handles personal contact details, private addresses, vehicle
registrations and provider verification documents (licences, insurance). Primary
threats considered:

- **Account compromise** (credential stuffing, brute force, session hijacking).
- **Injection** (SQL injection, XSS, CSRF).
- **Unauthorised data access** (insecure direct object references, exposure of
  private documents, leaking internal notes or customer addresses publicly).
- **Malicious uploads** (executable files disguised as images).
- **Configuration/secret exposure** (`.env`, DB credentials, file paths).
- **Privilege escalation** between roles.

## 2. Authentication controls

- Passwords hashed with `password_hash` (bcrypt/`PASSWORD_DEFAULT`); verified
  with `password_verify`; transparent rehash on cost change.
- Minimum password length enforced (10+).
- **Login rate limiting** with lockout (`LOGIN_MAX_ATTEMPTS`,
  `LOGIN_LOCKOUT_MINUTES`).
- **Session regeneration** on login; secure, HttpOnly, SameSite cookies; session
  files stored in `storage/sessions` outside the web root.
- **Admin idle-session timeout** (`ADMIN_SESSION_TIMEOUT`).
- Email verification and time-limited, single-use password-reset tokens (stored
  only as SHA-256 hashes).
- Login attempts (success/failure) recorded in `user_login_history`.
- Honeypot field on auth/public forms; optional Cloudflare Turnstile hook.

## 3. Authorisation (RBAC)

- Seven roles with fine-grained permissions (`role_permissions`).
- Every protected route runs `auth` + `role`/`permission` middleware.
- Super administrator bypasses individual permission checks (recovery path).
- Object ownership is checked in controllers to prevent insecure direct object
  reference (IDOR) — e.g. customers may only view their own requests.

## 4. Injection & request integrity

- **All** database access uses PDO **prepared statements** (no string-built SQL
  with user input).
- Output is escaped via `e()` / `View::e()` (htmlspecialchars, UTF-8).
- **CSRF tokens** required on every state-changing request (`VerifyCsrf`).
- Method spoofing limited to PUT/PATCH/DELETE via `_method`.

## 5. Security headers

Applied on every response (`SecurityHeaders` middleware):

- `Content-Security-Policy` (self + inline styles only)
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- `Strict-Transport-Security` when served over HTTPS
- HTTPS forced at the web-server level (`public/.htaccess`).

## 6. Upload controls (Phase 4+)

- Server-side MIME validation via `finfo` (never trust the client or filename).
- Allow-list: JPEG, PNG, WebP only; configurable max size and image count.
- Images resized/compressed, EXIF stripped, thumbnails generated.
- **Randomised filenames**; originals never used on disk.
- Stored in `storage/private/request-images` (outside web root); served only
  through an authenticated, authorised controller.
- Executable/malformed files rejected.

## 7. Private file & data protection

- Configuration, logs, sessions, private uploads and backups live **outside**
  `public/` and are additionally denied by `.htaccess`.
- Provider verification documents are never linked publicly.
- Customer names and exact addresses are never shown publicly; providers receive
  full contact details only after an approved, consented match.
- `.env` is written with `0640` and protected by `.htaccess` deny rules.

## 8. Backups

- Daily DB backups via cron with retention (`BACKUP_RETENTION_*`).
- Backups stored outside the web root; download restricted to super admin.
- Migration/restore guidance in `INSTALL-CPANEL.md`.

## 9. Admin controls & auditing

- Immutable `audit_logs` (login, approvals, verifications, status changes,
  broadcasts, content/settings updates, exports). Not editable via the UI.
- Maintenance mode (admins bypass) and launch modes gate public access.
- Errors logged to `storage/logs`; stack traces never shown in production
  (`APP_DEBUG=false`).

## 10. Incident response basics

1. Set **maintenance mode** (admin settings) to take the site offline.
2. Rotate the database password and SMTP credentials; update `.env`.
3. Force logout by clearing `storage/sessions/*`.
4. Review `audit_logs` and `storage/logs` for the affected window.
5. Restore from the most recent clean backup if data integrity is in doubt.
6. Reset affected user passwords (issue reset links) and notify impacted users
   as required by Australian privacy obligations.

## Reporting a vulnerability

Email the platform administrator with details and reproduction steps. Do not
disclose publicly until a fix is deployed.
