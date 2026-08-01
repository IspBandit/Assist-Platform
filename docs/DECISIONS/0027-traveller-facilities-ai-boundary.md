# ADR 0027: Traveller facilities remain separate from stays (AI workstream)

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / VanAssist
- **Backlog item:** DATA-012, CORE-012, VAN-001
- **Affected brands/modules:** Assist AI Orchestrator, stays directory

## Context

ADR 0016 already decided stays (`caravan_parks`) must not absorb standalone
amenity POIs. The AI workstream will interpret queries for toilets, dump points,
water, etc., and needs a clear adapter boundary without reopening Phase 1 Admin
API naming (`/stays`).

## Decision

Reaffirm ADR 0016 for the AI workstream. Design facility taxonomy and a
`TravellerFacilitySearchAdapter` stub. Do not add the traveller-facility
production migration in AI-0–AI-5. AI-6 proceeds only after DATA-012 and schema
approval. Until then, map amenity-like NL queries to provider category fallbacks
and/or clarification — never invent park rows for pure amenities.

## Alternatives considered

- Overload `caravan_parks` for AI results: rejected (ADR 0016).
- Ship facility table inside AI-1: rejected (premature).

## Consequences

Intent schema includes `facility_type_keys` early; adapter remains inert until
AI-6. Admin API Phase 1 scope unchanged.

## Quality Gate impact

- Architecture: consistent bounded contexts.
- UX: toilets never appear as “caravan parks.”
- Data: clean future migration.

## Validation and rollback

N/A for AI-0; future migration is forward-only and feature-flagged.
