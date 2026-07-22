# Routes and permissions

Route files are authoritative. All public routes use security headers and
state-changing browser routes use CSRF protection.

| Surface | Prefix/examples | Gate |
|---|---|---|
| Public | `/`, `/providers`, `/find`, `/services`, `/regions`, `/request-assistance` | Brand/module checks plus rate limits on abuse-prone submissions |
| TowSmart | `/calculator`, `/account/towing-combinations` | TowSmart host/module; saving requires authenticated owner |
| TrailerWise | `/marketplace`, `/trailers/{slug}` | TrailerWise host/module; current listing model only |
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

Adding a route requires appropriate middleware, controller ownership checks,
brand isolation tests and an update here when it creates a new surface.

