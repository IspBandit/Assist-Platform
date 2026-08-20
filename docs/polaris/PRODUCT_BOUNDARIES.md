# Polaris — Product Boundaries

- **Status:** Existing (policy); enforcement Partially implemented
- **Date:** 2026-08-01
- **Backlog:** POL-001
- **ADR:** [0002-rv-domain-boundaries.md](DECISIONS/0002-rv-domain-boundaries.md)

## What Polaris is

| In scope | Description |
| --- | --- |
| New RV catalogue | Manufacturers, model families, model years, variants |
| Governed specifications | Hybrid relational spec definitions and values |
| Floorplans and media | Manufacturer-supplied or reviewed imports |
| Price guidance | RRP/indicative with source and as-at date |
| Decision UX | Find, compare, tow-match presentation |
| Provenance display | Source, confidence, review state on claims |
| Manufacturer/dealer portals | Claim-first participation (later phases) |

## What Polaris is not

| Out of scope | Authoritative owner |
| --- | --- |
| Used RV marketplace | Not planned — TrailerWise secondary listings stay separate |
| Dealer stock inventory | Not planned |
| Private seller classifieds | Not planned |
| Tow vehicle catalogue | **TowSmart** |
| GVM/GCM/tow bar ratings as source data | **TowSmart** (+ user Garage) |
| Towing legal advice or certification | **TowSmart** (guidance-only) |
| Repairers, mobile mechanics, warranty agents | **VanAssist** |
| Caravan parks and stays | **VanAssist** |
| Trailer service business directory | **TrailerWise** |
| Shared authentication, RBAC, admin shell | **Platform** |
| Billing at launch | Disabled platform-wide |

## Data authority matrix

```
┌─────────────────────┬──────────────────┬─────────────────────────┐
│ Domain              │ Write authority  │ Read in Polaris         │
├─────────────────────┼──────────────────┼─────────────────────────┤
│ RV model/specs      │ Polaris (+ MFG)  │ Polaris (native)        │
│ Tow vehicles        │ TowSmart         │ Link / API (Phase 4)    │
│ Tow calculator      │ TowSmart         │ Embed / deep link       │
│ Service providers   │ VanAssist        │ Surface cards (Phase 8) │
│ User accounts       │ Platform         │ Shared session          │
│ Media uploads       │ Platform policy  │ Per-model attachments   │
└─────────────────────┴──────────────────┴─────────────────────────┘
```

## Brand and tenancy boundary

- Polaris is brand ID `polaris` in `config/brands.php` and the `brands` table.
- Host resolution uses the typed `Brand` registry — never query-parameter tenant
  switching in production.
- Status **`private`** until owner confirms production domain (currently
  **Blocked**).
- Local dev: `ASSIST_BRAND=polaris` or `polaris.test` with non-prod query flag
  if required.

## AI boundary

AI may:

- Interpret natural-language Find/search intent
- Suggest taxonomy mappings on import drafts
- Flag duplicate manufacturer names
- Summarise conflicts for administrators

AI may not:

- Invent specifications, prices, tow ratings or provider details
- Publish catalogue rows directly
- Override verified manufacturer-supplied fields
- Present generative text as factual specification

See [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md) and platform ADRs 0021–030.

## Integration boundary

| Integration | Pattern | Status |
| --- | --- | --- |
| TowSmart | Read-only compatibility service; deep links to calculator | Planned |
| VanAssist | Read-only provider cards filtered by category/region | Planned |
| Shared Garage | User tow vehicle reference for tow-match | Planned |
| Assist AI Orchestrator | Optional NL layer; flag-gated | Scaffolded (platform) |
| Admin API / RIC | Future sync; not required for Phase 1 | Planned |

## Content and legal language

Public copy must:

- Describe tow compatibility as **guidance** referencing TowSmart assumptions
- Label indicative pricing with source and date
- Distinguish manufacturer claims from platform-verified imports
- Avoid “approved”, “certified” or “guaranteed to tow” unless sourced from
  a named authority

See ADR [0013-public-verification-language.md](DECISIONS/0013-public-verification-language.md).

## Phase enforcement

| Phase | Boundary focus |
| --- | --- |
| 0–1 | No public DNS; seed data clearly non-production; no AI publish |
| 2–3 | Provenance on spec display; matching penalises missing data |
| 4 | TowSmart API only — no copied tow tables |
| 6 | Imports draft-first; admin review before publish |
| 7 | Manufacturer edits cannot bypass audit |
| 9 | SEO indexing only after release criteria |

## Related documents

- [TOWSMART_INTEGRATION.md](TOWSMART_INTEGRATION.md)
- [VANASSIST_INTEGRATION.md](VANASSIST_INTEGRATION.md)
- [DATA_ARCHITECTURE.md](DATA_ARCHITECTURE.md)
