# Production current state

Last verified: 23 July 2026 (Australia/Brisbane).

## Deployment

- Host: BinaryLane Ubuntu 24.04 VPS in Brisbane.
- Public domains: `vanassist.com.au`, `towsmart.com.au`, `trailerwise.com.au`
  with matching `www` hosts through Cloudflare.
- Runtime: Docker Compose, PHP 8.3-FPM, MariaDB 11.4 and Caddy 2.
- Production code commit: `515b3f6`.
- Release directory: `/opt/assist-platform/releases/515b3f6`.
- The deployed Social Studio service file was verified against the GitHub copy
  with SHA-256 `9754dbaf184f256e36f2d139e4f61bef27e751f4e918509fc5740d6c32fd14d1`.
- All migrations through `040_caravan_stay_directory.sql` are applied; the
  installer remains locked.

Do not put server passwords, application keys, database credentials or SMTP
credentials in this file or Git.

## Verified live controls

- All three `/healthz` and `/readyz` endpoints returned 200.
- `/install` returned 403.
- UFW, Fail2ban, unattended upgrades and a five-minute container health monitor
  were active.
- Scheduled application jobs were installed and manual notification, cleanup and
  database-backup runs succeeded after writable lock storage was corrected.
- Brand-specific canonical URLs, robots and sitemaps were verified.
- VanAssist, TowSmart and TrailerWise homepages, contact pages, provider
  directories and mobile hero artwork returned 200. TowSmart's calculator and
  TrailerWise's secondary marketplace also returned 200.
- Public support addresses resolve to `support@vanassist.com.au`,
  `support@towsmart.com.au` and `support@trailerwise.com.au` respectively.
- Production contains 7,304 providers, 10,912 brand listings and 22,115
  brand-category assignments. Imported evidence remains
  explicitly unverified until a business is verified.
- VanAssist contains 8,457 community-sourced Australian stay listings across all
  states and territories: caravan parks, campgrounds and 853 identified free
  stays. Council/authority and operator verification use distinct evidence-based
  labels. Town/GPS search and operator claims are live.
- Social Studio contains 30 reviewable/downloadable draft assets for each brand
  (90 total), covering Instagram and Facebook posts, stories, profiles and
  covers across five campaign intentions.
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

The environment is viewable in `provider-onboarding` mode. Search indexing is
disabled. This is not the same as full commercial/public launch approval.

Before full indexed launch:

1. Complete the Microsoft Entra application registration, certificate and
   mailbox policy, then activate and test Microsoft Graph transactional email.
2. Supply an independent automated S3-compatible repository (for example
   Cloudflare R2) and credentials. A manual off-server restore drill has passed;
   scheduled off-site replication is not active without these credentials.
3. Install owner-controlled SSH keys, disable password/root SSH as appropriate,
   and rotate previously exposed temporary passwords.
4. Change any exposed application administrator password.
5. Complete owner acceptance of content, providers and critical journeys.

## Known product limitations

See `PRODUCT_AND_FEATURES.md`. TowSmart remains an MVP whose calculations are
general guidance, and provider records still require progressive owner/business
verification. TrailerWise is now service-first; trailer sales remain a clearly
secondary module. Never infer commercial launch completion from an HTTP 200
response.

