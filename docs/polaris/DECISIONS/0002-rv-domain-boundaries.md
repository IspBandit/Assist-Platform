# ADR 0002: RV domain boundaries

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-001
- **Affected brands/modules:** polaris, towsmart, vanassist, trailerwise

## Context

Adjacent brands already own tow vehicles, service providers and trailer listings.
Polaris risks data duplication, user confusion and maintenance drift if it copies
those domains while building an RV catalogue.

## Decision

Polaris scope is **new RV product catalogue and decision UX only**:

| In Polaris | Not in Polaris |
| --- | --- |
| Manufacturers, models, specs, floorplans | Used RV / classifieds |
| Price guidance with provenance | Dealer stock inventory |
| Find, compare, tow-match presentation | Tow vehicle database |
| | Service provider directory |
| | TrailerWise secondary marketplace listings |

TowSmart remains authoritative for tow vehicles and calculator guidance. VanAssist
remains authoritative for repair and service providers. TrailerWise listings are
not imported as Polaris catalogue rows.

## Alternatives considered

- Unified “everything RV” mega-catalogue: rejected (scope explosion, duplicate data).
- Polaris used marketplace: rejected (product vision).
- Copy TowSmart JSON into Polaris for offline tow-match: rejected (ADR 0005).

## Consequences

- Integration read APIs/links only — no mirrored tables for tow or providers.
- Product and engineering reviews must reject features outside boundary.
- User journeys explicitly deep-link to sibling brands.

## Quality Gate impact

- Architecture: clear bounded context.
- UX: users may cross brands; copy must explain hand-off.
- Engineering: simpler Polaris schema.
- Business: preserves brand product clarity.

## Validation and rollback

Validate: schema review finds no tow_vehicle or provider duplicate tables. Rollback:
N/A — boundary policy; remove non-conforming features if introduced.
