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
- `GET /api/v1/admin/search-gaps` — ranked zero-result gaps from `provider_searches` (`analytics:read`); sparse/empty when analytics off

Increment 8b (MFA scaffold, OPS-010) adds:

- `POST /api/v1/admin/auth/mfa/challenge` — human bearer; returns enrollment status (scaffolded)
- `POST /api/v1/admin/auth/mfa/verify` — body `{code}`; returns 501 until TOTP validation ships

Increment 9 (RIC contract tests) adds:

- `tests/Contract/AdminApiRicContractTest.php` — Phase 1 path inventory, OpenAPI parity, mock-client health/auth checks

Phase 1 live API foundation is complete except production MFA gate (`ADMIN_API_MFA_REQUIRED`).

Enable only with `ADMIN_API_ENABLED=true` on non-production first. Keep
`ADMIN_API_RESTRICTED=true` and configure `ADMIN_API_ALLOWED_USER_IDS` (or rely
on super-administrator-only when empty). Do not set `ADMIN_API_MFA_REQUIRED`
until MFA verify endpoints exist. OpenAPI: `docs/openapi/admin-v1.yaml`.

**Phase 1 foundation complete** (Increments 1–9) except production MFA gate.

Remaining after Phase 1:

## Out of scope for first production enablement

- Unrestricted service-account publish/merge/purge
- Traveller-facility CRUD (planned entity)
- Bing Search
- Auto-publish of research or AI-only records
