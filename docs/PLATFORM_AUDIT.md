# Assist Platform Repository Audit

> Historical audit baseline. Findings describe the repository at the audit date;
> they are not a current feature inventory. See `PRODUCT_AND_FEATURES.md` and
> `PRODUCTION_CURRENT_STATE.md` for the present state.

## Scope and method

This audit covers the imported VanAssist codebase at source commit
`6975f33a63c9b53e36ad38434828c2d8a56ed7ff`. It reviewed the custom framework,
routes, controllers, middleware, services, 26 migrations, seed data, 149 PHP
views, CSS/JavaScript, tests, configuration, cron, and deployment scripts.

The audit is source-based. Production data, live host configuration, email
delivery, backups, and user journeys still require environment validation.

## Remediation snapshot

This document preserves the imported-source baseline. The current branch has
since remediated the installer race, outcome/review authorization, SMTP input
and TLS handling, migration locking/checksums, persistent throttling for primary
abuse flows, image decode limits, redirect safety, password-reset session
revocation, middleware fail-closed behavior, and placeholder cron success
reports. See `SECURITY.md` and `PRODUCTION_READINESS.md` for current status.

## Summary

VanAssist is a broad, working single-brand PHP/MySQL marketplace rather than a
skeleton. Its provider, request, matching, park, CMS, analytics, finance, and
admin capabilities are valuable and should be adapted incrementally.

It is not yet a production-ready multi-brand core. The principal blockers are:

- no brand boundary in code or data;
- an unsafe installer failure mode;
- stale consolidated schema and a fragile migration runner;
- two provider/review authorization gaps;
- insecure SMTP fallback behavior;
- weak automated integration and deployment assurance.

No TowSmart, TrailerWise, or existing multi-brand implementation was found.

## Critical

### Installer can remain remotely reusable

Installation is gated only by `storage/installed.lock`. Lock creation is not
verified, and deployment intentionally excludes that file. A fresh, damaged, or
partially failed deployment can leave the installer publicly available and allow
creation of a super administrator.

Evidence:

- `app/Core/Kernel.php`
- `app/Controllers/Install/InstallController.php`
- `.gitignore`

Required action: fail installation unless the lock is atomically created, add an
out-of-band installation secret or CLI-first workflow, and make readiness fail
while the installer is exposed.

### Consolidated schema is not a valid current baseline

`database/schema.sql` stops after migration 012. Current migrations continue
through 026 and define approximately 25 additional tables plus column changes.
The snapshot does not bootstrap migration history, so running the migrator after
a direct import can also attempt to recreate existing tables.

Required action: remove direct-import claims until a generated, CI-verified
snapshot and migration-history bootstrap exist.

## High

### Outcome and review provider authorization

Customer and tokenized follow-up workflows accept a submitted provider ID
without proving that provider was matched to the request. This can create false
verified-use reviews and analytics.

Evidence:

- `app/Controllers/AccountController.php`
- `app/Controllers/Site/FollowupController.php`
- `app/Services/Demand/OutcomeService.php`

### SMTP transport security

The built-in SMTP client disables TLS peer/hostname verification and permits
self-signed certificates. It also does not robustly reject CR/LF in headers.

Evidence: `app/Services/SmtpClient.php`.

### Migration execution

The migrator has no database advisory lock, checksum, dirty/failed state, or
atomic relationship between execution and migration metadata. MySQL DDL
auto-commit can leave partial, unrecorded migrations.

Evidence: `app/Services/Migrator.php`.

### Login and public abuse controls

Login throttling is session-local and bypassed by changing cookies. Turnstile is
configured but not enforced. Password reset, registration, public requests,
claims, and invitation workflows lack shared rate limiting.

Evidence:

- `app/Controllers/Auth/AuthController.php`
- `config/security.php`

### Image decompression denial of service

Byte limits are checked before GD decode, but dimensions are not safely bounded
before decode. Small compressed images with extreme dimensions can exhaust PHP
memory.

Evidence: `app/Services/ImageProcessor.php`.

### Deployment integrity

The deployment script defaults to FTP, compares files by size, and never removes
deleted remote files. Same-size changes may be skipped and obsolete PHP files
can remain executable.

Evidence:

- `scripts/deploy.ps1`
- `scripts/deploy.env.example`
- `INSTALL-CPANEL.md`

### Backup confidentiality and recovery

Backups are local and unencrypted; SMTP credentials may be included from
settings. The `mysqldump` password appears in process arguments. Offsite
replication and automated restore verification are absent.

Evidence: `app/Services/Backup.php`.

### Multi-brand coupling

Brand name, host, contacts, assets, email identity, analytics/cookie names,
colours, content, and legal details are hard-coded across configuration,
templates, seeds, controllers, and infrastructure.

Representative evidence:

