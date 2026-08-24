# Production current state

Last verified: 24 August 2026 (Australia/Brisbane).

## Deployment

- Host: BinaryLane Ubuntu 24.04 VPS in Brisbane.
- Public domains: `vanassist.com.au`, `towsmart.com.au`, `trailerwise.com.au`
  with matching `www` hosts through Cloudflare.
- Runtime: Docker Compose, PHP 8.3-FPM, MariaDB 11.4 and Caddy 2.
- Production code commit: `046a2c6b492935e4d56e6c3fd0f0372b28e83323`
  (PR #221 — production zero-result search fixes and release guard repair).
- Release directory: `/opt/assist-platform/releases/046a2c6b492935e4d56e6c3fd0f0372b28e83323`.
- Previous documented release: `6a3f09d78dda81f50fab584decb8fb4f382ef717` (superseded August 2026).
- The deployed Social Studio service file was verified against the GitHub copy
  with SHA-256 `9754dbaf184f256e36f2d139e4f61bef27e751f4e918509fc5740d6c32fd14d1`.
- All migrations through `133_assist_ai_outcomes.sql` are applied; the
  installer remains locked.

Do not put server passwords, application keys, database credentials or SMTP
credentials in this file or Git.

## Verified live controls

- All three `/healthz` and `/readyz` endpoints returned 200.
- All three `/readyz` endpoints reported release `046a2c6` (verified 24 August 2026).
- **Admin API** responds on production (`GET /api/v1/admin/health` → 200;
  `POST /api/v1/admin/auth/token` validates credentials — not disabled).
- **Ask VanAssist** is enabled on production VanAssist (`/ask` returns results;
  `assist_ai_search` on). Homepage includes the Ask form when the flag is on.
- **Traveller facilities in Ask** return live results (toilets, hospitals,
  pharmacies, showers, boat ramps, etc.) when `assist_ai_traveller_facilities`
  is on.
- **Google Routes** road-distance integration reports configured on Admin API
  health (`road_distance.provider=google_routes`).
- Griffiths Creek typo recovery and road-distance enforcement ship in release
  `6a3f09d` (see `docs/RELEASE_NOTES.md` unreleased notes merged to this release).
- Production deployment uses the restricted `assistdeploy` SSH account,
  pinned host keys and a root-owned release command. Remote root login and
  password authentication are disabled.
- `/install` returned 403.
- UFW, Fail2ban, unattended upgrades and a five-minute container health monitor
  were active.
- Scheduled application jobs are installed. The application database-backup
  task was found stale in `running` state on 24 August; the deployed app image
  lacks the MariaDB dump client and therefore fell back to an unsuitable large
  PHP export. The release candidate adds the client and strict output checks.
- Brand-specific canonical URLs, robots and sitemaps were verified.
- VanAssist, TowSmart and TrailerWise homepages, contact pages, provider
  directories and mobile hero artwork returned 200. TowSmart's calculator and
  TrailerWise's secondary marketplace also returned 200.
- TowSmart contains 199 tow-vehicle reference records and 3,769 caravan,
  camper, hybrid and trailer records. Its current-vehicle pass now includes
  separate 2025/2026 Prado 250 grades, Kia Tasman, BYD Shark 6, JAC T9,
  LDV Terron 9, GWM Cannon Alpha and all 20 current Mazda BT-50
  configurations with source metadata on new data.
- The calculator clears every mapped field before applying a selected catalogue
  record, preventing an unavailable value from being inherited from the
  previously selected vehicle.
- Public support addresses resolve to `support@vanassist.com.au`,
  `support@towsmart.com.au` and `support@trailerwise.com.au` respectively.
- Production contains 7,304 providers, 20,272 brand listings and 54,133
  brand-category assignments. Imported evidence remains
  explicitly unverified until a business is verified.
- LocalTorque is installed as a private fourth brand with 40 data-driven automotive categories, 6,760 relevant brand listings and 26,677 category assignments. Its private homepage, directory, category, sitemap and robots responses passed direct application checks. It has no public production domain and is not publicly launched.
- VanAssist contains 8,457 community-sourced Australian stay listings across all
  states and territories: caravan parks, campgrounds and 853 identified free
  stays. Council/authority and operator verification use distinct evidence-based
  labels. Town/GPS search and operator claims are live.
- Social Studio contains 33 reviewable/downloadable draft assets for each brand
  (99 total), covering Instagram and Facebook posts, stories, profiles and
  covers across five campaign intentions. The latest nine launch assets use the
  corrected responsive wide-cover layout and have been visually reviewed.
- A post-import database backup from 23 July was downloaded off-server, passed
  SHA-256 verification and restored into an isolated MariaDB database with 136
  tables, 7,304 providers, 8,457 stays and 17,615 towns. The test database was
  removed after validation.
- A super-administrator login reached `/admin` successfully.
- GitHub CI passed for the production commit.
- A rendered acceptance pass covered 72 public pages and 70 authenticated
  provider pages across desktop and mobile viewports with no HTTP failures,
  broken images, horizontal overflow, browser errors or cross-brand email
  mismatches. See `RENDERED_ACCEPTANCE_2026-07-22.md`.

## Current launch posture

VanAssist remains in an **initial launch** posture: free search, provider
onboarding and community stays are live; formal commercial launch approval and
full Platform Quality Gate **PASS** are not yet recorded.

Public pages use `index, follow` meta robots and expose sitemaps. Treat this as
**limited public availability**, not signed-off commercial launch.

Assist RIC facility catalogue packs (original, third-wave and gap-fill) have
been submitted to production Admin API `/facility-imports` from the operator
workstation (Aug 2026 — see assist-ric `docs/RIC_FACILITY_CATALOGUE_STATUS.md`).
Admin-side row counts and any unpublished import candidates still require
owner verification in MariaDB.

Before full indexed launch:

1. Deploy and verify the observability, provider-claim and scheduled-backup
   release candidate, including migrations `134` and `135` and a successful
   bounded local backup run.
2. Supply an independent automated S3-compatible repository (for example
   Cloudflare R2) and credentials. A manual off-server restore drill has passed;
   scheduled off-site replication is not active without these credentials.
3. Run the independent automated restore rehearsal and retain current status
   evidence; an old manual restore does not satisfy the current formal gate.
4. Rotate previously exposed temporary server and application administrator
   passwords through their owner-controlled consoles.
5. Complete owner acceptance of content, providers and critical journeys.

Microsoft Graph sender health is now healthy, all three application-path brand
mailbox probes are sent, and the failed email queue is zero. This closes the
previous email-delivery launch blocker; it does not create marketing consent.

## Known product limitations

See `PRODUCT_AND_FEATURES.md`. TowSmart remains an MVP whose calculations are
general guidance, and provider records still require progressive owner/business
verification. TrailerWise is now service-first; trailer sales remain a clearly
secondary module. Never infer commercial launch completion from an HTTP 200
response.

