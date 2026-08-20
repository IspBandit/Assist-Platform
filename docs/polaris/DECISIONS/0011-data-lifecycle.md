# ADR 0011: Data lifecycle

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-003
- **Affected brands/modules:** polaris catalogue, platform recycle bin

## Context

Catalogue entities require correction, archival and occasional removal. Hard deletes
break audit trails and SEO history. Platform soft-delete patterns exist; Recycle Bin
productisation (OPS-011) is incomplete.

## Decision

Polaris catalogue tables use **soft delete** (`deleted_at`) consistent with platform
model base:

- Public queries exclude soft-deleted rows
- Admin restore clears `deleted_at` when OPS-011 UI available; until then admin SQL/tool
- Hard purge admin-only with audit log entry and two-step confirmation
- Price history append-only — do not overwrite past price rows
- Spec value updates create new source linkage; prefer new row when source changes

Polaris does not implement a parallel delete system.

## Alternatives considered

- Hard delete default: rejected (audit, accidental loss).
- Immutable catalogue only: rejected (manufacturer corrections needed).
- Separate Polaris recycle bin: rejected (platform consolidation).

## Consequences

- Unique constraints must account for soft-deleted rows (partial indexes or slug suffix).
- Storage grows; purge policy defined in operations runbook addendum.
- Manufacturer “delete” maps to archive request.

## Quality Gate impact

- Architecture: consistent lifecycle.
- UX: restored content may reappear in search after delay.
- Engineering: all repositories apply soft-delete scope.
- Business: recoverable mistakes.

## Validation and rollback

Validate: delete hides from public; audit entry exists. Rollback: N/A policy;
implement restore when OPS-011 ships.
