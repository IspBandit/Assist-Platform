# Live Admin API (v1)

**Status:** Phase 1 and Option B implementation complete; production enablement
awaits the staging rehearsal and full Quality Gate evidence.
**Not yet a supported production partner API.**

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

RIC everyday management (analytics rollup) adds:

- `GET /api/v1/admin/overview` — operational dashboard rollup (`analytics:read`);
  website visitors exclude bot/unknown page views; queue counts gated by related
  read scopes (`claims:read`, `corrections:read`, `duplicates:read`, `drafts:read`,
  `facilities:read`); AI cost summary when `ai:read`; dataset sync timestamps when
  `datasets:read`. Attention items for backlog; warnings only for genuine load
  failures.
- `GET /api/v1/admin/website-insights` — full brand website insights document
  (`analytics:read`), reusing `WebsiteInsightsService`. Bot/unknown page views
  returned separately as `filtered_bot_page_views`.
- RIC default service scopes add `corrections:read`, `duplicates:read`, `ai:read`,
  `recycle_bin:restore` (never `recycle_bin:purge`).

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
- `/capabilities` includes the verified host-scoped brand key, name, status and
  modules. Clients must reject a response whose brand differs from the selected
  workspace; they must not send a `brand_id` to change scope.

Increment 4 (read-only directory) adds:

- `GET /api/v1/admin/providers` — cursor list (`providers:read`)
- `GET /api/v1/admin/providers/{id}` — detail
- `GET /api/v1/admin/stays` — cursor list (`stays:read`, VanAssist parks module)
- `GET /api/v1/admin/stays/{id}` — detail
- Filters: `q`, `status`/`lifecycle`, `town`, `state`; brand from host context

**RIC Directory Management** uses these Increment 4 routes plus Option B
facilities/claims/corrections reads:

- `GET /facilities`, `GET /facilities/{id}` (`facilities:read`; filters `q`, `status`)
- `GET /claims`, `GET /claims/{id}` (`claims:read`; filters `type`, `status`)
- `GET /corrections`, `GET /corrections/{id}` (`corrections:read`; filters `status`, `entity_type`)
- Cursor pagination via `limit` + `cursor` / `meta.next_cursor` (not offset).
- Read-only by default in Assist RIC; approve/reject mutations require human
  Admin API sessions (`claims:write` / `corrections:write`) and remain available
  in the website admin as backup.
- Categories: `GET /categories` (`categories:read`) — brand-scoped
  `brand_provider_categories` (not legacy `service_categories`); default active
  only; optional `active=all|0|1` and `q`
- Locations: `GET /locations/states`, `/locations/regions`, `/locations/towns`
  (`locations:read`) — global pickers with cursor pagination; towns support
  `state_id`, `region_id`, `q`. Writes remain website admin.

**RIC Data Review** uses existing draft, duplicate and recycle Admin API reads
(no new endpoints). Claims and corrections awaiting action stay on Directory:

- `GET /drafts`, `GET /drafts/{id}` (`drafts:read`; filters `status`, `entity_type`)
- `GET /duplicates`, `GET /duplicates/{id}` (`duplicates:read`; filters `status`,
  `include_suspects`; default open decisions plus heuristic suspects)
- `GET /recycle-bin`, `GET /recycle-bin/{entity_type}/{id}` (`recycle_bin:restore`
  or `recycle_bin:purge`; filters `entity_type`, `q`)
- Cursor pagination via `limit` + `cursor` / `meta.next_cursor`.
- Read-only by default in Assist RIC. Draft approve/reject (`drafts:approve`),
  duplicate merge / not-duplicate (`duplicates:merge`), and recycle purge remain
  human-session website admin actions. Recycle restore is allowed for service
  accounts with `recycle_bin:restore` (now in default `RIC_SERVICE`) but RIC UI
  still keeps restore out of the first Data Review increment.
- Facility/provider **import-candidate** queues are available:
  - `GET /facility-import-candidates`, `GET /facility-import-candidates/{id}`
    (`import_candidates:read`) — `traveller_facility_import_candidates`;
    default `status=pending` (non-expired); cursor pagination; list omits
    `raw_json`; detail may include sanitised `raw`
  - `POST /facility-import-candidates/{id}/approve|reject`
    (`import_candidates:review` + human Admin API session) — optional JSON
    `{ "reason": "..." }` maps to review notes; service accounts cannot hold
    this scope (`NEVER_SERVICE`). Website admin review remains available.
  - `POST /facility-import-candidates/bulk-approve|bulk-reject` — same human
    scope; body `{ "ids": [...], "reason": "..." }`; per-id results; capped by
    `admin_api.max_batch_size`
  - `GET /provider-import-candidates`, `GET /provider-import-candidates/{id}`
    (`import_candidates:read`) — brand-scoped `data_source_import_candidates`;
    optional `q` / `state`; same envelope and raw rules; detail may include
    `evidence_url` / `review_notes`
  - `POST /provider-import-candidates/{id}/approve|reject`
    (`import_candidates:review` + human session) — approve requires
    `retention_confirmed` + independent `evidence_url` (optional `category_id`
    / notes); reject accepts pending or held. Hold/confirm stay website
    admin.
  - `POST /provider-import-candidates/{id}/merge` — human-only manual merge into
    an unclaimed provider (same scope); requires retention + evidence URL;
    optional `provider_id` (defaults to `duplicate_provider_id`). Exact-identity
    gates apply. Not the status-only auto-link path.
  - These are **not** `GET /imports` (RIC package jobs / `api_import_jobs`).
  - Stale/missing quality lists still have no Admin API (product criteria
    deferred; not a missing read wrapper over an existing queue).
  - Overview `facility_candidates_pending` counts live facility lifecycle
    statuses, not import candidates.

