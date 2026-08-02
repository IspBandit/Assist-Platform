# Assist AI release criteria (AI-7 + DATA-012)

**Status:** checklist implemented; production still requires Platform Quality Gate.  
**Backlog:** CORE-012, DATA-012  
**Related:** [`PLATFORM_QUALITY_GATE.md`](PLATFORM_QUALITY_GATE.md), [`AI_OPERATIONS_RUNBOOK.md`](AI_OPERATIONS_RUNBOOK.md), [`DATA_012.md`](DATA_012.md)

## Conditional enablement (non-production / private)

Ask VanAssist may be enabled when:

1. Migrations through `110` + `097` applied (AI hardening `107`, facilities `108`, DATA-012 `109`/`110`, OSM offline `113`).
2. Feature flags intentional (`assist_ai_search`, optional `assist_ai_datasets`, optional `assist_ai_traveller_facilities`).
3. Paid AI remains off **or** caps + allowlist + env key are set.
4. `/ask` rate limit middleware present (20 / hour / IP) with Turnstile unlock when enabled.
5. Cron `ai_retention` scheduled (weekly recommended).
6. Admin release-gate panel shows no blocking checks (`AiReleaseGate`).
7. Structured `/find` verified unchanged.
8. Knowledge-gap click/contact attribution wired (`?g=` / `/ask/click`).
9. Offline OSM staging available (`stage-osm-offline-seed.php`); Ask never calls Overpass.

### Traveller facilities (DATA-012)

Before turning `assist_ai_traveller_facilities` on:

1. Import and **approve** at least one facility candidate via `/admin/data-sources/datasets` → Facility review (or real CKAN/ArcGIS/CSV/GeoJSON catalogue row).
2. Confirm gate check `traveller_facilities_populated` is green.
3. Confirm Ask shows facilities in a separate section (not as caravan parks).

## Production enablement (paid AI and/or public Ask + facilities)

Requires full Platform Quality Gate plus:

- Owner-approved pinned model snapshot (if paid AI on)
- Non-zero daily/monthly request and AUD caps (if paid AI on)
- `OPENAI_API_KEY` in server env/vault only (if paid AI on)
- Cost simulator used to set caps before enable
- Incident rollback steps rehearsed (`ai_enabled=0`, Ask/facilities flags off)
- Non-empty active reviewed/verified `traveller_facilities` if facilities flag on

## Explicitly not authorised by AI-7 / DATA-012 alone

- Public launch of Polaris
- Expanding Admin API OpenAPI with new `/traveller_facilities` resources
- Disabling rate limits or storing API keys in MariaDB
- `trusted_automatic` dataset policy without a recorded owner decision

Knowledge-gap RIC hand-off on this branch: `/admin/ai-search/gaps/export?format=json`
(SearchGap-shaped). Locked `GET /api/v1/admin/search-gaps` dual-source wiring lands with CORE-011 merge (`docs/SEARCH_GAP_DUAL_SOURCE.md`).