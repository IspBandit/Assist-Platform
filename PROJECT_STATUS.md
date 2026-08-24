# Current Status

Last aligned with production verification: 24 August 2026 (Australia/Brisbane).

## Completed
- CORE-001 Brand registry; CORE-004 Entitlements; CORE-009/010 Garage handoffs
- CORE-011 Admin API Phase 1 + Option B A–L on unified tree + dual-source /search-gaps
- CORE-012 Assist AI Orchestrator (AI-1–AI-7); **Ask + traveller facilities enabled on production VanAssist** (release `6a3f09d`); Quality Gate **CONDITIONAL PASS**
- DATA-006 Connectors; DATA-008/009 Regulatory; DATA-011 RIC live sync (code + production Admin API)
- **DATA-011A National Dataset Catalogue** (Platform 117/ADR 0033 + RIC catalogue)
- DATA-012 Dataset Engine; DATA-013 Knowledge gaps; DATA-002 duplicates
- VAN-010/011; OPS-011; Polaris private slice POL-002–008 residuals
- Queensland essential facility coverage (merged to main)
- **RIC third-wave + gap-fill facility dataset keys** (Platform migrations `125`–`126`, live release)
- **RIC third-wave Ready pack catalogue + orchestration** (assist-ric PR #30, PR #31)
- **RIC facility catalogue push to production** — local RIC SQLite records `last_live_upload_token` for original, third-wave and gap-fill facility packs (Aug 2026); live Ask returns traveller facilities
- **RIC everyday management A–H.4** — Overview through Operations, import-candidate reads + human review
- **RIC Increment I (Platform + UI)** — Operations Failed emails/tasks; Directory Categories/Towns tabs

## In Progress
- **Documentation accuracy** — reconciling August gate docs with verified production posture
- **VAN-002 Claims** — structured authority review, transactional approved-claim
  link and evidence-based verification are in the current release candidate;
  production acceptance remains pending
- **VAN-012 analytics accuracy** — traffic-quality filtering, directly measured
  returning visitors and Ask/stay outcomes are in the current release candidate
- **Admin-side facility audit** — confirm publish counts / import backlog in production MariaDB (operator)

## Blocked / owner-gated (not code-complete)
- **Full Platform Quality Gate PASS** (Ask/facilities are live but gate evidence still conditional)
- **Paid AI / Google Places connectors** — remain off by policy
- **POL-009 / LOC-003 / COM-004** launches
- **LocalTorque / Polaris** public domains
- Pre-launch checklist items in `docs/PRODUCTION_CURRENT_STATE.md` (candidate
  release, automated off-site backup and current restore evidence, credential
  rotation, owner journey sign-off)
- Feature-flag **writes**; provider hold/confirm/auto-link remain website admin

## Current task

1. ~~RIC everyday management A–H.4~~ — merged
2. ~~Increment I Platform + RIC UI~~ — merged
3. ~~Third-wave + gap-fill catalogue seeds and RIC orchestration~~ — merged; production upload recorded locally
4. Release and verify analytics accuracy, provider claims and local scheduled backup — **in progress**
5. Configure independent encrypted backup, rehearse restore and record the formal gate — **owner/ops**

## Overall Completion %
97% programme code; production VanAssist runs Ask, Admin API and traveller
facilities on release `046a2c6`; formal QG PASS and commercial launch remain
blocked by current independent backup/restore evidence and owner acceptance
