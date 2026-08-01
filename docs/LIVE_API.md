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

Increment 1 (routing foundation) currently exposes:

- `GET /api/v1/admin/health`
- `GET /api/v1/admin/version`
- `GET /api/v1/admin/capabilities`
- `GET /api/v1/admin/auth/me` (always 401 until Increment 2 auth)

Enable only with `ADMIN_API_ENABLED=true` on non-production first. OpenAPI skeleton:
`docs/openapi/admin-v1.yaml`.

Remaining Phase 1 targets:

- Human login + refresh tokens (restricted rollout until MFA enforced)
- Service accounts with least-privilege scopes
- Providers and stays read/write with audited lifecycle
- Soft delete / Recycle Bin
- Draft and import package submission for RIC
- Limited search-gap analytics read

## Out of scope for first production enablement

- Unrestricted service-account publish/merge/purge
- Traveller-facility CRUD (planned entity)
- Bing Search
- Auto-publish of research or AI-only records
