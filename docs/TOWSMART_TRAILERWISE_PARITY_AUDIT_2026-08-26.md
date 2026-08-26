# TowSmart and TrailerWise platform parity audit

Date: 26 August 2026

Owning backlog items: `EXP-005`, `TRL-001`

Dependencies: `CORE-003`, `DATA-004`, `INF-002`, `OPS-004`

## Evidence boundary

This audit compares the current `main` implementation with the last verified
production state in `PRODUCTION_CURRENT_STATE.md`. GitHub is the code source of
truth. A route or test in Git is implemented, not automatically deployed or
accepted. Production claims are limited to checks recorded on 24 August 2026.

## Live rendered audit — 26 August 2026

The public production sites were inspected without authentication at their
canonical hosts. This proves rendered public behaviour only; it is not
acceptance of signed-in, provider or administrator journeys.

### TowSmart

- Homepage, calculator, provider directory, specialist categories and rules
  rendered with the correct shell, canonical URL and indexable robots policy.
- The directory returned 18 cards with business/service, town/postcode, curated
  category and device-location controls.
- The calculator exposes the expected vehicle, trailer, loading, tank and
  accessory inputs. Completion, error recovery and assistive-technology
  behaviour still need current production evidence.
- The homepage links clearly to the calculator and directory but contains no
  direct service/location search or Ask form.
- No JSON-LD block was present on the audited homepage, directory, categories,
  calculator or rules pages. VanAssist's homepage exposes two blocks, making
  shared Brand-driven Organisation/WebSite schema a confirmed SEO gap.

### TrailerWise

- Homepage, directory, categories, marketplace and rules rendered with the
  correct shell, canonical URL and indexable robots policy.
- The directory returned 18 cards with the same shared location and category
  controls, correctly scoped to TrailerWise.
- The homepage has no direct location/service search or Ask entry point.
- The marketplace default view showed no listings. Because TrailerWise is
  service-first this is not a launch blocker, but the empty secondary module
  should not gain more navigation weight until inventory and owner acceptance
  justify it.
- No JSON-LD block was present on the audited homepage, directory, categories,
  marketplace or rules pages.

### VanAssist comparison and evidence limit

VanAssist starts with Ask and structured search on its homepage and publishes
Organisation/WebSite JSON-LD. TowSmart and TrailerWise should reuse the shared
directory/location platform while keeping brand-specific intent; VanAssist
stays and assistance must not be copied. The audited pages reported no desktop
horizontal overflow and exposed main, navigation and footer landmarks. A
subsequent 360 px browser pass covered both homepages and directories plus the
TowSmart calculator and TrailerWise marketplace. Every audited page had one H1
and main landmark, labelled form controls, no horizontal overflow and no
visible interactive target below 24 px. Both mobile menus opened and exposed a
visible primary navigation with the shared three-pixel focus treatment. This is
useful rendered evidence, but manual screen-reader, complete keyboard traversal,
denied-location and injected error-state acceptance remain release conditions.

## Current parity

| Area | TowSmart | TrailerWise | Shared-platform conclusion |
| --- | --- | --- | --- |
| Brand resolution and deployment | Live domain, canonical host, health, robots, sitemap and brand assets previously verified | Same | The shared `Brand` registry, application and release process are correct |
| Public shell and navigation | Branded responsive shell, calculator, guide, checklist, rules, directory and saved combinations | Branded responsive shell, directory, categories, rules and secondary marketplace | Shared header/footer, install metadata and design tokens are implemented |
| Provider discovery | Curated towing categories and brand-scoped listings | Curated trailer categories and brand-scoped listings | Provider pages, contact attribution, location lookup and demand analytics are shared and server-scoped. The shared directory now filters mobile/workshop delivery and ranks verified category matches before featured placement. |
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
4. Provider discovery could not express whether the customer needed a mobile
   service or could attend a workshop, and paid placement preceded stronger
   category evidence. The shared directory now supports that intent, exposes a
   narrow empty-result fallback and labels only direct or verified matches.

