# ADR 0010: Source provenance

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-003, POL-007
- **Affected brands/modules:** polaris catalogue, imports

## Context

RV specifications and prices affect purchasing decisions. Users and regulators
expect clarity about where claims originate. Platform ADR 0025 requires staged
provenance for external results.

## Decision

Every published spec value and price row links to **`polaris_sources`**:

- Fields: source_type, uri, retrieved_at, notes
- Value-level confidence: verified, imported, inferred, unknown
- Public UI shows source chip and retrieval date on specs and prices
- AI extraction sources typed `ai_extraction` with confidence **inferred** until
  human review promotes to imported/verified
- External web results in search (if any) labelled separately — not merged as specs

Manufacturer portal submissions create sources tied to organisation claim.

## Alternatives considered

- Single source per variant only: rejected (specs may come from multiple documents).
- Hidden provenance for cleaner UI: rejected (trust requirement).
- Trust AI without source attachment: rejected (ADR 0019).

## Consequences

- Admin publish blocked if required specs lack source (configurable strictness).
- Stale source monitoring job Planned Phase 6.
- More UI space required on model pages.

## Quality Gate impact

- Architecture: aligns with platform provenance direction.
- UX: busier spec tables with chips.
- Engineering: FK integrity on publish pipeline.
- Business: defensible catalogue quality.

## Validation and rollback

Validate: publish attempt without source fails or warns per policy. Rollback: relax
validation only with business sign-off.
