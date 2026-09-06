# Acquisition evidence register

Workstream: OPS-005 / COM-005. Prepared 6 September 2026.
Scope: VanAssist, TowSmart and TrailerWise only.

Status is OPEN until a dated artefact identifies the environment, release SHA,
operator, expected result and actual result. Repository implementation is not
production acceptance. Store sensitive evidence in the restricted transaction
data room; commit only redacted references and checksums.

## Three-site UX acceptance

Run each journey at desktop 1440x900 and mobile 390x844. Retain screenshots,
keyboard/focus observations, errors and the tested release SHA. Use designated
test accounts and avoid sending messages to real providers during rehearsal.

| ID | Journey and acceptance | Status |
| --- | --- | --- |
| UX-VA-01 | Location/service search, useful results, provider profile, contact/request; missing contacts and empty results remain honest | OPEN |
| UX-VA-02 | Ask result and provider/facility action; disabled state accurately reflects production flags | OPEN |
| UX-VA-03 | Claim, evidence review, approval, account access; a second account cannot access the claim | OPEN |
| UX-VA-04 | Stays, town search, GPS allowed/denied and no-result recovery | OPEN |
| UX-TS-01 | Vehicle/trailer selection, manual masses, calculation, missing/over-limit warnings and guidance wording | OPEN |
| UX-TS-02 | Save, reload, edit combination and specialist handoff; private combinations stay owner-scoped | OPEN |
| UX-TW-01 | Service discovery, provider profile and action; services remain primary and marketplace secondary | OPEN |
| UX-ALL-01 | Header/footer, legal links, sign-in, administrator navigation and denied permissions; no retired product links | OPEN |

A disabled feature is recorded as disabled, not a passed enabled journey.
Current visual acceptance cannot be inferred from older screenshots.

## Reliability and monitoring

Observation on 6 September 2026, approximately 18:56 AEST: all six public
`/healthz` and `/readyz` endpoints returned HTTP 200 across VanAssist, TowSmart
and TrailerWise. This was a status-only request; response identity and deployed
SHA were not captured. It does not close REL-02 for this undeployed candidate.
Browser access timed out, so no new desktop/mobile visual acceptance is claimed.

| ID | Existing basis | Evidence required to close | Status |
| --- | --- | --- | --- |
| REL-01 | GitHub CI | Passing full CI URL for the candidate SHA, including migrations, seeds, integration tests and production dependency build | OPEN |
| REL-02 | `/healthz` and `/readyz` | Timestamped status and expected identity for all three deployed hosts, tied to deployed SHA | OPEN |
| REL-03 | `infrastructure/binarylane/ops/assist-offsite-restore-drill.sh` | Current encrypted off-site snapshot ID, checksum, restore log and recovery measurements | OPEN |
| REL-04 | `docs/BACKUP_AND_RESTORE.md` | Restore matching application, database, public/private media and protected configuration; verify logins, brand isolation and critical journeys | OPEN |
| REL-05 | `docs/OPERATIONS_RUNBOOK.md` | Candidate deployment and rollback rehearsal with before/after SHA and health checks | OPEN |
| MON-01 | Existing health and scheduled-task controls | External uptime/error test event reaches owner-controlled destination; record event and receipt times, acknowledgement and recovery notification | OPEN |
| MON-02 | Scheduled jobs | Controlled staging job failure and stale-job event produce alerts; demonstrate correct recipient without sending customer mail | OPEN |

The automated restore script restores a database into a disposable MariaDB
container and checks that more than 20 tables exist. That is limited database
restore evidence. It does not establish a full application/media restore,
per-table integrity, buyer access or recovery-time/recovery-point objectives.
Record agreed RTO/RPO targets before rehearsal and compare observed values.

## Privacy and data provenance

These are evidence requests, not a statement of legal compliance or licence
transferability. The seller/data custodian owns completion and qualified review.

| Data class | Required record | Status |
| --- | --- | --- |
| Accounts, provider claims and evidence | Table/field inventory; purpose; roles; private file locations; retention trigger; deletion/export behaviour and exceptions | OPEN |
| Requests, messages and mail queues | Personal fields, recipients, delivery providers, consent/suppression records and retention for queued/sent/failed messages | OPEN |
| Garage, saved combinations and documents | Owner checks; VIN/document exposure; export/deletion behaviour; private storage and backup copies | OPEN |
| Analytics, audit and logs | Identifiers/IP fields, access, retention, anonymisation and operational/legal holds | OPEN |
| Backups | Encrypted storage, key custodian, expiry, restore access and procedure to reapply deletion after recovery | OPEN |
| Providers and stays | Source URL, acquisition date, licence/terms version, attribution, verification class, update method and transfer restrictions | OPEN |
| TowSmart catalogue | Manufacturer/source and model-year evidence, specification provenance, correction/update owner and permitted reuse | OPEN |
| Locations and government datasets | Match each imported source to terms and attribution; reconcile with `../DATA_TRUST_AND_PROVENANCE.md` | OPEN |
| Images, logos and marketing assets | Creator/source, licence or assignment, generation provenance where applicable, restrictions and transfer rights | OPEN |
| Subprocessors/APIs | Actual configured provider, data sent, region, agreement, retention, cost limits and buyer credential replacement | OPEN |

For each field record table/column, purpose, access role, retention trigger and
period, deletion mechanism, export mechanism, processor and supporting evidence.
Do not invent retention periods. Record exceptions and who approved them.
For each imported dataset classify transfer as confirmed, restricted or unknown;
unknown rights do not become included assets by appearing in the database.
Shared canonical providers need independent provenance even when a historical
LocalTorque import contributed records. Polaris data is excluded.

Export dependency names, locked versions, licences and source references from
`composer.lock`; distinguish runtime packages from development tooling. Source
metadata alone does not settle ownership of contributed application code.
`DEPENDENCIES.md` records locked package versions and declared licences, with
the lock-file checksum. Dataset and application IP review remain open.

## Evidence handover

For each closed row record artefact ID, SHA-256, capture time/timezone, release
SHA, environment, operator and reviewer. Retain failures alongside remediation.
The seller remains the provisional evidence owner until named custodians and
buyer reviewers are entered in the restricted data room. No sale gate is closed
by this register itself.
