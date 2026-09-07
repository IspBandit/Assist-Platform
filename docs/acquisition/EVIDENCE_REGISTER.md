# Acquisition evidence register

Workstream: OPS-005 / COM-005. Prepared 6 September 2026; production evidence
reconciled 7 September 2026.
Scope: VanAssist, TowSmart and TrailerWise only.

A row is closed only when a dated artefact identifies the environment, release
SHA, operator/system, expected result and actual result. Repository implementation
alone is not production acceptance. Store sensitive evidence in the restricted
transaction data room; commit only redacted references and checksums.

## Three-site UX acceptance

Run each journey at desktop 1440x900 and mobile 390x844. Retain screenshots,
keyboard/focus observations, errors and the tested release SHA. Use designated
test accounts and avoid sending messages to real providers during rehearsal.

| ID | Journey and acceptance | Status |
| --- | --- | --- |
| UX-VA-01 | Location/service search, useful results, provider profile, contact/request; missing contacts and empty results remain honest | PARTIAL |
| UX-VA-02 | Ask result and provider/facility action; disabled state accurately reflects production flags | PARTIAL |
| UX-VA-03 | Claim, evidence review, approval, account access; a second account cannot access the claim | OPEN |
| UX-VA-04 | Stays, town search, GPS allowed/denied and no-result recovery | OPEN |
| UX-TS-01 | Vehicle/trailer selection, manual masses, calculation, missing/over-limit warnings and guidance wording | PARTIAL |
| UX-TS-02 | Save, reload, edit combination and specialist handoff; private combinations stay owner-scoped | OPEN |
| UX-TW-01 | Service discovery, provider profile and action; services remain primary and marketplace secondary | PARTIAL |
| UX-ALL-01 | Header/footer, legal links, sign-in, administrator navigation and denied permissions; no retired product links | PARTIAL |

A disabled feature is recorded as disabled, not a passed enabled journey.
Current visual acceptance cannot be inferred from older screenshots.

### Current public acceptance evidence

The reproducible three-brand browser suite recorded a 12/12 isolated public pass
at 1440x900 and 390x844 before the corrective production release. That run used
live service workers blocked to isolate the then-known activation reload defect;
therefore it is retained as partial evidence rather than final public acceptance.

Production release `74b18116f19f0a5ba1b8a651cdf9cf4ad4b74843` subsequently
deployed the form-preserving service-worker correction successfully in GitHub
Actions run `34077589608`. The exact release passed the service-worker regression
test and protected public smoke checks for:

- VanAssist home, providers, request assistance, nearest location, login,
  registration and password reset;
- TowSmart home, calculator and rules;
- TrailerWise home, marketplace and rules;
- VanAssist Ask designated Griffiths Creek dump-point evidence;
- VanAssist Ask road/straight-line distance output for a provider search near
  Karratha; and
- direct provider-name search for Battery World Greenslopes.

These protected smoke checks prove the corrected release is live and its core
public routes/search checks work. They do not replace the remaining browser,
authenticated, GPS-denial, owner-isolation or administrator acceptance rows.

## Reliability and monitoring

The successful `74b1811...` production run built an immutable release, passed
archive checksum verification, created and verified the production database
backup, rebuilt healthy application/database containers, confirmed there were no
pending migrations, provisioned Google Routes and completed protected public
journey checks. The release log explicitly reports the release completed
successfully.

GitHub Actions run `34077589608` exercised `/healthz` and `/readyz` on all three
public hosts. At `2026-09-07T03:04Z`, each `/readyz` response
returned HTTP 200 with `status: ready` and release
`74b18116f19f0a5ba1b8a651cdf9cf4ad4b74843`, matching the workflow SHA.

Desktop directory trust-list copy now sits on an opaque dark-teal surface with
white text instead of depending on the underlying hero image. Formal end-to-end
accessibility acceptance remains part of the browser evidence gate.

| ID | Existing basis | Evidence required to close | Status |
| --- | --- | --- | --- |
| REL-01 | GitHub CI/release | GitHub Actions run `34077589608` passed full reusable validation for deployed SHA `74b18116f...`, including migrations, seeds, integration tests, dependency audit and production dependency build | CLOSED |
| REL-02 | `/healthz` and `/readyz` | Run `34077589608` exercised all six endpoints; the 7 September reconciliation retained matching `74b18116f...` release identity from each public `/readyz` endpoint | CLOSED |
| REL-03 | `infrastructure/binarylane/ops/assist-offsite-restore-drill.sh` | Independent off-site storage is not configured or included; customer/buyer supplies its destination, credentials, retention and alerts after transfer | BUYER CONFIGURATION |
| REL-04 | `docs/BACKUP_AND_RESTORE.md` | Restore matching application, database, public/private media and protected configuration; verify logins, brand isolation and critical journeys | OPEN |
| REL-05 | `docs/OPERATIONS_RUNBOOK.md` | Candidate deployment and rollback rehearsal with before/after SHA and health checks | PARTIAL |
| MON-01 | Existing health and scheduled-task controls | External uptime/error test event reaches owner-controlled destination; record event and receipt times, acknowledgement and recovery notification | OPEN |
| MON-02 | Scheduled jobs | Controlled staging job failure and stale-job event produce alerts; demonstrate correct recipient without sending customer mail | OPEN |

