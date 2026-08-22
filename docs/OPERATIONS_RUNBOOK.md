# Operations runbook

## Normal checks

- Confirm all containers are healthy and `/healthz` plus `/readyz` return 200 for
  each domain.
- Confirm the expected release SHA, clean migration state, writable shared
  storage, recent successful cron runs and no stuck email leases.
- Review Caddy/application/database logs without copying secrets into tickets.
- Confirm `/install` remains unavailable.

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

The workflow cannot run from a pull request or feature branch. A human must type
`DEPLOY`, approve the protected environment and allow the complete reusable CI
workflow to pass before upload. The remote release script verifies the archive,
takes a backup, uses an immutable commit directory, applies forward migrations,
checks all live brands and restores the previous symlink on application failure.

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

