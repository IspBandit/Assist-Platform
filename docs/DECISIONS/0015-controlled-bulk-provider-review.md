# ADR 0015: Controlled bulk provider review and exact duplicate linking

- **Status:** accepted
- **Date:** 2026-07-29
- **Owners:** Assist Platform Enterprise
- **Backlog item:** CORE-003
- **Affected brands/modules:** Shared provider master, brand listings, Data Sources review

## Context

National provider discovery can produce thousands of review candidates. Requiring
an administrator to publish or close every record individually does not scale,
but blindly copying discovery data into the canonical provider master risks
incorrect services, duplicate listings, licensing breaches and overwriting a
provider-controlled profile.

## Decision

The review queue supports controlled batches of at most 100 selected records.
Bulk approval requires a confirmed independent evidence URL, an active mapped
service category and no possible duplicate. Bulk merge additionally requires an
unclaimed target, a duplicate score of at least 90, the same normalised business
name, and the same phone or website.

Exact duplicates of an existing unclaimed listing in the same brand workspace
are linked automatically during import and may be resolved later in batches of
1,000. Automatic linking closes the duplicate candidate but copies no candidate
fields to the canonical provider. Claimed providers and cross-workspace-only
records always remain for review.

## Alternatives considered

- Publish every discovery row automatically: rejected because discovery is not
  service, retention or current-business verification.
- Keep all review actions individual: rejected because it makes national data
  operations impractical.
- Merge into claimed providers: rejected because the provider controls those
  details and service assignments.

## Consequences

Large imports become manageable while ambiguous records remain visible. Every
bulk and automatic action is audited. Some candidates still need individual
evidence confirmation, and exact records without an existing brand listing are
not auto-closed because doing so could leave a coverage gap.

## Quality Gate impact

- Architecture: one canonical provider remains the identity boundary; no second
  provider service or schema is introduced.
- UX: clear named actions report processed and safely skipped counts, with a
  confirmation control for publication-affecting batches.
- Engineering: brand scope, batch limits, exact-match policy and claimed-record
  protection are enforced server-side and covered by tests.
- Business: provider coverage can be released faster without sacrificing claim
  ownership, directory trust or auditability.

## Validation and rollback

Validate policy unit tests, the complete unit suite, PHP syntax and static
analysis. Roll back the application release to remove the controls; no migration
or destructive provider rewrite is required. Automatically linked candidates
retain their target provider ID and audit record for manual restoration if an
operator identifies an error.
