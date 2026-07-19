# VanAssist — Architecture & Phased Implementation Plan

This document describes the overall architecture, the database schema and the
10-phase build sequence. **Phase 1 is implemented** in this repository; later
phases extend it without rebuilding the core.

---

## 1. Technology & hosting

- **PHP 8.1+**, MySQL 8 / MariaDB, Apache `mod_rewrite`, server-side sessions.
- **No** Docker, Node runtime, Redis, websockets, Supabase/Firebase, PostgreSQL,
  Python services or daemons at runtime. Node is only used locally (optional) to
  minify assets.
- Custom **lightweight MVC** framework (no heavyweight framework) so it stays
  maintainable on shared cPanel hosting. Composer is used only for PHPMailer and
  dev tooling; the app autoloads without `composer install`.

## 2. Application architecture

```
HTTP request
   │
public/index.php            front controller (only web-accessible PHP entry)
   │
bootstrap/autoload.php      PSR-4 autoloader (App\ → app/) + Composer + helpers
   │
App\Core\Kernel             boot env/config, session, error handling, routing
   │  ├─ install gating (redirect to /install until locked)
   │  ├─ maintenance gating (admins bypass)
   │  └─ Router::dispatch
   │
Middleware pipeline         headers → csrf → auth → role/permission
   │
Controller                  thin; delegates to Services/Models
   │
Model (PDO) / Service       prepared statements, business logic
   │
View                        server-side PHP templates with layout inheritance
```

### Key components (`app/Core`)

| Class | Responsibility |
|-------|----------------|
| `Kernel` | Request lifecycle, install/maintenance gating, route loading |
| `Router` | Verb routing, `{param}` matching, groups, middleware pipeline |
| `Request` / `Response` | Request capture, method spoofing; HTML/JSON responses |
| `Database` | Shared PDO connection + prepared-statement helpers |
| `View` | Template engine with `extend`/`section`/`yield` |
| `Session` | Secure cookies, flash, CSRF token |
| `Config` | Dot-notation access to `config/*.php` |
| `Logger` / `ErrorHandler` | File logging + friendly error pages |

### Cross-cutting services (`app/Services`)

`Settings`, `Migrator`, `Seeder`, `DemoSeeder`, `EmailQueue`, `Mailer`,
`AuditLog`, `CronRunner`, `Backup`.

### Roles & permissions (`app/Auth`)

Seven roles — guest, customer, provider, caravan-park-partner, moderator,
administrator, super-administrator. Permissions are fine-grained and attached to
roles via `role_permissions`. The super administrator bypasses individual
permission checks. Route protection uses `role:` for area gating and
`permission:` for specific actions.

## 3. Database schema

Schema is defined in `database/migrations/*.sql` (authoritative, run in order)
and mirrored in `database/schema.sql` for direct import. utf8mb4 throughout,
InnoDB, foreign keys, indexes on common filters, soft-delete (`deleted_at`)
where appropriate, and `*_status_history` tables for auditing.

Migration groups:

1. `001_core_auth` — roles, permissions, users, sessions, resets, verifications, consents, login history
2. `002_locations` — countries → states → regions → towns → postcodes, town neighbours
3. `003_service_categories` — nestable categories + qualification requirements
4. `004_customers` — customer profiles, saved locations, alerts
5. `005_providers` — prospect CRM, providers (+ future billing fields), services, areas, documents, licences, availability, verifications, invitations, notes
6. `006_caravan_parks` — parks, park users, documents, service-day requests
7. `007_service_requests` — requests, categories, images, status history, notes, matches, messages
8. `008_service_runs` — runs, towns, services, requests, bookings, status history
9. `009_notifications_email` — templates, queue, log, notifications, recipients
10. `010_content_cms` — content pages, blocks, FAQs, settings, feature flags
11. `011_system` — audit logs, contact, complaints, reports, exports, scheduled tasks, health, page views
12. `012_billing` — `billing_plans`, `billing_plan_prices`, `billing_plan_features`, `billing_plan_limits`, `provider_subscriptions`, `provider_subscription_history`, `provider_plan_overrides`, `provider_entitlements`, `provider_usage_counters`, `billing_customers`, `payment_methods`, `invoices`, `invoice_items`, `payments`, `refunds`, `discount_codes`, `discount_redemptions`, `billing_events`, `billing_webhook_events`, `commission_rules`, `commission_transactions`, `booking_fees`, `tax_settings` (+ founding-provider/subscription columns on `providers`) — dormant while `ENABLE_BILLING=false`

