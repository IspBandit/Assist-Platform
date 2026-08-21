# ADR 0004: Model year versioning

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-003
- **Affected brands/modules:** polaris catalogue

## Context

RV models change year-to-year. Buyers search by model name but specs and prices
attach to a specific model year. Conflating years causes incorrect tow and price guidance.

## Decision

Introduce explicit **`polaris_model_years`** between model family and variant:

- One model family (e.g. Summit 540) has many model years
- Variants belong to exactly one model year
- At most one `is_current` year per family for default public URL resolution
- Public canonical URL defaults to current year; `?year=` selects historical
- Price rows scoped to variant (therefore year)

Historical years remain published for research but marked non-current in UI.

## Alternatives considered

- Variant-only with year attribute: rejected (ambiguous when multiple years active).
- Separate URL per year in path segment v1: deferred (query param first for simpler migration).
- Overwrite specs in place each year: rejected (destroys provenance and comparison).

## Consequences

- Admin must create new year rows for annual updates — supports manufacturer portal workflow.
- Compare feature must align variants from same or explicitly mixed years with UI warning.
- SEO canonical strategy documented in SEO_STRATEGY.md.

## Quality Gate impact

- Architecture: temporal data model explicit.
- UX: year selector on model pages.
- Engineering: additional FK joins in detail views.
- Business: accurate annual updates.

## Validation and rollback

Validate: two years for one family display distinct specs. Rollback: merge years only
via admin merge tool (ADR 0012), not silent updates.