**RIC Ask Insights** uses existing AI usage and dual-source search-gap reads:

- `GET /ai/usage/requests` (`ai:read`) — Ask VanAssist activity feed (question,
  interpretation/`intent_source`, model/provider, estimated AUD cost, success,
  `fallback_reason`, `answer_summary`). Cursor + `from`/`to`. No session/PII.
- `GET /ai/usage/summary`, `/ai/usage/costs`, `/ai/cache-performance` — cost and
  cache rollups (`ai:read`).
- `GET /search-gaps` (`analytics:read`) — dual-source gaps; knowledge-gap items
  carry `meta.source=knowledge_gaps` plus engagement counts
  (`click_through_count`, `contact_action_count`, `ai_used_count`) and taxonomy
  keys. Provider zero-results use `meta.source=provider_searches`.
- There is **no** separate `/knowledge-gaps` Admin API path (ADR/Option B dual
  source). Client-side CSV export is permitted from these payloads.

**RIC Operations** (read-only) uses:

- `GET /health`, `/version`, `/capabilities` — API availability and release
  metadata (unauthenticated probes)
- `GET /datasets`, `GET /datasets/{id}/sync-history` (`datasets:read`) — dataset
  sync run history
- `GET /sync-conflicts` (`sync:read`) — open conflict list (resolve stays
  human/website admin; not on the Operations page)
- `GET /imports` (`imports:read`) — brand-scoped import job index (cursor +
  optional `status`; sparse when the jobs table is absent). Item payloads are
  omitted; use `GET /imports/{id}` for line detail
- `GET /imports/{id}` (`imports:read`) — single import job detail
- `GET /ai/usage/summary`, `/ai/usage/costs`, `/ai/cache-performance` (`ai:read`)
  — usage and enable flags. Summary includes a nested read-only `budget`
  snapshot (spend vs caps / allow state). Setting or changing budget caps stays
  PHP AI admin only
- `GET /feature-flags` (`flags:read`) — platform feature-flag catalogue
  (enabled, description, updated_at). **No write/toggle paths** on the Admin
  API; toggles remain website admin with owner workflows
- `GET /ops/failed-emails`, `GET /ops/failed-scheduled-tasks` (`ops:read`) —
  failed `email_queue` rows (no bodies) and `scheduled_tasks` with
  `last_status=failed`. Not a Laravel `failed_jobs` table. Connector/Polaris
  import job indexes remain website admin where not covered by `GET /imports`
- `GET /audit`, `GET /audit/{id}` (`audit:read`) — audited mutation history
- Stale/missing quality list queues remain website admin until product criteria
  for those lists are defined. Operations must never toggle production AI,
  traveller facilities, releases or dangerous flags.

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
- `POST /api/v1/admin/facility-imports` — Assist RIC facility package ingest
  (`imports:write`, `Idempotency-Key`); stages then **auto-publishes** Assist
  RIC government packs into `traveller_facilities` (ADR 0034)
- `POST /api/v1/admin/facility-imports/publish-pending` — drain pending Assist
  RIC facility candidates in bounded batches (`imports:write`; ADR 0034)
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
- `GET/PATCH /api/v1/admin/datasets`, `POST /datasets/{id}/sync` (runs
  `GovernmentDatasetService::fetchDataset` / optional fixture; review-first),
  `GET .../sync-history`. DATA-011A expands catalogue fields (jurisdiction,
  source/API URLs, auto-update, duplicate rules, status, notes) — see
  `docs/DATA_011A.md`.
- `GET /api/v1/admin/ai/usage/*`, `GET /ai/cache-performance` — empty-safe AI reporting (`ai:read`)
- `GET /searches`, `/search-intents`, `/search-results-performance` — demand analytics (`analytics:read`)
- `GET /sync-conflicts`, `POST /sync-conflicts/{id}/resolve` — RIC conflict queue (`sync:read`)
- `GET/POST/PATCH /api/v1/admin/facilities` + lifecycle — traveller facilities (`facilities:*`, ADR 0019)
- `POST /imports/{id}/publish|cancel|retry` — import job lifecycle extensions

Option B Increment H adds:

- `GET /api/v1/admin/facility-import-candidates` + `/{id}` — facility import
  review queue (`import_candidates:read`); separate from `GET /imports`
- `GET /api/v1/admin/provider-import-candidates` + `/{id}` — provider import
  review queue (`import_candidates:read`); brand-scoped; optional `q`/`state`

Option B Increment H.1 adds:

