# ADR 0012: Authoritative provider pack and taxonomy routing

- Status: accepted
- Date: 2026-07-28
- Backlog: CORE-003, LOC-002, DATA-001, DATA-004
- Brands: LocalTorque, VanAssist, TowSmart, TrailerWise

## Context

LocalTorque is the master automotive business directory. A supplied publishable
pack contains canonical public-source business candidates and a taxonomy whose
`category.brands` field defines where each capability belongs. Copying the same
business into separate brand-owned records would create conflicting identity,
claim and verification states. Automatically changing a claimed provider from
third-party data would override the business owner.

## Decision

Import the pack additively into the shared canonical `providers` table. Preserve
each source record, licence, confidence and original payload in
`provider_source_records`. Create brand listings and category assignments only
where the taxonomy explicitly names the brand. Claimed providers are linked to
source evidence but are not changed by the importer.

Only records that are publishable, locatable, sufficiently confident and not
marked for review receive public listings. Review records remain draft and
hidden. Fuel and EV categories route only to LocalTorque and VanAssist. A narrow
compatibility mapping exposes those two categories in VanAssist's existing
traveller-service search without broadening unrelated capabilities.

The importer is idempotent, fingerprinted, resumable and runs during a controlled
release before the strict data audit. Provider coordinates take precedence over
town-centre coordinates for nearby ranking.

## Consequences

- One claim and verification state is retained across the platform.
- Source attribution and licence obligations remain queryable per record.
- A pack refresh can enrich unclaimed records without replacing owner-managed data.
- Missing contact fields stay missing; they are never invented.
- Straight-line distance remains clearly labelled until a production routing
  service is configured and tested.
- The known South Australia fuel gap and uneven EV coverage remain visible data
  gaps, not silently filled claims.

## Alternatives rejected

- Separate provider tables per brand: creates duplicates and conflicting claims.
- Trust every publishable row without review controls: publishable does not mean
  verified, accurately classified or currently trading.
- Infer brand visibility from business names: too error-prone and contradicts
  the supplied taxonomy.
