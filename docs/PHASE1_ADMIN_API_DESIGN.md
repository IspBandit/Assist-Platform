# Phase 1 — Admin API design package

**Status:** design complete — implementation may begin in small increments after
owner review of this package.  
**Architecture:** Option B (ADR 0015, 0017).  
**Backlog:** CORE-011, OPS-010, OPS-011, DATA-011, DATA-014 (plus links to
DATA-001, DATA-002, DATA-006, VAN-002, VAN-010).

Related ADRs:

- [0015 — Admin API is the only external write path](DECISIONS/0015-admin-api-no-direct-db.md)
- [0016 — Stays vs traveller facilities](DECISIONS/0016-stays-vs-traveller-facilities.md)
- [0017 — RIC as initial local management client](DECISIONS/0017-ric-as-management-client.md)

This document is the pre-implementation gate for Phase 1. It does **not**
authorise production deployment.

---

## 1. ADR drafts

Accepted (see files above). Summary:

| ADR | Decision |
| --- | --- |
| 0015 | No direct production DB from RIC/importers/AI; only `/api/v1/admin` |
| 0016 | Phase 1 uses `/stays` (`caravan_parks`); do not overload parks for toilets/dump points; narrow `traveller_facilities` later |
| 0017 | Extend Assist RIC; no new Tauri/React/FastAPI app or third staging DB |

Publication policy (owner-confirmed): web research, AI-only, and community
submissions never auto-publish; only explicitly documented
`trusted_automatic` datasets may, with provenance, validation, duplicates and
rollback. No dataset is `trusted_automatic` without a written owner decision.

Paid search: Google Places optional/off-by-default with hard caps; Bing removed;
Brave later; free sources first; no silent paid fallback.

---

## 2. Final Phase 1 endpoint inventory

Base path: `/api/v1/admin`. Brand scope from verified host/deployment context
(not client `brand_id`). JSON envelopes per `docs/API.md`.

### 2.1 Authentication

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/auth/login` | Email + password; returns access + refresh; throttled |
| POST | `/auth/refresh` | Rotate refresh; revoke previous refresh |
| POST | `/auth/logout` | Revoke current refresh (+ optional all) |
| GET | `/auth/me` | Actor, roles, scopes, MFA status |
| GET | `/auth/sessions` | List active API sessions for human actor |
| DELETE | `/auth/sessions/{id}` | Revoke session |

MFA verify endpoints are **scaffolded** in schema/OpenAPI in Phase 1 but not
required for the initial restricted rollout. General remote admin access waits
until MFA is enforced (OPS-010).

### 2.2 Service accounts (management)

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/service-accounts` | Admin-only list |
| POST | `/service-accounts` | Create; returns secret once |
| GET | `/service-accounts/{id}` | Metadata (no secret) |
| PATCH | `/service-accounts/{id}` | Enable/disable, scopes, expiry |
| POST | `/service-accounts/{id}/rotate` | New secret once |
| DELETE | `/service-accounts/{id}` | Revoke |

Machine auth: `POST /auth/token` with client id + secret → access token
(short-lived). No refresh for machines in v1 (re-authenticate / rotate).

### 2.3 System

| Method | Path |
| --- | --- |
| GET | `/health` |
| GET | `/version` |
| GET | `/capabilities` |

`capabilities` declares implemented resource groups, MFA required flag,
`traveller_facilities: planned`, recycle retention days, max batch sizes.

### 2.4 Providers (read then write)

| Method | Path |
| --- | --- |
| GET | `/providers` |
| GET | `/providers/{id}` |
| POST | `/providers` |
| PATCH | `/providers/{id}` |
| POST | `/providers/{id}/publish` |
| POST | `/providers/{id}/unpublish` |
| POST | `/providers/{id}/archive` |
| POST | `/providers/{id}/restore` |
| DELETE | `/providers/{id}` | Soft-delete → recycle bin |

List supports pagination (cursor), sort, filter, FTS-ish `q`, status/lifecycle,
source, geo bounding box / town / state, field selection.

### 2.5 Stays (caravan_parks)