- `POST /facility-import-candidates/{id}/approve|reject` — human-only facility
  candidate review (`import_candidates:review` in `NEVER_SERVICE`; not in
  `RIC_SERVICE`). Delegates to `GovernmentDatasetService::reviewCandidate`.

Option B Increment H.2 adds:

- `POST /provider-import-candidates/{id}/approve|reject` — human-only provider
  candidate review (same `import_candidates:review` + `admin_api_human`).
  Delegates to `DataSourceService::review`. Approve requires retention
  confirmation and independent evidence URL; auto-confirms evidence when
  needed. Merge/hold remain website admin.

Option B Increment H.3 adds:

- `POST /facility-import-candidates/bulk-approve|bulk-reject` — human-only
  batch facility candidate review (same scope). Per-id results; batch capped
  by `admin_api.max_batch_size`. Provider bulk remains out of scope.

Option B Increment H.4 adds:

- `POST /provider-import-candidates/{id}/merge` — human-only manual merge into
  an unclaimed provider via `DataSourceService::review`. Requires retention +
  evidence URL; optional `provider_id`. Hold/confirm/auto-link remain website
  admin.

Increment I adds:

- `GET /ops/failed-emails`, `GET /ops/failed-scheduled-tasks` (`ops:read` in
  `RIC_SERVICE`) — read-only failed email and cron task lists
- `GET /categories` (`categories:read`) — brand `brand_provider_categories`
- `GET /locations/states|regions|towns` (`locations:read`) — taxonomy pickers
- Staging checklist updated for new RIC scopes; no production flags

Phase 1 live API foundation is complete. Keep `ADMIN_API_MFA_REQUIRED=false`
until operators have enrolled TOTP and Platform Quality Gate evidence is recorded.

Enable only with `ADMIN_API_ENABLED=true` on non-production first. Keep
`ADMIN_API_RESTRICTED=true` and configure `ADMIN_API_ALLOWED_USER_IDS` (or rely
on super-administrator-only when empty). Enroll MFA before setting
`ADMIN_API_MFA_REQUIRED=true`. OpenAPI: `docs/openapi/admin-v1.yaml`.

**Phase 1 foundation complete** (Increments 1–9) plus OPS-010 TOTP.

## Staging enablement checklist

Do this on a disposable or staging deployment before production:

1. Apply Admin API migrations through `092_admin_api_sync_conflicts.sql`
   (Option B tables `088`–`092`). On the unified tree also apply Assist AI /
   Polaris migrations `101`–`116` when those features are in scope.
2. Set `ADMIN_API_ENABLED=true`, keep `ADMIN_API_RESTRICTED=true`.
3. Set `ADMIN_API_ALLOWED_USER_IDS` to known admin IDs (or leave empty for
   super-administrator only).
4. Keep `ADMIN_API_MFA_REQUIRED=false` until step 7.
5. Human login → `POST /auth/mfa/enroll/begin` → authenticator scan →
   `POST /auth/mfa/enroll/confirm`.
6. Create a least-privilege Assist RIC service account and print credentials
   once (store only in the RIC OS vault):
   `php scripts/admin-api-create-ric-service-account.php --email=admin@example.test`
   Confirm the printed scopes include Increment I additions: `ops:read`,
   `categories:read`, `locations:read` (plus earlier `flags:read` and
   `import_candidates:read`). Recreate the account or expand scopes if an
   older RIC service account predates these grants.
   Probe with:
   `php scripts/admin-api-probe.php --base-url=https://staging…/api/v1/admin --client-key=… --client-secret=…`
7. Optionally set `ADMIN_API_MFA_REQUIRED=true` and confirm human login returns
   `mfa_token` then completes via `/auth/mfa/verify`.
8. From Assist RIC: enable live API, set base URL, Test connection, submit a
   small JSON export package with **Validate only** checked first.
9. Smoke-check read surfaces used by everyday RIC management: `/overview`,
   `/website-insights`, Directory reads, Data Review queues, `/ops/failed-*`,
   `/categories`, `/locations/towns`, Ask Insights AI usage, Operations
   imports/flags. Do not enable production Ask, traveller facilities, or paid
   AI from this rehearsal.
10. Record Architecture, UX, Engineering and Business Quality Gate evidence
   before any production enablement (`docs/PLATFORM_QUALITY_GATE.md`).
   Code-complete conditional-pass packs:
   `docs/evidence/admin-api-2026-08-02/PLATFORM_QUALITY_GATE.md` and
   `docs/evidence/option-b-programme-2026-08-02/PLATFORM_QUALITY_GATE.md`
   (append staging probe + RIC validate-only results before flipping production).

**Owner-only gates (not authorised by this checklist alone):** production
`ADMIN_API_ENABLED`, Ask VanAssist / facilities / paid AI flags, DNS changes,
and live production migrations.

Production still requires the four Quality Gate pillars. Application code alone
does not authorise DNS, live migrations or turning `ADMIN_API_ENABLED` on in
production.

## Out of scope for first production enablement

- Unrestricted service-account publish/merge/purge (merge and draft approve remain human-preferred)
- Bing Search
- Auto-publish of research or AI-only records
