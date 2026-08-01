# ADR 0026: No direct AI publishing

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** CORE-012, DATA-006, CORE-011
- **Affected brands/modules:** Assist AI Orchestrator, imports, Admin API, RIC

## Context

ADR 0015 forbids external tools writing production DB directly. AI workers are
equally unsafe as silent publishers.

## Decision

AI must never publish records, merge duplicates, or write production canonical
rows directly. Staging goes through DATA-006 candidates and/or Admin API
drafts/imports. Merge remains audited administrative action (DATA-002).
Publication policy from Phase 1 remains: web research, AI-only and community
never auto-publish; `trusted_automatic` only with written owner decision.

## Alternatives considered

- Model writes providers table: rejected.
- Service account with publish scope for AI: rejected for v1.

## Consequences

Orchestrator may call DraftCandidateService; approve/publish stay human or
explicit trusted dataset pipelines.

## Quality Gate impact

- Security / Architecture: aligns with ADR 0015.
- Ops: audit enablement of any future trusted_automatic dataset.

## Validation and rollback

Revoke elevated scopes; review queue only.
