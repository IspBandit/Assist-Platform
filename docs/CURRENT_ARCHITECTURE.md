# Assist Platform: Current Architecture

## Purpose

This document records the verified architecture inherited from VanAssist before
multi-brand changes. It is a baseline for compatibility and migration decisions,
not a claim that every existing control is production-ready.

## Runtime and hosting

- PHP 8.1+ (validated locally on PHP 8.3), Apache `mod_rewrite`, MySQL 8 or
  MariaDB, and file-backed PHP sessions.
- Composer manages PHPMailer and development tooling. The application also has a
  custom `App\` autoloader and a built-in SMTP fallback.
- The deployment target is conventional Linux/cPanel hosting. Node and Python
  are used only by optional data-import tooling.
- `public/` is the intended document root. Private uploads, sessions, logs,
  backups, imports, and exports live under `storage/`.

## Request lifecycle

1. `public/index.php` loads `bootstrap/autoload.php`.
2. `App\Core\Kernel` loads environment and PHP configuration, registers error
   handling, starts the session, registers middleware, and loads routes.
3. Install, launch-mode, and maintenance gates run before route dispatch.
4. `App\Core\Router` matches the request and builds the middleware pipeline.
5. Controllers call hand-written services and PDO-backed models.
6. `App\Core\View` renders server-side PHP templates.
7. Optional first-party analytics are recorded after dispatch.

The codebase is a custom MVC application, not Laravel or Symfony. There is no
container, ORM, event bus, formal queue service, REST framework, or frontend
framework.

## Repository layout

| Path | Responsibility |
| --- | --- |
| `app/Core` | Kernel, router, request/response, database, sessions, views, logging |
| `app/Auth` | Session authentication and role/permission checks |
| `app/Middleware` | CSRF, authentication, role, permission, and response headers |
| `app/Controllers` | Public, account, provider, park, admin, installer, and billing endpoints |
| `app/Models` | Lightweight PDO query models |
| `app/Services` | Business logic, matching, imports, mail, cron, billing, finance, analytics |
| `app/Views` | Server-rendered public, portal, authentication, installer, and admin templates |
| `routes` | Route registration split by application area |
| `config` | PHP configuration derived from `.env` |
| `database/migrations` | Authoritative ordered database changes |
| `database/seeds` | Core, content, email, location, and provider data |
| `public` | Front controller, web-server rules, CSS, JavaScript, images, public uploads |
| `storage` | Non-public runtime data |
| `cron` | Scheduled-task entry point |
| `scripts` | Migration, seeding, reporting, and deployment tools |
| `tests` | PHPUnit unit suite; integration suite is currently empty |

## Route surface

The application registers approximately 292 runtime routes:

- Public website and marketplace: about 48 routes plus generated CMS routes.
- Authentication: 10 routes.
- Installer: 4 routes.
- Customer account: 8 routes.
- Provider portal: 37 routes.
- Caravan-park portal: 13 routes.
- Administration: 172 routes.

Only GET and POST are registered directly. Method spoofing exists in the request
layer. Public JSON endpoints are limited to town lookup, nearest-town, and nearby
provider responses. There is no separately versioned public API.

## Identity and authorisation

- `users` is the global account table.
- Authentication uses server-side sessions and `password_hash`.
- Email verification and password reset use hashed, expiring tokens.
- Global RBAC uses `roles`, `permissions`, `user_roles`, and
  `role_permissions`.
- Existing roles are guest, customer, provider, caravan-park-partner, moderator,
  administrator, and super-administrator.
- Portal ownership checks are implemented in controllers and services.
- `providers.user_id` acts as a legacy owner pointer, but is nullable and is not
  unique. There is no provider-team membership model.
- Roles have no brand, organisation, or provider scope.

## Domain modules

### Marketplace

The current product supports provider profiles, services, service areas,
availability, licences, documents, verification, claims, promotions, public
directory pages, saved providers, and provider analytics.

### Requests and matching

Customers and guests can submit assistance requests. Admins moderate them and
match providers manually or through feature-flagged automatic matching. Contact
release is consent-gated. Service runs and caravan-park workflows extend the
matching model.

### Content and SEO

The application includes CMS pages and blocks, FAQs, town/region/category pages,
provider pages, metadata, structured data, sitemap generation, and robots
generation. A static `public/robots.txt` currently takes precedence over the
dynamic route under the documented Apache configuration.

### Notifications

Email is queued in MySQL and processed by cron through PHPMailer or the built-in
SMTP client. In-app notifications and targeted broadcasts are present.

### Billing and finance

Billing tables, entitlements, invoices, webhook signature verification, and a
gateway abstraction exist behind disabled feature flags. Stripe customer,
subscription, and cancellation operations are not implemented. A separate
double-entry owner-finance ledger is implemented.

### Analytics

The application records first-party page views, searches, contact actions,
outcomes, provider coverage, and daily demand metrics. Analytics records are
single-brand and do not currently carry immutable brand attribution.

## Database

- The 26 ordered SQL migrations define approximately 116 tables.
- `database/migrations/*.sql` is authoritative.
- `database/schema.sql` is stale: it stops after migration 012 and must not be
  treated as equivalent to a migrated database.
- Models use hand-written prepared PDO queries; relationships are not described
  in an ORM.
- Core tables have useful foreign keys and indexes, but billing, finance,
  analytics, token, membership, and provider-area integrity have material gaps.
- No table currently provides a complete brand or tenant boundary.

## Frontend

- Server-rendered PHP templates with progressively enhanced vanilla JavaScript.
- One primary stylesheet serves public, portal, and admin interfaces.
- A small token set exists in CSS, but brand colours, marks, text, and layout
  values are also duplicated in templates and inline styles.
- Reusable partials exist, but provider cards, breadcrumbs, progress indicators,
  form errors, and portal navigation have competing implementations.
- The established VanAssist visual identity is teal, cream/sand, charcoal, and
  amber. It must be preserved while tokens and components are extracted.

## Integrations

- SMTP through PHPMailer or a built-in client.
- Stripe webhook verification and event intake; charging is dormant/incomplete.
- OpenStreetMap Overpass and Google Places development/import tooling.
- Browser geolocation and Google Maps direction links.
- Local/private filesystem storage.
- FTP/FTPS deployment through a PowerShell/WinSCP script.

## Tests and operations

- PHPUnit 10; 28 unit tests and 95 assertions currently pass on PHP 8.3.
- Five warnings occur because one disabled analytics test reaches database
  configuration without a test environment.
- `tests/Integration` contains no implemented integration tests.
- There is no CI workflow, protected-branch policy, container build, release
  artefact, or automated production deployment.
- Apache and MariaDB are suitable for local parity, but application/database
  setup still requires a dedicated `.env`, schema migration, and seed.

## Existing brand state

VanAssist is the only implemented brand. No TowSmart, TrailerWise, brand registry,
brand resolver, hostname mapping, brand-scoped roles, or brand-scoped data model
exists. VanAssist names, domains, contact details, colours, assets, email
identity, CMS content, and cookies are hard-coded in multiple layers.

This baseline requires an additive migration. A duplicated application or a
mass rename would increase risk and is not the selected approach.
