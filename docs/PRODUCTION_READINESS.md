# Production Readiness

## Current verdict

**Not ready for a new multi-brand production launch.**

VanAssist contains substantial working functionality, but production readiness
cannot be declared until the critical/high findings below are remediated and the
live VanAssist environment is tested. Existing production deployment should be
treated as a compatibility constraint, not evidence that all controls are safe.

## Release-blocking items

- [x] Installer cannot be remotely reused after partial or failed installation.
- [x] Customer and tokenized outcome/review provider IDs are authorization-bound
      to request matches.
- [x] SMTP peer and hostname verification are enforced; header values are safe.
- [x] Migration execution is locked, checksummed, and reports dirty/failed state.
- [x] Stale `database/schema.sql` removed; ordered migrations are authoritative.
- [x] Shared rate limiting protects login, reset, registration, and primary
      high-abuse public submission flows.
- [x] Image dimensions are safely bounded before expensive decode.
- [ ] Deployment uses encrypted transport and a manifest that detects changed
      and removed files.
- [ ] Backups are encrypted/offsite and restoration is tested.
- [x] Unknown middleware and critical launch/maintenance checks fail closed.
- [ ] Integration tests cover authentication, authorization, installer, uploads,
      migration, and brand isolation.
- [ ] Brand resolution and repository scoping cannot leak private cross-brand data.

## Application readiness

### Runtime

- PHP 8.3 and required extensions: verified locally.
- Composer platform requirements: verified.
- Apache rewrite module: verified locally.
- MariaDB: installed and active locally.
- Production `.env`, database, storage permissions, cron, and SMTP: not verified.

### Tests

- Unit tests: 55 passed, 139 assertions.
- Current unit-test warnings: none.
- Database integration tests: 6 passed, 21 assertions.
- End-to-end tests: absent.
- Accessibility automation: absent.
- Route/broken-link automation: absent.

### Dependencies

- Composer configuration is valid.
- No known Composer advisories were reported at the audit date.
- A lock file is required for reproducible releases and must be reviewed and
  committed deliberately.

## Data readiness

Before the first platform migration:

- capture database and media backups;
- restore into a staging environment;
- run duplicate/orphan/invalid-value preflight queries;
- validate migration on the restored production-shaped data;
- compare row counts, IDs, slugs, URL responses, and financial totals;
- record migration duration and lock impact;
- test application rollback while additive columns/tables remain present.

No destructive migration may run as part of an automatic deploy.

## Security readiness

### Security gate status

- [x] Installer lock and setup authorization.
- [x] Outcome/review IDOR remediation.
- [x] SMTP TLS and header hardening.
- [x] Shared persistent rate limiting for primary high-abuse flows.
- [x] Upload dimension and execution controls.
- [x] Safe redirect handling.
- [x] Password-reset session/token revocation.
- [x] Atomic email queue claim, lease, retry, and stale-worker recovery.
- [x] Authenticated response `no-store` policy and trusted-proxy allowlist.
- [x] Database SMTP secret encryption and plaintext-upgrade validation.
- [ ] Documented and rehearsed APP_KEY rotation procedure.
- [ ] Administrative MFA plan; implementation is strongly recommended before broad
  platform administration.

### Accepted current strengths

- Prepared PDO statements.
- Password hashing and session rotation.
- CSRF middleware.
- Private file storage and common ownership checks.
- Stripe webhook signature verification.

## Privacy readiness

- [ ] Complete a field-level personal-data inventory and lawful-purpose review.
- [ ] Implement authenticated user export and verified deletion/anonymization
      workflows with provider, finance, fraud, and legal-retention exceptions.
- [ ] Define retention schedules for requests, messages, documents, analytics,
      email logs, audit logs, backups, and abandoned accounts.
- [ ] Record subprocessors/data regions and complete privacy/terms legal review.
- [x] Consent records, private document storage, and analytics retention hooks
      exist, but do not by themselves constitute compliance.

## Reliability and operations

- [x] Health endpoint reports liveness and optional configured release safely.
- [x] Readiness endpoint fails for exposed installer, unavailable database, or
      unwritable required storage.
- [x] Structured logs include request ID and brand key when request context exists.
- [ ] Error monitoring and uptime hooks are configured.
- [ ] Cron task failures alert an operator.
- [x] Email queue has atomic claim/retry/recovery behavior.
- [ ] Backup restore is tested on a schedule.
- [ ] Deployment records artefact version and migration version.
- [ ] Previous artefact and rollback procedure are available.
- [ ] Removed remote files are handled explicitly.

## Performance readiness

Required baselines:

- provider search query plans at current and projected provider volume;
- route p95 latency and PHP memory;
- national town selector payload/DOM size;
- image processing memory and timeout behavior;
- analytics aggregation and retention duration;
- email and cron throughput;
- database index usage for provider, location, review, request, and analytics
  filters.

Do not add distributed infrastructure until these measurements show a need.

Suggested scaling triggers:

- introduce an external search index when indexed MySQL queries cannot meet the
  agreed latency under realistic search volume;
- introduce a durable queue when database email/notification claiming cannot
  meet throughput or retry guarantees;
- introduce object storage/CDN when media volume, backup requirements, or
  multi-host deployments exceed reliable local storage;
- introduce shared cache/session infrastructure only when multiple active web
  nodes are required.

## Accessibility readiness

Before declaring WCAG 2.2 AA readiness:

- associate field errors with controls and move focus to failed submissions;
- correct known contrast failures;
- complete autocomplete combobox semantics and keyboard behavior;
- add captions/scope or equivalent semantics to data tables;
- improve mobile-menu focus and dismissal;
- add `aria-current` to active navigation;
- ensure timed flash messages can be paused or persist appropriately;
- audit public, account, provider, park, and admin journeys with keyboard and
  screen reader testing.

## SEO readiness

- remove the static/dynamic robots conflict;
- explicitly noindex tokenized, transactional, portal, and admin pages;
- use brand-aware canonical hosts derived from trusted configuration;
- preserve current VanAssist paths and add tested aliases before changes;
- fix paginated provider canonical behavior;
- generate brand-independent sitemaps, robots, metadata, and structured data;
- review hero assets and contact/trademark content.

## Deployment approval checklist

1. CI passes on the exact release commit.
2. Dependency and secret scans pass.
3. Staging uses a restored production-shaped database.
4. Migration preflight and integrity reports are attached.
5. Critical user journeys pass.
6. Security and accessibility release blockers pass.
7. Backup and rollback are verified.
8. Release artefact hash/version is recorded.
9. Production migration receives manual approval.
10. Post-deploy smoke, health, logs, queue, cron, sitemap, and robots checks pass.

## Known unsupported claims

Until completed and verified, do not claim:

- seamless shared browser sessions across unrelated brand domains;
- production Stripe charging;
- complete integration/E2E test coverage;
- immutable/tamper-evident audit logs;
- automatic offsite recoverable backups;
- WCAG 2.2 AA compliance;
- fully safe no-downtime schema migration;
- complete TowWise or TrailerWise functionality.
