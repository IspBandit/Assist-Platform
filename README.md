# Assist Platform Enterprise

Assist Platform Enterprise is the shared commercial platform behind three active Australian brands:

- **VanAssist** (`vanassist.com.au`) — caravan/RV services, traveller assistance, provider discovery and stays.
- **TowSmart** (`towsmart.com.au`) — towing calculations, compatibility, saved combinations, safety guidance and specialist discovery.
- **TrailerWise** (`trailerwise.com.au`) — trailer services, repairs, parts, inspections, compliance and ownership support, with sales/hire secondary.

LocalTorque and Polaris are retired and are not part of the active product or sale boundary. Historical migrations and audit records may remain where deleting or rewriting them would reduce database or due-diligence integrity.

This is one shared platform rather than three copied applications. VanAssist remains the compatibility baseline for routes, users, providers and mature operational workflows.

Start with [`docs/START_HERE.md`](docs/START_HERE.md), [`docs/PRODUCTION_CURRENT_STATE.md`](docs/PRODUCTION_CURRENT_STATE.md) and [`docs/SALE_READINESS.md`](docs/SALE_READINESS.md).

## Implemented foundation

- Shared brand registry, trusted host resolution and request brand context.
- Existing VanAssist public, customer, provider, park and administration functionality.
- TowSmart towing calculator, catalogue, saved combinations and guidance.
- TrailerWise service-first directory and marketplace foundations.
- Shared provider/listing architecture and brand-specific presentation.
- Migration locking, checksums, dirty-state handling and restartable backfills.
- SMTP, queued email, throttling, session revocation, upload controls and secret storage.
- Health/readiness endpoints, release correlation, structured logging and rollback tooling.
- PHPUnit, integration tests, PHPStan, Composer validation/audit and CI migration checks.
- BinaryLane Docker production runtime for the three public domains.

## Sale-readiness objective

The active acquisition package is deliberately limited to VanAssist, TowSmart and TrailerWise plus the shared Assist Platform runtime, operational tooling, permitted data, documentation and associated transferable assets.

Before marketing the business, the sale-readiness gate requires:

- all three critical public/provider/admin journeys accepted;
- automated independent backup and current restore evidence;
- buyer-grade analytics and operating-cost records;
- privacy/data inventory and retention position documented;
- provider/data provenance and transfer rights documented;
- remaining production credentials rotated and account ownership recorded;
- no active dependency on retired brands;
- an acquisition asset register and handover runbook completed.

See [`docs/SALE_READINESS.md`](docs/SALE_READINESS.md) for the working gate.

## Runtime

- PHP 8.1+ (PHP 8.3 validated)
- MySQL 8 or MariaDB
- Composer 2
- Server-rendered PHP and vanilla JavaScript
- Docker/Caddy production runtime on BinaryLane; conventional Linux/cPanel also supported

Only `public/` should be web-accessible.

## Local setup

```bash
composer install
cp .env.example .env
php scripts/migrate.php
php scripts/seed.php
php scripts/backfill-platform.php --batch=500
php scripts/backfill-platform.php --validate-only
```

For a limited development server:

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
app/Services             shared business services
app/Views                server-rendered, brand-aware templates
config                   environment-derived application and brand configuration
database/migrations      authoritative ordered schema changes
database/seeds           core/content/location/provider seed data
public                   web-accessible directory
storage                  private runtime data
routes                   route registrars
scripts                  migration, seed, backfill and deployment tools
infrastructure/binarylane production Docker, Caddy, monitoring and backup tooling
tests                    unit and database integration tests
```

`database/migrations/` is authoritative. Historical brand migrations remain part of the immutable upgrade history even where those brands are retired.

## Architecture and operations

- [Start here](docs/START_HERE.md)
- [Sale readiness](docs/SALE_READINESS.md)
- [Products and feature status](docs/PRODUCT_AND_FEATURES.md)
- [Verified production state](docs/PRODUCTION_CURRENT_STATE.md)
- [Current architecture](docs/CURRENT_ARCHITECTURE.md)
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

AI coding agents must also follow [`AGENTS.md`](AGENTS.md) and the permanent Cursor rules under `.cursor/rules/`.

## Licence

Proprietary. All rights reserved.
