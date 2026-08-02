# Live Admin API (v1)

**Status:** design accepted; implementation in progress under CORE-011.  
**Not yet a supported production partner API until MFA gate and Quality Gate
evidence are recorded.**

## Boundary

- System of record: Assist Platform Enterprise (PHP / MariaDB).
- External tools (Assist RIC, importers): **HTTPS `/api/v1/admin` only**.
- Never connect desktop tools to production MariaDB (ADR 0018).

## Documents

| Doc | Purpose |
| --- | --- |
| `docs/PHASE1_ADMIN_API_DESIGN.md` | Phase 1 endpoint inventory, auth, lifecycle, migrations, tests |
| `docs/API.md` | Cross-cutting envelope, pagination and mutation standards |
| `docs/DECISIONS/0018-admin-api-no-direct-db.md` | No direct DB |
| `docs/DECISIONS/0019-stays-vs-traveller-facilities.md` | `/stays` vs future traveller facilities |
| `docs/DECISIONS/0020-ric-as-management-client.md` | Option B |
| `docs/openapi/admin-v1.yaml` | Contract (added during implementation) |

## Sibling client

Assist RIC lives at `D:\Works _in_progress\assist-ric` (sibling repository).
It is the initial research, staging and synchronisation client (DATA-011).
See RIC `docs/architecture/adr/0003-sibling-repository.md` and the forthcoming
live-api sync ADR in that repo.

## Phase 1 capabilities (target)

Increment 1 (routing foundation) exposes:

- `GET /api/v1/admin/health`
- `GET /api/v1/admin/version`
- `GET /api/v1/admin/capabilities`

Increment 2 (human auth) adds:

- `POST /api/v1/admin/auth/login`
- `POST /api/v1/admin/auth/refresh`
- `POST /api/v1/admin/auth/logout`
- `GET /api/v1/admin/auth/me`
- `GET /api/v1/admin/auth/sessions`
- `DELETE /api/v1/admin/auth/sessions/{id}`

Increment 3 (service accounts) adds:

- `POST /api/v1/admin/auth/token` — client id + secret → short-lived access token
- `GET /api/v1/admin/service-accounts` — list (human + `service_accounts:admin`)
- `POST /api/v1/admin/service-accounts` — create (secret returned once)
- `GET/PATCH/DELETE /api/v1/admin/service-accounts/{id}` — read, update, revoke
- `POST /api/v1/admin/service-accounts/{id}/rotate` — rotate secret
- `/capabilities` includes scope catalog and `service_accounts: active`

Increment 4 (read-only directory) adds:

- `GET /api/v1/admin/providers` — cursor list (`providers:read`)
- `GET /api/v1/admin/providers/{id}` — detail
- `GET /api/v1/admin/stays` — cursor list (`stays:read`, VanAssist parks module)
- `GET /api/v1/admin/stays/{id}` — detail
- Filters: `q`, `status`/`lifecycle`, `town`, `state`; brand from host context

Increment 5 (audited writes + lifecycle) adds:

- `POST /api/v1/admin/providers` — create (`providers:write`)
- `PATCH /api/v1/admin/providers/{id}` — update allowed fields
- `POST /api/v1/admin/providers/{id}/publish|unpublish|archive|restore` — lifecycle (`lifecycle:write`)
- `DELETE /api/v1/admin/providers/{id}` — soft-delete; JSON body `reason` (min 3 chars)
- Same write/lifecycle pattern for `/stays` with `stays:write` + `lifecycle:write`
- All mutations audited via `AdminApiAudit`; capabilities report `read_write`

Increment 6 (recycle bin) adds:

- `GET /api/v1/admin/recycle-bin` — list soft-deleted providers/stays (`recycle_bin:restore` or `recycle_bin:purge`)
- `GET /api/v1/admin/recycle-bin/{entity_type}/{id}` — deleted item detail
- `POST /api/v1/admin/recycle-bin/{entity_type}/{id}/restore` — restore (`recycle_bin:restore`)
- `DELETE /api/v1/admin/recycle-bin/{entity_type}/{id}/purge` — permanent purge; `{confirm:true, reason}` (`recycle_bin:purge`)
- `POST /api/v1/admin/recycle-bin/bulk-restore` and `/bulk-purge` — batch with `Idempotency-Key`

Increment 7 (drafts + imports) adds:

- `GET/POST /api/v1/admin/drafts`, `GET/PATCH /drafts/{id}` — RIC draft queue (`drafts:read` / `drafts:write`)
- `POST /api/v1/admin/drafts/{id}/approve|reject` — human-elevated (`drafts:approve`)
- `POST /api/v1/admin/imports` — checksummed package ingest (`imports:write`, `Idempotency-Key`)
- `GET /api/v1/admin/imports/{id}`, `POST .../validate`, `POST .../stage` — validation and draft staging

Increment 8 (audit + search gaps) adds:

