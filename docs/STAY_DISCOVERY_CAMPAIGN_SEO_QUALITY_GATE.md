# Stay discovery, provider campaigns and SEO quality gate

Date: 2026-07-29

Backlog items: VAN-001, DATA-001, COM-002, INF-002.

## Architecture — PASS

- Paid Places rows use new forward-only, brand-scoped staging tables and expire
  after 30 days.
- Approval creates a private unverified `caravan_parks` draft; public visibility
  remains a separate existing control.
- VanAssist sitemap provider URLs are sourced from active brand listings and the
  current server brand ID.
- Search verification values use the existing settings service; no new external
  dependency or secret is committed.

Rollback: revert the application release. The additive review tables may remain
empty and are not read by public journeys. Remove optional webmaster tokens in
Admin SEO if required.

## UX — PASS

- Stay review is linked from the VanAssist navigation and places-to-stay screen,
  with upload, progress, filtering, duplicate, authority-warning, empty and
  review states.
- Provider campaigns are named explicitly in navigation and separate factual
  accuracy from promotional marketing.
- Existing responsive review-card, button, form, focus and admin-shell patterns
  are reused; no horizontal-only workflow is introduced.

## Engineering — PASS subject to recorded release checks

- PHP syntax, Composer validation, static analysis, PHPUnit, migration and
  changed-file checks must pass on the exact pull-request head.
- Candidate field lengths, evidence URL rules, batch bounds, private staging,
  expiry and audit events are enforced server-side.
- Production verification must check `/readyz`, `/robots.txt`, `/sitemap.xml`, a
  public VanAssist page canonical tag, the admin campaign route and the stay
  review route.

## Business — PASS

- The paid discovery output now has an operator path to reviewed listings without
  presenting paid source data as independently verified truth.
- Fixed factual notices and consent-gated marketing remain distinct campaign
  types and both are visible per service category.
- Google and Bing discovery prerequisites are provided without promising ranking,
  indexing time or traffic.

Overall gate: **Pass when the exact-head checks and production smoke checks are
recorded.** No candidate is automatically public and no campaign is automatically
queued by this release.