Same lifecycle shape under `/stays` and `/stays/{id}/…`.  
**Not** `/facilities` in Phase 1 (ADR 0016).

### 2.6 Draft / import submission (RIC)

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/drafts` | Platform draft/import candidates visible to scopes |
| GET | `/drafts/{id}` | |
| POST | `/drafts` | Create draft from RIC normalised payload |
| PATCH | `/drafts/{id}` | |
| POST | `/drafts/{id}/approve` | Human or elevated scope; not default service scope |
| POST | `/drafts/{id}/reject` | |
| POST | `/imports` | Submit checksummed RIC package metadata + items |
| GET | `/imports/{id}` | Status / results |
| POST | `/imports/{id}/validate` | Dry validation |
| POST | `/imports/{id}/stage` | Enter review queue (DATA-006 alignment) |

Phase 1 service accounts may **submit/validate/stage**; they may **not**
approve/publish/merge/purge unless a later owner decision expands scopes.

### 2.7 Recycle Bin

| Method | Path |
| --- | --- |
| GET | `/recycle-bin` |
| POST | `/recycle-bin/{id}/restore` |
| DELETE | `/recycle-bin/{id}/purge` | Permanent; restricted permission |
| POST | `/recycle-bin/bulk-restore` | Idempotency key + confirm |
| POST | `/recycle-bin/bulk-purge` | Idempotency key + confirm + reason |

### 2.8 Audit (read)

| Method | Path |
| --- | --- |
| GET | `/audit` |
| GET | `/audit/{id}` |

### 2.9 Analytics for research gaps (limited read)

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/search-gaps` | Aggregated weak/zero searches for RIC (DATA-013 prep) |

### 2.10 Explicitly deferred past Phase 1 implementation

Duplicates merge UI/API completion (DATA-002 — design hooks only), claims
approve/reject API (VAN-010 after auth), corrections, dataset catalogue CRUD
(DATA-012), AI usage (VAN-011), traveller-facilities CRUD, unrestricted
publish/merge scopes for service accounts.

OpenAPI still lists deferred paths as `planned` in `/capabilities` where useful.

### Bulk write rules (all mutating collections)

- `Idempotency-Key` header required for bulk and create-from-package
- `dry_run=true` and `validate_only=true` query/body flags where applicable
- Max batch size from capabilities (proposed: 100)
- Per-record results array
- Audit every accepted mutation
- Destructive actions require `confirm: true` + `reason`

---

## 3. Authentication and service-account schema

### 3.1 New tables (migration sketch)

**`api_oauth_clients` (service accounts)**

| Column | Type | Notes |
| --- | --- | --- |
| id | BINARY(16) / CHAR(36) | UUID |
| name | VARCHAR(120) | |
| client_key | VARCHAR(64) | Public id |
| secret_hash | VARCHAR(255) | Argon2id/bcrypt |
| status | ENUM active,disabled,revoked | |
| scopes_json | JSON | Array of scope strings |
| token_ttl_seconds | INT | Default 3600 |
| expires_at | DATETIME NULL | Client expiry |
| last_used_at | DATETIME NULL | |
| created_by | INT UNSIGNED NULL | users.id |
| created_at / updated_at | DATETIME | |

**`api_access_tokens`**

| Column | Notes |
| --- | --- |
| id | UUID |
| token_hash | Unique; store hash only |
| actor_type | `user` \| `service` |
| user_id / client_id | Exactly one set |
| scopes_json | Granted subset |
| expires_at | Short-lived (e.g. 15 min human, ≤60 min service) |
| revoked_at | |
| request_id_created | |
| ip / user_agent | |

**`api_refresh_tokens`** (humans only)

| Column | Notes |
| --- | --- |
| id | UUID |
| token_hash | |
| user_id | |
| family_id | Rotation family |
| expires_at | Longer (e.g. 7–30 days) |
| revoked_at / replaced_by | |
| session_label | Device name |

**`api_login_throttle`**

| email_hash / ip | attempts | window_start | locked_until |

**`user_mfa_methods`** (scaffold)

| user_id | method `totp` | secret_encrypted | enabled_at | verified_at |

