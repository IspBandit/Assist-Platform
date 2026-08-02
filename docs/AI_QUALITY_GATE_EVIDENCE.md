# Assist AI Quality Gate evidence (CORE-012)

**Date:** 2026-08-02  
**Branch:** `feature/core-012-ai-1-deterministic`  
**Backlog:** CORE-012, VAN-011, DATA-012, DATA-013  
**Overall gate:** **CONDITIONAL PASS** — non-production / private enablement only.  
Production Ask + facilities + paid AI remain **prohibited** until a full Platform Quality Gate pass is recorded on a release candidate.

## Architecture — CONDITIONAL PASS

| Evidence | Status |
| --- | --- |
| ADRs 0018–0027 + **0029** (stays vs facilities) | Present |
| Flags default off (`assist_ai_search`, `assist_ai_datasets`, `assist_ai_traveller_facilities`) | Seeded / migrations |
| Never overload `caravan_parks` | Adapter + ingest publish to `traveller_facilities` only |
| Catalogue provenance isolated per dataset (`gov:{dataset_key}`) | Toilet Map toilet vs dump rows cannot overwrite |
| Admin API Phase 1 OpenAPI not expanded | Knowledge gaps use SearchGap-shaped JSON via admin export; dual-source glue ready (`docs/SEARCH_GAP_DUAL_SOURCE.md`); wire on CORE-011 merge |
| Migrations forward-only `085`–`094`, `097` (OSM offline; `095`/`096` are Polaris) | On branch |

## UX — CONDITIONAL PASS

| Evidence | Status |
| --- | --- |
| Ask VanAssist parallel to `/find` | `/ask` flagged |
| Facilities labelled separately from providers/stays | `assist-search.php` section |
| Dataset candidates labelled pending review | Provenance labels |
| Empty / clarification states | Orchestrator messages |

**Remaining for production PASS:** desktop/mobile rendered review of Ask + facilities with live curated data.

## Engineering — CONDITIONAL PASS

| Evidence | Status |
| --- | --- |
| Unit tests AiSearch + DataSources | `vendor/bin/phpunit tests/Unit/AiSearch tests/Unit/DataSources` |
| PHPStan on AiSearch / DataSources | Level 5 clean on touched paths |
| Release gate panel | `/admin/ai-search` (`AiReleaseGate`) |
| Demo populate script | `php scripts/import-demo-traveller-facilities.php --approve` |
| Knowledge gap RIC JSON | `/admin/ai-search/gaps/export?format=json` |

**Remaining for production PASS:** full CI green on release candidate; Composer production build; ops health/backup rehearsal recorded.

## Business — CONDITIONAL PASS

| Evidence | Status |
| --- | --- |
| Paid AI off by default | Caps + allowlist required before enable |
| No invented listings | ADR 0019 |
| Demo fixtures not official government truth | Catalogue attribution |
| Curated AU Toilet Map rows disabled until operator Fetch + approve | Migration `094` |

**Remaining for production PASS:** owner sign-off on public Ask; legal/licence review of live AU dataset enablement; analytics acceptance for Ask channel.

## Named conditions (expiry: until next release cut)

1. Keep Ask / datasets / facilities / paid AI flags **off** in production until a full Platform QG **PASS**.
2. Non-production may enable Ask after migrations `085`–`094` + `097`, Ask rate
   limit + Turnstile unlock, retention cron, and green release-gate panel.
3. Facilities flag only after reviewed `traveller_facilities` rows exist (`import-demo-… --approve` or DATA-012 approve).
4. Do not treat demo fixtures as national coverage.

## Rollback

- `assist_ai_search=0`, `assist_ai_traveller_facilities=0`, `assist_ai_datasets=0`, `ai_enabled=0`
- Structured `/find` unchanged

## Approver

_Pending owner / release manager signature on production candidate._
