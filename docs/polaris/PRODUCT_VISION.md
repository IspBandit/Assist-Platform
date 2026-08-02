# Polaris — Product Vision

- **Status:** Partially implemented (Phase 0–1 foundation)
- **Date:** 2026-08-01
- **Backlog:** POL-001, POL-002
- **Brand:** `polaris` (private until production domain confirmed)

## Purpose

Polaris helps Australian buyers research **new** recreational vehicles — caravans,
hybrids, camper trailers, motorhomes, campervans and slide-ons — with clear
specifications, honest provenance and guided matching against their towing and
travel needs.

Polaris is a **decision platform**, not a classifieds marketplace. It does not
list used stock, dealer inventory or private sales.

## Problem

Buyers face fragmented manufacturer websites, inconsistent specification labels,
unclear tow compatibility and marketing copy that obscures practical limits.
Existing Assist Platform brands solve adjacent problems:

| Need | Authoritative brand |
| --- | --- |
| Tow vehicle ratings and calculator | TowSmart |
| Repairers, warranty agents, RV services | VanAssist |
| Trailer service businesses | TrailerWise |

No shared product today offers a **canonical new-RV catalogue** with model-year
versioning, governed specifications and decision UX.

## Vision statement

Polaris becomes the trusted starting point for researching a new RV in Australia:
browse by category and manufacturer, compare variants honestly, understand tow
compatibility via TowSmart, and discover relevant VanAssist providers — without
Polaris inventing tow ratings or duplicating service directories.

## Product principles

1. **Catalogue truth is relational, not generative.** Specifications, prices and
   dimensions come from governed sources with provenance — not from LLM invention.
2. **AI interprets; it does not authorise.** Natural-language search and import
   assistance follow platform ADRs 0018–0027 and Polaris ADR 0008.
3. **Reuse platform boundaries.** Tow vehicles and calculators remain TowSmart;
   service providers remain VanAssist.
4. **Uncertainty is visible.** Missing specs, stale prices and low-confidence
   imports reduce match scores and display warnings — never silent defaults.
5. **Claim-first manufacturer participation.** Manufacturers and dealers join
   through verified organisation claims, reusing platform onboarding patterns.
6. **Private until ready.** The brand stays `private` until DNS, catalogue depth
   and Quality Gate evidence satisfy release criteria.

## Success measures (Phase 9 targets)

| Metric | Intent |
| --- | --- |
| Model detail completeness | ≥80% of published models show core tow-relevant specs with source |
| Find journey completion | Users complete guided Find without dead ends |
| Tow-match clarity | Compatibility results link to TowSmart with confidence labels |
| Manufacturer adoption | Verified manufacturers maintain at least current model year |
| Organic discovery | Indexed model pages with valid structured data |

## Non-goals

- Used RV marketplace or dealer stock feeds
- Tow vehicle catalogue (TowSmart owns this)
- Provider directory duplication (VanAssist owns this)
- Legal certification or “you can tow this” guarantees
- Paid AI features at launch
- Standalone application or separate auth stack

## Implementation status

| Area | Status |
| --- | --- |
| Repository audit | Existing |
| Documentation suite | Partially implemented (this folder) |
| Brand registration | Scaffolded |
| Public homepage / hero | Partially implemented |
| Browse, model, manufacturer pages | Scaffolded |
| Guided Find shell | Scaffolded |
| Matching engine | Planned (Phase 3) |
| TowSmart compatibility service | Planned (Phase 4) |
| Manufacturer portal | Planned (Phase 7) |
| Production domain | Blocked (owner decision) |

## Related documents

- [PRODUCT_BOUNDARIES.md](PRODUCT_BOUNDARIES.md)
- [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md)
- [DECISIONS/0001-polaris-tenant-integration.md](DECISIONS/0001-polaris-tenant-integration.md)
