# SearchGap dual-source (Option B) — merge plan

**Backlog:** DATA-013, CORE-011, DATA-011  
**Status on AI branch:** glue + docs landed; Admin API wiring waits for CORE-011 merge  
**Decision:** Prefer a single inventoried endpoint
`GET /api/v1/admin/search-gaps` that unions `provider_searches` zeros and
`knowledge_gaps`, distinguished by `meta.source` — **do not** invent a second
Admin API path or expand locked Phase 1 OpenAPI schemas beyond optional
description text.

Production Ask / facilities / datasets / paid AI flags stay **off**.

---

## 1. What exists where

### Sibling checkout `Assist-Platform-admin-api` (main / CORE-011)

| Piece | Path |
| --- | --- |
| Service | `app/Services/Api/AdminApiSearchGapService.php` |
| Controller | `app/Controllers/Api/V1/Admin/SearchGapController.php` |
| Route | `routes/api_v1_admin.php` → `GET /search-gaps` (`analytics:read`) |
| OpenAPI | `docs/openapi/admin-v1.yaml` — `/search-gaps`, `SearchGap`, `SearchGapCollectionResponse` |
| Behaviour today | Aggregates `provider_searches` where `result_count = 0`; collection `meta.source = provider_searches` |
| Contract tests | `tests/Contract/AdminApiRicContractTest.php`, `tests/Unit/AdminApiAuditSearchMfaTest.php` |

`SearchGap` required fields: `query_text`, `result_count`, `search_count`,
`first_seen`, `last_seen`. Optional: `location_text`, `intent`, `urgency_score`,
`town_id`, `category_id`. Collection `meta` / `links` allow
`additionalProperties: true` — so dual-source fields fit **without** a schema
break. Per-item `meta` is likewise allowable (not forbidden).

### This AI branch (`Assist-Platform`)

| Piece | Path |
| --- | --- |
| Gap engine | `app/Platform/AiSearch/Knowledge/KnowledgeGapService.php` |
| Mapper | `KnowledgeGapService::toSearchGapItems()` — locked SearchGap fields + item `meta.source = knowledge_gaps` |
| Admin export bridge | `KnowledgeGapService::searchGapCollection()` + `/admin/ai-search/gaps/export?format=json` |
| Dual-source merger | `app/Platform/AiSearch/Knowledge/SearchGapDualSource.php` (**landed here**) |
| Unit tests | `tests/Unit/AiSearch/KnowledgeGapServiceTest.php`, `tests/Unit/AiSearch/SearchGapDualSourceTest.php` |
| Admin API SearchGap service | **Absent** (partial `app/Services/Api/AdminApi*.php` auth helpers only — no route/controller for `/search-gaps`) |
| OpenAPI `/search-gaps` | **Absent** on this branch |

Bridge until merge: administrators use
`/admin/ai-search/gaps/export?format=json` (knowledge gaps only).

---

## 2. Target behaviour (post-merge)

```
GET /api/v1/admin/search-gaps?from=&to=&limit=&cursor=&q=
  → AdminApiSearchGapService::list
       1. Load provider_searches zeros (existing query; stamp item meta.source)
       2. Load knowledge_gaps via KnowledgeGapService::listForAdmin + toSearchGapItems
          (default status=open; optional query status=… if added later under meta only)
       3. Optional: SearchGapDualSource::filterByDateWindow on knowledge items
       4. SearchGapDualSource::merge(provider, knowledge, limit, baseMeta)
       5. Return AdminApiEnvelope::collection(items, meta, links)
```

### Collection `meta` (additive)

| Field | Value |
| --- | --- |
| `source` | `dual` |
| `sources` | `["provider_searches","knowledge_gaps"]` |
| `provider_searches_count` | int (pre-truncate contribution) |
| `knowledge_gaps_count` | int (pre-truncate contribution) |
| `truncated` | bool when merged set exceeded `limit` |
| `sparse` | true when `data` empty |
| Existing | `from`, `to`, `brand_id`, `brand_key`, `count`, `limit`, cursor fields |

When `knowledge_gaps` table missing or empty, still return `source: dual` with
`knowledge_gaps_count: 0` (honest dual contract). If `provider_searches` missing,
return knowledge-only items under the same dual envelope (or empty dual) —
do **not** flip back to inventing a second path.

### Item `meta.source`

| Origin | `meta.source` |
| --- | --- |
| Demand analytics zeros | `provider_searches` |
| Ask / NL knowledge gaps | `knowledge_gaps` (+ `gap_id`, quality, resolution, …) |

RIC may filter client-side by `meta.source`. No OpenAPI required-field changes.

### Sorting / limit

1. Sort merged list by `urgency_score` DESC, then `search_count` DESC, then
   `last_seen` DESC.
2. Apply `limit` after merge (provider query may over-fetch; see §3).
3. Soft-dedupe across sources is **out of scope** for v1 — keep both signals
   when the same query appears in both tables.

### Cursor pagination note

