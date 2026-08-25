# VanAssist formal launch gate — 24 August 2026

This record separates verified live evidence from release-candidate evidence.
Missing external proof is a failure. A passing code test is not represented as
a passing production control.

## Current result

**FAIL — commercial launch is not approved.**

The application and public search are operational. Email delivery is healthy.
The hard blockers are a successful current scheduled local backup after the
candidate release, an encrypted independent off-site backup, a current isolated
restore rehearsal, and owner acceptance of the final public journeys.

## Verified live evidence before the candidate

| Group | Result | Evidence |
|---|---|---|
| Release health | Pass | All brand `/readyz` responses reported immutable release `046a2c6b492935e4d56e6c3fd0f0372b28e83323`. |
| Email transport | Pass | Microsoft Graph health was healthy; all three application-path brand mailbox probes were sent; failed queue count was zero. |
| Search reliability | Pass | The production zero-result release checks and representative town/provider searches passed on release `046a2c6`. |
| Data trust | Pass with review workload | 13,353 active unclaimed providers, zero operator-verified providers and 742 quarantined/review source rows were reported. A separate strict public-coordinate audit found zero exposed conflicts. |
| Local scheduled backup | Fail | `scheduled_tasks.database_backup` was stale in `running`; its message referenced an old July file. The app image lacked the database dump client and used the unsuitable PHP fallback. |
| Independent off-site backup | Fail | `storage/ops/offsite-backup.status.json` was absent. Owner-controlled repository credentials were not available. |
| Restore rehearsal | Fail | `storage/ops/offsite-restore-drill.status.json` was absent or not current enough for the formal gate. |
| Public bot challenge | Warning | Rate limits and honeypots are active; server-verified Turnstile is not enabled. |

## Release-candidate evidence

- Fresh migrations `001` through `135` applied to an empty disposable MySQL
  database, then a second migration run reported no pending work.
- Core and LocalTorque production-shaped seeds loaded successfully. The strict
  data-quality audit reported zero publicly visible location-coordinate
  conflicts and zero review-only source rows exposed.
- Platform backfill completed and validated 9,743 of 9,743 brand listings.
- Database integration suite passed: 38 tests, 283 assertions, one explicitly
  skipped optional authentication test.
- The launch gate now queries published provider coordinates rather than
  optional JSON payload coordinates. Live verification found and the follow-up
  fixes a missing closing parenthesis that initially left this check at
  unavailable evidence even though the underlying production query returned
  zero conflicts.
- The production PHP image includes `mariadb-client`; application backups reject
  empty output, write a SHA-256 manifest, retain uncompressed SQL after
  compression failure, and report a stale running task explicitly. The formal
  gate also rejects a missing, unverified or older-than-36-hours local archive.
- Analytics reports and provider-facing performance reports use the same
  attributable-session filter. Raw suspect telemetry is retained but excluded
  from visitor, acquisition, search, contact and conversion rates.
- Provider claim approval queues a secure link but does not grant control or
  verification. Verification requires an active claimed provider, a recorded
  evidence basis and notes, and synchronises canonical and public listing state.

## Production acceptance required

1. Merge and deploy the exact reviewed candidate through the protected release workflow.
2. Confirm migrations `134` and `135` succeeded and all `/readyz` responses show the new immutable SHA.
3. Run the bounded `database_backup` task; verify a non-empty compressed dump and `success` task state.
4. Configure the independent encrypted repository, run off-site backup, and verify current machine-readable evidence.
5. Restore the newest backup into an isolated database, run integrity and critical-journey checks, destroy the rehearsal environment, and retain current evidence.
6. Re-run the live launch gate. Commercial launch remains blocked unless every hard check passes and the owner records acceptance.

## Live release verification update

- Release `ebad280f1d4924d2beef0c7316c2333e6ca0961d` reached all three brands and
  migrations `134` and `135` succeeded.
- The scheduled application backup completed through `mariadb-dump` in 11.9
  seconds. `db_20260824_130147.sql.gz` and its SHA-256 manifest verified, closing
  the local-backup control.
- The immutable release had reused the bootstrap-era app image. The live image
  was rebuilt from the exact merged Dockerfile with a tagged rollback image;
  the follow-up release makes reviewed runtime synchronisation automatic.
- Independent encrypted off-site backup and current restore rehearsal evidence
  remain absent, so the formal commercial launch gate remains **FAIL**.
