# Project Polaris

Polaris is Assist Platform Enterprise’s **new RV decision platform** for
Australian buyers researching and comparing new caravans, hybrids, camper
trailers, motorhomes, campervans, slide-ons and related recreational vehicles.

It is **not** a used-vehicle marketplace, classifieds product or dealer stock
inventory system.

## Status legend

| Label | Meaning |
| --- | --- |
| **Existing** | Shared platform capability already in production use |
| **Scaffolded** | Code/routes/schema present; incomplete product behaviour |
| **Partially implemented** | Usable vertical slice with known gaps |
| **Planned** | Specified; not built |
| **Blocked** | Waiting on dependency, legal or owner decision |

## Current milestone

Honest status vs the master prompt: see [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md).
**Master prompt is not complete.** Public launch remains blocked (`RELEASE_CRITERIA.md`).

| Item | Status |
| --- | --- |
| Repository audit | Existing (this folder) |
| Documentation suite | Existing |
| Brand registration (`polaris`) | Partially implemented / private |
| Homepage + hero | Partially implemented |
| Browse / model / manufacturer pages | Partially implemented |
| Guided Find My RV | Partially implemented (progressive stages + MatchScorer) |
| Tow Match + Compare + share links | Partially implemented |
| Account preferences | Scaffolded / partially implemented |
| Admin Polaris nav + imports/review + inventory | Partially implemented |
| Catalogue schema (`087`/`088`/`095`) | Scaffolded |
| Manufacturer claim portal + section shells | Partially implemented |
| VanAssist related services | Partially implemented |
| Saved shortlist + analytics events | Scaffolded |
| Production domain | Blocked (owner DNS) |

## Documents

| Document | Purpose |
| --- | --- |
| [POLARIS_REPOSITORY_AUDIT.md](POLARIS_REPOSITORY_AUDIT.md) | Capability gap analysis |
| [PRODUCT_VISION.md](PRODUCT_VISION.md) | Product purpose and principles |
| [PRODUCT_REQUIREMENTS.md](PRODUCT_REQUIREMENTS.md) | Requirements by user type |
| [PRODUCT_BOUNDARIES.md](PRODUCT_BOUNDARIES.md) | What Polaris is and is not |
| [INFORMATION_ARCHITECTURE.md](INFORMATION_ARCHITECTURE.md) | Navigation and routes |
| [USER_JOURNEYS.md](USER_JOURNEYS.md) | Primary journeys |
| [UX_SPECIFICATION.md](UX_SPECIFICATION.md) | Screen-level UX |
| [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) | Brand extension of platform DS |
| [DATA_ARCHITECTURE.md](DATA_ARCHITECTURE.md) | Domain model |
| [DATA_ACQUISITION.md](DATA_ACQUISITION.md) | Import / provenance pipeline |
| [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md) | AI use and non-use |
| [RECOMMENDATION_ENGINE.md](RECOMMENDATION_ENGINE.md) | Hybrid scoring |
| [SEARCH_ARCHITECTURE.md](SEARCH_ARCHITECTURE.md) | Structured + optional NL |
| [TOWSMART_INTEGRATION.md](TOWSMART_INTEGRATION.md) | Tow compatibility boundary |
| [VANASSIST_INTEGRATION.md](VANASSIST_INTEGRATION.md) | Provider surfacing |
| [MANUFACTURER_PORTAL.md](MANUFACTURER_PORTAL.md) | Manufacturer UX |
| [DEALER_PORTAL.md](DEALER_PORTAL.md) | Dealer UX (focused) |
| [ADMINISTRATION.md](ADMINISTRATION.md) | Shared admin sections |
| [SECURITY_AND_PRIVACY.md](SECURITY_AND_PRIVACY.md) | Security controls |
| [ANALYTICS_AND_METRICS.md](ANALYTICS_AND_METRICS.md) | Event catalogue |
| [SEO_STRATEGY.md](SEO_STRATEGY.md) | Discoverability |
| [TESTING_STRATEGY.md](TESTING_STRATEGY.md) | Test plan |
| [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md) | Phases 0–9 |
| [RELEASE_CRITERIA.md](RELEASE_CRITERIA.md) | Definition of done |
| [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) | Honest master-prompt status |
| [ACCESSIBILITY_QA.md](ACCESSIBILITY_QA.md) | Accessibility evidence (CONDITIONAL) |
| [DECISIONS/](DECISIONS/) | Polaris ADRs |

## Backlog

Owning workstream: **Brands**. Primary IDs: `POL-001` … `POL-009` (see
`docs/PRODUCT_BACKLOG.md`). Depends on TowSmart (`TOW-001`), VanAssist providers,
shared lifecycle (`OPS-011`), and platform design system (`EXP-001`).

## Local development

1. Register brand via migration `087` (after migrate).
2. Resolve brand with `ASSIST_BRAND=polaris` or local host `polaris.test`
   (and `ASSIST_ALLOW_BRAND_QUERY` in non-prod if needed).
3. Status remains **`private`** until a production domain is confirmed.
