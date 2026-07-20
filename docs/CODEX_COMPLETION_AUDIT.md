# Codex Completion Audit

Audit date: 20 July 2026

Repository: `IspBandit/Assist-Platform`

Audited commit: `b63343b` (`main`)

## Executive summary

The repository is a substantial VanAssist application with an additive
multi-brand platform foundation. It is not yet a completed three-product
platform. VanAssist contains the operational marketplace; TowSmart now has a
feature-gated public towing-mass comparison MVP and TrailerWise has a
feature-gated read-only marketplace MVP. Both remain disabled by default.

The current core includes domain-based brand resolution, typed brand records,
provider-brand listings, brand roles, additive migrations, security middleware,
health/readiness endpoints, CI, unit tests, and database integration tests.

This audit fixed two reproducibility/SEO issues and the PHP compatibility issues
revealed after static analysis could run successfully:

- PHPStan now has a deterministic 512 MB memory allowance instead of crashing
  under a common 128 MB local PHP limit.
- PHP 8.4+ implicit-nullability in `Model::all()` was corrected.
- CLI scripts now obtain typed arguments from `$_SERVER['argv']`, allowing
  static analysis to validate them without suppressions.
- The stale static `public/robots.txt` was removed. The registered dynamic
  `/robots.txt` endpoint is now the only source of robots policy and can honour
  brand launch state.
- A pure, typed TowSmart mass-comparison foundation was added with traceable
  assumptions, explicit non-certification limitations, and boundary tests.
- Additive TowSmart asset/combination and TrailerWise business/listing migrations
  were added without changing existing VanAssist IDs, slugs, or tables.
- A reproducible ZIP release builder now records the commit and SHA-256 hash of
  every tracked file; CI validates that a clean commit can be packaged.
- A fail-closed route policy prevents TowSmart and TrailerWise hosts from
  reaching VanAssist or each other's routes after activation.
- TowSmart now exposes a server-validated calculator UI with explicit safety,
  engineering, legal and source-data limitations.
- TrailerWise now exposes brand-scoped active listings, type filtering and
  individual listing pages with an honest empty state.
- Alternate-brand sitemaps only advertise routes implemented for that brand.

## Product completion status

### VanAssist

Implemented foundation includes public provider discovery, service requests,
provider/customer/admin portals, reviews/outcomes, parks, service runs, CMS,
analytics, billing foundations, imports, email queues, and operational controls.
Live environment acceptance still requires production-shaped integration and
browser testing.

### TowSmart

Brand configuration, safe coming-soon routing, calculation-domain foundation,
asset/combination schema, unit tests and a polished public assessment MVP exist.
The MVP includes a load planner, up to eleven mass/component checks, local saved
scenarios, printable results, education and contextual advertising foundations.
Sourced vehicle/trailer data and account-based history remain incomplete.

### TrailerWise

Brand configuration, safe coming-soon routing, trailer taxonomy, business-role
profiles, individual listing schema and a read-only public marketplace exist.
Provider/admin authoring, enquiries, media moderation and verification remain incomplete.
TrailerWise must not be described as complete.

## Validation results

| Check | Result |
|---|---|
| `composer install --no-interaction --prefer-dist` | Passed |
| `composer validate --strict` | Passed |
| `composer check-platform-reqs` | Passed on PHP 8.5.8 |
| `composer analyse` | Passed after fixes; no errors |
| `vendor/bin/phpunit --testsuite Unit` | Passed: 65 tests, 168 assertions |
| `composer audit` | Passed; no known advisories |
| `git diff --check` | Passed |
| GitHub Actions run for PR #1 | Passed on PHP 8.3 / MySQL 8 |

No compatible local MySQL/MariaDB service was available during this audit.
GitHub Actions therefore provided the authoritative database validation for the
change branch. It applied all 35 migrations twice, seeded the disposable
database, validated encrypted secrets, completed and validated the VanAssist
platform backfill, and passed all eight database integration tests on MySQL 8.

Draft pull request: `https://github.com/IspBandit/Assist-Platform/pull/1`

## Remaining production blockers

1. Build account-based named TowSmart combinations, weighbridge history and reminders.
2. Build advertiser campaign administration and provider lead workflows.
3. Build TrailerWise provider/admin listing authoring, enquiries and moderation.
4. Complete operational-table brand scoping and expand isolation integration
   tests before enabling additional brands.
5. Add browser end-to-end, accessibility, and broken-link tests.
6. Replace the legacy FTP deployment with an encrypted, immutable release
   artefact/manifest process that handles removed files and supports rollback.
7. Configure encrypted offsite backups and rehearse restoration.
8. Add administrative MFA or a documented step-up-authentication plan.
9. Complete privacy inventory, retention, export, and deletion/anonymisation
   workflows with legal review.
10. Configure error monitoring, uptime checks, and cron-failure alerting.
11. Validate production environment values, SMTP, storage permissions, cron,
    DNS, canonical hosts, and post-deployment smoke tests.

## Recommended next implementation order

1. Production-shaped MySQL integration and browser test harness.
2. Brand-scope all operational reads/writes and prove isolation.
3. TrailerWise relational domain plus manufacturer/dealer/provider workflows.
4. TowSmart sourced vehicle/caravan data, saved history and provider lead flows.
5. Secure artefact-based deployment, rollback, backups, and monitoring.
6. Accessibility and SEO release gates for each enabled brand.

## Exact local validation commands

```text
composer install --no-interaction --prefer-dist
composer validate --strict
composer check-platform-reqs
composer analyse
vendor/bin/phpunit
composer audit
git diff --check
```

For a configured disposable MySQL test database:

```text
php scripts/migrate.php
php scripts/migrate.php
php scripts/seed.php
php scripts/encrypt-secrets.php --validate-only
php scripts/backfill-platform.php --batch=500
php scripts/backfill-platform.php --validate-only
RUN_INTEGRATION_TESTS=1 vendor/bin/phpunit --testsuite Integration
```

On Windows PowerShell, set `RUN_INTEGRATION_TESTS=1` with:

```powershell
$env:RUN_INTEGRATION_TESTS = '1'
.\vendor\bin\phpunit --testsuite Integration
```