Current CORE-011 cursor is offset-based on the provider SQL page. Dual-source
v1 recommendation: **fetch provider page + knowledge window, merge, then
slice to `limit`**, and document that `has_more` / cursor remain
provider-primary until a follow-up designs a true dual cursor. Prefer
over-fetching provider (`limit` or `limit * 2`) so merge is not starved.
Exact cursor semantics must stay compatible with existing RIC clients —
if RIC only consumes the first page, ship dual first page correctly and
record cursor follow-up in the PR.

---

## 3. Exact merge steps (when joining AI branch ↔ CORE-011)

Work on a feature branch after merging CORE-011 Admin API into the AI line (or
cherry-picking AI knowledge gaps into admin-api). Order:

### Step A — Bring code together

1. Ensure migrations `089_assist_knowledge_gaps.sql` (+ later AI migrations as
   needed) apply on the target DB.
2. Ensure these AI-branch files are present:
   - `app/Platform/AiSearch/Knowledge/KnowledgeGapService.php`
   - `app/Platform/AiSearch/Knowledge/SearchGapDualSource.php`
   - related tests under `tests/Unit/AiSearch/`
3. Ensure CORE-011 files are present:
   - `AdminApiSearchGapService`, `SearchGapController`, routes, OpenAPI,
     contract tests

### Step B — Wire `AdminApiSearchGapService::list`

In `app/Services/Api/AdminApiSearchGapService.php`:

1. Keep existing date/`q`/brand/`provider_searches` aggregation.
2. After mapping provider rows with `mapGap()`, stamp each item:

   ```php
   $item['meta'] = ['source' => 'provider_searches'];
   ```

3. Load knowledge gaps (brand key from `AdminApiBrandScope::brand()->id()`):

   ```php
   $gapSvc = new \App\Platform\AiSearch\Knowledge\KnowledgeGapService();
   $knowledgeRows = $gapSvc->listForAdmin($brandKey, KnowledgeGapService::STATUS_OPEN, $fetchLimit);
   $knowledgeItems = $gapSvc->toSearchGapItems($knowledgeRows);
   $dual = new \App\Platform\AiSearch\Knowledge\SearchGapDualSource();
   $knowledgeItems = $dual->filterByDateWindow($knowledgeItems, $from, $to);
   ```

4. Merge:

   ```php
   $merged = $dual->merge($providerItems, $knowledgeItems, $limit, [
       'from' => $from,
       'to' => $to,
       'brand_id' => $brandId,
       'brand_key' => $brandKey,
       // preserve next_cursor / has_more policy documented above
   ]);
   ```

5. Return `$merged['items']` + `$merged['meta']` (+ `links`).

6. When `provider_searches` table is missing, still attempt knowledge path and
   merge with `[]` provider items (do not early-return empty-only unless both
   unavailable).

### Step C — OpenAPI description only (no schema break)

Update `docs/openapi/admin-v1.yaml` `/search-gaps` **description** text to
state dual-source + `meta.source` / item `meta.source`. Do **not** add a new
path, do **not** change `SearchGap` required properties. Optional: document
example `meta.source: dual` under collection meta in prose.

### Step D — Docs + tests on the merged tree

1. Update `docs/LIVE_API.md` and `docs/PHASE1_ADMIN_API_DESIGN.md` search-gaps
   bullets to dual-source.
2. Extend Admin API unit/contract tests:
   - capabilities still `search_gaps: read`
   - response items may include either source
   - collection `meta.source === dual`
3. Keep `SearchGapDualSourceTest` + `KnowledgeGapServiceTest::testToSearchGapItemsMapsLockedContractFields`.
4. Update `docs/KNOWLEDGE_GAPS.md`, `docs/AI_WORKSTREAM_STATUS.md`,
   `CHANGELOG.md`, `docs/RELEASE_NOTES.md`.

### Step E — RIC

RIC already targets inventoried `GET /search-gaps`. Teach the client to read
`meta.source` / item `meta.source` (ignore unknown meta keys). No second
endpoint.

---

## 4. Explicit non-goals

- Do **not** enable `assist_ai_search`, `assist_ai_traveller_facilities`,
  `assist_ai_datasets`, or paid AI in production as part of this work.
- Do **not** add `GET /api/v1/admin/knowledge-gaps` or similar.
- Do **not** auto-publish listings from gaps.
- Do **not** edit applied migrations; forward-only if schema changes are needed
  (none required for dual-source read).

---

## 5. Pre-merge verification (this AI branch)

```bash
composer validate --strict
# when PHPUnit available:
./vendor/bin/phpunit tests/Unit/AiSearch/SearchGapDualSourceTest.php
./vendor/bin/phpunit tests/Unit/AiSearch/KnowledgeGapServiceTest.php
```

Admin export bridge remains valid:

`GET /admin/ai-search/gaps/export?format=json` → knowledge-only
`SearchGapCollectionResponse` with `meta.source = knowledge_gaps`.

---

## 6. Rollback

Dual-source is read-only aggregation. Rollback = revert the
`AdminApiSearchGapService` wiring commit; knowledge tables and admin export
bridge are unaffected. No production flag changes.
