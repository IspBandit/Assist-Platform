# AI search engine

**Status:** design (Phase AI-0).  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md).

## Adapters

| Adapter | Backing | Phase |
| --- | --- | --- |
| Provider | `Provider`, `ProviderCoverage`, `Town`, `Geo` | AI-1 |
| Stay | `CaravanPark::searchStays` | AI-1 |
| Traveller facility | Future `traveller_facilities` | AI-6 (after DATA-012) |
| Dataset | DATA-006 `ConnectorInterface` | AI-5 |

## Source priority

1. Canonical Assist Platform database  
2. Trusted imported government/council datasets  
3. OSM-derived / targeted OSM queries (configurable; no Overpass abuse)  
4. Approved external provider/location APIs  
5. Optional paid connectors (disabled by default; hard caps)  
6. Explicitly approved web research workflows (review-only)

## Aggregation

Deduplicate, rank, preserve source, confidence, verification, distance. Label
external/unverified results. Reuse existing list cards where practical.

## Non-goals

General chat, trip planning, booking, payments, marketplace.