- `.htaccess`
- `app/Views/partials/header.php`
- `app/Views/partials/footer.php`
- `app/Views/layouts/admin.php`
- `database/seeds/content.php`
- `database/seeds/email_templates.php`
- `config/security.php`

### Search indexing and workflow privacy

Static `public/robots.txt` bypasses dynamic launch-aware robots behavior.
Tokenized verification/follow-up pages and authenticated portal layouts lack a
strong default noindex boundary.

Evidence:

- `public/robots.txt`
- `public/.htaccess`
- `app/Controllers/Site/SitemapController.php`
- `app/Views/partials/seo-meta.php`

### Accessibility: errors and contrast

Form errors are not programmatically associated with controls. Several colour
combinations fail WCAG AA, and the autocomplete is not a complete ARIA
combobox.

Evidence:

- `app/Views/public/request-form.php`
- `public/assets/css/app.css`
- `public/assets/js/app.js`

## Medium

- Unknown middleware names fail open in `app/Core/Router.php`.
- Maintenance and launch gates fail open on database/settings exceptions.
- `back()` and some external provider links can redirect to insufficiently
  validated external URLs.
- Password reset does not revoke existing sessions or all older reset tokens.
- Email queue claiming is non-atomic and can duplicate or strand messages.
- SMTP credentials can be stored plaintext in `site_settings`.
- Public image uploads lack a dedicated no-script-execution web-server rule.
- Authenticated HTML responses do not consistently use `Cache-Control: no-store`.
- Town autocomplete inserts database values through `innerHTML`.
- Trusted-admin CMS HTML is rendered without sanitization.
- CSV exports do not neutralize spreadsheet formula prefixes.
- Security headers do not cover all early/exception responses.
- Proxy headers are trusted without a trusted-proxy allowlist.
- The sitemap remains populated while indexing is disabled.
- Provider pagination canonicalizes all pages to page one.
- The public provider town selector can render thousands of options.
- Data tables commonly lack captions and header scope.
- Mobile menus lack Escape, focus restoration, and robust dismissal behavior.
- Static third-party/fictitious hero identity details require asset/licensing
  review.

## Low

- Some `target="_blank"` links omit `rel="noopener"`.
- Favicon, application manifest, Apple touch icon, and theme colour are absent.
- Tracking-cookie secure detection is incomplete behind TLS proxies.
- Root/public web-server behavior is inconsistent for canonical hosts.

## Technical debt

- No CI/CD workflows or protected main branch.
- Only 28 unit tests; no implemented integration suite.
- Documentation and changelog status are inconsistent with implemented phases.
- Two documented cron jobs are placeholders.
- Audit logging silently ignores failures and is not tamper-evident.
- Log cleanup truncates rather than rotates.
- `database/schema.sql` and testing documentation are stale.
- One stylesheet and pervasive inline styles couple public, portal, and admin UI.
- Reusable provider cards, breadcrumbs, progress meters, and form-error patterns
  are duplicated.
- Existing provider ownership cannot represent teams.
- Billing schema advertises users/branches that do not exist.
- Legacy and current billing/provider token columns overlap.

## Future enhancements

- Central cross-domain identity using a standards-based authorization flow.
- TowSmart calculations, safety checks, and reports.
- TrailerWise manufacturer/dealer/listing workflows.
- External search index when MySQL search thresholds are exceeded.
- Malware-scanning adapter for uploaded documents.
- Administrative MFA and step-up authentication.
- Distributed queue/cache infrastructure only after measured need.

## Positive controls

- PDO native prepared statements are broadly used.
- Passwords use `PASSWORD_DEFAULT`; sessions rotate on login.
- CSRF protection covers normal state-changing routes.
- Private documents are generally outside the web root with ownership checks.
- Request images are MIME-checked, re-encoded, and randomly named.
- Stripe signatures use HMAC, timestamp tolerance, and constant-time comparison.
- No committed live API key, Stripe secret, private key, or password was found.
- Existing layouts include skip links, labels, focus styling, reduced-motion
  support, and mobile-first grids.

## Dependencies and compatibility

- Composer resolves current supported PHPMailer and PHPUnit 10 dependencies.
- Composer audit reports no known advisories at the audit date.
- First-party PHP parses on PHP 8.3.
- The unit suite passes with five warnings caused by missing test database
  configuration in one disabled analytics path.

## Immediate order of remediation

1. Harden installer, outcome/review authorization, SMTP, and middleware failure.
2. Establish integration tests and CI.
3. Harden migrations and correct schema documentation.
4. Add brand registry/context with VanAssist as the default.
5. Add additive brand/provider membership schema and backfills.
6. Extract brand presentation, SEO, and notification identity.
7. Add TowSmart and TrailerWise scaffolds only after isolation tests pass.
