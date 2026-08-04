# Current Status

## Completed
- CORE-001 Brand registry; CORE-004 Entitlements; CORE-009/010 Garage handoffs
- CORE-011 Admin API Phase 1 + Option B A–L on unified tree + dual-source `/search-gaps`
- CORE-012 Assist AI Orchestrator (AI-0–AI-7); flags off; QG CONDITIONAL PASS
- DATA-006 Connectors; DATA-008/009 Regulatory; DATA-011 RIC live sync (code)
- **DATA-011A National Dataset Catalogue** (Platform `117`/ADR 0033 + RIC catalogue)
- DATA-012 Dataset Engine; DATA-013 Knowledge gaps; DATA-002 duplicates
- VAN-010/011 (flags); OPS-011; Polaris private slice POL-002–008 residuals
- Queensland essential facility coverage (merged to main)

## In Progress
- **RIC everyday management programme** (this track) — Admin API overview + website insights first
- OPS-010 / DATA-011 staging enablement rehearsal — **owner/ops**
- VAN-002 Claims end-to-end acceptance — **owner/staging**
- OPS-012 production Ask/facilities remain off

## Blocked
- Production `ADMIN_API_ENABLED` / Ask / facilities / paid AI
- POL-009 / LOC-003 / COM-004 launches
- Full Platform Quality Gate / production release

## Current task — RIC everyday management (dependency order)

1. **Platform Admin API** `feature/ric-management-admin-api`
   - `GET /api/v1/admin/overview` — operational rollup (health/version + website KPIs + queue counts + AI cost + last dataset sync)
   - `GET /api/v1/admin/website-insights` — reuse `WebsiteInsightsService` (bots labelled separately)
   - Expand RIC default scopes where needed (`corrections:read`, `duplicates:read`, `ai:read`)
   - OpenAPI + LIVE_API + PHPUnit
2. **RIC UI** `feature/ric-everyday-management` (from `origin/main`)
   - Overview page replaces local-only Dashboard KPIs when live API enabled
   - Then Directory / Data review / AI / Insights / Operations increments
3. Stop for owner PR approval before merge; no production flags; no deploy

## Audit snapshot (do not rebuild)
- Admin API already has providers/stays/facilities CRUD, claims, corrections, duplicates, drafts, datasets, AI usage, searches, gaps, recycle, audit, sync-conflicts
- Missing for Overview: website visitors/contacts API, single overview rollup
- RIC: research shell + Sync/Sources/Exports/Settings live; Dashboard local-only; no Ask learning UI

## Overall Completion %
91% programme; RIC everyday management **started** (API increment 1)

*Branch: `feature/ric-management-admin-api` from `origin/main`. Queensland branch left alone.*
