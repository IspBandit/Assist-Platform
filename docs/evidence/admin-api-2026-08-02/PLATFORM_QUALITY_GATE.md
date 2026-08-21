# CORE-011 / OPS-010 / DATA-011 — Admin API + Assist RIC Quality Gate

**Date:** 2026-08-02  
**Scope:** Versioned `/api/v1/admin` Phase 1 foundation, TOTP MFA, staging
tooling, and Assist RIC live Admin API client (Option B).  
**Overall gate:** **CONDITIONAL PASS** — merge and non-production enablement
allowed; production `ADMIN_API_ENABLED=true` remains prohibited until staging
rehearsal evidence and an explicit Business pass are recorded.

## Backlog

| ID | Outcome | Status after this gate |
| --- | --- | --- |
| CORE-011 | Versioned Admin API | **done** (PRs #139–#142) |
| OPS-010 | Admin API security / MFA / cost controls | **in progress** — code done; production MFA/enable flags wait on staging + Business |
| OPS-011 | Recycle bin lifecycle | **done** (shipped in CORE-011 Phase 1) |
| DATA-011 | Assist RIC live Admin API sync | **done** for client foundation; staging sync rehearsal remains operational |

## Architecture — PASS

- ADRs 0018–0020 accepted: Admin API is the only external write path; stays vs
  traveller facilities; Assist RIC is the management client (Option B).
- No direct production MariaDB access from RIC or importers.
- Brand scope server-enforced; service accounts least-privilege
  (`AdminApiScopes::RIC_SERVICE`).
- Forward-only migrations `085`–`087` (renumbered after main claimed 080–084).
- Feature remains disabled by default (`ADMIN_API_ENABLED=false`).
- Rollback: leave flags false; revoke service accounts; RIC falls back to
  checksummed export packages.

## UX — CONDITIONAL PASS

- Admin API is an operator/machine surface, not a public traveller UI.
- Assist RIC Settings/Exports expose enablement, Test connection, Validate-only
  submit, Pull search gaps, and persisted sync status (no secrets in SQLite).
- No competing design system; no Tauri/React management app introduced.
- Condition: public brand UX unchanged; API error envelopes are machine-readable.

## Engineering — PASS

- PHPUnit Admin API suite green (100+ focused tests including TOTP, scopes,
  routes, recycle/draft/import, RIC contract).
- `composer analyse` (PHPStan) green after MFA login return-type union.
- Living documentation governance + OpenAPI `docs/openapi/admin-v1.yaml` updated.
- CLI: `admin-api-create-ric-service-account.php`, `admin-api-probe.php`.
- Assist RIC unit/GUI tests for live_api client, mapper, status, Settings/Exports.
- Skipped: live staging MySQL rehearsal and production enablement smoke
  (require operator environment) — recorded as **risk**, not a pass for
  production flags.

## Business — CONDITIONAL PASS

- Value: trusted RIC ingest without opening production DB; recycle/drafts/audit
  for operators.
- API stays off in production until owner staging sign-off.
- MFA enrollment path exists; `ADMIN_API_MFA_REQUIRED` defaults false.
- No billing, publish-automation, or traveller-facility claims implied.
- Condition: do not market Admin API as a public partner API.

## Gate result

**CONDITIONAL PASS**

Allowed now:

- Land code on `main` (already merged through PR #142 + RIC `main`).
- Enable on local/staging with restricted mode and rehearsed checklist in
  `docs/LIVE_API.md`.

Not allowed yet:

- Production `ADMIN_API_ENABLED=true` or `ADMIN_API_MFA_REQUIRED=true`
  without a follow-up Quality Gate record citing staging probe + RIC
  validate-only import evidence.

## Approver / date

Owner review required for production flag flip. Engineering closeout recorded
2026-08-02 against PRs #139–#142 and Assist RIC DATA-011 commits through
sync-status persistence.

## Release and rollback notes

1. Deploy code with flags **false**.
2. On staging: migrate → create RIC service account → probe → RIC validate-only.
3. Production enablement only after Business pass update to this evidence folder.
4. Rollback: set flags false, revoke `api_oauth_clients`, rotate secrets.

## Documentation impact

Updated: `docs/LIVE_API.md`, `docs/PHASE1_ADMIN_API_DESIGN.md`,
`docs/RELEASE_NOTES.md`, `docs/PRODUCT_BACKLOG.md`, `docs/OPERATIONS_RUNBOOK.md`,
`docs/user-guide/api-guide/current-api-boundary.md`, OpenAPI, Assist RIC
`docs/architecture/live-api-sync.md`.
