# Option B Management Programme — Quality Gate

**Date:** 2026-08-02  
**Scope:** Increments A–L (Admin API expansion, claim-first onboarding, PHP
admin polish, Assist RIC sync console).  
**Overall gate:** **CONDITIONAL PASS** for merge to `main` and non-production
enablement. Production `ADMIN_API_ENABLED=true` still requires staging
rehearsal evidence appended below.

## Architecture — PASS

- ADRs 0018–0020 and RIC ADR 0010 unchanged and authoritative.
- No Tauri/React/FastAPI management app introduced.
- External writes remain on `/api/v1/admin` only.
- Stays vs `traveller_facilities` preserved (ADR 0019).
- Migrations 088–092 forward-only after Admin API 085–087.

## UX — CONDITIONAL PASS

- PHP admin: claim-first register flow, API service accounts, recycle bin.
- RIC: Sync page (pull/conflicts/gaps), Sources dataset catalogue, budget strip.
- Condition: production traveller UX for claim-first should be smoke-tested on
  staging before broad marketing.

## Engineering — PASS (code) / CONDITIONAL (staging)

- Platform Admin API Option B resources + PHPUnit AdminApi filter green at
  implementation time (169+ tests).
- Claim-first unit tests green.
- RIC unit/contract tests for live_api pull, matching, gap research green.
- Skipped: live staging migrate 088–092 + RIC validate-only against real host
  (operator environment required).

## Business — CONDITIONAL PASS

- Functional coverage of the management-platform specification via Option B.
- API and AI/paid connectors remain disabled by default.
- Do not market Admin API as a public partner API.
- Production flag flip waits on staging checklist in `docs/LIVE_API.md`.

## Staging rehearsal checklist (append results)

1. [ ] Apply migrations through `092_admin_api_sync_conflicts.sql`
2. [ ] `ADMIN_API_ENABLED=true`, `ADMIN_API_RESTRICTED=true` on staging
3. [ ] `php scripts/admin-api-create-ric-service-account.php …`
4. [ ] `php scripts/admin-api-probe.php …`
5. [ ] RIC: Test connection → Pull providers → Validate-only import
6. [ ] RIC: Pull search gaps → Start research from one gap
7. [ ] Human MFA enroll; optional `ADMIN_API_MFA_REQUIRED=true` smoke
8. [ ] Claim-first register smoke on staging VanAssist host
9. [ ] Update this gate to **Pass** before production enablement

## Rollback

Set `ADMIN_API_ENABLED=false` and `CLAIM_FIRST_ONBOARDING=false` if needed;
revoke service accounts; disable RIC live sync; forward compensating migrations
only.
