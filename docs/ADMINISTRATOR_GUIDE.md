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

## Sensitive changes

Do not enable billing, public indexing, broad data imports, destructive cleanup,
SMTP delivery or feature flags without a backup, review and a test plan. Never
upload identity/licence documents into a public media path.

## Account recovery

Password resets require working transactional email. Until that is verified, an
authorised operator may use a documented, audited server-side recovery procedure;
never insert plaintext passwords into the database. Rotate all temporary
credentials after recovery.

