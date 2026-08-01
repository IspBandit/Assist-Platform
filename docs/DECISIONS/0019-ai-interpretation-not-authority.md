# ADR 0019: AI is interpretation, not factual authority

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** CORE-012, VAN-011, DATA-001
- **Affected brands/modules:** Assist AI Orchestrator, imports, RIC assist

## Context

LLMs invent plausible businesses, phones and hours. Treating model output as
directory truth would destroy trust and violate data provenance rules.

## Decision

AI may interpret intent, extract locations/radii, map wording to taxonomy keys,
suggest adapters, classify imports, suggest duplicates/field mappings and
summarise conflicts for humans. AI must not invent providers, facilities,
addresses, coordinates, contacts, hours or availability; must not publish;
must not bypass duplicates or provenance; must not override verified
provider-supplied data; must not make production DB changes directly.

AI output is interpretation and classification metadata only.

## Alternatives considered

- Generative “answer with businesses”: rejected.
- Auto-publish model suggestions: rejected.

## Consequences

Search results always come from adapters over trusted or clearly labelled
external sources. Success metric is reliable platform answers plus knowledge
growth, not “AI answered.”

## Quality Gate impact

- Architecture / Business: trust preserved.
- Engineering: schema validation mandatory.
- UX: no conversational factual claims from intent layer.

## Validation and rollback

Contract tests forbid result fabrication from interpreter fixtures.
