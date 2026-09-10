# Buyer transfer rehearsal

Workstream: OPS-005 / COM-005. Status: OPEN; no transfer or deployment recorded.
Use with `ASSET_REGISTER.md`, `EVIDENCE_REGISTER.md` and the operations runbook.

1. Freeze the included asset schedule to VanAssist, TowSmart, TrailerWise and
   required shared assets. Confirm source/IP assignments, dataset rights and
   the separate inclusion/licensing decision for Assist RIC. Exclude Polaris
   and LocalTorque product assets while retaining necessary migration history.
2. Record each account's current legal owner, buyer custodian, transfer method,
   MFA control, recovery contact and renewal date in the restricted data room.
   Obtain provider-specific transfer instructions before making account changes.
3. Identify the reviewed candidate SHA and passing CI run. A buyer operator
   obtains a clean checkout and builds production dependencies using the
   documented workflow, recording lock hash and release checksum.
4. Provision isolated buyer-controlled staging. Disable outbound customer mail,
   payments and scheduled external actions. Restrict access and indexing.
   Transfer authorised encrypted backup/media and configuration through the
   protected channel. Never place dumps or secrets in Git or a public artefact.
5. Restore database, public/private media and matching configuration. Verify
   migration history, representative row counts, private file access, all three
   brands and the acceptance journeys. Record restore point, elapsed recovery
   time and differences. Follow the documented APP_KEY rotation procedure;
   replacing it blindly can make encrypted records unreadable.
6. Configure buyer-owned mail, backup, monitoring and API credentials. Exercise
   test mail, off-site backup/restore and alert receipt. Confirm cost caps and
   sender/DNS settings. Record each acceptance result.
7. Rehearse immutable deployment and rollback with the buyer operator using
   `../OPERATIONS_RUNBOOK.md`. Confirm application and proxy use the same
   release and all three hosts pass health/readiness after each switch.
8. Demonstrate creating/revoking an administrator, provider/claim management,
   content editing, queue handling and backup recovery from maintained guides.
   Record any founder-only step as an unresolved transfer defect.
9. Obtain the four Platform Quality Gate approvals and explicit production
   release authorisation before DNS, live account or production changes.
   Schedule cutover, final backup, acceptance window and rollback decision owner.
10. After buyer acceptance, rotate/revoke seller credentials and sessions using
    a recorded access inventory. Confirm buyer MFA/recovery and backup-key
    custody before removing seller access. Retain required audit history and
    sanitise rehearsal data under the agreed retention policy.

Completion evidence names the buyer operator, candidate/deployed SHA, signed
asset schedule, account receipts, build/restore/rollback results and unresolved
exceptions. A document or successful source-code transfer alone is not a passed
operational handover.
