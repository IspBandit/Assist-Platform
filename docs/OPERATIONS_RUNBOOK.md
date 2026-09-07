# Operations runbook

## Normal checks

- Confirm all containers are healthy and `/healthz` plus `/readyz` return 200 for
  each domain.
- Confirm the expected release SHA, clean migration state, writable shared
  storage, recent successful cron runs and no stuck email leases.
- Review Caddy/application/database logs without copying secrets into tickets.
- Confirm `/install` remains unavailable.
- Require both `/healthz` and `/readyz` on all three domains to return the exact
  selected 40-character release SHA, not only HTTP 200. The protected production
  workflow performs this identity assertion after every release.

## Release

1. Merge a reviewed pull request only after CI passes.
2. Build from the exact commit with production Composer dependencies.
3. Record commit, lock hash and artefact SHA-256.
4. Create and verify database/media backups.
5. Upload over SSH/SFTP to a new immutable release directory and verify checksum.
6. Verify environment and shared storage; rehearse migrations on staging first.
7. Apply forward migrations once through the locked CLI runner.
8. Atomically switch `/opt/assist-platform/current`.
   Recreate both the PHP application and Caddy containers after the switch;
   bind mounts resolved through the `current` symlink must never remain pinned
   to different release directories.
9. Run every affected-brand smoke test, health/readiness, installer, queue, cron,
   sitemap, robots and authentication checks.
10. Monitor; retain the preceding release for rollback.

### Isolated staging release

The manually dispatched `Staging release` workflow builds and validates a
selected ref, then calls the root-owned
`/usr/local/sbin/assist-platform-staging-release` command. Staging uses separate
storage and MariaDB data under `/opt/assist-platform-staging`; it does not share
the production database. The temporary endpoint is password-protected and
blocked from search indexing. Email, billing, paid AI, Admin API and public Ask
remain disabled until a controlled staging rehearsal needs them.

The `Production release` GitHub workflow implements this sequence for a reviewed
commit on `main`. Protect the `production` environment with a required owner
reviewer. Configure `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY` and pinned
`VPS_KNOWN_HOSTS` as production-environment secrets. The deploy user requires
write access only to `/opt/assist-platform/incoming` and narrowly scoped sudo
permission for the root-owned `/usr/local/sbin/assist-platform-release`
command. The deploy user must not be able to replace or modify that command;
release archives and checksum files are the only writable incoming artefacts.

Provider-import processing resumes through the root-owned
`process_provider_import_queue` cron entry every five minutes. The production
release workflow must not run Docker Compose directly as the restricted deploy
user. Review current queue counts in **Admin → Provider data**; use the
root-owned cron runner for an authorised server-side diagnostic.

The versioned production schedule also runs session/request expiry, demand and
run-capacity refresh, matching and follow-ups, database backup, application and
AI retention, daily aggregation, regulatory alerts, provider/document reminders
and the VanAssist performance report. Compare `/etc/cron.d/assist-platform` with
`infrastructure/binarylane/ops/assist-platform.cron` whenever the root-owned
runtime is provisioned or refreshed. A registered task in `never` state is not a
failure by itself if it is explicitly manual/disabled, but every recurring task
listed in the reviewed schedule must produce current success/failure evidence.

VanAssist's daily website performance report runs at 06:15 Australia/Brisbane
through `vanassist_daily_performance_email`. It reports the preceding calendar
day to `support@vanassist.com.au`, then the existing two-minute email worker
delivers it through Microsoft Graph. The task checks its date-specific queue key
before inserting, so a retry reports `already_queued` and does not duplicate the
email. Confirm both the task's `success` state and the matching email-queue row;
an HTTP health check alone does not prove delivery. To suspend the report,
remove or comment only that cron entry. To recover a missed day, run the task
once before the next calendar day; never hand-insert a queue copy.

The workflow cannot run from a pull request or feature branch. A human must type
`DEPLOY`, approve the protected environment and allow the complete reusable CI
workflow to pass before upload. The remote release script verifies the archive,
takes a backup, uses an immutable commit directory, applies forward migrations,
checks all live brands and restores the previous symlink on application failure.
Before uploading, the workflow also compares the installed root-owned release
command with the reviewed `scripts/release-remote.sh` in the exact release. A
hash mismatch stops before production changes; install the reviewed command as
root, verify its hash and retry rather than bypassing this drift check.

The root-owned release command also refreshes bootstrap-managed Compose,
Dockerfile, PHP, Caddy and operations scripts from the reviewed immutable
release before rebuilding containers. It keeps the preceding runtime files for
the duration of deployment and restores them with the prior application
symlink if any migration, data audit or health check fails. This prevents a
merged infrastructure change from remaining stranded in GitHub while the
application code appears current.

### CQDiggings release ownership

Assist's release workflow no longer asserts CQDiggings research counts or worker
versions. Those checks belong to the CQDiggings release at its nominated SHA.
Shared proxy configuration remains owned here and must still be checked when changed.
Called CI has a workflow-specific concurrency key so standalone CI cannot cancel
the validation job within a production release.

Service-worker activation must preserve open forms and navigation; release changes
must not force-reload active browser tabs. Retest first-visit and existing-browser
journeys after deployment. See acquisition/SALE_REVIEW_2026-09-06.md for current
recovery evidence and unresolved off-site/full-application restore gates.

CQDiggings investigations, maps, service-worker assets and research data are
released only from the CQDiggings repository into `/opt/cqdiggings/current`.
Assist Platform owns the shared reverse proxy and runtime community-data mounts,
but must not overlay CQDiggings product files. After either product is released,
use cache-busting requests to confirm investigation pages and assets resolve from
the nominated CQDiggings release. See superseded ADR 0038 for the retired bridge.

## Rollback

For additive compatible migrations, switch the current symlink to the previous
release and smoke-test. Never reverse a destructive/data-transforming migration
ad hoc; use its reviewed restore/migration plan. Enter maintenance mode for
brand leakage, authentication/authorisation regression, dirty migrations,
private-file exposure or material data-integrity failures.

## Credential and configuration rules

Production configuration is root-owned under `/opt/assist-platform/config`.
Never print secrets in logs or command history. Use distinct brand sender
addresses even when the SMTP transport account is shared. DNS and Cloudflare
changes are separate owner-approved operations.

## Admin API (`/api/v1/admin`)

Disabled by default. Do **not** set `ADMIN_API_ENABLED=true` in production
without staging rehearsal and an updated Platform Quality Gate pass (see
`docs/LIVE_API.md` and `docs/evidence/admin-api-2026-08-02/`).

Non-production enablement:

1. Apply migrations through `087_admin_api_drafts_imports.sql`.
2. Set `ADMIN_API_ENABLED=true`, keep `ADMIN_API_RESTRICTED=true`.
3. Bootstrap RIC service account:
   `php scripts/admin-api-create-ric-service-account.php --email=…`
4. Probe: `php scripts/admin-api-probe.php --base-url=… --client-key=… --client-secret=…`
5. Assist RIC: Test connection → Validate-only import before write submit.
6. Enroll human TOTP before `ADMIN_API_MFA_REQUIRED=true`.

Rollback: set `ADMIN_API_ENABLED=false` (and MFA flag false), revoke
`api_oauth_clients`, rotate secrets. RIC falls back to checksummed export
packages. Never open production MariaDB from RIC or importers (ADR 0018).

## Incident evidence

Record timestamps, release, affected brand/routes, request IDs, symptoms,
containment, commands/actions, data impact, recovery and follow-up. Redact secrets
and personal information.
