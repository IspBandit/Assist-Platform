# Assist AI Orchestrator

**Status:** AI-1–AI-7 implemented behind flags (Ask / datasets / facilities /
paid AI **off** by default).  
**Backlog:** CORE-012, VAN-011, DATA-012, DATA-013.  
**Gate package:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md),
[`AI_WORKSTREAM_STATUS.md`](AI_WORKSTREAM_STATUS.md).

## Runtime

- Namespace: `App\Platform\AiSearch\`
- Entry: `SearchOrchestrator::handle(SearchRequest): SearchResponse`
- Public: `GET /ask` via `AssistSearchController` (VanAssist + `assist_ai_search`)
- Logging: `assist_searches` (085); usage (086); gaps (089); hardening (091)

## Pipeline components

| Concern | Class |
| --- | --- |
| Rules intent | `Intent\IntentRuleEngine` |
| Cache | `Cache\IntentCache` |
| Budget | `Budget\AIBudgetService`, `AiSettings` |
| Paid AI | `Intent\IntentInterpreter` → `Provider\AiProviderInterface` |
| Routing | `Routing\SearchRouter` |
| Adapters | Provider / Stay / TravellerFacility / Dataset |
| Aggregate | `Aggregate\ResultAggregator` |
| Gaps | `Knowledge\KnowledgeGapService` |
| Staging | `Staging\DraftCandidateService` |
| Usage | `Logging\AIUsageService`, `AssistSearchLogger` |

## Purpose

Shared Assist Platform capability that turns natural-language traveller queries
into structured search against trusted platform data and approved external
sources. It is **not** a chatbot and **not** a second search stack.

Brands (VanAssist, TowSmart, TrailerWise, LocalTorque) are tenants of one
orchestrator. VanAssist is the first public surface (“Ask VanAssist”).

## Ownership

- Lives under `App\Platform\AiSearch\`.
- All AI vendor calls go through this service (`OpenAiProvider` only via
  `IntentInterpreter`).
- Public controllers, importers, brand modules, scheduled jobs and Admin API
  clients must not call AI vendors directly.
- Assist RIC remains the research client (ADR 0017); no production MariaDB from
  RIC (ADR 0015); AI never publishes (ADR 0029).

## Success metric

Not “AI answered.” Success is: the platform answered reliably **and** trusted
local knowledge became more complete.

## Feature flags

| Flag | Default | Role |
| --- | --- | --- |
| `assist_ai_search` | off | Ask VanAssist `/ask` |
| `assist_ai_datasets` | off | Pending dataset candidates on Ask |
| `assist_ai_traveller_facilities` | off | Facilities adapter |
| `ai_enabled` / `openai_enabled` | off | Paid interpretation |

Structured dropdown / Near Me / automatic-location search remain available when
AI is disabled or budget-exhausted.

## Related ADRs

0021–030, 0032.
