# AI usage and observability

**Status:** implemented (AI-2 / AI-7).  
**Tables:** `ai_usage_events`, `ai_usage_daily` (migration `086`).  
**Admin:** `/admin/ai-search`.  
**Related:** [`AI_COST_CONTROLS.md`](AI_COST_CONTROLS.md),
[`AI_OPERATIONS_RUNBOOK.md`](AI_OPERATIONS_RUNBOOK.md).

## Per-call event (`ai_usage_events`)

Recorded by `AIUsageService` from the orchestrator path:

- Request ID / correlation ID  
- Brand key  
- Operation type (`intent_resolve`, interpreter call, etc.)  
- Provider (`rules` \| `cache` \| `openai` \| …)  
- Model (allowlisted id only; never invent)  
- Input / output tokens  
- Cached status  
- Estimated cost AUD (and actual when reported)  
- Duration ms  
- Success / failure  
- Fallback reason (`budget_blocked`, `ai_failed`, `ai_disabled`, …)  
- Linked `assist_search_id`  
- Intent confidence  
- Budget state (`ok`, `soft_warn`, `hard_stop`, `ai_disabled`, `provider_disabled`)

## Daily rollups (`ai_usage_daily`)

Powers admin cards: requests today/month, estimated spend today/month, budget
remaining vs caps, cache hit rate, % resolved via rules, AI call share, failed
and budget-blocked counts.

## Search analytics (`assist_searches`)

NL channel logging (migration `085`): raw/normalised query, intent JSON,
adapters, local/external counts, fallback, town_id + location precision (no
raw GPS long-term — see `LocationPrivacy`).

## Knowledge-gap interaction analytics

Weak/zero Ask results upsert `knowledge_gaps`. Click-through and contact
actions increment counters via `?g=` / `/ask/click/{gapId}` /
`/go/…?g=` (30s session dedupe).

## Retention

`ai_retention` cron (migration `091`) purges aged raw telemetry per
`config/ai_search.php` windows. Aggregates and open gaps are retained for ops.

## Secrets

Never log API keys or raw credentials. Redact provider error bodies that may
echo prompts.
