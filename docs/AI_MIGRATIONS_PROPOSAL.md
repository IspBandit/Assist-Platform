# AI migrations proposal (design only)

**Status:** design (Phase AI-0). **Do not apply** until the owning phase is
authorised. Forward-only numbered SQL under `database/migrations/` when
implemented. Names below are illustrative.

## AI-1 — logging + feature flag

Additive only; structured search untouched.

Proposed:

- Feature flag `assist_ai_search` default **false** (seed/settings).  
- Table `assist_searches` (or additive columns on a new NL-specific table;
  prefer **new table** over widening `provider_searches` aggressively):

| Column | Notes |
| --- | --- |
| id | BIGINT PK |
| brand_id | FK brands |
| session_id | nullable tracking session |
| request_id | correlation |
| channel | `ask_vanassist` / future |
| raw_query | bounded VARCHAR |
| normalised_query | |
| intent_json | JSON |
| intent_source | `rules` / `cache` / `ai` / `none` |
| confidence | DECIMAL |
| adapter_keys | JSON |
| local_result_count | |
| external_result_count | |
| fallback_reason | nullable |
| town_id / radius_km | nullable |
| location_precision | `none` / `town` / `gps_short` / `derived` |
| created_at | |

Link impressions via existing patterns or `assist_search_results` if needed.

**Privacy:** do not store raw GPS long-term; store town_id or rounded geohash
per owner decision.

## AI-2 — cache, settings, budget, usage

| Table | Purpose |
| --- | --- |
| `ai_settings` | enabled flags, allowlist JSON, caps, thresholds |
| `ai_intent_cache` | cache key hash, intent_json, versions, hits, expires |
| `ai_usage_events` | per-call observability |
| `ai_usage_daily` | rollups for admin |

Secrets remain outside DB (env/vault). Budget counters may live in
`ai_usage_daily` + settings.

## AI-3

Usually no new tables if AI-2 settings hold model allowlist and provider flags.

## AI-4 — knowledge gaps

`knowledge_gaps` grouped rows per [`KNOWLEDGE_GAPS.md`](KNOWLEDGE_GAPS.md).
Optional `knowledge_gap_events` for increments.

## AI-5

Prefer reuse of `data_source_*` tables. Add provenance fields on result DTOs;
DB changes only if DATA-014 gaps require them.

## AI-6 — traveller facilities

**Blocked** until DATA-012 / owner approval. New `traveller_facilities` (+
provenance, lifecycle) — never overload `caravan_parks` (ADR 0016/0027).

## AI-7

Indexes, retention jobs, abuse counters as needed — no schema surprises.

## Explicitly out of scope for AI migrations

- Admin API Phase 1 auth/token tables (owned by CORE-011).  
- Editing applied migrations.  
- Storing API keys in MariaDB plaintext.