**`api_security_events`**

| id | actor | event_type | ip | meta_json | created_at |

Reuse existing `audit_logs` for resource mutations; security events for auth.

### 3.2 Password login flow

1. Validate credentials against `users` + admin-capable roles.
2. Check throttle / status / deleted_at.
3. If MFA enabled and enforced, return `mfa_required` challenge (Phase 1
   scaffold; enforcement gate before broad remote use).
4. Issue access + refresh; write security event.
5. Until MFA enforced: allowlist of administrator user IDs and/or deployment
   flag `ADMIN_API_RESTRICTED=1` limiting callers.

### 3.3 Secrets storage

- Platform: hashed secrets in MariaDB; encryption key from env for MFA secrets.
- RIC: OS keyring for access/refresh or client secret — never SQLite.

---

## 4. Permission and scope matrix

### 4.1 Human roles (existing RBAC ∩ API)

API login requires an active user with at least one of:
`moderator`, `administrator`, `super-administrator`, `platform-administrator`,
`brand-administrator` (brand-scoped), or a future `api-operator` role.

Existing permission slugs continue to gate HTML admin; API maps scopes →
checks inside services.

### 4.2 Phase 1 service-account scopes

| Scope | Allowed | Default RIC sync account |
| --- | --- | --- |
| `providers:read` | List/get providers | Yes |
| `stays:read` | List/get stays | Yes |
| `drafts:read` | List/get drafts | Yes |
| `drafts:write` | Create/patch drafts | Yes |
| `imports:write` | Submit/validate/stage packages | Yes |
| `imports:read` | Import job status | Yes |
| `sync:read` | Sync/capabilities/health | Yes |
| `analytics:read` | `search-gaps` limited | Yes |
| `audit:read` | Audit query | Optional |
| `providers:write` | Create/patch providers | No (Phase 1) |
| `stays:write` | Create/patch stays | No |
| `drafts:approve` | Approve/reject drafts | No |
| `lifecycle:write` | publish/unpublish/archive | No |
| `recycle_bin:restore` | Restore | No |
| `recycle_bin:purge` | Permanent purge | **Never** for RIC v1 |
| `users:admin` | Users/roles | **Never** |
| `billing:admin` | Billing | **Never** |
| `duplicates:merge` | Merge | **Never** in Phase 1 service |

Human tokens may receive broader scopes matching their RBAC permissions.

---

## 5. Provider / stay lifecycle definition

Unify API lifecycle (map to existing columns in migration):

| API lifecycle | Providers today | Stays (`caravan_parks`) today | Notes |
| --- | --- | --- | --- |
| `draft` | `status=draft` | `status=draft` | Not public |
| `pending_review` | `status=pending` | `status=pending` | |
| `published` | `status=active` + visible | `active` + `public_page_enabled` | Exact visibility rules in service |
| `unpublished` | active but not publicly listed | `public_page_enabled=0` | Retained |
| `inactive` | `suspended` | `suspended` | |
| `archived` | New flag or status value | New | Soft operational shelving |
| `deleted` | `deleted_at` set | `deleted_at` set | Recycle bin |
| `merged` | New | New | Loser record after merge |
| `rejected` | `rejected` | `rejected` | |

Phase 1 migration adds explicit `lifecycle_status` **or** documents a pure
mapping layer. Preference: add `lifecycle_status` + keep legacy `status` in
sync via service for one release (dual-write), then HTML admin reads lifecycle
through the same service.

Additional recycle metadata (both entity types):

- `deleted_reason`, `deleted_by`, `purge_after`

---

## 6. Recycle Bin design

1. `DELETE /providers/{id}` sets `deleted_at`, lifecycle=`deleted`, reason
   required, audit event; removes from public search.
2. Recycle Bin lists soft-deleted providers and stays (entity_type discriminator).
3. Restore clears `deleted_at`, restores prior lifecycle (stored in
   `recycle_meta_json` or previous_value audit).
4. Retention default **90 days** (configurable 30/60/90 via settings).
5. Purge: requires `recycle_bin:purge` + confirm + dependency report
   (brand listings, claims, reviews, areas — block or cascade policy documented
   per dependency).
