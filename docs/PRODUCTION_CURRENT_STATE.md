# Production current state

Last verified: 22 July 2026 (Australia/Brisbane).

## Deployment

- Host: BinaryLane Ubuntu 24.04 VPS in Brisbane.
- Public domains: `vanassist.com.au`, `towsmart.com.au`, `trailerwise.com.au`
  with matching `www` hosts through Cloudflare.
- Runtime: Docker Compose, PHP 8.3-FPM, MariaDB 11.4 and Caddy 2.
- Production code commit: `2f7ef9f`.
- Release directory: `/opt/assist-platform/releases/2f7ef9f`.
- The deployed Social Studio service file was verified against the GitHub copy
  with SHA-256 `9754dbaf184f256e36f2d139e4f61bef27e751f4e918509fc5740d6c32fd14d1`.
- All migrations through `039_social_media_studio.sql` are applied; the
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
- Production contains 1,399 active providers, 2,600 TowSmart/TrailerWise brand
  listings and 5,341 brand-category assignments. Imported evidence remains
  explicitly unverified until a business is verified.
- Social Studio contains 30 reviewable/downloadable draft assets for each brand
  (90 total), covering Instagram and Facebook posts, stories, profiles and
  covers across five campaign intentions.
- A fresh database backup from 22 July passed its SHA-256 check and restored
  into a disposable MariaDB 11.4 instance with 135 tables and 1,399 providers.
- A super-administrator login reached `/admin` successfully.
- GitHub CI passed for the production commit.

## Current launch posture

The environment is viewable in `provider-onboarding` mode. Search indexing is
disabled. This is not the same as full commercial/public launch approval.

Before full indexed launch:

1. Complete the Microsoft Entra application registration, certificate and
   mailbox policy, then activate and test Microsoft Graph transactional email.
2. Supply an independent S3-compatible repository (for example Cloudflare R2)
   and credentials so the tested backup set can be copied off-server and the
   repository-based restore drill can run.
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