- `GET /api/v1/admin/audit` — cursor list (`audit:read`); filters `action`, `object_type`, `object_id`, `user_id`, `from`, `to`, `q`
- `GET /api/v1/admin/audit/{id}` — single audit event
- `GET /api/v1/admin/search-gaps` — dual-source ranked gaps from `provider_searches` zeros + open `knowledge_gaps` (`analytics:read`); collection `meta.source=dual`; sparse/empty when both sources contribute nothing

Increment 8b / OPS-010 (MFA TOTP) adds:

- `POST /api/v1/admin/auth/mfa/challenge` — human bearer; enrollment status
- `POST /api/v1/admin/auth/mfa/enroll/begin` — issue TOTP secret + otpauth URI
- `POST /api/v1/admin/auth/mfa/enroll/confirm` — confirm with authenticator code
- `POST /api/v1/admin/auth/mfa/verify` — validate TOTP; completes MFA login when
  using an `mfa_token` from password login under `ADMIN_API_MFA_REQUIRED=true`

Increment 9 (RIC contract tests) adds:

- `tests/Contract/AdminApiRicContractTest.php` — Phase 1 path inventory, OpenAPI parity, mock-client health/auth checks

Option B Increments B–G add:

- `GET /api/v1/admin/claims`, `GET /claims/{id}` — park claims + provider invites (`claims:read`)
- `POST /claims/{id}/approve|reject|request-evidence` — human park-claim review (`claims:write`)
- `GET /api/v1/admin/corrections`, `GET /corrections/{id}`, `POST .../approve|reject` — listing corrections
- `GET /api/v1/admin/duplicates`, `POST /duplicates/check`, merge/defer/not-duplicate, merge-history
- `GET/PATCH /api/v1/admin/datasets`, `POST /datasets/{id}/sync`, sync-history
- `GET /api/v1/admin/ai/usage/*`, `GET /ai/cache-performance` — empty-safe AI reporting (`ai:read`)
- `GET /searches`, `/search-intents`, `/search-results-performance` — demand analytics (`analytics:read`)
- `GET /sync-conflicts`, `POST /sync-conflicts/{id}/resolve` — RIC conflict queue (`sync:read`)
- `GET/POST/PATCH /api/v1/admin/facilities` + lifecycle — traveller facilities (`facilities:*`, ADR 0019)
- `POST /imports/{id}/publish|cancel|retry` — import job lifecycle extensions

Phase 1 live API foundation is complete. Keep `ADMIN_API_MFA_REQUIRED=false`
until operators have enrolled TOTP and Platform Quality Gate evidence is recorded.

Enable only with `ADMIN_API_ENABLED=true` on non-production first. Keep
`ADMIN_API_RESTRICTED=true` and configure `ADMIN_API_ALLOWED_USER_IDS` (or rely
on super-administrator-only when empty). Enroll MFA before setting
`ADMIN_API_MFA_REQUIRED=true`. OpenAPI: `docs/openapi/admin-v1.yaml`.

**Phase 1 foundation complete** (Increments 1–9) plus OPS-010 TOTP.

## Staging enablement checklist

Do this on a disposable or staging deployment before production:

1. Apply migrations through `092_admin_api_sync_conflicts.sql` (includes Option B tables `088`–`092`).
2. Set `ADMIN_API_ENABLED=true`, keep `ADMIN_API_RESTRICTED=true`.
3. Set `ADMIN_API_ALLOWED_USER_IDS` to known admin IDs (or leave empty for
   super-administrator only).
4. Keep `ADMIN_API_MFA_REQUIRED=false` until step 7.
5. Human login → `POST /auth/mfa/enroll/begin` → authenticator scan →
   `POST /auth/mfa/enroll/confirm`.
6. Create a least-privilege Assist RIC service account and print credentials
   once (store only in the RIC OS vault):
   `php scripts/admin-api-create-ric-service-account.php --email=admin@example.test`
   Probe with:
   `php scripts/admin-api-probe.php --base-url=https://staging…/api/v1/admin --client-key=… --client-secret=…`
7. Optionally set `ADMIN_API_MFA_REQUIRED=true` and confirm human login returns
   `mfa_token` then completes via `/auth/mfa/verify`.
8. From Assist RIC: enable live API, set base URL, Test connection, submit a
   small JSON export package with **Validate only** checked first.
9. Record Architecture, UX, Engineering and Business Quality Gate evidence
   before any production enablement (`docs/PLATFORM_QUALITY_GATE.md`).
   Code-complete conditional-pass packs:
   `docs/evidence/admin-api-2026-08-02/PLATFORM_QUALITY_GATE.md` and
   `docs/evidence/option-b-programme-2026-08-02/PLATFORM_QUALITY_GATE.md`
   (append staging probe + RIC validate-only results before flipping production).

Production still requires the four Quality Gate pillars. Application code alone
does not authorise DNS, live migrations or turning `ADMIN_API_ENABLED` on in
production.

## Out of scope for first production enablement

- Unrestricted service-account publish/merge/purge (merge and draft approve remain human-preferred)
- Bing Search
- Auto-publish of research or AI-only records
