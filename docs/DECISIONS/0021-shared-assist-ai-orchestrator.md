# ADR 0024: Shared Assist AI Orchestrator

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** CORE-012, VAN-011, DATA-013
- **Affected brands/modules:** Assist Platform Enterprise (all brands), VanAssist first

## Context

Natural-language search and knowledge growth must not become brand-specific AI
stacks or a second competing search implementation. Admin API Phase 1 and Assist
RIC already define external write boundaries (ADR 0015–0017).

## Decision

Create one shared internal **Assist AI Orchestrator** under platform services.
All Platform AI vendor access goes through it. Public structured search remains;
NL search is a parallel consumer of the same adapters.

**AI-0 approved 2026-08-01. AI-1 shipped deterministic foundation**
(`App\Platform\AiSearch`, `/ask`, feature flag `assist_ai_search` off by default).

## Alternatives considered

- Embed OpenAI in VanAssist controllers: rejected (unreusable, hard to control).
- Put NL search only in Assist RIC: rejected (wrong latency/UX boundary).
- General chatbot platform: rejected (out of scope).

## Consequences

- Shared taxonomy, budgets, logging and adapters across brands.
- AI-0 design before any production code (see `docs/PHASE_AI0_DESIGN.md`).

## Quality Gate impact

- Architecture: single orchestration boundary.
- UX: Ask VanAssist alongside `/find`.
- Engineering: new `App\Platform\AiSearch` module in later phases.
- Business: cost controls mandatory.

## Validation and rollback

Feature flag off restores prior UX. No AI-0 runtime change.
