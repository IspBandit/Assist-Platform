# Production current state

## Verified update: 6 September 2026

SSH confirms release `4d5a4c957df1e556dc0c26f5345880aaad13277b` is current.
Release run [34030818368](https://github.com/IspBandit/Assist-Platform/actions/runs/34030818368)
passed full reusable CI, upload/release and Assist public checks, including
service-worker form-preservation checks. A checksum-verified local
database restore passed with 232 tables in 44 seconds. Independent off-site backup
configuration is absent; no users have MFA enrolled. Public-key root SSH was
available, superseding the older all-root-login-disabled claim below.
See `acquisition/SALE_REVIEW_2026-09-06.md` and its evidence directory for the
current counts, limitations and source data. A historical snapshot using
release `ccb8fb2c96c85dc1760fca0a407627aef8e6728e` recorded a CQDiggings mismatch,
while the active sale-ready Assist baseline is now anchored to
`4d5a4c957df1e556dc0c26f5345880aaad13277b`.

Last verified: 24 August 2026 (Australia/Brisbane).
Sale-readiness boundary updated: 6 September 2026.

## Deployment

- Host: BinaryLane Ubuntu 24.04 VPS in Brisbane.
- Public acquisition-scope domains: `vanassist.com.au`, `towsmart.com.au`, `trailerwise.com.au`
  with matching `www` hosts through Cloudflare.
- Runtime: Docker Compose, PHP 8.3-FPM, MariaDB 11.4 and Caddy 2.
- Production code commit at last live verification: `046a2c6b492935e4d56e6c3fd0f0372b28e83323`.
- All migrations through `133_assist_ai_outcomes.sql` were applied at the last
  verified production-state snapshot; later sale-readiness retirement migrations
  must be applied only through the normal controlled release process.
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

## Verified live controls at last production check

- All three public acquisition-scope `/healthz` and `/readyz` endpoints returned 200.
- Admin API responds on production and validates configured authentication.
- Ask VanAssist and traveller-facility results were live on VanAssist.
- Google Routes road-distance integration reported configured.
- Production deployment uses the restricted `assistdeploy` SSH account,
  pinned host keys and a root-owned release command. Remote root login and
  password authentication are disabled.
- `/install` returned 403.
- UFW, Fail2ban, unattended upgrades and a five-minute container health monitor
  were active.
- Scheduled application jobs were installed. At the last verification, the local
  application database-backup task needed the MariaDB dump client/strict-output
  release correction and a new formal scheduled-backup verification.
- Brand-specific canonical URLs, robots and sitemaps were verified for VanAssist,
  TowSmart and TrailerWise.
- VanAssist, TowSmart and TrailerWise homepages, contact pages, provider
  directories and mobile hero artwork returned 200. TowSmart's calculator and
  TrailerWise's secondary marketplace also returned 200.
- TowSmart contained 199 tow-vehicle reference records and 3,769 caravan,
  camper, hybrid and trailer records at the last verified snapshot.
- Public support addresses resolved to `support@vanassist.com.au`,
  `support@towsmart.com.au` and `support@trailerwise.com.au` respectively.
- Production contained 7,304 providers, 20,272 brand listings and 54,133
  brand-category assignments at the last verified snapshot. Imported evidence
  remains explicitly unverified until a business is verified.
- VanAssist contained 8,457 community-sourced Australian stay listings across all
  states and territories, including 853 identified free stays, at the last
  verified snapshot.
- A post-import database backup from 23 July was downloaded off-server, passed
  SHA-256 verification and restored into an isolated MariaDB database with 136
  tables, 7,304 providers, 8,457 stays and 17,615 towns. This historical manual
  restore is useful evidence but does not replace a current automated restore gate.
- A super-administrator login reached `/admin` successfully.
- GitHub CI passed for the then-production commit.
- A rendered acceptance pass covered 72 public pages and 70 authenticated
  provider pages across desktop and mobile viewports with no HTTP failures,
  broken images, horizontal overflow, browser errors or cross-brand email
  mismatches at that time.

## Current sale-readiness posture

The platform remains production-backed but is now in a **sale-readiness release
cycle**. Do not describe it as sale-ready until `docs/SALE_READINESS.md` is fully
satisfied and current evidence is attached to the exact candidate commit.

Before a sale candidate can be signed off:

1. Deploy and verify the current observability, provider-claim and scheduled-backup changes.
2. Configure an independent automated encrypted S3-compatible backup target and credentials.
3. Run and record a current automated restore rehearsal.
4. Rotate any previously exposed temporary server/application administrator credentials.
5. Complete owner acceptance of critical VanAssist, TowSmart and TrailerWise journeys.
6. Verify retired LocalTorque and Polaris hosts/brand keys cannot resolve as active product brands.
7. Complete buyer-facing asset, provenance, cost and transfer inventories.

Microsoft Graph sender health was healthy at the last verification and all three
application-path brand mailbox probes were sent successfully. This is operational
mail evidence only and does not create marketing consent.

## Known product limitations requiring disclosure or close-out

- TowSmart calculations are general guidance, not certification or legal advice.
- Provider records require progressive owner/business verification.
- TrailerWise is service-first; trailer sales remain a secondary module.
- Formal scheduled off-site backup/restore evidence must be current.
- Full commercial charging must not be claimed unless production billing is enabled
  and verified on the sale candidate.
- Accessibility/compliance claims must match current evidence rather than roadmap intent.

