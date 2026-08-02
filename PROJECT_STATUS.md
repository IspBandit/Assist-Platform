# Current Status

## Completed
- CORE-001 Brand registry; CORE-004 Entitlements; CORE-009/010 Garage handoffs
- CORE-011 Admin API Phase 1 + Option B A–L on unified tree + dual-source `/search-gaps`
- CORE-012 Assist AI Orchestrator (AI-0–AI-7); flags off; QG CONDITIONAL PASS
- DATA-006 Connectors; DATA-008/009 Regulatory; DATA-011 RIC live sync (code)
- **DATA-011A National Dataset Catalogue** (`117`, ADR 0033) — portals/themes seeded; no auto-publish
- DATA-012 Dataset Engine including Admin API sync wire
- DATA-013 Knowledge gaps + dual-source `/search-gaps`
- DATA-002 Duplicate management (Admin API check/dry_run/merge/defer; soft-delete+audit)
- VAN-010 Claim-first onboarding (flag); VAN-011 Ask VanAssist (flag off)
- OPS-011 Recycle Bin (Admin API); INF-001/003; OPS-001; COM-006/007

## In Progress
- OPS-010 / DATA-011 staging enablement rehearsal — **owner/ops**
- OPS-012 VanAssist reliability (local CONDITIONAL PASS; production Ask/facilities off)
- VAN-002 Claims end-to-end acceptance — **owner/staging**
- POL-001–008 Polaris vertical slice (master prompt incomplete; launch blocked)
- CORE-002/003/005/007/008; EXP-*; VAN-001; DATA-001/004/005/010; INF-002; OPS-003/006; COM-001/002

## Blocked
- LOC-003 LocalTorque production launch (domain/DNS/legal)
- POL-009 Polaris public launch (domain + Quality Gate + real catalogue)
- COM-004 Billing gateway (owner choice + legal/tax)
- Production Ask / traveller facilities / paid AI / `ADMIN_API_ENABLED`
- DATA-011 staging probe append to QG evidence (requires staging environment)
- VAN-002 E2E acceptance (requires staging + human review)
- Full Platform Quality Gate sign-off

## Deferred
- Soft-dedupe across SearchGap dual sources; true dual-cursor pagination
- LPG/fuel facility coverage; FK-repoint duplicate merges
- Dataset-specific importers **after** DATA-011A (catalogue-first rule)
- Polaris AI import (`polaris_ai_import`) — paid AI; needs owner approval
- OPS-005 / COM-005 sale-readiness packs

## Remaining
1. Owner: staging Admin API rehearsal (OPS-010 / DATA-011)
2. Owner: VAN-002 E2E acceptance
3. Traveller Facilities residuals (DATA-012 coverage) only if gaps remain
4. Polaris non-AI roadmap residuals
5. Full Platform Quality Gate
6. Production release package

## Dependencies
- Task order: CORE-011 → DATA-011 → **DATA-011A** → Dataset Engine residuals → …
- Additional dataset importers blocked until DATA-011A catalogue is used
- Production Admin API enablement depends on OPS-010 staging evidence (owner)
- Paid AI / facilities require owner approval

## Current Task
None active — DATA-011A committed (`f9455e7`).

## Next Highest Priority Unblocked Task
Traveller Facilities coverage residuals (DATA-012) only if open; otherwise owner staging (OPS-010/DATA-011) or Polaris non-AI gaps. No new dataset-specific importers until catalogue rows drive them.

## Overall Completion %
85%

*Reason for order change vs prior pause: owner programme addendum inserted DATA-011A immediately after RIC synchronisation; catalogue before importers.*
