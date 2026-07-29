# Current release state

## Purpose

Explain where to find release status and what evidence is required before describing a change as deployed.

## Intended users

Administrators, developers and operators reviewing release readiness or recent product changes.

## Permissions

None on this page. Repository access does not authorise a production release; the deployment workflow and owner approvals remain separate.

## Fields

Each dated release records version and date, commit and checksum, user/provider/admin changes, brands, migrations, environment changes, quality-gate evidence, validation, deployment result, known issues and rollback target.

## Actions

Read the **Unreleased** section, follow linked historical deployment records, compare claims with `PRODUCTION_CURRENT_STATE.md`, and add a dated entry only after the Platform Quality Gate and verified deployment record exist.

## Workflows

Prepare an immutable artefact, verify checksums and backup, run controlled migrations, perform health checks, record the gate, and retain rollback availability. Until that succeeds, keep the work under **Unreleased** and state external blockers explicitly.

## Examples

A feature merged to a branch remains unreleased. After a verified production deployment, its release entry records the deployed commit, migration set, checks, result and rollback target.

## Common mistakes

- Equating merged code with production state.
- Copying secrets or private operational data into release notes.
- Omitting a failed or unavailable check.
- Claiming LocalTorque public launch before domain and operational prerequisites pass.

## Related pages

See **Project changelog**, **Repository workflow**, `docs/OPERATIONS_RUNBOOK.md` and `docs/PRODUCTION_CURRENT_STATE.md`.

## FAQ

**Where is the latest verified live state?** In `docs/PRODUCTION_CURRENT_STATE.md`, not inferred from the branch.

**Can a conditional quality-gate pass go public?** Only within the stated condition; the gate document says a conditional pass permits non-production or private/disabled deployment.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
