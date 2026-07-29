# ADR 0016: Server-owned provider import and canonical campaign taxonomy

- Status: accepted
- Date: 2026-07-30
- Backlog: DATA-003, COM-002
- Brands: VanAssist initially; shared implementation

## Context

National discovery screening and eligible publication were advanced by repeated browser submissions. Closing the dashboard stopped the work. Provider campaigns also selected recipients through the legacy `service_categories` relationship while current public listings and imports assign the canonical `brand_provider_categories` taxonomy. This could leave a valid active listing with an email invisible to its service campaign.

Google Places content cannot be converted into a permanent independent directory merely because the API request was paid for. Place IDs may be retained, while other Places content remains subject to Google's storage and attribution restrictions. Publication therefore still requires independent evidence and retention rights; this decision does not weaken that boundary.

## Decision

1. A locked, resumable server worker screens staged discovery files, links safe 70%+ duplicates, publishes only evidence-confirmed unclaimed listings and refreshes campaign drafts.
2. Production schedules that worker independently of browser sessions and exposes aggregate progress in Import review.
3. One factual and one marketing draft is prepared for every active VanAssist brand category, including zero-recipient categories so gaps are visible.
4. Provider-category campaigns and their recipient controls use `provider_brand_category_assignments`. Legacy `category_id` remains for customer/general audiences and older campaigns.
5. Factual notices remain fixed, source-backed and separately suppressible. Marketing remains consent-gated and cannot auto-send without the existing staged reviews.

## Consequences

- Closing the admin page no longer stops safe import work.
- Pending records are split into immediately processable versus records requiring evidence or a manual decision.
- Campaign counts follow the same category assignments as public search.
- Invalid addresses are held rather than counted as sendable.
- Claimed providers and externally suppressed addresses remain protected.

## Rollback

Disable the `process_provider_import_queue` cron entry and deploy the previous application release. Migration 083 is additive; its column and task row may remain unused. Do not reverse already recorded duplicate links, publications, exclusions or deliveries automatically.