6. Bulk endpoints require idempotency key and max batch size.
7. Scheduled job (later OPS) auto-purges expired rows only if setting enabled;
   Phase 1 may ship manual purge only.

---

## 7. Canonical entity and source-link design

**Goal (DATA-014 / DATA-001):** every published or staged fact can point at a
stable canonical id and one or more source links.

### 7.1 Concepts

- **Canonical entity:** `provider` | `stay` | (future) `traveller_facility`
- **Canonical ID:** existing integer PK exposed as string in API; future UUID
  alias column optional (`public_id`) for external stability.
- **Source link:** row tying entity to external/import evidence.

### 7.2 Table sketch `entity_source_links`

| Column | Notes |
| --- | --- |
| id | UUID |
| entity_type | provider/stay/… |
| entity_id | |
| source_system | `ric`, `data_source`, `osm`, `google_places`, `manual`, … |
| source_dataset | |
| source_record_id | |
| source_url | |
| licence | |
| attribution | |
| payload_hash | |
| retrieved_at | |
| confidence | |
| is_primary | |

Reuse/align with `provider_discovery_evidence`, data_source candidates, and
`caravan_parks.source_type/external_id` rather than discarding them — Phase 1
may introduce `entity_source_links` and backfill from existing columns.

RIC packages must include source links; Admin API draft ingest persists them
before publish.

---

## 8. OpenAPI structure

Proposed artefact: `docs/openapi/admin-v1.yaml` (generated or hand-maintained
in Phase 1; CI validates examples).

```text
openapi: 3.1.0
info:
  title: Assist Platform Admin API
  version: 1.0.0
servers:
  - url: https://{host}/api/v1/admin
tags: [Auth, ServiceAccounts, System, Providers, Stays, Drafts, Imports,
       RecycleBin, Audit, Analytics]
components:
  securitySchemes:
    bearerAuth: HTTP bearer JWT-or-opaque
  schemas:
    EnvelopeData / EnvelopeCollection / Error / Provider / Stay /
    Draft / ImportJob / RecycleItem / AuditEvent / CapabilityDocument
paths:
  /auth/login: ...
  ...
```

Error codes (stable strings): `unauthenticated`, `forbidden`, `not_found`,
`validation_failed`, `conflict`, `rate_limited`, `mfa_required`,
`idempotency_replay`, `budget_exceeded` (later), `gone`.

---

## 9. Database migration list (Phase 1)

Forward-only under `database/migrations/`. Proposed filenames (numbers after
current max **079**):

| Migration | Purpose |
| --- | --- |
| `080_admin_api_credentials.sql` | clients, access/refresh tokens, throttle, security events |
| `081_admin_api_mfa_scaffold.sql` | `user_mfa_methods` (unused until MFA enabled) |
| `082_entity_lifecycle_recycle.sql` | lifecycle columns, delete reason/by/purge_after on providers + caravan_parks |
| `083_entity_source_links.sql` | canonical source link table + backfill from known columns |
| `084_admin_api_idempotency.sql` | idempotency key store for bulk/create |
| `085_admin_api_seed_permissions.sql` | permission slugs for API scopes if needed |

No Phase 1 migration for `traveller_facilities` (ADR 0016).

---

## 10. Exact file-level change list (Phase 1 implementation)

### Assist Platform