REL-05 is partial because a protected immutable deployment of `74b1811...` is
proven, including the previous-release path and pre-release backup, but an actual
rollback rehearsal has not been retained.

The automated restore script restores a database into a disposable MariaDB
container and checks that more than 20 tables exist. The 6 September isolated
rehearsal went further and restored 232 tables in 44 seconds. This remains limited
database restore evidence. It does not establish a full application/media restore,
buyer access or agreed recovery-time/recovery-point objectives.

## Privacy and data provenance

These are evidence requests, not a statement of legal compliance or licence
transferability. The seller/data custodian owns completion and qualified review.

| Data class | Required record | Status |
| --- | --- | --- |
| Accounts, provider claims and evidence | Table/field inventory; purpose; roles; private file locations; retention trigger; deletion/export behaviour and exceptions | PARTIAL |
| Requests, messages and mail queues | Personal fields, recipients, delivery providers, consent/suppression records and retention for queued/sent/failed messages | PARTIAL |
| Garage, saved combinations and documents | Owner checks; VIN/document exposure; export/deletion behaviour; private storage and backup copies | PARTIAL |
| Analytics, audit and logs | Identifiers/IP fields, access, retention, anonymisation and operational/legal holds | PARTIAL |
| Backups | Local-backup handling is disclosed; buyer must define its independent encrypted storage, key custodian, expiry, restore access and deletion-after-recovery procedure | BUYER CONFIGURATION |
| Providers and stays | Source URL, acquisition date, licence/terms version, attribution, verification class, update method and transfer restrictions | PARTIAL |
| TowSmart catalogue | Manufacturer/source and model-year evidence, specification provenance, correction/update owner and permitted reuse | PARTIAL |
| Locations and government datasets | Match each imported source to terms and attribution; reconcile with `../DATA_TRUST_AND_PROVENANCE.md` | PARTIAL |
| Images, logos and marketing assets | Creator/source, licence or assignment, generation provenance where applicable, restrictions and transfer rights | OPEN |
| Subprocessors/APIs | Actual configured provider, data sent, region, agreement, retention, cost limits and buyer credential replacement | PARTIAL |

The partial privacy statuses reflect the engineering field inventory now recorded
in `PRIVACY_REGISTER.md`; seller approval, retention decisions, deletion/export
behaviour and legal/finance exceptions remain open.

The partial provenance statuses reflect the production aggregate/source review in
`DATA_PROVENANCE_REGISTER.md`. That review explicitly found material unresolved
rights, including 10,979 non-deleted provider records with no recorded licence and
3,528 with no source URL. Unknown rights do not become included assets by appearing
in the database.

For each field record table/column, purpose, access role, retention trigger and
period, deletion mechanism, export mechanism, processor and supporting evidence.
Do not invent retention periods. Record exceptions and who approved them.
For each imported dataset classify transfer as confirmed, restricted or unknown;
unknown rights must be quarantined or excluded at transaction time unless resolved.

Shared canonical providers need independent provenance even when a historical
LocalTorque import contributed records. LocalTorque and Polaris are excluded as
active products/assets except for necessary historical migration/audit lineage.

Export dependency names, locked versions, licences and source references from
`composer.lock`; distinguish runtime packages from development tooling.
`DEPENDENCIES.md` records locked package versions and declared licences, with the
lock-file checksum. Dataset and application IP review remain open.

## Supporting operational tooling

Assist RIC (`IspBandit/assist-ric`) is included as supporting proprietary
operational tooling. It is not a public brand. Its documented architecture keeps
Assist Platform as the system of record and synchronises approved data through the
versioned Admin API rather than direct production MariaDB access. Final source/IP
assignment evidence for both repositories remains required.

## Evidence handover

For each closed row record artefact ID, SHA-256 where applicable, capture
time/timezone, release SHA, environment, operator/system and reviewer. Retain
failures alongside remediation.

The seller remains the provisional evidence owner until named custodians and buyer
reviewers are entered in the restricted data room. No sale gate is closed merely
because a row or document exists; the evidence described by the row must be
available for diligence.
