# Routes and permissions

Route files are authoritative. All public routes use security headers and
state-changing browser routes use CSRF protection.

| Surface | Prefix/examples | Gate |
|---|---|---|
| Public | `/`, `/providers`, `/find`, `/ask` (Ask VanAssist; flag `assist_ai_search`), `/services`, `/regions`, `/request-assistance` | Brand/module checks plus rate limits on abuse-prone submissions |
| Public stay facilities | `GET/POST /caravan-parks/{slug}/suggest-facility` | VanAssist active stay; CSRF, rate limit and Turnstile; creates pending evidence only |
| LocalTorque motorsport | `/motorsport` | LocalTorque host only; official sanctioning-body, venue and calendar links are public/read-only |
| TowSmart | `/calculator`, `/account/towing-combinations` | TowSmart host/module; saving requires authenticated owner |
| TrailerWise | `/marketplace`, `/trailers/{slug}` | TrailerWise host/module; current listing model only |
| Polaris | `/rvs`, `/find`, `/compare`, `/tow-match`, `/floorplans`, `/saved`, `/portal/manufacturer` | Polaris host + `rv_catalogue` module; brand remains private until launch |
| Authentication | `/login`, `/register`, reset/verification/logout | Guest/auth state, CSRF and rate limiting |
| Customer account | `/account/*` | `auth`; controllers must enforce user ownership and brand scope |
| Provider portal | `/provider/*` | `auth` plus provider/administrator/super-administrator role; controllers enforce provider ownership |
| Park portal | `/park/*` | Authenticated park membership/administration |
| Admin | `/admin/*` | Moderator/administrator/super-administrator role plus controller permission checks |
| Installer | `/install/*` | Setup authorisation and permanent installer lock after installation |
| Stripe webhook | `/billing/webhook/stripe` | No browser CSRF; signature verification and idempotency required |

Common admin permissions include `users.manage`, `users.export`,
`providers.manage`, `providers.approve`, `documents.verify`, `requests.manage`,
`runs.manage`, `parks.manage`, `prospects.manage`, `content.manage`, `seo.manage`,
`settings.manage`, `notifications.send`, `reports.view`, `logs.view`, finance and
billing permissions. Super-administrator bypass exists deliberately; all other
roles require an assigned permission.

## Website insights

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/demand` | `demand.view` | Selected brand only; aggregate visits, sources, devices, service/location searches, provider interest and contact actions |
| `GET /admin/demand/providers` | `demand.view` | Selected-brand provider result appearances, profile views and contact actions |
| `GET /admin/demand/funnel` | `demand.view` | Selected-brand search-to-confirmed-use funnel |
| `GET /admin/demand/export` | `demand.export` | Selected-brand date-filtered CSV output |

## Assist AI Search (CORE-012)

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/ai-search` | `settings.manage` | AI settings, budget remaining, usage today/month, cache hit rate, paid-AI gate |
| `POST /admin/ai-search` | `settings.manage` | Update caps/flags/allowlist; audit `ai.settings_updated`; no API keys stored |
| `GET /admin/ai-search/gaps` | `demand.view` or `settings.manage` | Ranked knowledge gaps for selected brand |
| `POST /admin/ai-search/gaps` | `settings.manage` | Update gap status / RIC job notes |
| `GET /admin/ai-search/gaps/export` | `demand.export` or `settings.manage` | CSV for RIC research hand-off |

## Polaris catalogue (POL-001…POL-009)

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/polaris` | `polaris.manage` | Selected brand with `rv_catalogue` |
| `GET /admin/polaris/manufacturers` | `polaris.manage` | Lifecycle-filtered manufacturer list |
| `GET /admin/polaris/models` | `polaris.manage` | Lifecycle-filtered model list |
| `POST /admin/polaris/models/lifecycle` | `polaris.manage` | Soft lifecycle transitions |
| `GET /admin/polaris/imports` | `polaris.manage` | CSV draft import jobs |
| `POST /admin/polaris/imports/upload` | `polaris.manage` | Creates drafts only; never auto-publishes |
| `GET /admin/polaris/review-queue` | `polaris.manage` | Pending drafts and manufacturer claims |
| `POST /admin/polaris/review-queue/draft` | `polaris.review` | Approve (publish) or reject draft |
| `POST /admin/polaris/review-queue/claim` | `polaris.manage` | Approve or reject manufacturer claim |
| `GET/POST /portal/manufacturer*` | auth + claim gate | Claim-first; edits set verification pending |

Anonymous session ids are never shown in the administrator UI. Signed-in and
anonymous counts are aggregated; reports do not expose IP addresses or raw
event metadata.

Adding a route requires appropriate middleware, controller ownership checks,
brand isolation tests and an update here when it creates a new surface.

## Provider email campaign recipients

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/notifications/show` | `notifications.send` | Selected-brand campaign and searchable provider candidate pool |
| `POST /admin/notifications/recipient-exclude` | `notifications.send` | Remove one in-scope provider from one campaign |
| `POST /admin/notifications/recipient-restore` | `notifications.send` | Restore a consent-eligible provider; global suppression still wins |
| `POST /admin/notifications/recipient-include` | `notifications.send` | Record dated consent evidence and add one in-scope provider |

# Data Sources

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/data-sources` | `data_sources.view` | Platform Admin only |
| `POST /admin/data-sources/connector` | `data_sources.manage` | Platform Admin only |
| `POST /admin/data-sources/mapping` | `data_sources.manage` | Platform Admin only |
| `POST /admin/data-sources/run` | `data_sources.run` | Platform Admin only |
| `GET/POST /admin/data-sources/review` | `data_sources.review` | Platform Admin only |
| `POST /admin/data-sources/schedule` | `data_sources.manage` | Platform Admin only |
| `GET /admin/data-sources/datasets` | `data_sources.view` | Platform Admin only |
| `GET /admin/data-sources/datasets/edit` | `data_sources.manage` | Platform Admin only |
| `POST /admin/data-sources/datasets/upsert` | `data_sources.manage` | Platform Admin only |
| `POST /admin/data-sources/datasets/save` | `data_sources.manage` | Platform Admin only |
| `POST /admin/data-sources/datasets/fetch` | `data_sources.run` | Platform Admin only |
| `GET/POST /admin/data-sources/facilities/review` | `data_sources.review` | Platform Admin only |
| `GET /admin/facility-contributions*`, `POST /admin/facility-contributions/moderate` | `parks.manage` | Human moderation of VanAssist stay-facility suggestions |

Admin API facility scopes also expose `GET /facility-contributions`, `GET /facility-contributions/{id}` and human-only `POST /facility-contributions/{id}/{action}`. Supported actions are `approve`, `approve-with-edit`, `partial-approve`, `reject` and `duplicate`.

# Data Intelligence

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/data-intelligence` | `data_intelligence.view` | Platform Admin only, selected brand |
| `POST /admin/data-intelligence/tasks` | `data_intelligence.manage` | Platform Admin only, selected brand |
| `POST /admin/data-intelligence/tasks/status` | `data_intelligence.manage` | Platform Admin only, selected brand |
