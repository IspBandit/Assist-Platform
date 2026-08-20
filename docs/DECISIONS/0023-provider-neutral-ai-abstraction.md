# ADR 0026: Provider-neutral AI abstraction

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering
- **Backlog item:** CORE-012, OPS-010
- **Affected brands/modules:** Assist AI Orchestrator

## Context

OpenAI is the initial hosted vendor candidate, but hard-coding OpenAI into
controllers would lock cost, availability and compliance choices.

## Decision

All model calls go through an internal provider interface supporting model name,
provider name, structured output, token/cost metrics, timeout/retry, validation
failure, cache key and correlation ID. OpenAI is the first adapter after owner
approval; rules-only mode is always available. Switching provider must not
require public controller or result-view changes.

## Alternatives considered

- OpenAI-only SDK in controllers: rejected.
- Multi-vendor in AI-1: deferred; interface first.

## Consequences

Allowlisted models in settings; no hard-coded model names in application logic.

## Quality Gate impact

- Architecture: swappable dependency.
- Engineering: adapter tests with recorded fixtures.
- Business: negotiate vendors without UX rewrites.

## Validation and rollback

Disable vendor adapter; rules-only continues.
