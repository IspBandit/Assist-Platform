# ADR 0007: Recommendation engine

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-004
- **Affected brands/modules:** polaris find/matching

## Context

Guided Find My RV needs ranked results. Opaque ML or LLM-generated recommendations
conflict with platform AI policy and cannot explain missing specification data honestly.

## Decision

Implement a **deterministic hybrid rule engine**:

- Weighted factors over user answers and normalised variant specs
- Explicit penalties for missing critical specs and stale prices
- Output includes score band, positive/negative reasons and data gap list
- Tow compatibility factor added via TowSmart service (Phase 4)
- Pure PHP service layer with unit tests — no LLM scoring

LLMs may not compute or override match rankings.

## Alternatives considered

- Collaborative filtering: rejected (cold start, no behavioural data at launch).
- LLM “best match” narrative: rejected (ADR 0019).
- Manual curator-only rankings: rejected (non-scalable).

## Consequences

- Weights tunable in code initially; admin UI for weights post-v1 optional.
- Find must degrade gracefully when catalogue sparse.
- Analytics logs bands and clicks for weight tuning.

## Quality Gate impact

- Architecture: testable decision logic.
- UX: explainability builds trust.
- Engineering: unit test coverage mandatory.
- Business: defensible guidance vs black box.

## Validation and rollback

Validate: fixture tests for penalties and bands. Rollback: disable results scoring;
show unranked filter-only list.
