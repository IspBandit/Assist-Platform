# Production current state

Last verified: 22 July 2026 (Australia/Brisbane).

## Deployment

- Host: BinaryLane Ubuntu 24.04 VPS in Brisbane.
- Public domains: `vanassist.com.au`, `towsmart.com.au`, `trailerwise.com.au`
  with matching `www` hosts through Cloudflare.
- Runtime: Docker Compose, PHP 8.3-FPM, MariaDB 11.4 and Caddy 2.
- Production commit: `2e62a6876759b837ea7e8c691920793d7d316605`.
- Release directory: `/opt/assist-platform/releases/2e62a6876759`.
- Artefact SHA-256: `e2ac93a331725bf13cf18f1efc43c898cbfb0dcfe0229d8b1345e73b57a25cc2`.
- All 37 migrations were applied; the installer is locked.

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
- VanAssist representative pages, TowSmart calculator/login and TrailerWise
  homepage/marketplace returned 200.
- A super-administrator login reached `/admin` successfully.
- GitHub CI passed for the production commit.

## Current launch posture

The environment is viewable in `provider-onboarding` mode. Search indexing is
disabled. This is not the same as full commercial/public launch approval.

Before full indexed launch:

1. Configure and test transactional SMTP.
2. Ensure TowSmart and TrailerWise use their own sender domains.
3. Establish encrypted independent off-server application/media/database backups
   and perform a restore rehearsal.
4. Install owner-controlled SSH keys, disable password/root SSH as appropriate,
   and rotate previously exposed temporary passwords.
5. Change any exposed application administrator password.
6. Correct TrailerWise product positioning and content.
7. Complete owner acceptance of content, providers and critical journeys.

## Known product limitations

See `PRODUCT_AND_FEATURES.md`. In particular, TowSmart is an MVP and TrailerWise
currently over-emphasises sales/hire listings. Never infer product completion
from an HTTP 200 response.

