# Administrator guide

## Active brands and provider imports

The active registry and administration navigation contain VanAssist, TowSmart
and TrailerWise only. Polaris catalogue administration and manufacturer/dealer
portals have been removed. Historical records remain for audit and upgrades;
do not recreate retired brand entries or enable old permissions as products.

LocalTorque is retired and no longer appears in the brand switcher, social
studio, outreach controls or production checks. Its former provider corpus is
managed as the **VanAssist provider pack**. Use the
`import_vanassist_provider_pack` maintenance task; the old LocalTorque task and
progress settings are removed by migration 136.

The same platform administrator identity can administer all three brands. Browser
sessions are scoped per domain, so signing in separately on each domain may be
required. Credentials are never documented in Git.

## Access

Use `/login`, then `/admin` on the applicable brand domain. Use a named human
account for normal work. Reserve emergency accounts for recovery only. Enable MFA
when implemented and use a password manager.

## Main responsibilities

- Providers: review, approve, verify, merge duplicates, maintain brand listings,
  services and coverage.
- Users and roles: grant least privilege and suspend compromised accounts.
- Content/SEO: maintain brand-scoped pages, blocks, FAQs, metadata and index state.
- Requests/runs: monitor assistance demand, matching and service-run workflows.
- Trailer listings: current secondary TrailerWise capability; do not use it to
  redefine TrailerWise as a classifieds product.
- Email: configure shared transport securely and verify each queued message uses
  the sender identity belonging to its recorded `brand_id`.
- Operations: review logs, queues, cron, health, backups and release version.

## Social Studio

Open **Content → Social studio** on the domain for the brand you want to manage.
Choose a campaign purpose and an Instagram or Facebook format. The platform
creates brand-correct premium artwork and post copy as a private draft. Review
the preview, approve it, then download the full-resolution PNG. Available
formats include Instagram post, story and profile graphics plus Facebook post,
cover and profile graphics. Campaign purposes include launch, provider
recruitment, service discovery, education/safety and community engagement.

## Government datasets and traveller facilities

Open **Data sources → Government datasets** for the **DATA-011A National Dataset
Catalogue** (extends DATA-012). Catalogue rows include jurisdiction, source/API
URLs, licence, attribution, format, update frequency, trust, auto-update (RIC
schedule only), duplicate rules, entity types and notes. National portals and
themes are seeded disabled. Enable a concrete importable row and **Fetch**
(row-capped) or **Import fixture**, then approve candidates under **Facility
review**. Approval writes `traveller_facilities` only — never `caravan_parks`.
Caravan parks/campgrounds remain stays. No dataset publishes directly to
production; RIC stages and Admin API is the write path. See `docs/DATA_011A.md`.
Use Assist AI Search to confirm the release gate before turning on
`assist_ai_traveller_facilities`.

## Provider discovery and verification

`php scripts/classify-brand-providers.php --dry-run` reports the canonical
provider scan. Running it without `--dry-run` creates relevant TowSmart and
TrailerWise brand listings and category assignments. Automated matches are
always unverified and retain discovery evidence; they must be claimed by the
business or reviewed by an administrator before a verified badge is granted.

### Claim-first public onboarding (VAN-010)

When `CLAIM_FIRST_ONBOARDING=true` (default), `/for-providers/register` on
VanAssist runs search-before-create:

1. The business enters name and town, then sees “Is this your business?” matches.
2. They may claim an existing unclaimed listing or confirm **none of these** before
   the full registration form appears.
3. On submit, a second duplicate check runs. Likely duplicates create a **pending
   hold** provider (not published) with an internal note and optional
   `listing_corrections` row; clean submissions continue into Provider prospects.

Disable with `CLAIM_FIRST_ONBOARDING=false` in `.env` if the flow must be rolled
back without redeploying code.

### Recycle bin and API service accounts

- **Directory → Recycle bin** lists soft-deleted providers and stays (and facilities
  when migrated). Restore actions reuse the Admin API recycle-bin service.
- **Administration → API service accounts** manages machine credentials for
  `/api/v1/admin` (create, rotate secret, disable). Requires administrator or
  super-administrator role; secrets are shown once at creation or rotation.

