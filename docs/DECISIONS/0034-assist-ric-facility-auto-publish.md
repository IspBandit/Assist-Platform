# ADR 0034: Assist RIC government facility packs auto-publish

- **Status:** accepted
- **Date:** 2026-08-05
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** DATA-011, DATA-011A, DATA-012
- **Affected brands/modules:** Assist RIC, Admin API `/facility-imports`,
  `traveller_facilities`, VanAssist facility map

## Context

ADR 0033 kept Assist RIC as the national acquisition engine and kept production
publish review-first. That queue blocked travellers from using free government
facility packs already staged via `POST /facility-imports`. The business owner
has directed that Assist RIC government pack ingest must make records available
to users without a separate website Approve click.

ADR 0029 / Phase AI trust policy still require a written owner decision before
any `trusted_automatic` publish path.

## Decision

1. Owners authorise **trusted automatic publish** for candidates whose import
   job uses `connector_key = assist_ric_package` (Assist RIC facility packages
   only). Other connectors, drafts, provider imports, AI research and community
   submissions stay review-first.
2. `POST /api/v1/admin/facility-imports` stages candidates then immediately
   publishes pending Assist RIC candidates for that dataset into
   `traveller_facilities` (`status=active`, `verification_status=reviewed`) using
   the existing `publishCandidate` path and audit events.
3. `POST /api/v1/admin/facility-imports/publish-pending` (`imports:write`) drains
   remaining Assist RIC pending candidates in bounded batches so Assist RIC can
   flush backlog queued before this decision without requiring human
   `import_candidates:review`.
4. Human Approve/Reject endpoints remain for non-RIC and exceptional review.
5. This amends ADR 0033 §6 / “no auto-publish” **only** for the Assist RIC
   facility-package path described above.

## Alternatives considered

- Keep human-only Approve UI / bulk-approve: rejected — too slow operationally;
  blocks user-visible coverage.
- Auto-publish every government connector (including platform `datasets/sync`):
  rejected — wider blast radius than needed; ADR 0033 review-first remains for
  non-RIC sync.
- Lower NEVER_SERVICE on `import_candidates:review` for service accounts:
  rejected — keeps broad approve power off service tokens; auto-publish stays
  scoped inside the RIC facility-imports contract.

## Consequences

- Trusted AU government packs pushed from Assist RIC become live without a
  second operator step.
- Provenance (`source_key` / `source_record_id`), duplicate updates, licences
  and audit events remain on the existing publish path.
- Operators can still reject/correct via admin tools after publish.
- Rollback: disable the auto-publish/publish-pending callers and revert to
  staging-only responses (forward code change); published rows remain until
  explicitly curated.

## Quality Gate impact

- Architecture: Admin API remains the only external write path (ADR 0018).
- UX: fewer admin steps; public facility coverage improves faster.
- Engineering: tests + LIVE_API/OpenAPI contract updates required.
- Business: explicit owner approval for this trusted_automatic scope.

## Validation and rollback

Validate with Assist RIC UPDATE ALL after production release: pending Assist
RIC queue drains; new chunks return `status=published`; facilities appear on
brand maps. Rollback by reverting this ADR’s code path so
`/facility-imports` stages only.
