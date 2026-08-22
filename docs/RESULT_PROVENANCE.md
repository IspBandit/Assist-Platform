# Result provenance

**Status:** implemented (AI-5 / AI-6).  
**ADR:** [0025](DECISIONS/0025-external-results-staged-with-provenance.md) (accepted).  
**Related:** [`DATA_TRUST_AND_PROVENANCE.md`](DATA_TRUST_AND_PROVENANCE.md),
[`DATASET_ROUTING.md`](DATASET_ROUTING.md), DATA-001, DATA-014.

## Display contract

Every Ask / adapter result carries provenance fields (DTO / row keys):

| Field | Meaning |
| --- | --- |
| `assist_origin` | `canonical` \| `local` \| `external` \| `facility` |
| `assist_provenance_label` | Human label (e.g. pending review) |
| Source / connector key | Publisher or connector |
| Source record ID | External or canonical id |
| Verification status | Verified / reviewed / pending |
| Distance | Straight-line km when known |
| Confidence | Adapter or staging score |
| Attribution | Licence / publisher text when required |
| Temporary / pending review | External candidates only |

Implementation: `ResultProvenance` helpers + adapter mapping in
`DatasetSearchAdapter`, `TravellerFacilitySearchAdapter`, and
`ResultAggregator`.

## Presentation rule

Do **not** present a general web-found or live external result as equivalent to
a provider-confirmed canonical listing. Ask UI separates:

- Providers / stays (canonical)
- Traveller facilities (reviewed/verified only when flag on)
- Pending dataset candidates (labelled, not verified VanAssist listings)

## Staging

Only identifiable source + acceptable trust policy → `DraftCandidateService` →
DATA-006 duplicate check → human review (or documented `trusted_automatic`,
which is **never** auto-enabled in code) → publish through approved workflows.

Ask never calls Google Places or live Overpass. Offline OSM seed staging is
admin/CLI only (`osm_offline_seed`, migration `097`).
