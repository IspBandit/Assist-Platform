# Result provenance

**Status:** implemented (AI-5 / AI-6) + ADR 0042 Places rescue.  
**ADR:** [0028/0031](DECISIONS/0028-external-results-staged-provenance.md),
[0042](DECISIONS/0042-demand-driven-places-rescue.md).  
**Related:** [`DATA_TRUST_AND_PROVENANCE.md`](DATA_TRUST_AND_PROVENANCE.md),
[`DATASET_ROUTING.md`](DATASET_ROUTING.md), DATA-001, DATA-014, VAN-011.

## Display contract

Every Ask / adapter result carries provenance fields (DTO / row keys):

| Field | Meaning |
| --- | --- |
| `assist_origin` | `canonical` \| `imported` \| `external_live` \| `staged_candidate` \| facility origins |
| `assist_provenance_label` | Human label (e.g. pending review) |
| Source / connector key | Publisher or connector |
| Source record ID | External or canonical id |
| Verification status | Verified / reviewed / pending |
| Distance | Straight-line km when known |
| Confidence | Adapter or staging score |
| Attribution | Licence / publisher text when required |
| Temporary / pending review | External candidates only |

Implementation: `ResultProvenance` helpers + adapter mapping in
`DatasetSearchAdapter`, `TravellerFacilitySearchAdapter`,
`ZeroResultProviderRescueService`, and `ResultAggregator`.

## Presentation rule

Do **not** present a general web-found or live external result as equivalent to
a provider-confirmed canonical listing. Ask UI separates:

- Providers / stays (canonical, including unclaimed public-source listings)
- Traveller facilities (reviewed/verified only when flag on)
- Public-source / pending candidates (labelled, not verified VanAssist listings)

## Places rescue (ADR 0042)

When feature flag `provider_places_rescue` is on and a provider search is zero or
weak, Ask and `/find` may call Google Places **once** through the Data Sources
connector (budget-gated). Hits are labelled `external_live`. Matching hits may
also be auto-created as **unclaimed** canonical providers with Place ID
provenance. Dataset browsing still must not casually call Places
(`DatasetTrustPolicy::ASK_BLOCKED_CONNECTORS`).

## Staging

Only identifiable source + acceptable trust policy → `DraftCandidateService` →
DATA-006 duplicate check → human review (or documented `trusted_automatic`,
which is **never** auto-enabled in code) → publish through approved workflows.
Demand-driven unclaimed Places create is an explicit owner decision in ADR 0042
and is separate from verified publication.
