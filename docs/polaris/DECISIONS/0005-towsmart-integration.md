# ADR 0005: TowSmart integration

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-005
- **Affected brands/modules:** polaris, towsmart

## Context

Buyers need tow compatibility guidance when researching RVs. TowSmart already
maintains tow vehicle catalogues and a guidance calculator. Duplicating that data
in Polaris creates sync risk and false authority.

## Decision

Polaris integrates with TowSmart via a **read-only compatibility service**:

- Polaris stores RV-side weights (ATM, tare, ball weight) only
- Tow vehicle data fetched from TowSmart service/API or shared read repository
- `CompatibilityService` returns guidance status, margins, assumptions and deep link
  to TowSmart calculator — never legal certification language by default
- Shared Garage may supply user tow vehicle ID
- Find scoring may include tow factor after Phase 4

No Polaris tables for tow vehicles. No writes to TowSmart schema from Polaris code paths.

## Alternatives considered

- Copy tow vehicle JSON into Polaris DB: rejected (TOW-001 provenance debt, duplication).
- Inline calculator clone: rejected (TowSmart brand boundary and formula governance).
- Omit tow features entirely: rejected (core buyer need).

## Consequences

- Dependency on TowSmart data quality and API stability.
- UX must show disclaimers (ADR 0013) and confidence when tow data incomplete.
- Integration tests mock TowSmart boundary.

## Quality Gate impact

- Architecture: single tow authority preserved.
- UX: cross-brand navigation required.
- Engineering: service boundary interface to implement Phase 4.
- Business: strengthens TowSmart ↔ Polaris ecosystem.

## Validation and rollback

Validate: schema audit; tow-match integration test with mock. Rollback: hide tow-match
routes; RV specs remain.
