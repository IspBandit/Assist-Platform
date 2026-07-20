# Assist Platform Testing

## Current automated checks

```bash
composer install
composer validate --strict
composer check-platform-reqs
composer analyse
./vendor/bin/phpunit
composer audit
```

Current local baseline:

- PHP 8.3
- 55 unit tests and 139 assertions
- 6 database integration tests and 21 assertions
- PHPStan level 3 over application/framework/config/route/script code
- fresh MariaDB migration through migration 033
- repeat migration produces no changes
- core seed succeeds
- platform backfill and integrity validation succeed
- no known Composer advisory at the latest local run

The exact counts may grow; CI output is authoritative.

## Test layers

### Unit

Fast, database-independent tests under `tests/Unit` cover helpers, routing,
validation, reporting, billing entitlement logic, analytics vocabulary, brand
registry/resolution, and SMTP input rejection.

### Integration

Database-backed tests under `tests/Integration` must use a disposable database.
Required coverage includes:

- migration lock/checksum/dirty behavior;
- fresh install and installer lock failure;
- registration, verification, login, logout, reset, and session revocation;
- CSRF and server-side permission denial;
- provider/customer/brand ownership and IDOR attempts;
- upload MIME, size, dimensions, private serving, and public execution rules;
- email queue claim/retry/recovery without sending external mail;
- provider listing and membership backfill;
- brand-scoped content, search, reviews, analytics, and admin access;
- existing VanAssist URL compatibility.

### End-to-end

E2E coverage is not yet implemented. It must exercise a real Apache-compatible
staging environment and disposable mail/storage/database dependencies.

Critical journeys:

1. Customer registration, verification, login, and password reset.
2. Provider search and profile view.
3. Assistance request and provider enquiry/matching.
4. Provider registration, profile update, and request response.
5. Review submission and moderation.
6. Admin login and provider management.
7. Brand resolution and disabled-brand coming-soon response.
8. Cross-brand and cross-provider denial.
9. Existing VanAssist routes, metadata, sitemap, robots, and media.

## Database test setup

Use a dedicated database and environment variables:

```dotenv
APP_ENV=test
APP_DEBUG=false
APP_URL=http://vanassist.test
APP_KEY=test-only
DB_HOST=127.0.0.1
DB_NAME=assist_platform_test
DB_USER=assist_test
DB_PASSWORD=test-only
SESSION_SECURE=false
MAIL_DRIVER=log
```

Then:

```bash
php scripts/migrate.php
php scripts/migrate.php
php scripts/seed.php
php scripts/backfill-platform.php --batch=500
php scripts/backfill-platform.php --validate-only
RUN_INTEGRATION_TESTS=1 ./vendor/bin/phpunit --testsuite Integration
```

Never run tests or backfills against production.

## Brand isolation matrix

For each brand-aware resource, test:

- unauthenticated access;
- correct-brand owner/member;
- wrong-brand user;
- same-brand wrong provider/organisation;
- brand administrator;
- platform administrator;
- disabled feature/module;
- numeric-ID and slug/alias access;
- queued/background processing using stored brand rather than worker host.

Both HTML and JSON/API responses must avoid leaking existence or private data.

## Security tests

Priorities:

- missing middleware fails closed;
- forged provider IDs cannot create outcomes or verified reviews;
- reset/verification/follow-up tokens are single-use and expire;
- brute-force and abuse controls survive cookie rotation;
- external redirects reject untrusted schemes/hosts;
- SMTP headers reject CR/LF and TLS verification cannot be disabled silently;
- malicious image dimensions fail before decode;
- uploads cannot execute from public storage;
- webhooks reject bad signatures and are idempotent/retry-safe;
- CSV output neutralizes formulas;
- production errors omit stack traces and secrets.

## Accessibility tests

Combine automation and manual checks:

- axe-compatible page scans for representative public/portal/admin routes;
- keyboard-only navigation and visible focus;
- screen-reader form labels/errors and autocomplete;
- modal/menu focus management and Escape handling;
- table semantics and responsive overflow;
- WCAG 2.2 AA contrast;
- reduced motion and mobile touch targets.

Automation does not replace assistive-technology testing.

## Release smoke tests

After deployment verify:

- expected deployment version and brand;
- installer unavailable;
- homepage, search, provider profile, CMS/legal, login, account/provider/admin;
- authorization denial paths;
- queue and cron status;
- private/public media;
- canonical URL, sitemap, robots, structured data, and 404/500 pages;
- TowSmart and TrailerWise coming-soon response until enabled;
- no unexpected VanAssist route exposure on disabled brands.

## Test safety

- Tests never send production email, payment, analytics, or external import
  requests.
- Fixtures are clearly marked and isolated.
- Secrets and production personal data are prohibited.
- Failing tests are fixed, not skipped or suppressed to pass CI.
- Static-analysis baselines/ignore comments are not used to hide defects.