### Expansion model

The location hierarchy is fully data-driven; **no state is hardcoded in business
logic**. Queensland ships active and seeded, but admins can activate other states
later. Town demand totals are derived from open requests.

### Billing / monetisation architecture (implemented, dormant)

VanAssist is free at launch but built as a subscription-capable platform. The
full architecture exists and is switched off by `ENABLE_BILLING=false`.

- **Schema** (`012_billing.sql`): 23 gateway-neutral tables — plans, prices,
  features, limits; provider subscriptions, history, overrides, entitlements,
  usage counters; billing customers, payment methods, invoices, items,
  payments, refunds; discount codes/redemptions; billing + webhook events;
  commission rules/transactions; booking fees; tax settings. Stripe IDs live
  in `external_*_ref` columns. `providers` gains `plan_id`,
  `subscription_state`, founding-provider snapshot fields, etc.
- **Gateway abstraction** (`app/Billing`): `BillingGatewayInterface`,
  `NullGateway` (free launch), `StripeGateway` (signature verification ready),
  resolved by `BillingManager`. New gateways need no controller changes.
- **Central entitlements**: `PlanEntitlementService` is the only place that
  answers "can this provider do X / are they within limit Y?". While billing is
  disabled it is fully permissive, so no feature is gated by payment. When
  enabled it resolves overrides → snapshot → plan → safe default. Keys live in
  `App\Billing\Entitlements`.
- **Usage metering**: `UsageMeteringService` recalculates counters server-side;
  browser-supplied values are never trusted.
- **Subscriptions**: `SubscriptionService` provisions every provider (assigned
  plan, complimentary subscription, entitlement snapshot, usage counters,
  founding status, billing-customer placeholder), and handles plan assignment,
  bulk migration, grandfathering, complimentary access, per-provider overrides
  and founding snapshots. Records are never deleted when a subscription ends —
  providers fall back to a configurable free plan.
- **Invoices & webhooks**: `InvoiceService` builds AUD/GST tax invoices (marked
  for accountant review). `PaymentEventService` ingests webhooks with signature
  verification, idempotency and retry; subscriptions update only from verified
  server-side events.
- **UI**: admin `/admin/billing` (edit plans/limits/entitlements, view flags,
  `billing.manage`); provider `/provider/billing` and the `/billing/webhook/*`
  endpoint return 404 while billing is disabled.
- **Feature flags**: granular `ENABLE_*` flags; a capability is never exposed
  merely because its tables exist.

## 4. Security model

See [`SECURITY.md`](../SECURITY.md). Highlights: `password_hash`, CSRF tokens,
PDO prepared statements, output escaping, secure/HttpOnly/SameSite cookies,
session regeneration on login, login rate limiting, RBAC on every protected
route, security headers + CSP, private file storage outside the web root, audit
logging, admin idle-session timeout.

---

## 5. Phased implementation plan

Each phase ends with: run tests, document DB changes, update README + changelog,
commit cleanly, no broken placeholder routes.

### Phase 1 — Foundation ✅ (implemented)
Folder structure, configuration, DB connection, routing, authentication, roles &
permissions, installation wizard, admin shell, public layout, error pages, cron
framework, queued email, backups.

### Phase 2 — Locations & service categories ✅ (implemented)
Admin CRUD for states, regions and towns (active/featured/launch flags, SEO
fields, public content, town geo + postcode) and nestable service categories
(parent, SEO content, show/hide). Public service-category index + detail,
region index + detail, and town detail pages generated from the database, with
canonical URLs and per-town `noindex` honoured. Models: `State`, `Region`,
`Town`, `ServiceCategory`.

### Phase 3 — Provider management
Provider prospect CRM (CSV import/export, outreach status, notes), invitation
tokens, provider onboarding wizard, verification of licences/insurance,
public provider profiles, service areas, admin provider management.

**Part 1 implemented.** Admin provider management (`/admin/providers`):
list/filter, create/edit, approval workflow (approve/reject/suspend/reactivate)
with notification emails, verified/insured/featured flags, service & service-area
management, document/licence verification, internal notes; new providers
auto-provisioned with dormant billing records. Provider prospect CRM
(`/admin/prospects`): outreach pipeline, contact-log notes, CSV import/export,
and 14-day tokenised invitations (`provider_invitation` email). Public
acceptance flow (`/provider/join/{token}`) creates the user + provider, marks the
prospect `registered` and signs in. Public provider directory (`/providers`,
filterable) and profiles (`/providers/{slug}`) honour opt-in contact visibility.
Model: `Provider`.

