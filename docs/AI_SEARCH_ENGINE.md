# AI search engine

**Status:** AI-1–AI-6 adapters implemented (flags off by default).  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md).

## Adapters

| Adapter | Class | Backing | Phase |
| --- | --- | --- | --- |
| Provider | `ProviderSearchAdapter` | Providers + coverage + geo | AI-1 |
| Stay | `StaySearchAdapter` | `CaravanPark::searchStays` | AI-1 |
| Traveller facility | `TravellerFacilitySearchAdapter` | `traveller_facilities` | AI-6 |
| Dataset | `DatasetSearchAdapter` | Pending `data_source_import_candidates` + provenance labels | AI-5 |

Offline OSM seed (`OsmOfflineSeedConnector`) stages into DATA-006 candidates
via CLI; Ask surfaces them only through the dataset adapter when flagged — never
via live Overpass.

## Source priority

1. Canonical Assist Platform database  
2. Trusted imported government/council datasets (staged candidates when flagged)  
3. OSM-derived offline seed / managed extracts (configurable; no Overpass from Ask)  
4. Approved external provider/location APIs (admin DATA-006 runs only)  
5. Optional paid connectors (disabled by default; hard caps; **not** from Ask)  
6. Explicitly approved web research workflows (review-only)

## Aggregation

`ResultAggregator` deduplicates, ranks, preserves source, confidence,
verification, and distance. External/unverified results are labelled. Provider
cards reuse existing list partials where practical.

## Non-goals

General chat, trip planning, booking, payments, marketplace.
