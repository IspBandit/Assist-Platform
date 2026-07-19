# VanAssist Testing Guide

> **Migration notice:** The current Assist Platform test strategy and commands
> are maintained in [`docs/TESTING.md`](docs/TESTING.md). This legacy checklist
> predates the brand foundation, PHPStan, current unit suite, platform migrations,
> and CI workflow.

VanAssist targets ordinary shared hosting, where a full automated browser suite
is often impractical. We therefore combine **PHPUnit unit tests** (no database
required), an **integration approach**, and a **manual acceptance checklist**.

## 1. Automated unit tests

Requires dev dependencies (`composer install`). Run:

```bash
composer test          # or: ./vendor/bin/phpunit
```

Current unit coverage (Phase 1):

- `tests/Unit/ValidatorTest.php` — validation rules
- `tests/Unit/RouterTest.php` — route matching, params, 404/405, named URLs
- `tests/Unit/HelpersTest.php` — slug, escaping, URL helpers

These run without a database or `.env`.

## 2. Integration tests (database-backed)

For DB-backed tests, create a **separate test database**, point a temporary
`.env` at it, and run `php scripts/migrate.php` then `php scripts/seed.php`.
Integration tests for later phases (request submission, image validation,
provider onboarding, matching, run join, status transitions, private document
access, cron email processing, backup generation) are added per phase under
`tests/Integration`.

## 3. Manual acceptance checklist

### Installation
- [ ] Fresh visit redirects to `/install`.
- [ ] Requirements step flags missing extensions / unwritable folders.
- [ ] Bad DB credentials show a clear error (no stack trace).
- [ ] Successful install writes `.env`, runs migrations, seeds data, creates super admin.
- [ ] Installer is locked afterwards (`/install` redirects to `/admin`).
- [ ] `/.env` and `/storage/logs/*` are **not** web accessible.

### Authentication & roles
- [ ] Register creates a customer, logs in, queues a verification email.
- [ ] Email verification link marks the address verified.
- [ ] Login works; wrong password is rejected.
- [ ] Repeated failed logins trigger lockout.
- [ ] Logout clears the session.
- [ ] Password reset link works and expires/one-time only.
- [ ] `/admin` is blocked for guests and customers (403/redirect).
- [ ] Super admin can reach `/admin`; dashboard shows stats.

### Security
- [ ] Submitting a POST without a CSRF token is rejected.
- [ ] Security headers present (CSP, X-Frame-Options, nosniff).
- [ ] HTTPS is forced once SSL is active.
- [ ] No stack traces with `APP_DEBUG=false`; friendly 404/403/500 pages render.

### Public site
- [ ] Homepage renders hero, search, sections and free-launch message.
- [ ] Static/legal pages render from the CMS.
- [ ] Informational pages (how it works, for providers, for parks) render.
- [ ] "Coming soon" placeholders render for not-yet-built sections (no broken routes).
- [ ] Mobile navigation toggle works; pages are responsive.

### Email & cron
- [ ] `php cron/run.php process_email_queue` sends queued mail (with SMTP + PHPMailer).
- [ ] Cron lock prevents overlapping runs.
- [ ] `php cron/run.php database_backup` creates a backup in `storage/backups`.
- [ ] Admin dashboard shows scheduled-task last-run status.

### Data & expansion
- [ ] Queensland, 9 regions and the seeded towns exist after install.
- [ ] All 24 service categories exist.
- [ ] Demo data (if seeded) is clearly labelled `[DEMO]` and removable.

Record results against each release in `CHANGELOG.md`.
