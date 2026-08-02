# ADR 0008: AI provider abstraction

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-004, POL-007
- **Affected brands/modules:** polaris, platform AI orchestrator

## Context

Polaris may use natural-language search and import extraction. Direct vendor SDK
calls in Polaris controllers would duplicate cost controls, logging and provider
lock-in already addressed at platform level.

## Decision

All Polaris AI usage routes through the **shared Assist AI Orchestrator**
(`App\Platform\AiSearch` and successors), governed by platform ADRs:

| Platform ADR | Requirement |
| --- | --- |
| 0018 | Single orchestrator boundary |
| 0019 | Interpretation only — no factual authority |
| 0020 | Provider-neutral adapters |
| 0021 | Cache-first routing |
| 0022 | Hard budget enforcement — fail closed |
| 0026 | No direct AI publishing to catalogue |

Polaris adds:

- `PolarisSearchAdapter` — intent → MariaDB catalogue filters
- `ImportDraftMapper` — extraction → `polaris_import_drafts` only
- Feature flags `polaris_ask`, `polaris_ai_import` default **off**

Site must function with all AI disabled.

## Alternatives considered

- Polaris-specific OpenAI integration: rejected (ADR 0020 violation).
- No AI ever for Polaris: rejected (optional acceleration for NL search/imports).
- Generative buyer advice chatbot: rejected (out of scope, trust risk).

## Consequences

- Depends on platform AI-1 maturity; Polaris can ship without AI.
- Polaris taxonomy extensions registered in orchestrator config.
- Shared logging and budget dashboards cover Polaris queries.

## Quality Gate impact

- Architecture: consistent AI boundary.
- UX: NL is additive to structured search (ADR 0023).
- Engineering: adapter tests with mock provider.
- Business: predictable AI cost.

## Validation and rollback

Validate: flags off → no `/ask` route registration. Rollback: disable flags;
adapters unused.