**Part 2 implemented.** Provider self-service dashboard (`/provider`) with a
profile-completeness checklist and sub-navigation: business profile editor,
services, service areas, verification documents (secure upload via the new
`FileStorage` service — finfo MIME validation, size cap, opaque filenames stored
outside the web root, authenticated download, removal blocked once verified),
licences and availability. Admins can download documents from the provider
screen for verification. Status/verification/featured flags remain
admin-controlled. **Remaining (optional polish):** a guided multi-step
onboarding wizard (the dashboard checklist currently fills this role).

### Phase 4 — Customer requests
Multi-step request form (location → vehicle → category → fault → images →
contact/consent), secure image processing (MIME inspection, resize, EXIF strip,
thumbnails, random names, outside web root), customer dashboard, admin
moderation, full status history.

**Implemented.** Public `/request-assistance` sectioned form with honeypot +
server-side validation and category multi-select; guest double opt-in email
verification (`/request/verify`) vs. signed-in fast path; `VA-XXXXXX`
references. `ImageProcessor` service does finfo MIME validation, GD re-encode
(EXIF stripped), downscale, thumbnails, opaque names, private storage. Customer
dashboard lists/views requests with a status timeline, photos and ownership-
checked image serving. Admin moderation (`/admin/requests`): list/filter,
detail, approve (emails customer)/reject/spam, status changes with notes,
internal notes, image viewing. Model `ServiceRequest`; service `RequestWorkflow`
records the immutable status history.

### Phase 5 — Matching
Scored provider suggestions (town/region/distance/category/run/verification/
response history), admin matching console, provider interest workflow, gated
contact release, customer notifications.

**Implemented.** `MatchingService` scores active providers (primary + related
category, same-town / service-area town/region/state, service-model fit,
verified/insured/featured) and returns ranked suggestions with reasons. Admin
matching console (`/admin/matching`): urgency-ordered queue + per-request page to
add/invite providers (`provider_match_invitation` email), update match status,
and release customer contact (emailed); request status and history auto-sync with
customer notifications. Provider portal (`/provider/requests`): incoming matches
with photos (contact gated until released) and interested/more-info/decline
responses that notify the customer. Uses `service_request_matches`.

### Phase 6 — Service runs
Create/edit runs, run detail with progress indicator, capacity calculation,
join-run flow, linking requests, public run listings.

**Implemented.** `ServiceRun` model + `RunWorkflow` service handle listings,
unique slugs, status history (`service_run_status_history`) and automatic
capacity bookkeeping (`recalcCapacity` recounts active registrations and moves
runs to/from `fully_booked`). Public listing (`/service-runs`) and run detail
(`/service-runs/{slug}`) show a capacity progress bar, stops and services with a
**join-run flow** — registering requires a free account, de-duplicates per
customer, can link an existing request and preferred stop, and emails a
confirmation. Admin console (`/admin/runs`) covers create/edit, status, stops,
services, linking matched requests (also setting `service_request_matches.run_id`)
and per-registration management. Providers manage their own runs at
`/provider/runs`. Tables: `service_runs`, `service_run_towns`,
`service_run_services`, `service_run_requests`, `service_run_bookings`,
`service_run_status_history`.

### Phase 7 — Caravan park partners
Park application + dashboard, register guest request, nearby runs, request a
service day, QR code + printable materials, public park page.

**Implemented.** Public application (`/caravan-parks/apply`) creates a
`caravan-park-partner` login + `pending` park and signs the manager in. Park
portal (`/park`): dashboard with profile checklist, editable profile (logo via
`FileStorage`, SEO, public-page toggle), documents, register-a-guest-request
(`park` source, straight to moderation), nearby runs, request-a-service-day, and
QR code + printable materials. The `QrCode` service is a dependency-free QR
generator (byte mode, EC level M) emitting an SVG `data:` URI (allowed by the
CSP). Public park page (`/caravan-parks/{slug}`) shows details and nearby runs
once active + enabled. Admin console (`/admin/parks`) covers applications,
approve/reject/suspend (emailed), documents and service-day triage. Guest
requests carry `park_id` and a `park`/`park_qr` source. Tables: `caravan_parks`,
`caravan_park_users`, `caravan_park_documents`,
`caravan_park_service_day_requests`.

