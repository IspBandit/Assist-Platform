# Assist AI Orchestrator

**Status:** AI-1 foundation implemented (deterministic only). Paid AI not enabled.  
**Backlog:** CORE-012, VAN-011, DATA-013.  
**Gate package:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md).

## AI-1 runtime

- Namespace: `App\Platform\AiSearch`
- Entry: `SearchOrchestrator::handle(SearchRequest)`
- Public: `GET /ask` via `AssistSearchController` (VanAssist + flag)
- Flag: `assist_ai_search` (default **false**)
- Logging: `assist_searches` (migration 085)

## Purpose

Shared Assist Platform capability that turns natural-language traveller queries
into structured search against trusted platform data (and approved external
sources in later phases). It is **not** a chatbot and **not** a second search
stack.

Brands (VanAssist, TowSmart, TrailerWise, LocalTorque) are tenants of one
orchestrator. VanAssist is the first public surface (“Ask VanAssist”).

## Ownership

- Lives under `App\Platform\AiSearch\` (proposed).
- All AI vendor calls go through this service.
- Public controllers, importers, brand modules, scheduled jobs and Admin API
  clients must not call AI vendors directly.
- Assist RIC may keep local classification assist for research staging; it must
  not open production MariaDB (ADR 0015) and must not publish AI output as
  facts.

## Logical pipeline

See [`SEARCH_PIPELINE.md`](SEARCH_PIPELINE.md) and
[`AI_SEARCH_ENGINE.md`](AI_SEARCH_ENGINE.md).

## Success metric

Not “AI answered.” Success is: the platform answered reliably **and** trusted
local knowledge became more complete.

## Feature flags

- Off by default.
- Structured dropdown / Near Me / automatic-location search unaffected when AI
  is disabled or budget-exhausted.

## Related ADRs

0018 (shared orchestrator), 0019 (interpretation only), 0020 (provider-neutral),
0021 (deterministic/cache-first), 0022 (hard budgets), 0023 (NL alongside
structured search).
