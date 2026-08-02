# ADR 0027: Traveller facilities remain separate from stays (AI workstream)

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / VanAssist
- **Backlog item:** DATA-012, CORE-012, VAN-001
- **Affected brands/modules:** Assist AI Orchestrator, stays directory

## Context

[ADR 0029](0029-stays-vs-traveller-facilities.md) decided stays (`caravan_parks`)
must not absorb standalone amenity POIs. (Earlier AI drafts incorrectly cited
this as “ADR 0016”; that number is the provider-import ADR.) The AI workstream
interprets queries for toilets, dump points, water, etc., and needs a clear
adapter boundary without reopening Phase 1 Admin API naming (`/stays`).

## Decision

Reaffirm ADR 0029 for the AI workstream. Design facility taxonomy and a
`TravellerFacilitySearchAdapter`. Do not overload `caravan_parks`. AI-6 ships
the entity behind a flag; DATA-012 populates via review-first ingest. Until the
flag is on with reviewed rows, map amenity-like NL queries to provider category
fallbacks and/or clarification — never invent park rows for pure amenities.

## Alternatives considered

- Overload `caravan_parks` for AI results: rejected (ADR 0029).
- Ship facility table inside AI-1: rejected (premature).

## Consequences

Intent schema includes `facility_type_keys` early; adapter is flag-gated.
Admin API Phase 1 scope unchanged.

AI-6 shipped `traveller_facilities` behind `assist_ai_traveller_facilities`.
Populate via DATA-012 ingest; keep Admin API Phase 1 scope unchanged.

## Quality Gate impact

- Architecture: consistent bounded contexts.
- UX: toilets never appear as “caravan parks.”
- Data: clean future migration.

## Validation and rollback

Feature-flagged; migration is forward-only.