### Phase 8 — CMS & SEO ✅ (built)
Content pages/blocks editor, homepage editing, per-page SEO + Open Graph +
schema, XML sitemap, robots.txt, breadcrumbs, noindex control for thin pages.

Built: a shared `partials/seo-meta` head partial (title with single site-name
suffix, meta description, canonical, robots, Open Graph + Twitter cards, and
JSON-LD blocks). Admin content tools (`Admin\ContentController`) cover editable
pages with per-page SEO/OG/JSON-LD and publish control, homepage blocks, and
FAQs CRUD; `Admin\SeoController` manages site-wide name, default description,
social image and a master indexing switch (`seo_allow_indexing`, defaulting to
off until the live launch). Public side adds a grouped FAQ page with `FAQPage`
structured data, `Organization`/`WebSite` JSON-LD on the homepage and
`LocalBusiness` JSON-LD on provider profiles. `Site\SitemapController` serves a
dynamic `sitemap.xml` (pages, categories, regions, towns, active providers,
public runs, public parks) and a `robots.txt` that disallows private areas — or
the whole site while indexing is off. Tables: `content_pages`,
`content_blocks`, `faqs`, `site_settings` (migration 010).

### Phase 9 — Notifications & cron ✅ (built)
Email template editing, targeted broadcasts (town/region/category/providers),
scheduled jobs, reminders, full cron schedule.

Built: `Admin\EmailTemplatesController` edits the seeded transactional templates
(subject/HTML/text/enabled) with a sample-data preview and a "send test" that
queues a real email. `Admin\NotificationsController` composes targeted
broadcasts — audiences resolved by `BroadcastAudience` (everyone opted in,
all active providers, customers with open requests, or by town/region/category
combining opted-in customers with relevant providers) — with a recipient-count
preview, then save-as-draft, schedule, or send now. `NotificationService`
resolves the audience at dispatch time, records `notification_recipients`, and
queues the emails for the existing `Mailer` cron. The previously stubbed cron
tasks are now real: `process_notifications` (dispatch due scheduled broadcasts),
`send_run_reminders` (booked customers N days before a run), `document_expiry`
(provider licence reminders at 30/14/7 days), and `provider_followups` (surfaces
expiring invitations and stale applications to `system_health_logs`). The full
cron schedule is documented in `INSTALL-CPANEL.md`. Tables: `email_templates`,
`email_queue`, `email_log`, `notifications`, `notification_recipients`,
`scheduled_tasks` (migrations 009/011).

### Phase 10 — Reports, audit & launch tools ✅ (built)
Reports + CSV export, audit log viewer, backups UI (super admin), launch modes,
demo data removal, production checklist, analytics settings.

Built: `Admin\ReportsController` (request funnel, demand by town/category,
provider/run/park summaries, email-queue health, 30-day traffic) with CSV
exports via a new `CsvExport` helper. `Admin\AuditController` is a filterable,
paginated, exportable viewer over the immutable `audit_logs`. `Admin\SettingsController`
edits general/contact/business-identity settings (making the ABN/legal-name
fields editable), launch mode + maintenance, first-party analytics, demo-data
removal (via `DemoSeeder::remove`), and renders a production-readiness checklist
(app key, debug off, HTTPS, SMTP, demo data, super admin, backup run, indexing,
legal review). `Admin\FeatureFlagsController` toggles the DB `feature_flags`
table through a new cached `FeatureFlag` service (the master billing switch
stays in `.env`). `Admin\BackupsController` (super-admin only) lists, generates,
downloads and deletes dumps from `storage/backups`, building on the existing
`Backup` service, with strict filename validation. A privacy-friendly,
cookie-free, CSP-safe `Analytics` service records first-party page views from
the kernel (off unless `analytics_enabled`). Tables: `audit_logs`, `reports`,
`saved_exports`, `scheduled_tasks`, `system_health_logs`, `page_views`,
`feature_flags`, `site_settings` (migrations 010/011).

All ten build phases are now implemented.

---

## 6. Coding standards

Strict typing where practical, PSR-style naming, small methods, centralised
validation/permissions/status constants, no inline SQL in views, no business
logic in templates, no hardcoded credentials or production URLs, no silent
exception swallowing in critical paths.
