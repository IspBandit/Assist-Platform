# Assist Platform Enterprise

Assist Platform Enterprise is the primary commercial product. VanAssist,
TowSmart, TrailerWise and LocalTorque are brands running on its shared runtime,
data and administration platform. Start with
[`docs/START_HERE.md`](docs/START_HERE.md) and the
[`Enterprise architecture specification`](docs/ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md).
The [`Product Bible`](docs/PRODUCT_BIBLE.md) defines the portfolio promises and
brand boundaries; the [`UX component inventory`](docs/UX_COMPONENT_INVENTORY.md)
tracks promotion of the current redesign into the shared Design System.

Shared platform core for:

- **VanAssist** (`vanassist.com.au`) — the existing caravan/RV service marketplace and compatibility baseline.
- **TowSmart** (`towsmart.com.au`) — towing mass-limit calculator with saved user combinations and safety guidance.
- **TrailerWise** (`trailerwise.com.au`) — trailer-industry business and service platform. Its current sales/hire listing MVP is secondary and requires product correction before full launch.
- **LocalTorque** (private foundation; production domain pending) — Australian automotive workshop and specialist directory.

This is an incremental evolution of VanAssist, not a mass rename or three copied
applications. VanAssist routes, IDs, slugs, users, providers, and workflows remain
the backward-compatibility baseline.

## Current status

Implemented foundation:

- Existing VanAssist public, customer, provider, park, and admin functionality.
- Typed brand registry, domain/environment resolution, and request brand context.
- Disabled-brand gate that prevents TowSmart/TrailerWise hosts from exposing
  VanAssist routes.
- Additive brand, provider-listing, brand-profile, brand-role, and provider-
  membership database migrations.
- Restartable batched VanAssist data backfill with integrity validation.
- Migration advisory locking, checksums, and failed/dirty state.
- Installer, SMTP, middleware, outcome/review, upload, redirect, persistent
  throttling, session-revocation, proxy, secret-storage, and queue-lease controls.
- Brand-aware queued email attribution, liveness/readiness endpoints, request
  correlation, and authenticated no-store responses.
- PHPUnit unit/database integration tests, PHPStan, Composer audit, and fresh
  MySQL migration/seed/backfill CI checks.

Implemented brand applications:

- TowSmart towing calculator, saved combinations, safety explanations, and
  brand-isolated public routes.
- TrailerWise searchable trailer marketplace, provider listing management, and
  admin moderation foundations.
- BinaryLane Docker production runtime for the shared three-domain deployment.

Future expansion:

- Full brand-scoping of every operational table/query.
- Shared cross-domain sign-in.
- Full integration/E2E/accessibility test coverage.
- Production Stripe charging.
- Transactional production email, off-server application backups, and final
  public-launch acceptance testing.

Do not treat the current branch as production-ready without following
[`docs/PRODUCTION_READINESS.md`](docs/PRODUCTION_READINESS.md).

## Runtime

- PHP 8.1+ (PHP 8.3 validated)
- Apache `mod_rewrite`
- MySQL 8 or MariaDB
- Composer 2
- Server-rendered PHP and vanilla JavaScript

Production runs on the documented BinaryLane Docker runtime. The application
also supports conventional Linux/cPanel hosting. Only `public/` should be
web-accessible.

## Local setup

```bash
composer install
cp .env.example .env
# configure a dedicated local database in .env
php scripts/migrate.php
php scripts/seed.php
php scripts/backfill-platform.php --batch=500
php scripts/backfill-platform.php --validate-only
```

Point Apache at `public/`, or use the limited PHP development server:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

See [`docs/LOCAL_DEVELOPMENT.md`](docs/LOCAL_DEVELOPMENT.md).

## Validation

```bash
composer validate --strict
composer check-platform-reqs
composer analyse
./vendor/bin/phpunit
composer audit
php scripts/migrate.php
php scripts/backfill-platform.php --validate-only
RUN_INTEGRATION_TESTS=1 ./vendor/bin/phpunit --testsuite Integration
```

## Project layout

```text
app/Core                 custom HTTP/runtime framework
app/Platform/Brand       typed brand registry, resolver and context
app/Controllers          public and portal/admin endpoints
app/Models               lightweight PDO models
app/Services             shared and legacy VanAssist business services
app/Views                server-rendered, brand-aware application templates
config                   environment-derived application and brand configuration
database/migrations      authoritative ordered schema changes
database/seeds           core/content/location/provider seed data
public                   only web-accessible directory
storage                  private runtime data
routes                   route registrars
scripts                  migration, seed, backfill and deployment tools
infrastructure/binarylane production Docker, Caddy, monitoring and backup tooling
tests                    unit and database integration tests
```

`database/migrations/` is authoritative. The stale legacy consolidated schema
was removed so fresh installs cannot accidentally bypass later migrations.

## Architecture and operations

- [Start here](docs/START_HERE.md)
- [Products and feature status](docs/PRODUCT_AND_FEATURES.md)
- [Verified production state](docs/PRODUCTION_CURRENT_STATE.md)
- [Current architecture](docs/CURRENT_ARCHITECTURE.md)
- [Target architecture](docs/TARGET_ARCHITECTURE.md)
- [Migration plan](docs/MIGRATION_PLAN.md)
- [Platform audit](docs/PLATFORM_AUDIT.md)
- [Production readiness](docs/PRODUCTION_READINESS.md)
- [Brand configuration](docs/BRAND_CONFIGURATION.md)
- [Security](docs/SECURITY.md)
- [API architecture](docs/API.md)
- [Testing](docs/TESTING.md)
- [Local development](docs/LOCAL_DEVELOPMENT.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Routes and permissions](docs/ROUTES_AND_PERMISSIONS.md)
- [Database dictionary](docs/DATABASE_DICTIONARY.md)
- [Administrator guide](docs/ADMINISTRATOR_GUIDE.md)
- [Operations runbook](docs/OPERATIONS_RUNBOOK.md)
- [Transactional email](docs/TRANSACTIONAL_EMAIL.md)
- [Backup and restore](docs/BACKUP_AND_RESTORE.md)

AI coding agents must also follow [`AGENTS.md`](AGENTS.md) and the permanent
Cursor rules under `.cursor/rules/`.

Legacy VanAssist implementation notes remain under `docs/` for historical
context. Where they conflict, the Assist Platform documents above are current.

## Licence

Proprietary. All rights reserved.
