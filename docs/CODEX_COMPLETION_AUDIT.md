# Codex Completion Audit

Audit date: 20 July 2026

Repository: `IspBandit/Assist-Platform`

Audited commit: `b63343b` (`main`)

## Executive summary

The repository is a substantial VanAssist application with an additive
multi-brand platform foundation. It is not a completed three-product platform.
VanAssist contains the operational marketplace; TowWise and TrailerWise remain
deployable, disabled scaffolds. The existing documentation describes that state
accurately.

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
- A pure, typed TowWise mass-comparison foundation was added with traceable
  assumptions, explicit non-certification limitations, and boundary tests.
- Additive TowWise asset/combination and TrailerWise business/listing migrations
  were added without changing existing VanAssist IDs, slugs, or tables.
- A reproducible ZIP release builder now records the commit and SHA-256 hash of
  every tracked file; CI validates that a clean commit can be packaged.

## Product completion status

### VanAssist

Implemented foundation includes public provider discovery, service requests,
provider/customer/admin portals, reviews/outcomes, parks, service runs, CMS,
analytics, billing foundations, imports, email queues, and operational controls.
Live environment acceptance still requires production-shaped integration and
browser testing.

### TowWise

Brand configuration, safe coming-soon routing, calculation-domain foundation,
asset/combination schema, and unit tests exist. Public workflows, sourced
vehicle/trailer data, saved-combination application services, reports, and
independent safety review remain incomplete. TowWise must not be described as
complete.

### TrailerWise

Brand configuration, safe coming-soon routing, trailer taxonomy, business-role
profiles, and individual listing schema exist. Public/provider/admin workflows,
search, enquiries, media moderation, and verification remain incomplete.
TrailerWise must not be described as complete.

## Validation results

| Check | Result |
|---|---|
| `composer install --no-interaction --prefer-dist` | Passed |
| `composer validate --strict` | Passed |
| `composer check-platform-reqs` | Passed on PHP 8.5.8 |
| `composer analyse` | Passed after fixes; no errors |
| `vendor/bin/phpunit --testsuite Unit` | Passed: 60 tests, 151 assertions |
| `composer audit` | Passed; no known advisories |
| `git diff --check` | Passed |
| Latest GitHub Actions run on audited commit | Passed |

The six skipped tests require a configured MySQL test database. No compatible
local MySQL/MariaDB service was available during this audit, so migrations,
seeds, backfill, and integration tests were not re-run locally. The latest
GitHub Actions workflow completed those MySQL-backed checks successfully on the
audited commit.

## Remaining production blockers

1. Build full TowWise product functionality and have formulas/data reviewed by
   an appropriately qualified towing/engineering specialist.
2. Build the TrailerWise domain and public/provider/admin workflows.
3. Complete operational-table brand scoping and expand isolation integration
   tests before enabling additional brands.
4. Add browser end-to-end, accessibility, and broken-link tests.
5. Replace the legacy FTP deployment with an encrypted, immutable release
   artefact/manifest process that handles removed files and supports rollback.
6. Configure encrypted offsite backups and rehearse restoration.
7. Add administrative MFA or a documented step-up-authentication plan.
8. Complete privacy inventory, retention, export, and deletion/anonymisation
   workflows with legal review.
9. Configure error monitoring, uptime checks, and cron-failure alerting.
10. Validate production environment values, SMTP, storage permissions, cron,
    DNS, canonical hosts, and post-deployment smoke tests.

## Recommended next implementation order

1. Production-shaped MySQL integration and browser test harness.
2. Brand-scope all operational reads/writes and prove isolation.
3. TrailerWise relational domain plus manufacturer/dealer/provider workflows.
4. TowWise calculation domain, source attribution, pure formula library, and
   independent safety review.
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