| Path | Change |
| --- | --- |
| `routes/api_v1_admin.php` | **New** route registrar |
| `app/Core/Kernel.php` | Load API routes; JSON middleware stack |
| `app/Controllers/Api/V1/Admin/*` | Auth, Providers, Stays, Drafts, Imports, RecycleBin, Audit, System |
| `app/Services/Api/AdminApiAuthService.php` | Login, refresh, token issue/revoke, throttle |
| `app/Services/Api/ServiceAccountService.php` | CRUD/rotate |
| `app/Services/Api/AdminApiEnvelope.php` | Response helpers |
| `app/Services/Lifecycle/RecordLifecycleService.php` | Shared lifecycle + recycle |
| `app/Services/Provenance/EntitySourceLinkService.php` | Source links |
| `app/Middleware/AuthenticateApiBearer.php` | **New** |
| `app/Middleware/RequireApiScope.php` | **New** |
| `app/Middleware/AdminApiRateLimit.php` | **New** |
| Reuse | `AuditLog`, `ProviderClaimService` (later), `DataSourceService` |
| `database/migrations/080–085_*.sql` | As above |
| `tests/Api/V1/Admin/*` | Contract + auth + lifecycle tests |
| `docs/openapi/admin-v1.yaml` | Contract |
| `docs/LIVE_API.md` | Operator/developer reference |
| `docs/API.md` | Point at live admin v1 status |
| `docs/START_HERE.md` | Link RIC + Phase 1 design |
| `docs/ARCHITECTURE_DECISION_RECORDS.md` | Index 0015–0017 |
| `docs/PRODUCT_BACKLOG.md` | Done in design pass |
| `.env.example` | `ADMIN_API_*` flags, token TTLs, restricted mode |

### Assist RIC (after Platform contract tests green)

| Path | Change |
| --- | --- |
| `docs/architecture/adr/0010-live-admin-api-client.md` | **New** |
| `docs/architecture/live-api-sync.md` | **New** |
| `README.md` / overview | Link Platform CORE-011 |
| `src/assist_ric/infrastructure/live_api/` | Client skeleton + fixtures **after** auth works |
| `tests/contract/` | Mock server tests |

**Do not** begin broad RIC UI changes in the first implementation increments.

---

## 11. Test matrix

| Area | Tests |
| --- | --- |
| Auth | Login success/fail, throttle lock, refresh rotation, logout revoke, disabled user |
| Scopes | Service token missing scope → 403; read-only cannot PATCH |
| Providers | List cursor, get, create validation, patch, publish/unpublish/archive |
| Stays | Same as providers for lifecycle |
| Recycle | Soft delete → list → restore; purge forbidden without scope; purge with confirm |
| Drafts/Imports | Validate-only, stage, reject unknown fields, idempotent replay |
| Audit | Mutation writes audit row with actor_type service/user |
| Security | No secret in logs; error envelope redaction |
| MFA scaffold | Schema present; enforcement flag off in restricted mode |
| Contract | OpenAPI response shape fixtures; RIC mock client against recorded OpenAPI |
| Non-goals | No production DB; no live Google calls in CI |

---

## 12. Rollback plan

1. **Feature flag:** `ADMIN_API_ENABLED=false` disables route registration.
2. **Credentials:** revoke all `api_oauth_clients` and refresh families.
3. **Data:** soft-deleted rows remain; lifecycle columns harmless to HTML admin
   if dual-written carefully — document read path fallback to legacy `status`.
4. **Migrations:** never edit applied SQL; compensating forward migration if a
   column must be abandoned.
5. **RIC:** disable live sync setting; fall back to export packages.
6. **Incidents:** rotate secrets; export `api_security_events` for forensics.

---

## Implementation order (confirmed)

1. API routing + envelopes/errors — **Increment 1 complete**
2. Authentication + admin sessions — **Increment 2 complete (restricted / MFA scaffold)**
3. Service accounts + scopes — **Increment 3 complete**
4. Health / version / capabilities — **Increment 1 skeletons shipped**
5. Read-only providers + stays — **Increment 4 complete**
6. Audited create/update + lifecycle — **Increment 5 complete**
7. Recycle Bin list/purge — **Increment 6 complete**
8. Draft/import submission — **Increment 7 complete**
9. Audit read + search-gap analytics — **Increment 8 complete**
10. MFA verify scaffold — **Increment 8b complete (501 until TOTP ships)**
11. RIC mock-client contract tests — **Increment 9 complete**
12. OpenAPI + operating docs — **Increment 1–9 contract updates**

Phase 1 live API foundation is **complete** except enabling `ADMIN_API_MFA_REQUIRED` in production.

### Increment 1 shipped surface

| Method | Path | Behaviour |
| --- | --- | --- |
| GET | `/api/v1/admin/health` | Envelope `{data.status=ok}` when `ADMIN_API_ENABLED` |
| GET | `/api/v1/admin/version` | API/product/release metadata |
| GET | `/api/v1/admin/capabilities` | Planned vs active resources; no `/facilities` |