## Provider email campaigns

Open **Growth → Growth & outreach**, then choose **Email campaigns**. VanAssist prepares two clearly
separated drafts for each active service category that has at least one provider
email: a fixed factual listing-accuracy notice and a consent-gated marketing
campaign. Each remains a draft until an administrator reviews its audience and
uses the staged delivery controls. Marketing drafts use relevant human copy and
a compressed service-family header. Review the copy and recipient summary before
use.

The summary deliberately separates all active providers with email from those
with documented marketing consent. Addresses held for review cannot be queued.

Choose the campaign type before selecting recipients:

- **Provider marketing** is promotional and requires recorded consent evidence.
- **Factual listing accuracy** uses locked wording for an unclaimed, sourced
  public record. It asks for CONFIRM, CORRECT or REMOVE and cannot contain a
  promotional offer or commercial link.

Never use the factual notice as a marketing workaround. Remove questionable
recipients from the review pool and keep source evidence with the provider.
For every campaign, send an internal test, inspect it in the mailbox, run the
25-provider pilot and review bounces, replies, complaints and opt-outs before
using the 50/day and 100/day stages. Do not treat a public business email as
consent and do not edit database consent fields merely to increase reach.

## PR and organisation outreach

Open **Growth → Growth & outreach** to manage caravan/RV clubs, federations,
industry bodies, manufacturers, dealer and rental networks, park groups,
tourism organisations, 4WD/touring associations and publications. Research is
imported with an official source URL, date checked, published role, publication
context and relevance reason. Importing an address never makes it sendable.
The initial official-source research set is loaded idempotently by the release
migration command, so deployment does not leave contacts in a local file waiting
for an operator upload.

Review the official page and destination restrictions before marking a contact
eligible. Personal or ambiguous addresses, stale sources and any no-unsolicited
warning remain held. Prefer one peak-body approach before writing to every
affiliated club. Never request or upload member, customer or subscriber lists.

Organisation campaigns select one target type and matching copy: club member
resource, industry/data collaboration, fleet/dealer owner support, tourism
visitor resource or earned editorial pitch. Use the normal internal test,
maximum-25 pilot and reviewed daily stages. There is no automatic continuation.
The platform retains the organisation contact and evidence used for each queued
recipient and honours marketing/all suppression. Record replies, interest,
sharing, declines, bounces and opt-outs in the hub; an opted-out outcome adds
marketing suppression and cancels applicable pending mail.

The hub distinguishes an address accepted into the application queue from a message accepted by the configured outbound mail transport. Its append-only history records queue, sent, failure, suppression and manual response/outcome events. A campaign marked complete means the current reviewed audience was queued; it is not a delivery or mailbox-read claim.

## Paid caravan-stay discovery review

Open **Customer operations → Stay discovery review** in the VanAssist workspace
and upload the generated JSONL discovery pack. The pack is screened in bounded
batches into a private queue. It never creates public stays automatically.
Before creating a private draft, open and retain a current independent operator
or authority page. Free camps, rest areas, showgrounds, council camps and
national parks require an Australian government or council URL because lawful
overnight access, permits and restrictions can change. Review the resulting
draft in **Places to stay** before separately enabling its public page.

## Search discovery

Open **Content → SEO**. Keep indexing enabled for the public VanAssist launch,
maintain an accurate default description and social image, and paste the token
values supplied by Google Search Console and Bing Webmaster Tools. After each is
verified, submit the displayed sitemap URL in both webmaster consoles. The
platform emits self-canonical URLs, brand-aware public provider URLs, robots
directives, Open Graph/Twitter metadata and structured data; search engines
still control crawl timing and rankings.

## Sensitive changes

Do not enable billing, public indexing, broad data imports, destructive cleanup,
SMTP delivery or feature flags without a backup, review and a test plan. Never
upload identity/licence documents into a public media path.

## Account recovery

Password resets require working transactional email. Until that is verified, an
authorised operator may use a documented, audited server-side recovery procedure;
never insert plaintext passwords into the database. Rotate all temporary
credentials after recovery.
