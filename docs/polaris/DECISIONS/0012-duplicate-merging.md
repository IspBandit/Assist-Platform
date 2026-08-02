# ADR 0012: Duplicate merging

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-007
- **Affected brands/modules:** polaris catalogue, imports

## Context

Imports and manual entry will create duplicate manufacturers and model families
( spelling variants, rebrands). Separate records fragment SEO and matching.

## Decision

Provide **administrator-controlled merge** workflow:

- Duplicate detection: exact slug collision blocked; fuzzy name match suggests candidates
  (AI may suggest — not auto-merge per ADR 0022)
- Merge UI selects survivor entity; merges slugs as aliases where supported
- Related variants, spec values and sources re-point to survivor in transaction
- Full audit log: merged_from_id, merged_to_id, actor, timestamp
- Public URLs from deprecated slugs 301 to survivor when alias table exists

Manufacturers cannot merge other manufacturers’ records.

Automatic silent merge is prohibited.

## Alternatives considered

- Ignore duplicates: rejected (SEO and data quality).
- Auto-merge on fuzzy match: rejected (false positives).
- Delete duplicate with data loss: rejected (ADR 0011).

## Consequences

- Merge tool Phase 6; manual admin workaround until then.
- Requires alias slug table or redirect map.
- Tests cover FK repointing and rollback failure modes.

## Quality Gate impact

- Architecture: explicit data stewardship.
- UX: redirects must work for bookmarked URLs.
- Engineering: transactional merge complexity.
- Business: cleaner catalogue.

## Validation and rollback

Validate: merge integration test on fixture duplicates. Rollback: restore from backup
if merge transaction fails mid-way — design for atomicity.