### Increment 2 shipped surface

| Method | Path | Behaviour |
| --- | --- | --- |
| POST | `/api/v1/admin/auth/login` | Email/password; access + refresh; throttled |
| POST | `/api/v1/admin/auth/refresh` | Rotate refresh; revoke previous; reuse detection |
| POST | `/api/v1/admin/auth/logout` | Revoke current session (optional all) |
| GET | `/api/v1/admin/auth/me` | Actor, roles, scopes, MFA status |
| GET | `/api/v1/admin/auth/sessions` | Active refresh families for actor |
| DELETE | `/api/v1/admin/auth/sessions/{id}` | Revoke session family |

Migrations `080_admin_api_credentials.sql` and `081_admin_api_mfa_scaffold.sql`.
Flag `ADMIN_API_ENABLED=false` by default. Restricted mode defaults on.
Errors use `{error:{code,message,request_id}}`. See `docs/openapi/admin-v1.yaml`.

### Increment 3 shipped surface

| Method | Path | Behaviour |
| --- | --- | --- |
| POST | `/auth/token` | Service client key + secret → access token (no refresh) |
| GET | `/auth/me` | Human or service actor with scopes |
| GET | `/service-accounts` | List service accounts (human + scope) |
| POST | `/service-accounts` | Create account; secret returned once |
| GET | `/service-accounts/{id}` | Metadata (no secret) |
| PATCH | `/service-accounts/{id}` | Update name, scopes, status, TTL, expiry |
| POST | `/service-accounts/{id}/rotate` | Rotate secret; revoke outstanding tokens |
| DELETE | `/service-accounts/{id}` | Revoke account and tokens |

Human-only routes (logout, sessions) remain gated by `admin_api_human`.
Service accounts receive `DEFAULT_SERVICE` scopes unless specified; `NEVER_SERVICE`
scopes are rejected. Machine token TTL capped at 3600s via `ADMIN_API_SERVICE_TOKEN_TTL`.

### Increment 4 shipped surface

| Method | Path | Behaviour |
| --- | --- | --- |
| GET | `/providers` | Cursor list; brand-scoped listings; `providers:read` |
| GET | `/providers/{id}` | Detail for brand listing; 404 if out of scope |
| GET | `/stays` | Cursor list of `caravan_parks`; empty when parks module off |
| GET | `/stays/{id}` | Stay detail; 404 when parks module off or missing |

Query params: `limit` (1–100), `cursor`, `q`, `status` (DB status or lifecycle alias),
`town`, `state` (id/abbrev/name). Lifecycle mapped per §5. No `/facilities`.

### Increment 5 shipped surface

| Method | Path | Scope | Behaviour |
| --- | --- | --- | --- |
| POST | `/providers` | `providers:write` | Create provider + brand listing; default `pending`; audit |
| PATCH | `/providers/{id}` | `providers:write` | Patch allowed provider/listing fields; audit |
| POST | `/providers/{id}/publish` | `lifecycle:write` | `active` + listing visible; sets `approved_at` when null |
| POST | `/providers/{id}/unpublish` | `lifecycle:write` | `search_visible=0`; provider stays `active` |
| POST | `/providers/{id}/archive` | `lifecycle:write` | `status=suspended`, `search_visible=0` |
| POST | `/providers/{id}/restore` | `lifecycle:write` | Clears soft-delete; status `pending` |
| DELETE | `/providers/{id}` | `lifecycle:write` | Soft-delete provider + listing; `reason` required (≥3 chars) |
| POST | `/stays` | `stays:write` | Create stay; gated when parks module off |
| PATCH | `/stays/{id}` | `stays:write` | Patch allowed stay fields |
| POST | `/stays/{id}/publish` | `lifecycle:write` | `active`, `public_page_enabled=1` |
| POST | `/stays/{id}/unpublish` | `lifecycle:write` | `public_page_enabled=0` |
| POST | `/stays/{id}/archive` | `lifecycle:write` | `status=suspended`, `public_page_enabled=0` |
| POST | `/stays/{id}/restore` | `lifecycle:write` | Clears soft-delete; status `pending` |
| DELETE | `/stays/{id}` | `lifecycle:write` | Soft-delete; `reason` required (≥3 chars) |

