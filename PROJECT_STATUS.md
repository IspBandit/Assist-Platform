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
- **RIC everyday management programme** — Increment C Data Review in flight after A/B merged
- OPS-010 / DATA-011 staging enablement rehearsal — **owner/ops**
- VAN-002 Claims end-to-end acceptance — **owner/staging**
- OPS-012 production Ask/facilities remain off

## Blocked
- Production `ADMIN_API_ENABLED` / Ask / facilities / paid AI
- POL-009 / LOC-003 / COM-004 launches
- Full Platform Quality Gate / production release

## Current task — RIC everyday management (dependency order)

1. ~~Overview~~ — merged (Platform #161, RIC #4)
2. ~~Directory~~ — merged (Platform #163, RIC #5)
3. **Data Review (Increment C)** — Platform docs + `recycle_bin:restore` scope; RIC Data Review page (drafts/duplicates/recycle); claims/corrections remain on Directory
4. Next without pause: AI and knowledge gaps → Website insights → Operations
5. No production flags; no deploy without owner

## Audit snapshot (do not rebuild)
- Admin API already has providers/stays/facilities CRUD, claims, corrections, duplicates, drafts, datasets, AI usage, searches, gaps, recycle, audit, sync-conflicts, overview/website-insights
- Genuine remaining API gaps: facility/provider import-candidate queues; stale/missing quality lists
- RIC: Overview + Directory live; Research Queue remains local; Data Review next

## Overall Completion %
92% programme; RIC everyday management Increments A–B merged; C in progress

*Increment C branches from `origin/main` (`edb025a` / RIC `eac8612`).*
