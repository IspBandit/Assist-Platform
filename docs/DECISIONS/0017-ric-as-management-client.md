# ADR 0017: Assist RIC as initial local management client (Option B)

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** DATA-011, CORE-011
- **Affected brands/modules:** Assist RIC sibling repo, Assist Platform Admin API

## Context

Phase 0 considered three options for a local VanAssist management platform.
Assist RIC already provides discovery, staging SQLite, duplicates, review,
coverage, export packages, paid-API budgets and optional AI classification.
Creating a third desktop stack (Tauri/React/FastAPI) would duplicate engines and
risk a second staging database.

## Decision

1. **Option B:** Implement `/api/v1/admin` in Assist Platform Enterprise and
   extend Assist RIC for research, staging, duplicate review and
   synchronisation.
2. Do **not** create a new Tauri/React/FastAPI management application now.
3. Do **not** create a third local staging database; RIC’s SQLite remains the
   research/staging store.
4. PySide6 RIC is the initial local management and research client. A
   React/Tauri companion may be reconsidered later only if PySide6 cannot
   support required management workflows efficiently.
5. Broad RIC UI changes wait until the live API contract and authentication are
   functioning and covered by tests.
6. Both repositories must cross-link this relationship from their start-here /
   architecture docs.

## Alternatives considered

- Option A (new Tauri stack): deferred.
- Option C (web admin only, no RIC sync): insufficient for research/staging.

## Consequences

- RIC gains an Admin API client module and sync state; packaging continues in
  the sibling repo.
- Platform owns auth, lifecycle, recycle bin and publish rules.
- Operator training remains one desktop research tool plus web admin where
  needed.

## Quality Gate impact

- Architecture: accepted — reuse over rewrite.
- UX: temporary PySide6 constraints accepted.
- Engineering: contract tests on both sides.
- Business: lower cost and faster path to safe sync.

## Validation and rollback

- Mock-client contract tests in Platform; RIC client tests against recorded
  fixtures.
- Rollback: disable sync client; RIC export packages remain usable.