All writes record `AdminApiAudit` events. No approval emails. Recycle-bin list/purge
remains a later increment; restore is available via `POST .../restore`.

### Increment 6 shipped surface

| Method | Path | Scope | Behaviour |
| --- | --- | --- | --- |
| GET | `/recycle-bin` | `recycle_bin:restore` or `recycle_bin:purge` | Cursor list; `entity_type`, `q` filters |
| GET | `/recycle-bin/{entity_type}/{id}` | restore or purge | Deleted item detail |
| POST | `/recycle-bin/{entity_type}/{id}/restore` | `recycle_bin:restore` | Reuses provider/stay restore services |
| DELETE | `/recycle-bin/{entity_type}/{id}/purge` | `recycle_bin:purge` | Permanent delete; body `{confirm:true, reason}` |
| POST | `/recycle-bin/bulk-restore` | `recycle_bin:restore` | `Idempotency-Key`; `{items, confirm:true}` |
| POST | `/recycle-bin/bulk-purge` | `recycle_bin:purge` | Same + reason; idempotent |

Provider purge removes brand listing first; provider row is hard-deleted only when no
other brand listings remain. Stay purge hard-deletes `caravan_parks` row (CASCADE children).
Purge reasons are stored in audit only (no recycle metadata migration).

### Increment 7 shipped surface

| Method | Path | Scope | Behaviour |
| --- | --- | --- | --- |
| GET | `/drafts` | `drafts:read` | List brand-scoped drafts |
| GET | `/drafts/{id}` | `drafts:read` | Draft detail + payload |
| POST | `/drafts` | `drafts:write` | Create draft; requires `entity_type` + payload keys |
| PATCH | `/drafts/{id}` | `drafts:write` | Update draft payload/status |
| POST | `/drafts/{id}/approve` | `drafts:approve` | Creates/updates live provider or stay |
| POST | `/drafts/{id}/reject` | `drafts:approve` | Sets rejected + optional review note |
| POST | `/imports` | `imports:write` | `Idempotency-Key`; checksum + items array |
| GET | `/imports/{id}` | `imports:read` | Job status + per-line results |
| POST | `/imports/{id}/validate` | `imports:write` | Validates payloads; sets item statuses |
| POST | `/imports/{id}/stage` | `imports:write` | Creates `api_drafts` from valid items |

Migration `082_admin_api_drafts_imports.sql` adds `api_drafts`, `api_import_jobs`,
`api_import_job_items`, `api_idempotency_keys`. Service accounts receive
`drafts:write` and `imports:write` by default; `drafts:approve` is human-elevated.

### Increment 8 shipped surface

| Method | Path | Scope | Behaviour |
| --- | --- | --- | --- |
| GET | `/audit` | `audit:read` | Cursor list from `audit_logs`; filters action/object/user/date/q |
| GET | `/audit/{id}` | `audit:read` | Single audit row mapped to stable JSON |
| GET | `/search-gaps` | `analytics:read` | Ranked zero-result gaps from `provider_searches`; sparse when analytics off |

No new migration: search gaps aggregate existing demand analytics tables.

### Increment 8b shipped surface (OPS-010 scaffold)

| Method | Path | Behaviour |
| --- | --- | --- |
| POST | `/auth/mfa/challenge` | Human bearer; enrollment status from `user_mfa_methods` |
| POST | `/auth/mfa/verify` | Body `{code}`; 501 `not_implemented` until TOTP library ships |

`ADMIN_API_MFA_REQUIRED` remains **false** by default.

### Increment 9 shipped surface

| Area | Behaviour |
| --- | --- |
| `tests/Contract/AdminApiRicContractTest.php` | Phase 1 path inventory vs routes; OpenAPI parity; mock-client health/capabilities/auth validation |
| `phpunit.xml` | Contract testsuite added |
