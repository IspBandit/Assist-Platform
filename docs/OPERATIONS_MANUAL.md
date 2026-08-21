# Operations manual

This is the operational index for Assist Platform Enterprise. Detailed command
sequences remain in the linked runbooks so they are not duplicated or allowed to
drift.

## Daily operation

- Use `OPERATIONS_RUNBOOK.md` for health, readiness, queues, logs and releases.
- Use the unified admin All Brands view for portfolio health and switch to a
  brand context for scoped providers, content, campaigns and analytics.
- Treat mail, billing, imports, scheduled jobs and private-brand launch controls
  as fail-closed integrations.

## Releases and recovery

- Production release: `OPERATIONS_RUNBOOK.md` and `.github/workflows/production-release.yml`.
- Backup/restore: `BACKUP_AND_RESTORE.md`.
- Current verified live state: `PRODUCTION_CURRENT_STATE.md`.
- Deployment configuration: `DEPLOYMENT.md`.
- Every release records commit, artefact checksum, migrations, health checks,
  quality-gate result and rollback target in `RELEASE_NOTES.md` or a linked dated
  release record.

## Incidents

Contain first, preserve evidence, protect private data and record brand/release,
timestamps, request IDs, impact, actions and recovery. Brand leakage,
authentication/authorisation regression, private-file exposure, dirty migrations
or material data-integrity failures require maintenance mode and owner escalation.

## External-owner actions

DNS, sender-domain verification, payment activation, credentials and irreversible
production actions require explicit owner approval. Their absence is a documented
blocker, never an excuse to fabricate completion.
