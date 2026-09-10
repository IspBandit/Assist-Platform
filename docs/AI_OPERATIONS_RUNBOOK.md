# AI operations runbook

**Status:** AI-1–AI-7 foundation live (AI-6 facilities flag off by default;
DATA-012 catalogue/connectors available for review-first populate). Paid AI off
by default.

## Enable Ask VanAssist (no paid AI)

The optional Stage 1 explanation is controlled by `assist_ai_outcomes`. It shows
the interpreted need/location/radius, evidence-backed fit reasons, honest
distance method and safest next action. Disabling it restores the prior Ask
presentation without changing search or ranking.

1. Apply migrations `085`–`094`.  
2. Enable feature flag `assist_ai_search` via `/admin/ai-search` or Feature flags.  
3. Confirm `/ask` responds on VanAssist; `/find` unchanged.  
4. Schedule weekly cron: `php scripts/cron.php ai_retention`.  
5. Review release-gate panel on `/admin/ai-search` (see `docs/AI_RELEASE_CRITERIA.md`).  
6. For facilities: import/approve via `/admin/data-sources/datasets` before
   enabling `assist_ai_traveller_facilities`.

## Populate traveller facilities (DATA-012)

1. Apply migrations `108`–`110`.  
2. Non-production CLI: `php scripts/import-demo-traveller-facilities.php --approve`  
   (refuses production `APP_ENV` unless `--force`).  
   Or `/admin/data-sources/datasets` → Import fixture / enable+Fetch → Facility review.  
3. Confirm release-gate checks `data012_ingest_wired` and
   `traveller_facilities_populated` before enabling
   `assist_ai_traveller_facilities`.  
4. Details: `docs/DATA_012.md`.

## Enable paid AI (AI-3 — owner approval required)

1. Confirm owner approval of a **pinned** model snapshot and non-zero caps.  
2. Set `OPENAI_API_KEY` in server env / vault (never MariaDB, never commit).  
3. Optional: `OPENAI_API_BASE` (default `https://api.openai.com/v1`).  
4. On `/admin/ai-search`: allowlist approved snapshot, set caps, enable OpenAI,
   then global AI.  
5. Smoke-test golden queries on non-production.  
6. Monitor `/admin/ai-search` soft-warning thresholds.  
7. See pricing/schema notes in `docs/OPENAI_INTEGRATION.md` (re-verify live).

## Incident: unexpected spend

1. Disable global AI immediately on `/admin/ai-search`.  
2. Rotate `OPENAI_API_KEY`.  
3. Inspect `ai_usage_events` / audit for abuse.  
4. Confirm structured search still healthy.  
5. Post-incident review before re-enable.

## Incident: bad interpretations

1. Raise deterministic rules / lower AI confidence threshold.  
2. Flush or version-bump intent cache (`taxonomy` / `rules` version change).  
3. Do not “fix” by inventing listings.

## Rollback

Feature flags and `ai_enabled=0` restore pre-paid-AI public behaviour.
Forward migrations leave unused tables if operationally rolled back.
