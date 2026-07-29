# Routes and permissions

Route files are authoritative. All public routes use security headers and
state-changing browser routes use CSRF protection.

| Surface | Prefix/examples | Gate |
|---|---|---|
| Public | `/`, `/providers`, `/find`, `/services`, `/regions`, `/request-assistance` | Brand/module checks plus rate limits on abuse-prone submissions |
| LocalTorque motorsport | `/motorsport` | LocalTorque host only; official sanctioning-body, venue and calendar links are public/read-only |
| TowSmart | `/calculator`, `/account/towing-combinations` | TowSmart host/module; saving requires authenticated owner |
| TrailerWise | `/marketplace`, `/trailers/{slug}` | TrailerWise host/module; current listing model only |
| Authentication | `/login`, `/register`, reset/verification/logout | Guest/auth state, CSRF and rate limiting |
| Customer account | `/account/*` | `auth`; controllers must enforce user ownership and brand scope |
| Provider portal | `/provider/*` | `auth` plus provider/administrator/super-administrator role; controllers enforce provider ownership |
| Park portal | `/park/*` | Authenticated park membership/administration |
| Admin | `/admin/*` | Moderator/administrator/super-administrator role plus controller permission checks |
| Installer | `/install/*` | Setup authorisation and permanent installer lock after installation |
| Stripe webhook | `/billing/webhook/stripe` | No browser CSRF; signature verification and idempotency required |
| Public documentation | `/help`, `/help/whats-new`, `/help/{guide}/{article}` | Public catalogue allowlist; administrator/developer/API guides return 404 |
| Operational documentation | `/admin/help`, `/admin/help/whats-new`, `/admin/help/{guide}/{article}` | Existing admin role middleware; all registered guides searchable |

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

Anonymous session ids are never shown in the administrator UI. Signed-in and
anonymous counts are aggregated; reports do not expose IP addresses or raw
event metadata.

Adding a route requires appropriate middleware, controller ownership checks,
brand isolation tests and an update here when it creates a new surface.

Dashboard layouts resolve their Help link from registered article routes. A
missing exact route falls back to the owning audience overview; this does not
grant access because the destination controller applies its normal public/admin
catalogue boundary.

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

# Data Intelligence

| Route | Permission | Scope |
| --- | --- | --- |
| `GET /admin/data-intelligence` | `data_intelligence.view` | Platform Admin only, selected brand |
| `POST /admin/data-intelligence/tasks` | `data_intelligence.manage` | Platform Admin only, selected brand |
| `POST /admin/data-intelligence/tasks/status` | `data_intelligence.manage` | Platform Admin only, selected brand |
