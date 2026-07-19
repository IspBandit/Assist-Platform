# Deployment

## Deployment model

Assist Platform uses one versioned codebase and may deploy the same immutable
release artefact independently for VanAssist, TowWise, TrailerWise, and admin
hosts. VanAssist is the only fully enabled product during the initial migration.

The deployment target remains PHP/Apache/MySQL-compatible hosting. cPanel is
supported, but deployment safety must not depend on FTP timestamp/size heuristics.

## Required production topology

- Document root points to `public/`.
- `.env`, application source, logs, sessions, private media, exports, and
  backups are outside the public document root.
- HTTPS is terminated by the host or trusted proxy.
- Database and storage credentials are environment-only.
- Cron uses PHP CLI from the same release.
- Each deployment declares its expected brand and canonical hostname.

## Release artefact

Build releases from a reviewed commit:

```bash
composer install --no-dev --prefer-dist --classmap-authoritative
composer check-platform-reqs --no-dev
```

The artefact includes application code, public assets, migrations, and production
Composer dependencies. It excludes:

- `.env` and deployment credentials;
- test/development caches;
- runtime sessions/logs/backups/imports;
- user-uploaded public and private media;
- local database dumps.

Record the commit SHA, Composer lock hash, migration version, build time, and
artefact checksum.

## Pre-deployment checks

1. CI passes syntax, validation, unit/integration, security, and secret checks.
2. The release is tested against a restored production-shaped staging database.
3. Migration preflight and post-migration queries are prepared.
4. Database and media backups complete and a recent restore is verified.
5. The previous artefact and rollback instructions are available.
6. Brand, canonical host, SMTP, storage, analytics, and feature flags are
   reviewed.
7. Production migration has manual approval.

## Safe deployment sequence

1. Enable maintenance mode when the migration is not backward-compatible.
2. Upload/extract to a new versioned release directory.
3. Install production dependencies in the release or use the built artefact.
4. Verify environment and writable shared-storage paths.
5. Run non-destructive migration preflight.
6. Apply migrations once through the locked CLI runner.
7. Run `php scripts/encrypt-secrets.php` once when upgrading a database that
   contains a legacy plaintext SMTP password, then run it with `--validate-only`.
8. Run integrity checks.
9. Atomically switch the active release/symlink or document-root target.
10. Clear only safe generated caches.
11. Run health/readiness and user-journey smoke tests.
12. Resume cron and disable maintenance mode.
13. Monitor errors, queue, cron, database, and route health.

Do not run production migrations concurrently from installer, admin, and CLI.

## Multi-brand configuration

Each deployment sets:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://brand.example.com
ASSIST_BRAND=vanassist
ASSIST_DEFAULT_BRAND=vanassist
ASSIST_STRICT_BRAND_HOSTS=true
ASSIST_ALLOW_BRAND_QUERY=false
SESSION_SECURE=true
```

Use the matching brand key for future TowWise and TrailerWise deployments.
Hostname validation must agree with the brand registry. A release must not serve
an unknown production host.

`APP_KEY` is also the key-encryption key for database-held SMTP credentials.
Back it up in the deployment secret manager. Do not rotate it until stored
secrets have been decrypted and re-encrypted through an approved rotation runbook.

## Database migration policy

- `database/migrations` is authoritative.
- No consolidated schema file is shipped; install and upgrade only through the
  ordered migration runner.
- Never edit an already applied migration.
- Additive/expand migrations deploy before code that requires them.
- Backfills run in bounded batches and are restartable.
- Constraints and contract/cleanup migrations occur only after validation and
  an observation period.
- Destructive migration requires an explicit label, backup, rollback/restore
  plan, and manual approval.

See `docs/MIGRATION_PLAN.md`.

## Cron

Cron commands run from the active release with an explicit brand context for
brand-specific work. Platform-wide work iterates registered brands deliberately.

Task locks must include task and brand keys. Monitor non-zero exits and stale
`processing` records. Do not schedule placeholder tasks as if they provide
operational coverage.

## Health and smoke checks

Use `GET /healthz` for process liveness and `GET /readyz` for installed-state,
database, and clean-migration readiness. Both return minimal JSON and bypass
maintenance/coming-soon presentation so infrastructure probes remain reliable.

Required post-deploy checks:

- application version and expected brand;
- database connectivity and migration version;
- required storage writable;
- installer unavailable;
- homepage and representative static/CMS pages;
- provider search and provider profile;
- login/logout/password reset queueing;
- customer/provider/admin authorization boundaries;
- request submission without real production email in staging;
- sitemap, robots, canonical, and error pages;
- queue and cron status;
- TowWise/TrailerWise coming-soon behavior when deployed.

Health endpoints must not expose credentials, stack traces, private paths, or
detailed dependency errors to unauthenticated users.

## Rollback

For additive migrations:

1. re-enable maintenance if needed;
2. switch to the prior application artefact;
3. leave additive tables/columns in place;
4. verify the older application ignores them;
5. run smoke tests and monitor.

For destructive or data-transforming migrations, application rollback alone may
be insufficient. Use the migration-specific rollback or restore procedure.
Never improvise a production down-migration.

## Backups

Production requires:

- encrypted database backups;
- private and public media backups;
- offsite copies;
- retention aligned with privacy and operational requirements;
- restricted access and audited download;
- scheduled restore verification;
- documented recovery point and recovery time objectives.

Do not expose database passwords in process arguments where avoidable.

## Current FTP script

`scripts/deploy.ps1` is legacy tooling and is not sufficient for production
platform releases because it may use plain FTP, compares by file size, and does
not delete obsolete remote files.

Until replaced, it must not be described as an atomic or complete deployment.
Prefer SFTP/SSH or a hosting deployment mechanism that verifies a manifest and
supports release directories. If cPanel limits available tooling, upload one
complete archive, verify its checksum, extract to a new release directory, and
switch atomically where supported.

## Incident rollback triggers

Rollback or enter maintenance mode when:

- brand resolution serves the wrong brand;
- authentication or authorization regresses;
- existing VanAssist URLs fail unexpectedly;
- database integrity checks differ from preflight;
- error rate or latency exceeds the release threshold;
- email/notification duplication occurs;
- private files or cross-brand records become accessible;
- migration state is dirty or unknown.
