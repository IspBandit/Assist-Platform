# Administrator guide

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

## Provider discovery and verification

`php scripts/classify-brand-providers.php --dry-run` reports the canonical
provider scan. Running it without `--dry-run` creates relevant TowSmart and
TrailerWise brand listings and category assignments. Automated matches are
always unverified and retain discovery evidence; they must be claimed by the
business or reviewed by an administrator before a verified badge is granted.

## Provider email campaigns

Open **Growth → Email campaigns**. VanAssist prepares a separate draft for each
active service category that has at least one provider email. Each draft uses
relevant human copy and a compressed service-family header, and targets only
providers in that category. Review the copy and recipient summary before use.

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

## Sensitive changes

Do not enable billing, public indexing, broad data imports, destructive cleanup,
SMTP delivery or feature flags without a backup, review and a test plan. Never
upload identity/licence documents into a public media path.

## Account recovery

Password resets require working transactional email. Until that is verified, an
authorised operator may use a documented, audited server-side recovery procedure;
never insert plaintext passwords into the database. Rotate all temporary
credentials after recovery.
