# ADR 0003: Hybrid specification storage

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-003
- **Affected brands/modules:** polaris catalogue

## Context

RV manufacturers use inconsistent specification labels. A wide static column table
requires frequent migrations; unstructured JSON blobs hinder filtering and matching.

## Decision

Use a **hybrid relational model**:

- Stable entities: manufacturers, model families, model years, variants (normalised tables)
- Specifications: governed `polaris_spec_definitions` dictionary plus
  `polaris_spec_values` per variant (EAV-style)
- Store normalised `value_numeric` for filter/sort on hot keys (ATM, length, berths)
- Each value links to `polaris_sources` with confidence enum

Admin manages definition catalogue; imports map external labels to definition keys.

## Alternatives considered

- Wide SQL table per spec: rejected (migration churn).
- Document store only (MongoDB): rejected (not platform stack; weak relational filters).
- Free-form JSON per variant without definitions: rejected (matching/units inconsistent).

## Consequences

- Join-heavy list queries — mitigate with indexes and selective materialisation later.
- Spec definition changes require care for match engine weights.
- Unit tests cover definition validation and numeric normalisation.

## Quality Gate impact

- Architecture: extensible without weekly migrations.
- UX: consistent labels across manufacturers.
- Engineering: query complexity managed in repository layer.
- Business: supports varied manufacturer data.

## Validation and rollback

Validate: `/rvs` filters on ATM and length against seed data. Rollback: migrate
forward to revised definitions; do not drop values without soft-delete policy (ADR 0011).
