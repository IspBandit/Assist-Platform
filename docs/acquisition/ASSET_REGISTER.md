# Assist Platform asset register

This register describes the known operational assets required to transfer and
operate the active three-brand Assist Platform. It intentionally contains no
credentials or secret values.

Status values:

- **Included** — expected to transfer with the sale package.
- **Shared/confirm** — required by the platform but ownership/transfer mechanics must be confirmed before completion.
- **Excluded** — deliberately outside the acquisition boundary.

## Brands and domains

| Asset | Status | Purpose | Transfer evidence required |
| --- | --- | --- | --- |
| `vanassist.com.au` + `www` | Included | VanAssist public product | Registrar owner, expiry, DNS zone export, transfer method |
| `towsmart.com.au` + `www` | Included | TowSmart public product | Registrar owner, expiry, DNS zone export, transfer method |
| `trailerwise.com.au` + `www` | Included | TrailerWise public product | Registrar owner, expiry, DNS zone export, transfer method |
| LocalTorque brand/domain assets | Excluded | Retired experiment | Excluded-assets schedule |
| Polaris brand/domain assets | Excluded | Retired experiment | Excluded-assets schedule |

## Source code and repositories

| Asset | Status | Purpose | Transfer evidence required |
| --- | --- | --- | --- |
| `IspBandit/Assist-Platform` | Included | Authoritative application source, migrations, tests, deployment tooling and docs | Repository transfer/copy procedure; branch/release inventory; IP assignment |
| `IspBandit/assist-ric` | Included supporting operational asset | Proprietary Regional Intelligence Collector used to discover, review, catalogue and submit approved data through the versioned Admin API; it does not connect directly to production MariaDB | Repository transfer/copy procedure; dependency/build instructions; IP assignment; buyer API credentials after transfer |

### Assist RIC sale disposition

Assist RIC is included with the Assist Platform sale package as supporting
internal operational tooling. This closes the earlier include/license/exclude
decision; it is not a fourth public brand and does not change the three-brand
runtime/acquisition boundary.

The basis for inclusion is the sibling repository's own current description and
licence: it is proprietary internal Assist Platform Enterprise tooling, the
Assist Platform remains the system of record, and approved synchronisation uses
`/api/v1/admin` rather than direct production database access.

Final legal transfer still requires the same source-code/IP assignment evidence
as the main repository. Inclusion here records the commercial/operational asset
decision; it does not by itself prove legal title or contributor assignment.

The sale candidate must not require an excluded repository, unpublished local
workstation directory or founder-only file to build or operate the three active
brands.

## Production infrastructure

| Asset | Status | Purpose | Transfer evidence required |
| --- | --- | --- | --- |
| BinaryLane Ubuntu 24.04 VPS | Included/transfer or replace | Production Docker host | Current plan/cost, account-transfer or clean-host migration method |
| Docker Compose application runtime | Included | PHP 8.3-FPM, MariaDB 11.4, Caddy production stack | Clean deployment proof from repository |
| Cloudflare DNS/proxy configuration | Included/transfer or recreate | Public DNS, proxy/TLS controls | Zone export, account-transfer/recreate checklist |
| Encrypted off-site backup destination | Shared/confirm | Independent production recovery copy | Provider/account, encryption ownership, retention, restore proof |
| External monitoring/alert destination | Shared/confirm | Uptime/error/task failure notification | Provider/account, alert recipients, transfer/reconfigure method |

## Email and communications

| Asset | Status | Purpose | Transfer evidence required |
| --- | --- | --- | --- |
| `support@vanassist.com.au` sending path | Included/transfer | VanAssist transactional/support email | Mailbox/sender provider, DNS records, transfer/recreate procedure |
| `support@towsmart.com.au` sending path | Included/transfer | TowSmart transactional/support email | Mailbox/sender provider, DNS records, transfer/recreate procedure |
| `support@trailerwise.com.au` sending path | Included/transfer | TrailerWise transactional/support email | Mailbox/sender provider, DNS records, transfer/recreate procedure |
| Transactional email configuration | Included/transfer | Queued application mail | Provider/subprocessor, cost, sender health and secret-rotation method |

## Data assets

| Asset | Status | Purpose | Transfer evidence required |
| --- | --- | --- | --- |
| Canonical provider database | Included subject to provenance | National service-provider discovery | Reproducible counts, source/provenance classes, licence/terms position |
| Provider brand/category assignments | Included subject to provenance | Brand-scoped service discovery | Reproducible counts and derivation/update process |
| VanAssist stay catalogue | Included subject to provenance | Caravan parks, camps and supported stay discovery | Source/provenance and verification-label rules |
| TowSmart tow-vehicle catalogue | Included subject to provenance | Towing calculator/reference data | Source register and update workflow |
| TowSmart caravan/camper/trailer catalogue | Included subject to provenance | Towing calculator/reference data | Source register and update workflow |
| Retired LocalTorque brand listings | Excluded as active asset | Historical/import lineage only | Confirm transferred canonical provider data is independently valid for VanAssist |
| Polaris catalogue/data | Excluded | Retired experiment | Confirm no active three-brand dependency |

## Application accounts and services

The following must be inventoried without storing secrets in Git:

- domain registrar(s);
- Cloudflare;
- BinaryLane;
- GitHub;
- email/transactional sender provider;
- analytics properties for all three brands;
- Google Routes and any other paid/free external API in production;
- backup/object-storage provider;
- uptime/error monitoring;
- payment/billing provider if enabled at completion;
- CAPTCHA/Turnstile or anti-abuse services;
- any social/business accounts explicitly included in the transaction.

For each account record in the transaction data room: current legal/account
owner, monthly/annual cost, renewal date, transfer method, MFA owner, required
secret regeneration and whether historical billing/customer data transfers.

## Intellectual property and licences

Before sale-ready sign-off attach or reference:

- source-code/IP ownership statement covering both `Assist-Platform` and
  `assist-ric`;
- contractor/contributor assignment position where applicable;
- open-source dependency licence export from lock files;
- third-party dataset/API terms register;
- logo/image/icon/marketing-asset ownership register;
- excluded-assets schedule for LocalTorque and Polaris.

## Transfer acceptance

This register is complete only when a buyer can:

1. obtain/control all three included domains;
2. obtain the authoritative Assist Platform and Assist RIC source and build each
   from a clean checkout using documented steps;
3. restore the production database/media into an isolated environment;
4. deploy the three-brand release to a buyer-controlled host;
5. configure mail, DNS, monitoring, backups and required APIs using buyer-owned secrets;
6. create and revoke administrators without founder assistance;
7. use Assist RIC with buyer-controlled Admin API credentials without direct
   production-database access; and
8. operate VanAssist, TowSmart and TrailerWise without any LocalTorque, Polaris or founder-workstation dependency.
