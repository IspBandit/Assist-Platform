# Knowledge gaps

**Status:** implemented (Phase AI-4 / DATA-013 on this branch).  
**Backlog:** DATA-013.  
**Tables:** `knowledge_gaps`, `knowledge_gap_events` (migration `089`).  
**Admin:** `/admin/ai-search/gaps` (+ CSV and SearchGap-shaped JSON export for RIC).

## Behaviour

- Orchestrator records observations for **unknown**, **zero-result**, and
  **weak** NL searches (default weak threshold: &lt; 3 local results).
- Gaps are **grouped** by hash(brand + normalised query + intent + town +
  taxonomy keys + radius bucket) — repeats strengthen one row.
- Priority considers frequency, zero/weak rates, urgency, safety taxonomy,
  remoteness, **click-through** and **contact-action** counters.
- Ask result interactions write those counters:
  - Provider profile views with `?g={gapId}` → `click_through_count`
  - `/go/{action}/{slug}?g={gapId}` → `contact_action_count`
  - Stay links via `/ask/click/{gapId}?to=…` → `click_through_count`
  (30s session dedupe; never blocks redirects)
- Adequate local results do not create gaps.

## Admin API / RIC hand-off

Locked Phase 1 path `GET /api/v1/admin/search-gaps` is **not re-implemented** on
this AI branch (Admin API lives on CORE-011). DATA-013 ships the knowledge side
plus dual-source glue for merge:

1. `/admin/ai-search/gaps` — ranked list + status/job notes  
2. CSV export → Assist RIC research queue  
3. **SearchGap JSON (bridge):** `/admin/ai-search/gaps/export?format=json`  
   (`KnowledgeGapService::toSearchGapItems` / `searchGapCollection`) maps into
   the inventoried SearchGap fields; extras live under item `meta` only  
4. **Option B dual-source (preferred at CORE-011 merge):** union
   `provider_searches` zeros + knowledge gaps on the **same** inventoried
   endpoint; collection `meta.source = dual`, item `meta.source` =
   `provider_searches` | `knowledge_gaps`. Merger:
   `SearchGapDualSource` — see `docs/SEARCH_GAP_DUAL_SOURCE.md`.  
   No new OpenAPI paths.

## Resolution statuses

`open` | `researching` | `resolved` | `wont_fix`
