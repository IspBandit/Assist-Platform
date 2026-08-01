# Live Admin API (v1)

**Status:** design accepted; implementation in progress under CORE-011.  
**Not yet a supported production partner API until MFA gate and Quality Gate
evidence are recorded.**

## Boundary

- System of record: Assist Platform Enterprise (PHP / MariaDB).
- External tools (Assist RIC, importers): **HTTPS `/api/v1/admin` only**.
- Never connect desktop tools to production MariaDB (ADR 0015).

## Documents

| Doc | Purpose |
| --- | --- |
| `docs/PHASE1_ADMIN_API_DESIGN.md` | Phase 1 endpoint inventory, auth, lifecycle, migrations, tests |
| `docs/API.md` | Cross-cutting envelope, pagination and mutation standards |
| `docs/DECISIONS/0015-admin-api-no-direct-db.md` | No direct DB |
| `docs/DECISIONS/0016-stays-vs-traveller-facilities.md` | `/stays` vs future traveller facilities |
| `docs/DECISIONS/0017-ric-as-management-client.md` | Option B |
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

Enable only with `ADMIN_API_ENABLED=true` on non-production first. Keep
`ADMIN_API_RESTRICTED=true` and configure `ADMIN_API_ALLOWED_USER_IDS` (or rely
on super-administrator-only when empty). Do not set `ADMIN_API_MFA_REQUIRED`
until MFA verify endpoints exist. OpenAPI: `docs/openapi/admin-v1.yaml`.

Remaining Phase 1 targets:
- Recycle Bin list/purge
- Draft and import package submission for RIC
- Limited search-gap analytics read

## Out of scope for first production enablement

- Unrestricted service-account publish/merge/purge
- Traveller-facility CRUD (planned entity)
- Bing Search
- Auto-publish of research or AI-only records
