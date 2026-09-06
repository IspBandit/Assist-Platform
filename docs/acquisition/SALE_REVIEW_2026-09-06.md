# Sale candidate review: 6 September 2026

Workstreams: OPS-005 / COM-005. Scope: VanAssist, TowSmart and TrailerWise only.
LocalTorque and Polaris are excluded, including their commercial metrics.

## Decision

NOT SALE-READY. This is a documented software/data asset with operating sites,
not evidence of an established paid business. Do not mark the open items below
complete merely because the source or a document exists.

## Verified production baseline

- Source: `ccb8fb2c96c85dc1760fca0a407627aef8e6728e`, fetched from current main.
- SSH resolved `/opt/assist-platform/current` to that release on 6 September.
- [Release run 34024561960](https://github.com/IspBandit/Assist-Platform/actions/runs/34024561960)
  passed reusable CI: metadata, syntax, PHPStan, unit tests, migrations, seeds,
  data audit, encrypted-secret validation, backfill, integration tests,
  dependency audit and production dependency build.
- The same run verified the archive, backed up, deployed and passed its public
  Assist journey checks. Its later CQDiggings content check failed. Therefore
  the overall release run is FAILED even though Assist deployment succeeded.
- Prior release: `4f5fb267317fa5436c09676524e841166245e19c`.
- Production brand rows: VanAssist, TowSmart and TrailerWise active;
  LocalTorque and Polaris disabled. Historical rows remain in backups.

## Recovery evidence

The deployment backup `assist-20260906T193058.sql.gz` passed its SHA-256 manifest
and gzip validation. On 6 September at 11:18:25 UTC, it restored into a disposable
MariaDB 11.4 container, with networking disabled and a 512 MB memory limit.
The restore and table checks completed in 44 seconds: 232 tables, 14,098 providers,
4 users and 17,615 towns. Production data and the current symlink were untouched.
The rehearsal container and its volume are removed by the EXIT trap.

This establishes local database recoverability only. It does not establish
off-site recovery, media/configuration recovery, application login after restore,
or a production rollback rehearsal. The 44 seconds is not a full-service RTO.
`/opt/assist-platform/config/backup.env` is absent. The owner confirmed there is
no independent backup-storage account. REL-03/04/05 remain open.

## Acceptance and defects

The reproducible browser suite is `tests/Acceptance/sale-three-brand.spec.js`.
It covers all three homepage/directory/profile journeys, public health and
installer lock, VanAssist Ask and stays, legal-page reachability and TowSmart
custom calculation/over-limit warning at 1440x900 and 390x844.

Final isolated run: 12/12 passed in 32.5 seconds, with live service workers
blocked to isolate the known reload defect. Screenshots and SHA-256 manifest
are retained with the evidence. All six brand/viewport home screens were visually
reviewed. This is partial public acceptance, not a full release gate pass.

Live testing discovered service-worker activation forcibly navigating every
open window, which can discard typed form values and interrupt navigation.
The candidate removes that navigation and waits for cache cleanup and client
claim. A Node regression test verifies that open forms are not navigated.
Browser results with service workers blocked isolate this known defect; they are not proof that this correction is live.

The candidate also separates reusable CI concurrency from production workflow
concurrency, removes stale CQDiggings product-content assertions from Assist's
release, and corrects browser selectors and MIME/scope assumptions. CQDiggings
continues to be released from its own repository. Shared proxy configuration is
unchanged. No application migrations, billing enablement or new features.

Still required: authenticated provider claim/review/approval/account acceptance,
saved-combination ownership/reload and administrator journeys; GPS allow/deny and
empty-state acceptance; complete catalogue-selection acceptance; keyboard and
contrast acceptance; final deployed-candidate repetition without overlays.

## Operating observations

See `../evidence/sale-2026-09-06/` for reproducible metadata/aggregate collectors.
These collectors use read-only database transactions and export no customer rows.

- No task was recorded failed or running in the snapshot. Ten tasks were `never`,
  including AI retention, provider-import queue and daily performance email.
  A task registry is not proof that every expected cron entry is installed.
- Email queue: 747 sent, 28 cancelled. This is queue status, not mailbox receipt.
- MFA: zero users have an enabled MFA method. No credentials were rotated here.
- SSH public-key root access worked in this session. The older statement that
  all remote root login is disabled is not an accurate current control claim.
- App and database containers healthy. Host filesystem: 18 GB used of 59 GB;
  40 GB available. RAM: approximately 3.9 GB total. Other products share this host;
  costs and a buyer migration must separate those products.

## Remaining sign-off conditions

| Gate | Position and next evidence | Owner |
| --- | --- | --- |
| A Product | Public subset tested; complete authenticated and final deployment acceptance | Engineering/operator |
| B Reliability | Configure independent encrypted backup; full restore and rollback rehearsal; prove alert receipt and cron coverage | Seller/operator |
| C Security/privacy | Enrol admin MFA, rotate exposed credentials, approve field retention and privacy/subprocessor records | Seller/security reviewer |
| D Data/IP | Reconcile missing and conflicting source licences; obtain image/code assignment evidence | Seller/data custodian |
| E Business | Aggregate snapshot exists; invoices, costs, verified attribution/conversion and financial statements absent | Seller |
| F Transfer | Runbooks exist; clean buyer-host application/media restore and account transfers unperformed | Seller/buyer operator |
| G Data room | Indexed technical evidence exists; restricted legal/account documents still required | Seller |

No final sale-approved tag is asserted by this record. A candidate tag must bind
the approved commit and archive checksum after CI, review and release acceptance.
Never move an existing published candidate tag; issue a new version if it changes.
