# ADR 0016: Stays vs traveller facilities

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / VanAssist
- **Backlog item:** DATA-012, DATA-014, VAN-001, CORE-011
- **Affected brands/modules:** VanAssist stays directory, data sources, RIC

## Context

The management brief asked for a “facilities” API covering public toilets, dump
points, drinking water, rest areas and similar traveller services. The live
schema has no generic `facilities` table. Traveller stays live in
`caravan_parks` with amenity flags (`toilets`, `dump_point`, `potable_water`, …)
and `stay_type` values including `rest_area` and `free_camp`.

## Decision

### Do not overload `caravan_parks` for non-accommodation POIs

`caravan_parks` models **stay/accommodation partner listings**: site counts,
powered/unpowered sites, park partner users, park claims, listing plans and
guest service-day requests. Stretching it to hold standalone public toilets,
isolated dump points, drinking-water taps, boat ramps or EV chargers would:

- confuse partner-claim and commercial listing semantics;
- force nonsense fields (`number_of_sites`, listing plans) onto amenity POIs;
- pollute stay search and Park Partner portals.

`stay_type = rest_area` / `free_camp` remains valid for places where overnight
stopping is the primary traveller offer.

### Phase 1 Admin API surface

- Expose **`/api/v1/admin/stays`** for `caravan_parks` records.
- Do **not** invent a parallel generic `facilities` table merely for naming.
- Advertise `traveller_facilities` as a **planned** capability until a dedicated
  entity ships.

### Narrow future entity (not Phase 1 schema)

When DATA-012 imports require standalone amenity POIs, add a narrowly scoped
table such as `traveller_facilities` (name TBD in the implementing migration)
with:

- facility type enum/taxonomy (toilet, dump_point, drinking_water, …);
- geo, address, locality, status/lifecycle;
- source provenance (`source_type`, `external_id`, licence, attribution);
- **no** park-partner claim portal and **no** site-count commercial fields.

Providers remain businesses. Stays remain accommodation. Traveller facilities
remain point amenities. Canonical entity links (DATA-014) join them where a
place is both (e.g. a park that also publishes a public dump point).

## Alternatives considered

- Put toilets/dump points into `caravan_parks` with `stay_type=other`: rejected.
- One polymorphic `places` mega-table: rejected (too broad for Phase 1).
- Providers-only for amenities: rejected (amenities are not businesses).

## Consequences

- Phase 1 OpenAPI uses `stays`, not `facilities`.
- RIC export mapping continues to target providers and stays; amenity-only
  packs wait for the traveller-facility migration + ADR-linked implementation.
- Public search UX must not treat toilets as “caravan parks”.

## Quality Gate impact

- Architecture: accepted — clear bounded contexts.
- UX: stay directory language preserved; facility search later.
- Engineering: one fewer overloaded table; deferred migration.
- Business: avoids corrupting stay partner commercial model.

## Validation and rollback

- Confirm with sample imports: toilets must not appear in stay partner lists.
- If a future entity is wrong-sized, replace via forward migration; do not
  quietly widen `caravan_parks`.
