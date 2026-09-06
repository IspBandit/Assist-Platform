# Production current state

## Evidence reconciliation: 7 September 2026

GitHub Actions production release run `34030818368` proves that release
`4d5a4c957df1e556dc0c26f5345880aaad13277b` was deployed successfully on
6 September 2026. This supersedes the earlier statement that
`ccb8fb2c96c85dc1760fca0a407627aef8e6728e` was still current and that the
service-worker form-preservation fix remained undeployed.

The exact `4d5a4c9...` release passed reusable validation, built an immutable
archive, passed SHA-256 verification, created and verified the production
database backup, rebuilt healthy application containers, confirmed that no
migrations remained, provisioned Google Routes and passed protected public smoke
checks across VanAssist, TowSmart and TrailerWise.

The later repository commit
`5618605ac82cbca6a83343c61c336ffa3634b857` (PR #248) passed CI but adds generic
shared-host edge plumbing for separately deployed products. It has no Assist
application feature or migration and is not required for the current Assist sale
baseline. No later production release is recorded in the reviewed Actions runs.

A checksum-verified isolated database restore also passed with 232 tables in
44 seconds. Independent off-site backup configuration remains absent; the last
sale review recorded no enabled MFA enrolments and public-key root SSH access.
Those remain sale-readiness items rather than reasons to misstate the successful
application deployment.

See `acquisition/SALE_REVIEW_2026-09-06.md`,
`acquisition/EVIDENCE_REGISTER.md` and GitHub Actions run `34030818368` for the
underlying evidence.

## Deployment

- Host: BinaryLane Ubuntu 24.04 VPS in Brisbane.
- Public acquisition-scope domains: `vanassist.com.au`, `towsmart.com.au`,
  `trailerwise.com.au`, with matching `www` hosts through Cloudflare.
- Runtime: Docker Compose, PHP 8.3-FPM, MariaDB 11.4 and Caddy 2.
- Current verified Assist application release:
  `4d5a4c957df1e556dc0c26f5345880aaad13277b`.
- Production release workflow: GitHub Actions run `34030818368`, successful.
- The release reported `Nothing to migrate. Database is up to date.`
- The installer remains locked.

Do not put server passwords, application keys, database credentials or SMTP
credentials in this file or Git.

## Active saleable brands

The active product and acquisition boundary is exactly:

1. VanAssist
2. TowSmart
3. TrailerWise

LocalTorque and Polaris are retired/excluded. Historical database rows,
migrations, ADRs and audit material may remain for upgrade and due-diligence
integrity. They are not active sale brands and must not be re-enabled by future
configuration or deployment work.

## Verified release and live controls

The successful `4d5a4c9...` production release provides the following current
evidence:

- Reusable validation passed service-worker form-preservation regression,
  Composer validation, PHP syntax, PHPStan/static analysis, unit tests, fresh
  migrations, seeds, provider-pack validation, database integration tests,
  dependency audit and production dependency build.
- The immutable release archive passed checksum verification before installation.
- The release created and verified production backup
  `backups/database/assist-20260906T214622.sql.gz` before switching releases.
- MariaDB and application containers returned healthy and Caddy restarted.
- Google Routes credentials were tested against the route-matrix API, provisioned
  to production and the protected release endpoint reported configured.
- Protected smoke checks passed for VanAssist home, providers, request assistance,
  nearest-location, login, registration and password-reset routes.
- Protected smoke checks passed for TowSmart home, calculator and rules routes.
- Protected smoke checks passed for TrailerWise home, marketplace and rules routes.
- VanAssist Ask returned Griffiths Creek dump-point evidence for the designated
  test query.
- VanAssist Ask returned road/straight-line distance output for an auto-electrician
  query near Karratha.
- Direct provider-name Ask search returned Battery World Greenslopes.
- Brand assets were verified for TowSmart and TrailerWise and public pages did not
  expose the retired platform symbol checked by the release workflow.
- The Ask question library contained at least 1,000 active questions during the
  protected release check; the deployment log recorded 1,550 active catalogue
  entries applied.
- The provider-pack release processing completed 9,730 source records and the
  data audit reported no provider-brand duplicates or orphan provider services.

Earlier verified controls also remain part of the evidence set unless superseded:

- Admin API responds on production and validates configured authentication.
- `/install` returned 403.
- UFW, Fail2ban, unattended upgrades and a five-minute container health monitor
  were active at the production review.
- Brand-specific canonical URLs, robots and sitemaps were verified for VanAssist,
  TowSmart and TrailerWise.
- A super-administrator login reached `/admin` successfully in prior acceptance.
- Microsoft Graph sender health was healthy and all three application-path brand
  mailbox probes were sent successfully. Application-side sending is proven;
  recipient mailbox receipt remains a separate acceptance item.

## Current data observations

The `4d5a4c9...` release data audit recorded:

- 17,615 towns, with 110 missing postcode/coordinates;
- 11,876 active providers;
- 3,528 unclaimed providers missing source metadata;
- 290 providers missing a brand listing;
- 59 duplicate-name/town provider candidates;
- 10,224 active VanAssist stay/park records with no missing source field in that
  audit and 288 duplicate-name/state candidates;
- 199 TowSmart vehicle references, 163 with missing source URL;
- 3,769 TowSmart towable references, all with missing source URL in that audit,
  plus 5 invalid-weight and 86 duplicate-identity candidates;
- VanAssist provider-pack source records: 9,989 total, 7,579 public and 2,410 held
  for review, with no required-source-licence omissions reported for that pack and
  no review rows publicly visible.

These are engineering/data-audit counts, not claims that every underlying record
has commercially transferable rights. The acquisition provenance register remains
authoritative for unresolved licensing and transfer decisions.

## Recovery evidence

- A deployment database backup was created and checksum-verified during the
  successful `4d5a4c9...` production release.
- A separate isolated restore rehearsal restored 232 tables in 44 seconds.
- This proves database recoverability, not complete off-site disaster recovery.

Still required for sale-readiness reliability sign-off:

1. configure an independent automated encrypted off-site backup destination;
2. produce and retain a current off-site snapshot/checksum;
3. restore matching application, database, public/private media and protected
   configuration into an isolated clean environment;
4. verify critical logins and three-brand journeys after that restore; and
5. rehearse immutable rollback with before/after release identities and health
   checks.

## Current sale-readiness posture

The platform is production-backed and in a **sale-readiness release cycle**. Do
not describe it as fully sale-ready until `docs/SALE_READINESS.md` is satisfied or
remaining exceptions are explicitly disclosed and accepted for the transaction.

The application deployment itself is no longer an open blocker. The principal
remaining gates are:

1. authenticated provider/admin and TowSmart saved-combination acceptance;
2. independent off-site backup, full restore and rollback rehearsal;
3. external monitoring and scheduled-task failure alert evidence;
4. MFA enrolment, credential rotation and privileged-access review;
5. dataset/IP provenance and commercial-transfer decisions;
6. invoice-backed operating costs and account/domain transfer records;
7. privacy retention/export/deletion/subprocessor approval;
8. Assist RIC inclusion/licensing decision;
9. buyer-style clean-host transfer rehearsal; and
10. final immutable sale-candidate tag/checksum bound to the completed quality
    gate evidence.

## Known product limitations requiring disclosure or close-out

- TowSmart calculations are general guidance, not certification or legal advice.
- Provider records require progressive owner/business verification.
- TrailerWise is service-first; trailer sales remain a secondary module.
- Formal independent off-site backup/full restore evidence is not yet complete.
- Production charging must not be claimed unless enabled and evidenced; billing
  activation is not required to sell the current software/data asset.
- Accessibility/compliance claims must match current evidence rather than roadmap
  intent.
- Data counts do not imply unrestricted commercial transfer rights; unresolved
  sources must be reconciled, quarantined or excluded.
