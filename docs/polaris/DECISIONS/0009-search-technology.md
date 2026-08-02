# ADR 0009: Search technology

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-006
- **Affected brands/modules:** polaris search

## Context

Catalogue search must support filters on specifications, manufacturer, category and
name. Introducing Elasticsearch or similar adds operational cost before catalogue
volume justifies it. Platform ADR 0026 requires structured search to remain primary.

## Decision

**MariaDB structured search first**:

- Filtered list queries via joins on `polaris_spec_values` for indexed keys
- Pagination and sort in SQL
- Phase 2: FULLTEXT on manufacturer and model names
- Optional NL layer maps to same SQL filters via orchestrator (not separate index)

No external search cluster at launch. Revisit when published variant count and query
latency metrics exceed agreed thresholds (Phase 9 review).

## Alternatives considered

- Elasticsearch/OpenSearch day one: rejected (ops cost, premature).
- Algolia SaaS: rejected (vendor cost, data residency review overhead).
- Client-only filter of full JSON dump: rejected (scale, SEO pagination).

## Consequences

- Query optimisation and indexing are engineering focus for Phase 2.
- Complex relevance tuning limited vs dedicated search engine.
- NL search still depends on SQL adapter execution.

## Quality Gate impact

- Architecture: aligns with platform MariaDB stack.
- UX: fast enough for thousands of variants target.
- Engineering: familiar tooling; integration tests on filters.
- Business: no new infrastructure bill at launch.

## Validation and rollback

Validate: explain plans for hot filters; load test on staging seed. Rollback: simplify
filters if perf issues; defer FULLTEXT.
