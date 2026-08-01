# ADR 0025: External results are staged with provenance

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Data
- **Backlog item:** DATA-001, DATA-006, DATA-014, CORE-012
- **Affected brands/modules:** Assist AI Orchestrator, Data Sources, public search

## Context

External connectors and OSM can fill gaps, but undifferentiated display erodes
trust. DATA_TRUST_AND_PROVENANCE already forbids inventing missing fields.

## Decision

Live external hits may be shown only with clear origin/provenance labels.
Trusted results may be staged as draft candidates subject to source policy,
licence, attribution and duplicate checks. They become canonical only through
approved Platform workflows. Web research and AI-only suggestions never
auto-publish.

## Alternatives considered

- Show all Google/OSM hits as first-class listings: rejected.
- Never show external until fully imported: deferred as too strict for remote
  zero-result emergencies; labelled live external allowed under policy.

## Consequences

Result DTOs carry provenance fields. UX must label pending/external.

## Quality Gate impact

- Trust / Business: honest directory.
- Engineering: aggregator provenance required.
- UX: labelling acceptance tests.

## Validation and rollback

Disable dataset adapter; canonical-only results remain.
