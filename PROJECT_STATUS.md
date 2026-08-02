# Current Status

## Completed
- CORE-001 Brand registry; CORE-004 Entitlements; CORE-009/010 Garage handoffs
- CORE-011 Admin API Phase 1 + Option B A–L on unified tree + dual-source `/search-gaps`
- CORE-012 Assist AI Orchestrator (AI-0–AI-7); flags off; QG CONDITIONAL PASS
- DATA-006 Connectors; DATA-008/009 Regulatory; DATA-011 RIC live sync (code)
- DATA-012 Dataset Engine including Admin API sync wire
- DATA-013 Knowledge gaps + dual-source `/search-gaps`
- DATA-002 Duplicate management (Admin API check/dry_run/merge/defer; soft-delete+audit; HTML list-only)
- VAN-010 Claim-first onboarding (flag); VAN-011 Ask VanAssist (flag off)
- OPS-011 Recycle Bin (Admin API); INF-001/003; OPS-001; COM-006/007

## In Progress
- OPS-010 production Admin API enablement (staging rehearsal) — owner/ops
- OPS-012 VanAssist reliability (local CONDITIONAL PASS; production Ask/facilities off)
- VAN-002 Claims end-to-end acceptance
- POL-001–008 Polaris vertical slice (master prompt incomplete; launch blocked)
- CORE-002/003/005/007/008; EXP-*; VAN-001; DATA-001/004/005/010; INF-002; OPS-003/006; COM-001/002

## Blocked
- LOC-003 LocalTorque production launch (domain/DNS/legal)
- POL-009 Polaris public launch (domain + Quality Gate + real catalogue)
- COM-004 Billing gateway (owner choice + legal/tax)
- Production Ask / traveller facilities / paid AI / `ADMIN_API_ENABLED`
- DATA-011 staging probe append to QG evidence (requires staging environment)
- Full Platform Quality Gate sign-off (Architecture/UX/Engineering/Business)

## Deferred
- Soft-dedupe across SearchGap dual sources; true dual-cursor pagination
- LPG/fuel facility coverage; Socrata/KML connectors; FK-repoint duplicate merges
- OPS-005 / COM-005 sale-readiness packs

## Remaining
1. VAN-002 claim/assistance end-to-end acceptance
2. AI Search production gate (flags + QG — owner)
3. Polaris roadmap gaps only (master prompt)
4. Full Platform Quality Gate
5. Production release package

## Dependencies
- Production Admin API enablement depends on OPS-010 staging evidence (owner)
- Paid AI / facilities require owner approval + AI Release Criteria
- Polaris / LocalTorque launch need domains + QG

## Current Task
Session closeout after DATA-002 — next code task is VAN-002 acceptance residuals if unblocked, else Polaris gaps.

## Next Highest Priority Unblocked Task
VAN-002 claims/corrections end-to-end acceptance (Admin API Increment B shipped; finish acceptance/tests/docs only).

## Overall Completion %
84%
