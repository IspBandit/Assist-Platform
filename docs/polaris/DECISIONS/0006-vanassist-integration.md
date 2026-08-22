# ADR 0006: VanAssist integration

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-008
- **Affected brands/modules:** polaris, vanassist

## Context

Researching a new RV often leads to service needs. VanAssist already operates the
provider directory. Polaris model pages could incorrectly invent or duplicate providers.

## Decision

Polaris **surfaces** VanAssist providers via read-only query:

- `ProviderSurfacingService` maps RV category + user region to VanAssist categories
- Model pages show limited provider cards linking to VanAssist profiles
- Empty state when no providers — no fabricated listings
- Sponsored VanAssist content labelled per platform rules
- AI search must not create provider records (platform ADR 0022, 0027)

Polaris admin has no CRUD for VanAssist providers.

## Alternatives considered

- Embed full VanAssist search iframe: rejected (UX fragmentation, auth complexity).
- Copy provider subset into Polaris DB: rejected (duplicate maintenance).
- Skip service discovery: rejected (incomplete buyer journey).

## Consequences

- Phase 8 delivery; not required for Phase 1 browse.
- Category mapping maintained in config.
- Analytics event `polaris.provider_click` for attribution.

## Quality Gate impact

- Architecture: directory authority preserved.
- UX: clear “listing on VanAssist” labelling.
- Engineering: read API or shared repository dependency.
- Business: cross-brand traffic without merge.

## Validation and rollback

Validate: model page query returns only VanAssist-sourced IDs. Rollback: remove
surfacing block; catalogue unaffected.
