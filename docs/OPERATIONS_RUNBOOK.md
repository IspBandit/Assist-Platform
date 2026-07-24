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
9. Run every affected-brand smoke test, health/readiness, installer, queue, cron,
   sitemap, robots and authentication checks.
10. Monitor; retain the preceding release for rollback.

The `Production release` GitHub workflow implements this sequence for a reviewed
commit on `main`. Protect the `production` environment with a required owner
reviewer. Configure `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY` and pinned
`VPS_KNOWN_HOSTS` as production-environment secrets. The deploy user requires
write access only to `/opt/assist-platform/incoming` and narrowly scoped sudo
permission for the root-owned `/usr/local/sbin/assist-platform-release`
command. The deploy user must not be able to replace or modify that command;
release archives and checksum files are the only writable incoming artefacts.

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

## Incident evidence

Record timestamps, release, affected brand/routes, request IDs, symptoms,
containment, commands/actions, data impact, recovery and follow-up. Redact secrets
and personal information.

