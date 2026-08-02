# Assist AI workstream — where we are

## Phases

| Phase | Meaning | Status |
| --- | --- | --- |
| AI-0 | Design/audit (24 deliverables) | **Complete** — `PHASE_AI0_DESIGN.md` + topic docs/ADRs |
| AI-1 | Deterministic orchestrator | **Complete** |
| AI-2 | Cache + budget | **Complete** |
| AI-3 | OpenAI intent interpreter | **Complete (off until configured)** |
| AI-4 / DATA-013 | Knowledge gaps + RIC SearchGap JSON + dual-source glue | **Complete on this branch** (Admin API wire on CORE-011 merge) |
| AI-5 | Dataset routing + offline OSM staging | **Complete (flag off)** |
| AI-6 | Traveller facilities | **Complete (flag off)** |
| AI-7 | Hardening + Ask CAPTCHA unlock | **Complete** |
| DATA-012 | Gov dataset catalogue → facilities | **Complete (review-first)** |
| Quality Gate | Production Ask | **CONDITIONAL PASS** — see `docs/AI_QUALITY_GATE_EVIDENCE.md` |

## Original prompt — complete

The original Assist AI Search and Knowledge Orchestration prompt is **satisfied
for design + implementation on this branch**:

- Shared orchestrator (not a chatbot; not a second search stack)
- NL Ask VanAssist alongside unchanged structured `/find`
- Provider-neutral AI abstraction; OpenAI via orchestrator only
- Deterministic → cache → budgeted AI → adapters → provenance → gaps → staging
- Cost hard stops; AI off until configured
- Traveller facilities separate from stays (ADR 0030/0029)
- Required docs + ADRs 0021–030/0032
- Test matrix covered under `tests/Unit/AiSearch` (+ DataSources)
- Locked Admin API Phase 1 OpenAPI **not** expanded
- `POST /api/v1/search/assist` **not** finalised (per prompt / `API.md`)

**Production-only holds (explicit, not missing code):** full Platform Quality
Gate PASS before enabling public Ask / facilities / paid AI; live AU coverage
via review-first ingest; CORE-011 merge wires Option B dual-source into
inventory `/search-gaps` (`docs/SEARCH_GAP_DUAL_SOURCE.md`; admin JSON export
already bridges knowledge-only).

## Operator bootstrap (non-production)

```bash
php scripts/migrate.php
php scripts/import-demo-traveller-facilities.php --approve
# optional OSM offline staging into DATA-006 review queue:
# AI_OSM_OFFLINE_ENABLED=1 php scripts/stage-osm-offline-seed.php --state=ACT --limit=50
```

Demo CLI refuses production `APP_ENV` unless `--force`. Then optionally enable
Ask / facilities flags after the `/admin/ai-search` release-gate panel is green.

## Production

Full Platform Quality Gate **PASS** required before production Ask / facilities / paid AI. Flags stay off by default.

## Branch

`feature/core-012-ai-1-deterministic`
