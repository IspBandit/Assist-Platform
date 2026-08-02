# ADR 0027: Deterministic and cache-first routing

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering
- **Backlog item:** CORE-012, VAN-011
- **Affected brands/modules:** Assist AI Orchestrator

## Context

Most traveller queries (“dump point near X”, “LPG refill”) are pattern-matchable.
Calling a paid model for every request wastes budget and adds failure modes.

## Decision

Every free-text request enters the orchestrator. Resolve with the deterministic
keyword/pattern engine when confidence is sufficient. Consult intent cache
before AI. Call AI only when needed. Invalid AI output falls back to rules or
clarification.

## Alternatives considered

- AI-first for all NL: rejected (cost/latency).
- Skip orchestrator when rules hit: rejected (breaks logging/knowledge growth).

## Consequences

Higher % of searches with zero AI cost. Rules and taxonomy versions are part of
cache keys.

## Quality Gate impact

- Business: lower spend.
- Engineering: golden-query rule tests in AI-1.
- UX: faster obvious queries.

## Validation and rollback

Disable AI; rules + cache + structured search remain.