## Remaining gaps and release conditions

| Area | Gap | Required evidence or next action |
| --- | --- | --- |
| Ask / natural-language search | Product-brand deterministic intent matrices, provenance, clarification and outcome routes are implemented behind the existing Assist-search flag. | Render and accept both brands after normal deployment; paid AI remains separately gated |
| TowSmart calculations and data | Catalogue provenance and formulas still require domain-expert review. Saved combinations now support private detail/delete, edit/recalculate, comparison and print/PDF reporting. | Complete `TOW-001` domain review; retain guidance/not-certification wording |
| TrailerWise product depth | Direct manufacturer/dealer, repair, mobile, parts, certifier and engineering journeys are implemented. Sales/hire remains secondary and partial. | Accept representative live providers and journeys under `TRL-001`; retain `TRL-002` shared Garage and regulatory boundaries |
| Location and search UX | Directory location search, GPS town resolution and list fallback exist. Corrected `/find` lacks current two-brand rendered evidence. | Test 360 px, tablet, desktop, denied GPS, unknown location, empty results, map failure and keyboard-only use under `EXP-005`/`EXP-004` |
| Accessibility | The 360 px rendered baseline passes the checks described above, but current WCAG 2.2 AA sign-off is absent for all critical journeys. | Complete manual keyboard and screen-reader evidence under `EXP-004` |
| Provider and claim operations | Shared claim/admin workflows now bind claim tokens and Admin API invite review to the selected brand. Production end-to-end acceptance and evidence-backed verification remain incomplete. | Complete `CORE-003` production acceptance and audit evidence |
| Analytics | Events are brand scoped and now include mobile/workshop directory intent, but production collection, zero-result review and baselines are not signed off. | `DATA-004` dashboard evidence and privacy review |
| SEO / bot readiness | Sitemap coverage is corrected in code. Search Console, structured-data and crawl evidence are not recorded for this head. | Deploy via `INF-002`, validate canonical/robots/sitemap/schema on every host, then record crawler evidence |
| Configuration and deployment | Domains/mailboxes were previously verified. This change adds no migration, secret, integration or environment variable. | Normal immutable release, all-host smoke checks and rollback availability |
| Commercial readiness | Billing remains disabled and full commercial launch approval is absent. | Keep charging off until `COM-004` and the four-part quality gate pass |

## Required delivery sequence

1. **P0 production truth and safety:** deploy the merged brand-scoped `/find`
   and sitemap corrections normally; verify every host, claim route, sender,
   scheduled backup and rollback evidence.
2. **P0 rendered acceptance:** test 360 px, tablet and desktop home, directory,
   category/profile, TowSmart calculator/result, TrailerWise rules/marketplace,
   provider registration, claim and sign-in. Include keyboard, screen-reader,
   denied-GPS, empty and error states.
3. **P1 discovery entry:** add a compact brand-specific service/location finder
   to each homepage using the existing directory and location endpoints.
4. **P1 structured data:** add Brand-driven Organisation/WebSite schema and
   validated breadcrumb, profile and collection schema where supported.
5. **P1 provider operations:** accept search-before-create, claim, proof review,
   ownership transfer, portal editing and admin moderation in both brand scopes.
6. **P1 TowSmart depth:** finish `TOW-001`, then detail/delete, edit, compare and
   report increments under `TOW-002` without stronger unreviewed safety claims.
7. **P1 TrailerWise depth:** accept representative repair, mobile, parts,
   inspector, certifier, manufacturer and dealer data/journeys. Keep the empty
   marketplace secondary until it is useful.
8. **P2 brand Ask:** design brand intent matrices and deterministic knowledge
   routing before enabling the shared orchestrator.
9. **P2 measurement and launch:** baseline search success, zero results,
   contact conversion, claims, calculator completion and marketplace utility;
   obtain all four Quality Gate approvals before claiming commercial parity.

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
