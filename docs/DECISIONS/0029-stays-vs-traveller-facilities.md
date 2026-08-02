# ADR 0029: Stays vs narrowly scoped traveller facilities

- **Status:** accepted
- **Date:** 2026-08-02
- **Owners:** Platform Engineering / VanAssist
- **Backlog item:** DATA-012, VAN-001, CORE-012
- **Affected brands/modules:** stays directory, traveller facilities, Assist AI

## Context

AI and import workstreams need a clear place for toilets, dump points, drinking
water and similar point amenities. Historically some drafts numbered this
decision as “ADR 0016”, but **ADR 0016 is already allocated** to server-owned
provider import and campaign taxonomy
(`0016-server-owned-provider-import-and-campaign-taxonomy.md`). This ADR is the
authoritative stays-vs-facilities decision; ADR 0027 reaffirms it for Assist AI.

## Decision

1. `caravan_parks` (stays) remain accommodation and related stay listings only.
2. Standalone amenity POIs live in `traveller_facilities` (or provider categories
   where the amenity is a business service), never as invented park rows.
3. Amenity flags on parks are not a national facility search index.
4. Population of facilities is review-first via DATA-012 (and related ingest);
   Ask VanAssist surfaces only active + reviewed/verified rows behind a flag.

## Alternatives considered

- Overload `caravan_parks` for toilets/dump/water: rejected (pollutes stays UX
  and Admin API `/stays` meaning).
- Provider-only forever: insufficient for public toilets with no business
  listing.

## Consequences

- Clean taxonomy for Ask VanAssist facility intents.
- Admin API Phase 1 may keep advertising `traveller_facilities: planned` until
  a later API increment; Ask uses the table when the feature flag is on.
- AI-6 / DATA-012 implement the entity and ingest without reopening Phase 1
  OpenAPI scope.

## Quality Gate impact

- Architecture: bounded contexts preserved.
- UX: amenities never appear as “caravan parks.”
- Data: forward-only migration `092` (+ DATA-012 `093`).

## Validation and rollback

Feature flag `assist_ai_traveller_facilities` off restores Ask without facility
results. Tables may remain unused after operational rollback.
