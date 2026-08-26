# TowSmart and TrailerWise platform parity audit

Date: 26 August 2026

Owning backlog items: `EXP-005`, `TRL-001`

Dependencies: `CORE-003`, `DATA-004`, `INF-002`, `OPS-004`

## Evidence boundary

This audit compares the current `main` implementation with the last verified
production state in `PRODUCTION_CURRENT_STATE.md`. GitHub is the code source of
truth. A route or test in Git is implemented, not automatically deployed or
accepted. Production claims are limited to checks recorded on 24 August 2026.

## Current parity

| Area | TowSmart | TrailerWise | Shared-platform conclusion |
| --- | --- | --- | --- |
| Brand resolution and deployment | Live domain, canonical host, health, robots, sitemap and brand assets previously verified | Same | The shared `Brand` registry, application and release process are correct |
| Public shell and navigation | Branded responsive shell, calculator, guide, checklist, rules, directory and saved combinations | Branded responsive shell, directory, categories, rules and secondary marketplace | Shared header/footer, install metadata and design tokens are implemented |
| Provider discovery | Curated towing categories and brand-scoped listings | Curated trailer categories and brand-scoped listings | Provider pages, contact attribution, location lookup and demand analytics are shared and server-scoped |
| Provider onboarding and claims | Shared search-before-create, invitation, claim, portal and admin review routes | Same | Canonical provider ownership is preserved; no duplicate brand claim system is required |
| Administration | Unified admin, permissions, brand switching, campaigns, analytics, data sources and audit foundations | Same | Production acceptance remains part of the platform gate |
| Analytics | Brand-scoped directory filters, impressions, profiles and contacts | Same | Production collection and dashboard acceptance remain under `DATA-004` |
| SEO and bots | Canonical metadata, robots and sitemap exist | Same | This increment adds omitted provider-profile and trust/legal URLs |

## Defects closed in this increment

1. `/find` on TowSmart and TrailerWise used the VanAssist result template and
   could expose VanAssist copy, stays, fuel, service-run and assistance actions.
   It now delegates to the shared brand-scoped provider directory and accepts
   public category keys.
2. Product-brand sitemaps omitted provider profiles and trust/legal pages. Both
   now use one shared provider-listing query and trust-page list.
3. Brand footer configuration referenced `/privacy` and `/terms`, while the
   implemented CMS routes are `/privacy-policy` and `/terms-of-use`.

## Remaining gaps and release conditions

| Area | Gap | Required evidence or next action |
| --- | --- | --- |
| Ask / natural-language search | `Ask VanAssist` is intentionally VanAssist-only. TowSmart needs towing-calculation/safety intent design; TrailerWise needs brand-category and ownership-content intent coverage. | Add brand prompt matrices, provenance, zero-result and outcome tests under `CORE-012`; do not rename VanAssist copy and call it parity |
| TowSmart calculations and data | Catalogue provenance is incomplete and formulas still require domain-expert review. Saved combinations lack edit/delete/compare/report. | Complete `TOW-001` and `TOW-002`; retain guidance/not-certification wording |
| TrailerWise product depth | Representative manufacturer, dealer, repair, parts and certifier journeys need acceptance. Sales/hire remains secondary and partial. | Complete `TRL-001`; retain `TRL-002` shared Garage and regulatory boundaries |
| Location and search UX | Directory location search, GPS town resolution and list fallback exist. Corrected `/find` lacks current two-brand rendered evidence. | Test 360 px, tablet, desktop, denied GPS, unknown location, empty results, map failure and keyboard-only use under `EXP-005`/`EXP-004` |
| Accessibility | Shared semantics and focus styles exist, but current WCAG 2.2 AA sign-off is absent for all two-brand critical journeys. | Automated checks plus manual keyboard and screen-reader evidence under `EXP-004` |
| Provider and claim operations | Shared claim/admin workflows exist. Production end-to-end claim acceptance and evidence-backed verification remain incomplete. | Complete `CORE-003` production acceptance and audit evidence |
| Analytics | Events are brand scoped, but production collection, zero-result review and baselines are not signed off. | `DATA-004` dashboard evidence and privacy review |
| SEO / bot readiness | Sitemap coverage is corrected in code. Search Console, structured-data and crawl evidence are not recorded for this head. | Deploy via `INF-002`, validate canonical/robots/sitemap/schema on every host, then record crawler evidence |
| Configuration and deployment | Domains/mailboxes were previously verified. This change adds no migration, secret, integration or environment variable. | Normal immutable release, all-host smoke checks and rollback availability |
| Commercial readiness | Billing remains disabled and full commercial launch approval is absent. | Keep charging off until `COM-004` and the four-part quality gate pass |

## Quality-gate assessment

- Architecture: **PASS for review** — existing `Brand`, canonical provider
  listings and shared controllers; no schema or duplicate service.
- UX: **CONDITIONAL** — wrong-brand paths are removed; rendered mobile and
  accessibility acceptance is still required before release.
- Engineering: **pending checks** — record Composer validation, static analysis,
  PHPUnit and production dependency build in the pull request.
- Business: **CONDITIONAL** — brand purposes and commercial boundaries are
  preserved; owner acceptance and production analytics remain outstanding.
- Overall: **CONDITIONAL** — suitable for review, not evidence of full commercial
  launch readiness.

## Deployment and rollback

There are no migrations or environment changes. Deploy through the immutable
release workflow. Verify `/find`, `/providers`, `/sitemap.xml`, legal pages and
a provider profile on all public hosts. Roll back by restoring the prior release
symlink according to `OPERATIONS_RUNBOOK.md`.
