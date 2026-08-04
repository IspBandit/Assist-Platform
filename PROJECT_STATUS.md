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
- **RIC everyday management A–E** — Overview, Directory, Data Review, Ask Insights,
  Website Insights (merged)
- **RIC everyday management Operations (Increment F–G)** — merged
- **Import-candidate queues (Increment H)** — merged read-only
- **Facility/provider candidate review (Increment H.1–H.3)** — merged
  (single + facility bulk)

## In Progress
- **Provider candidate merge (Increment H.4)** — human-only Admin API merge
- OPS-010 / DATA-011 staging enablement rehearsal — **owner/ops**
- VAN-002 Claims end-to-end acceptance — **owner/staging**
- OPS-012 production Ask/facilities remain off

## Blocked
- Production `ADMIN_API_ENABLED` / Ask / facilities / paid AI
- POL-009 / LOC-003 / COM-004 launches
- Full Platform Quality Gate / production release
- Ops completeness: failed-job queue Admin API; feature-flag **writes**;
  provider hold/confirm/auto-link remain website admin only

## Current task — RIC everyday management (dependency order)

1. ~~Overview through H.3~~ — merged
2. **Provider candidate merge (Increment H.4)** — in flight
3. No production flags; no deploy without owner

## Overall Completion %
95% programme; RIC everyday management A–H.3 merged; H.4 in flight
