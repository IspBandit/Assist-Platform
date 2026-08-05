# Assist Platform API Architecture

## Current status

The application is server-rendered. Existing JSON location/search endpoints are
first-party web endpoints, not a stable public API. A versioned Admin API under
`/api/v1/admin` is implemented for Assist RIC and trusted management clients
(CORE-011). Phase 1 + Option B code is present; production enablement still
requires MFA gate, staging rehearsal and Quality Gate evidence. See
`docs/LIVE_API.md`, `docs/PHASE1_ADMIN_API_DESIGN.md` and
`docs/OPTION_B_MANAGEMENT_PROGRAMME.md`.

RIC everyday management adds read-only analytics rollups when `analytics:read`
is granted:

- `GET /api/v1/admin/overview` — operational dashboard (API health/release,
  genuine website visitors with bot/unknown page views labelled separately,
  searches, no-result searches, provider contacts, scope-gated review queues,
  AI cost when `ai:read`, dataset sync timestamps when `datasets:read`).
- `GET /api/v1/admin/website-insights` — full brand website insights document
  reusing `WebsiteInsightsService`; `filtered_bot_page_views` is never mixed
  into visitor totals.

Ask VanAssist natural-language search is a parallel **web** entry at `GET /ask`
(VanAssist only), gated by feature flag `assist_ai_search` (default off). It
calls the internal Assist AI Orchestrator (`App\Platform\AiSearch`). A future
`POST /api/v1/search/assist` is not finalised. Vendor AI HTTP APIs are never
exposed to browsers. See `docs/PHASE_AI0_DESIGN.md` and
`docs/NATURAL_LANGUAGE_SEARCH.md`.

## Future contract

New external APIs use `/api/v1/...` and separate route files/controllers from
HTML handlers. A version is additive within its published compatibility window;
breaking field, authentication, or semantic changes require a new major path.

The active brand is resolved from the verified deployment/host context. Clients
cannot select a private data scope by sending `brand_id` in a body or query.
Every repository query also applies brand and provider/organisation ownership
where relevant.

Assist RIC Directory Management browses Platform directory resources through
versioned Admin API reads only: `GET /api/v1/admin/providers`, `/stays`,
`/facilities`, `/claims` and `/corrections`, with cursor pagination and
least-privilege scopes. Controlled writes use the existing audited mutation
routes; claim and correction approval requires a human Admin API session.
Taxonomy pickers use `GET /categories` (`categories:read`, brand provider
categories) and `GET /locations/states|regions|towns` (`locations:read`).
Category/location writes remain website admin.

Assist RIC Data Review browses existing Admin API review queues:
`GET /api/v1/admin/drafts`, `/duplicates` and `/recycle-bin` (cursor
pagination), plus import-candidate queues
`GET /facility-import-candidates` and `GET /provider-import-candidates`
(`import_candidates:read`; separate from `GET /imports` RIC package jobs).
Assist RIC stages and auto-publishes trusted government facility packs with
`POST /api/v1/admin/facility-imports` and can flush backlog via
`POST /api/v1/admin/facility-imports/publish-pending` (`imports:write`,
ADR 0034). Facility import-candidate approve/reject (including
bulk-approve/bulk-reject) remains for human Admin API sessions
(`import_candidates:review`) when non-RIC review is needed; provider
import-candidate approve/reject/merge is human-only Admin API (same
scope; hold/confirm stay website admin). Service accounts stay read-only on
review and cannot hold that scope. Draft approve, duplicate merge and recycle
purge remain human-session / website-admin actions. Stale/missing quality
queues remain PHP-admin only.
Default RIC service scopes include `recycle_bin:restore` (list/restore) but
not `recycle_bin:purge`.

Assist RIC Ask Insights reads Ask activity and gaps through
`GET /api/v1/admin/ai/usage/requests` (and summary/costs) plus dual-source
`GET /api/v1/admin/search-gaps`. Knowledge-gap engagement counters appear under
item `meta`. No separate knowledge-gaps path. Export stays client-side from
those JSON collections without exposing private user identifiers.

Assist RIC Operations provides read-only operational visibility through
`GET /api/v1/admin/health`, `/version`, `/capabilities`, dataset
`/sync-history`, `/sync-conflicts`, `GET /imports` + `/imports/{id}`, AI usage
rollups (summary includes nested `budget`), `GET /feature-flags`
(`flags:read`) and `/audit`. It does not expose feature-flag writes, AI budget
cap mutation, or import/publish controls from the Operations page. Dangerous
production toggles stay in website admin with owner workflows.

`GET /api/v1/admin/capabilities` is host scoped. Its `brand` object identifies
the resolved Assist brand (`key`, `name`, `status`, `url` and enabled
`modules`). Resource modes also reflect that brand: provider or stay resources
are reported as `unavailable` when the selected brand does not support them.
Management clients must verify this identity before displaying live data so a
misconfigured endpoint cannot mix records between VanAssist, TowSmart,
TrailerWise, LocalTorque or Polaris.

## Authentication and authorization

- Browser endpoints retain secure host-only sessions and CSRF protection.
- Future machine clients require scoped, revocable credentials; browser session
  cookies are not a general API credential.
- Authorization is checked server-side for every resource, including numeric
  IDs, slugs, aliases, exports, media, and nested resources.
- Cross-brand and cross-provider denials return a non-enumerating `404` where
  revealing existence would leak private data.

## Request and response standards

- JSON uses UTF-8 and `application/json`.
- Successful single resources use `{"data": {...}}`; collections use
  `{"data": [...], "meta": {...}, "links": {...}}`.
- Errors use `{"error": {"code": "...", "message": "...",
  "request_id": "..."}}`; validation may include a field-error map.
- Pagination is bounded and cursor-based for mutable/high-volume collections.
- Timestamps are ISO 8601 with an explicit offset; money is integer minor units
  plus ISO currency.
- Unknown fields are rejected for security-sensitive writes.
- Request and response correlation uses `X-Request-ID`.

## Mutation safety

State-changing endpoints validate content type, schema, feature/module gates,
brand scope, ownership, and current resource state. Retryable create/payment/
webhook operations require an idempotency key with a scoped uniqueness record.
Webhooks additionally require signature/timestamp verification and durable
event deduplication before side effects.

## Abuse controls and caching

Authentication and public-submission flows use persistent hashed rate-limit
buckets. API limits must return `429` and `Retry-After`. Authenticated/private
responses are `private, no-store`; explicitly public resources may opt into
short cache lifetimes with brand-aware keys and invalidation.

## Deprecation

Published fields or endpoints receive a documented replacement and sunset
window. Deprecation metadata should use `Deprecation`, `Sunset`, and `Link`
headers. Removal requires usage review, release notes, and contract tests.
