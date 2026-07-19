# Local Development

## Prerequisites

- PHP 8.1 or newer (PHP 8.3 is validated locally)
- Extensions: PDO MySQL, mbstring, JSON, fileinfo, DOM/XML, curl, zip, GD
- Composer 2
- MySQL 8 or MariaDB 10.6+
- Apache with `mod_rewrite`, or the PHP development server for limited testing

Optional data-import tools require Node.js or Python and are not needed for
normal application runtime.

## Install dependencies

```bash
composer install
composer check-platform-reqs
```

Do not use `composer update` for routine setup when `composer.lock` exists.

## Environment

Copy the example without committing the result:

```bash
cp .env.example .env
```

Use a dedicated local database and non-production credentials. Recommended
development values:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://vanassist.test
SESSION_SECURE=false
MAIL_DRIVER=log
ASSIST_DEFAULT_BRAND=vanassist
ASSIST_BRAND_RESOLUTION=host
```

Never copy production secrets or a production database into an unprotected
developer workstation.

## Database

Create an empty local database and account, then set `DB_*` in `.env`.

The ordered files in `database/migrations/` are authoritative:

```bash
php scripts/migrate.php
php scripts/seed.php
```

Optional isolated development fixtures:

```bash
php scripts/seed.php --demo
```

There is no consolidated schema import. Run the ordered migrations; they are the
only authoritative fresh-install and upgrade path.

## Web server

### Apache

Point the virtual host document root to this repository's `public/` directory
and permit `.htaccess` overrides. Suggested local hosts:

```text
127.0.0.1 vanassist.test
127.0.0.1 towwise.test
127.0.0.1 trailerwise.test
127.0.0.1 admin.assist.test
```

Only VanAssist is fully operational until the other brand scaffolds are enabled.

### PHP development server

For request-level development where Apache rules are not under test:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

This does not validate Apache redirects, static-file precedence, or `.htaccess`
security. Use Apache for release smoke tests.

## Initial installation

The browser installer is retained for compatibility but must not be exposed on a
public interface. Prefer CLI migration/seeding in development. If the installer
is used:

1. bind the site to localhost;
2. complete installation;
3. verify `storage/installed.lock` exists;
4. verify `/install` is no longer available.

## Tests

```bash
composer validate --strict
composer check-platform-reqs
./vendor/bin/phpunit
composer audit
```

Database-backed integration tests must use a separate test database. Never point
the test suite at development, staging, or production data.

## Local email

Tests must not send real email. Development should use the configured log/file
transport or a local mail catcher when one is added. Production SMTP credentials
must not be used locally.

## Storage

Ensure these paths are writable by the web process:

- `storage/cache`
- `storage/logs`
- `storage/sessions`
- `storage/private`
- `storage/backups`
- `storage/imports`
- `public/uploads-public`

Private files must not be served directly by the web server.

## Brand development

- Resolve brands by local hostname.
- Use explicit `ASSIST_BRAND` for cron/CLI commands where required.
- Do not emulate cross-domain authentication with an overly broad cookie.
- Keep TowWise and TrailerWise fixtures clearly marked as development-only.
- Test that a brand-scoped user/resource cannot be accessed through another
  brand host.

## Common commands

```bash
php scripts/migrate.php
php scripts/seed.php
php cron/run.php process_email_queue
php scripts/coverage-report.php
./vendor/bin/phpunit
```

## Troubleshooting

### Redirected to `/install`

Check database settings, migration state, and `storage/installed.lock`. Do not
create the lock merely to hide a failed migration.

### Database configuration warnings in tests

Use the isolated integration-test environment. Unit tests should not rely on
the developer `.env`.

### Apache 404s

Confirm `mod_rewrite` is enabled, the document root is `public/`, and
`AllowOverride` permits the bundled rules.

### Upload failures

Check PHP upload limits, GD/fileinfo extensions, storage permissions, file size,
and configured MIME allowlists.

### Brand resolves incorrectly

Check exact local hostname mapping, `ASSIST_BRAND`,
`ASSIST_DEFAULT_BRAND`, and trusted-proxy configuration. Unknown production
hosts should fail rather than default silently.
