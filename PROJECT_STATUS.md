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
- **RIC everyday management Operations (Increment F)** — docs + RIC Operations page
  contract (merged)
- **RIC Operations gaps (Increment G)** — merged
- **Import-candidate queues (Increment H)** — merged read-only
- **Facility candidate review (Increment H.1)** — merged
- **Provider candidate review (Increment H.2)** — merged human Admin API approve/reject

## In Progress
- **Facility candidate bulk review (Increment H.3)** — human-only Admin API
  bulk-approve/bulk-reject
- OPS-010 / DATA-011 staging enablement rehearsal — **owner/ops**
- VAN-002 Claims end-to-end acceptance — **owner/staging**
- OPS-012 production Ask/facilities remain off

## Blocked
- Production `ADMIN_API_ENABLED` / Ask / facilities / paid AI
- POL-009 / LOC-003 / COM-004 launches
- Full Platform Quality Gate / production release
- Ops completeness: failed-job queue Admin API; feature-flag **writes**;
  provider-candidate **merge** remains website admin only

## Current task — RIC everyday management (dependency order)

1. ~~Overview~~ — merged
2. ~~Directory~~ — merged
3. ~~Data Review~~ — merged
4. ~~Ask Insights~~ — merged
5. ~~Website Insights~~ — merged (RIC #10)
6. ~~Operations (Increment F docs)~~ — merged
7. ~~Operations gaps (Increment G)~~ — merged
8. ~~Import-candidate queues (Increment H)~~ — merged
9. ~~Facility candidate review (Increment H.1)~~ — merged
10. ~~Provider candidate review (Increment H.2)~~ — merged
11. **Facility candidate bulk review (Increment H.3)** — in flight
12. No production flags; no deploy without owner

## Overall Completion %
95% programme; RIC everyday management A–H.2 merged; H.3 in flight
