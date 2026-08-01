# ADR 0024: Knowledge-gap-driven database growth

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Data
- **Backlog item:** DATA-013, DATA-011, CORE-012
- **Affected brands/modules:** Assist AI Orchestrator, demand analytics, Assist RIC

## Context

Coverage will always be incomplete nationally. Paid AI answering without
capturing gaps wastes demand signal. DATA-013 already targets ranked zero/weak
searches → research jobs.

## Decision

Every NL search feeds a grouped knowledge-gap engine. Priority considers
frequency, urgency, zero-result rate, safety, remoteness, contact demand and
dataset availability. Gaps hand off to RIC via the planned Admin API
`/search-gaps` surface (Phase 1 inventory preserved; implementation when
authorised) and existing draft/import paths.

## Alternatives considered

- Log searches only without grouping: rejected (noise).
- Auto-scrape the web into production: rejected.

## Consequences

Extends demand analytics; does not replace `provider_searches`. Aligns Platform
and RIC without a third staging DB (ADR 0017).

## Quality Gate impact

- Data: measurable coverage growth.
- Architecture: reuses Admin API + RIC.
- Business: research effort prioritised by real demand.

## Validation and rollback

Disable gap writer; search continues; tables unused if flagged off.
