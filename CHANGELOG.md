# Changelog

All notable changes to VanAssist are documented here.

## [Unreleased] — TowSmart launch identity

### Changed

- Renamed the public TowWise brand to **TowSmart** while preserving its stable
  database ID and legacy internal storage namespace.
- Set `vanassist.com.au`, `towsmart.com.au`, and `trailerwise.com.au` as the
  canonical purchased production domains.
- Renamed TowSmart routes, code namespaces, assets, advertising placement,
  environment variables, tests, and operator documentation.
- Kept legacy `TOWWISE_*` environment variables as temporary deployment
  fallbacks so an existing installation does not fail during rollout.

### Added
- **Town coverage report** — `TownCoverageService` + Admin Maintenance table +
  `php scripts/coverage-report.php` (local 0 / 1–2 / 3+ and serving coverage by state;
  `--thin` exports a gap-fill queue).

### Changed
- **OSM import selectors widened** (`tools/osm-import.js` + `OsmRefreshService`) —
  more `service:vehicle:*` tags, welder/metal craft, and name matches for trailer,
  brakes/bearings, mobile diesel/tyre, aircon/fridge, roadside, LPG.

### Changed
- **Provider claim invite email** — personalised greeting, listing preview box
  (town, services, public profile link), plain-link fallback, dynamic expiry days,
  and launch-town **free ad graphic** offer line.

### Added
- **Founding free ad graphic** — launch-town providers who claim and verify can
  request one free 1200×400 local ad graphic; admin fulfils via provider profile;
  delivery features the listing and emails the provider.
- **`provider_promotions` table** (migration 026) and `FoundingGraphicService`.
- Provider **Promote** dashboard page (`/provider/promotion`) and admin delivery tools.
- **Admin → Ad graphics** queue (`/admin/promotions`) to filter, track and fulfil requests.
- **Desktop + mobile ad creatives** — separate uploads (1200×400 and 800×450); public pages use responsive `<picture>` to serve the right version.
- Email templates: `provider_founding_graphic_unlocked`, `provider_founding_graphic_delivered`.

## [Unreleased] — Distance filtering on search

### Added
- **Filter and sort providers by distance** on `/find` and service category pages —
  optional "Within distance" (25–500 km) when a town or GPS location is set.
- **`App\Helpers\Geo`** — shared haversine distance, annotation, filtering, and
  nearest-first sorting for provider result rows.
- **Shared provider result card** partial with distance labels and mobile-service badges.

### Changed
- Search and category results are sorted nearest-first when an origin point is known
  (GPS coordinates or selected town centre).

## [Unreleased] — Homepage local provider spotlight

### Added
- **"Providers near you"** on the homepage — shows up to 6 **claimed** providers
  serving the visitor's town (featured listings first, then other local matches).
  Unclaimed OSM/import listings are excluded from this module.
- **`GET /locations/nearby-providers`** — JSON API accepting `town_id` or `lat`/`lng`.
- **`Provider::forHomeNearTown()`** — coverage-aware selection via existing
  `ProviderCoverage` / `inTown()` rules.
- **GPS "Use my location"** on the homepage section; remembers last town in
  `sessionStorage` for return visits.
- Server-rendered fallback using the first **launch town** when JS is unavailable.

## [Unreleased] — Free national provider coverage (OpenStreetMap)

### Added
- **Full default website content (Pages & Blocks)** — About, Privacy, Terms,
  Provider terms, Disclaimer, Safety, Complaints, Accessibility and Contact pages
  now ship with complete, accurate copy (legal pages are practical defaults to be
  reviewed), along with enriched homepage blocks and an expanded FAQ set. A new
  **Admin → Maintenance → "Populate Pages & Blocks"** action fills these on an
  existing site, refreshing only unedited rows so admin edits are preserved.
- **Real businesses in every state, sourced free from OpenStreetMap** — the new
  `tools/osm-import.js` pulls vehicle/caravan/trade businesses (car repair, tyres,
  caravan/RV, trailer, plumbing, gas, roadworthy/inspection) from the Overpass API
  (one query per state, not per town), maps them to VanAssist trade buckets by tag
  + name heuristics, assigns each to its nearest known town/region, dedupes against
  the existing import, and writes `database/seeds/businesses_osm.json` (~4,100
  listings). No API key, no cost.
- **`NationalImportSeeder::seedOsm($offset, $limit)`** — imports that set as
  clearly-marked **unclaimed listings** through the same provider/services/area
  path as the researched import, in batches. Idempotent (keyed on the OSM id).
- **Admin → Maintenance → "Import OpenStreetMap businesses"** — time-boxed,
  resumable browser import (click *Continue import* until complete); no shell access
  needed. Shows current unclaimed-listing count and the bundled dataset size.

### Changed
- `NationalImportSeeder` refactored to share one `processBusiness()` path between
  the researched import and the OpenStreetMap import.
- **Service ("find a service") results now show an approximate distance** from the
  searched town to each provider's base, plus a clear **"Mobile service"** badge
  (van glyph) for mobile/both operators (and a "Workshop" tag otherwise).
- **Admin Providers list — richer filters:** search now also matches phone, plus
  new filters for **base town**, **service type**, **state**, **listing source
  (claimed/unclaimed)** and **verified/featured only**. Unclaimed listings are
  badged in the table.

### Fixed
- **"Register interest" (For providers) was a dead link to /contact** — it now
  opens a real provider-interest form (`/for-providers/register`) that captures
  business/contact/town/services, stores the lead as a warm prospect in the
  outreach CRM (Admin → Provider prospects, status "interested") and emails the
  site inbox. Includes validation, honeypot and consent note.
- **Demand map 500** — analytics queries that group by a joined town key no longer
  fail on hosts with `ONLY_FULL_GROUP_BY`; the connection drops just that sql_mode
  flag and the map queries group by the selected town columns.
- **Admin "locked out" after errors** — an already-signed-in staff member who hits
  `/login` is now sent to the admin dashboard instead of the customer account page.

## [Unreleased] — Complete national town coverage

### Added
- **Every Australian town/locality (all states & territories)** — a generated
  national town list (`database/seeds/towns_national.json`, ~17,400 localities)
  built from the open `australian_postcodes` dataset by the new
  `tools/build-national-towns.js`. Each locality is assigned to the nearest
  VanAssist region within its state (centroid-based) with postcode + coordinates.
- **`App\Services\NationalTownSeeder`** — creates the full town list idempotently
  (bulk `INSERT IGNORE` on `(state_id, slug)`; never overwrites existing towns).
  Wired into `Seeder::seedAll()` for fresh installs, and exposed as
  **Admin → Maintenance → "Import all Australian towns"** for existing databases.
- This makes **postcode / town search resolve anywhere in Australia**, and every
  town surfaces the relevant regional and statewide providers.

### Changed
- Region pages now show the most relevant towns (launch/featured first) with a
  "showing X of N" note, instead of rendering every locality, so pages stay fast.
- The sitemap only lists curated/indexable towns; bulk-imported localities are
  `noindex` by default and no longer flood it.

## [Unreleased] — Roadside assistance & roadworthy inspections

### Added
- **Two new service categories** — *Roadside assistance* and *Roadworthy
  inspection* — plus matching `roadside` / `roadworthy` trade buckets in
  `NationalImportSeeder`. The importer now **self-seeds** any referenced service
  category that is missing, so new trades work on an already-seeded database
  without a full reseed.
- **8 state/territory roadside clubs** as listings (RACQ, NRMA NSW + ACT, RACV,
  RAA, RAC, RACT, AANT), all on **13 11 11** for 24/7 roadside, with caravan/
  trailer towing notes. Roadside listings get a **state-wide service area**.
- **5 roadworthy / safety-certificate providers** (mobile + workshop): ASAP
  Mobile Roadworthy (Gladstone), SafeT Cert (Wide Bay–CQ), Murphys Mobile
  Mechanical (Rockhampton), Mobile Roadworthy 2U (QLD network); All 4 Mobile
  Mechanic also tagged roadworthy.
- **`tools/places-import.js`** extended with roadside/roadworthy search queries
  and classification, so the Places pipeline finds these trades nationally too.

### Changed
- **`Provider::inTown()` / `forCategory()`** now honour **region- and state-level
  service areas** (not just town areas), so statewide operators such as roadside
  assistance correctly surface for every town in their state.

## [Unreleased] — Deeper per-town directory coverage

Addresses thin town results (e.g. Gladstone had only 2 listings) by widening the
trades the importer understands and adding a way to research real businesses per
town at scale. All new entries remain clearly-marked **unclaimed** listings using
only contact details the business already publishes, opted out of auto-invites.

### Added
- **Plumber & gas-fitter trades** in `NationalImportSeeder` (`TRADE_PRIMARY` /
  `TRADE_RELATED`): plumbers and gas fitters now map to real direct services
  (*Plumbing and water leaks* / *Gas appliance servicing*) plus sensible
  possible-matches (hot water, toilets, refrigeration, etc.) instead of being
  forced into the caravan bucket. Benefits every town.
- **`tools/places-import.js`** — Google Places API (New) pipeline that finds
  caravan-relevant trades (caravan/RV repairers, mobile mechanics, auto
  electricians, mobile gas/plumbing, cooling) per town, dedupes them against the
  existing dataset (name+town / phone / website host), and appends them to
  `national_import.json` for the existing idempotent seeder. Supports
  `--launch`, `--state`, `--town`, `--region`, `--dry-run`/`--write`; auto-
  resolves each town's region (launch list → existing towns → nearest region
  centroid). Documented in `docs/national-provider-import.md` (incl. cost notes).
- **14 verified launch-region listings** added by hand to `national_import.json`:
  Gladstone 2→6 (M&M Diesel Repairs, Lister Motors, ACES, Betts Plumbing),
  Rockhampton 2→7 (All 4 Mobile Mechanic, Rockhampton Mobile Mechanic, Robust
  Industries, Capricorn Caravan Centre, Tropical 4x4), Bundaberg 5→7 (Mick's RV
  Repairs, ATB Mechanical), and Agnes Water 0→3 (Caravan And 4x4 Auto Electrics,
  SS Auto Electrics, Agnes All Sparks). Rod's Auto Electrics (Emerald) address
  sharpened.

## [Unreleased] — Demand-to-outcome analytics (Phase 11, stages 2–8)

Builds the full measurement layer on the stage-1 foundation, all behind the
`demand_analytics` feature flag (off by default; zero behaviour change until on).

### Added
- **Migration `019_reviews_saved_providers.sql`** (additive, reversible):
  `provider_reviews` (one moderated review per confirmed outcome) and
  `saved_providers`.
- **Funnel tracking (stage 2):** the `/find` search now records a
  `provider_searches` session + deduped `provider_search_results` impressions;
  provider profile views and `need_submitted` emit `analytics_events`.
- **Contact attribution (stage 3):** profile phone/email/website/directions
  buttons route through `GET /go/{action}/{slug}`, which records a
  `provider_contact_actions` row (deduped per session) then redirects to the
  real target. Tracking failure never blocks the redirect.
- **Outcome confirmation (stage 4):** customers confirm "Did you use a
  provider?" at `/account/requests/{ref}/outcome` (and login-free via
  `/followup/{token}`); providers set job status on each request. Both feed the
  `service_outcomes` record-of-truth with a confidence ladder
  (`inferred→contact_only→customer_reported→provider_reported→both_confirmed→admin_verified`),
  repeat-provider detection, reviews and demand-gap feedback.
- **Saved providers:** save/remove from a profile; listed at `/account/saved`.
- **Provider analytics (stage 5):** `/provider/analytics` — own data only,
  estimated (impressions/clicks) vs confirmed (jobs) clearly separated, with
  7/30/90-day, this/previous AU financial year and custom-range filters.
- **Admin analytics (stage 6):** `/admin/demand` overview, `/providers` usage
  table, `/funnel`, `/coverage` gaps, `/map` (server-rendered SVG density + table),
  and permission-gated CSV `/export`. Nav group "Analytics".
- **Follow-ups (stage 7):** `FollowupService` auto-enqueues and sends a single
  tokenised `outcome_followup` email per settled request (cron
  `customer_followups`), respecting consent and skipping already-confirmed
  outcomes. New settings `followup_delay_days`,
  `analytics_retention_event_days`, `analytics_retention_session_days`.
- **Tests (stage 8):** `ReportingServiceTest` (rate suppression, range + AU FY
  math) and `ActivityTrackerTest` (event vocabulary, disabled-by-default safety).

### Services
- `Demand\DemandRecorder`, `Demand\OutcomeService`, `Demand\ReportingService`,
  `Demand\FollowupService` — all no-ops unless the flag is on, all failure-safe.

### Notes
- Reuses existing `service_requests` / `service_request_matches` rather than
  duplicating provider-response/quote/usage tables.
- Privacy/consent wording for the policy page is documented (marked for legal
  review) in `docs/demand-analytics-implementation.md`.

## [Unreleased] — Town pages show all relevant businesses

### Changed
- **`Provider::inTown()`** now returns every business relevant to a town, ranked
  by how directly it serves it: (0) based in the town or service-area covers it,
  (1) a mobile/both operator elsewhere in the same region, (2) a workshop
  elsewhere in the region. Previously only businesses with that exact base town
  were shown (e.g. Gladstone showed 2 of the 6 Fitzroy-region businesses).
- **Town page** groups these into “Service businesses in {town}”, “Mobile
  operators serving the {region} area”, and “Workshops elsewhere in {region}”,
  with a “Based in {town}” note on out-of-town cards.
- **`Provider::forCategory()`** town filter now also includes providers operating
  anywhere in the town's region, so service-page and postcode searches surface
  the same wider, relevant pool.

## [Unreleased] — Mobile contact + maps navigation

### Added
- **Migration `018_provider_street_address.sql`** (additive): `providers` gains a
  `street_address` column holding each listing's source location string.
- **Tap-to-contact provider profiles**: phone numbers are now `tel:` links with a
  prominent **“Call now”** button, emails are `mailto:` links, and the address /
  service area is shown. Designed for phone/tablet use.
- **“Get directions”** on workshop/both listings with a fixed address: opens the
  visitor's native maps app and navigates from their current GPS location
  (`google.com/maps/dir` universal link, works on iOS & Android). Mobile-only
  businesses show a “they travel to you — give them a call” note instead.
- **Import now stores `street_address`** and **backfills** contact detail
  (`street_address`, public phone, email, website) and cleans the description on
  existing unclaimed listings, reported via a new `providers_enriched` count.

### Changed
- Unclaimed listings now display the business's published email (as a `mailto:`
  link) so travellers can contact them directly.

## [Unreleased] — Town postcode + coordinate enrichment

### Added
- **`database/seeds/town_details.json`**: a curated dataset of postcodes and
  approximate town-centre coordinates for all 163 towns referenced by the
  national import (93 interstate + 70 QLD), keyed by state abbreviation then
  town name.
- **`NationalImportSeeder` town enrichment**: when importing, each town is now
  created with its `primary_postcode`, `latitude` and `longitude`, and existing
  town rows are **backfilled** for any of those fields (plus `region_id`) that
  are still empty. Values already present are never overwritten (`COALESCE`).
  The import summary now reports a `towns_enriched` count, surfaced in the
  Admin → Maintenance success message.

### Fixed
- **Postcode search for interstate towns**: every interstate town now has a
  `primary_postcode`, so the homepage “Find a service” postcode lookup resolves
  locations outside Queensland.

## [Unreleased] — Admin maintenance tools (no-CLI updates)

### Added
- **Admin → System → Maintenance** (`/admin/maintenance`, super-administrators
  only): apply pending database migrations and re-run the national provider
  import from the browser — no shell/CLI access required. Shows the list of
  pending migrations, is idempotent, and writes audit-log entries
  (`system.migrate`, `system.reimport`). New `Admin\MaintenanceController`.

## [Unreleased] — Area/service matching (possible matches)

### Added
- **Migration `017_service_inferred.sql`** (additive): `provider_services` gains
  `is_inferred` to mark a service link as a **possible match** (inferred from the
  business's trade) vs an explicit direct match. Existing links default to direct.
- **Trade → service expansion** in `NationalImportSeeder`: each imported business
  is now linked to its trade's headline service (direct match) plus every service
  that trade plausibly covers (possible match), so the ~27 service pages and the
  town/region pages are populated instead of empty.
- **Provider listings on public pages**: service pages now list providers split
  into “Providers offering this service” and “Businesses that may offer this
  service”; town and region pages list local businesses. New
  `Provider::forCategory()`, `inTown()`, `inRegion()`.

### Notes
- Possible matches are clearly labelled (“May offer this service”) with a confirm-
  before-booking note. Backfill on an existing DB: migrate then
  `php scripts/seed.php --national`.

### Changed (follow-up)
- **Widened the trade→service map**: caravan now also covers DC-DC charging,
  inverters and Starlink/comms; auto-electrical adds pre-trip inspection;
  mechanical adds air conditioning; trailer adds general servicing.
- **Town/area filter on service pages**: `/services/{slug}?town=<id>` (e.g.
  “Brakes & bearings in Gympie”), matching providers based in *or* serving the
  town. `Provider::forCategory()` now widens the town filter to service areas.
- **Auto-matcher now considers possible matches**: `MatchingService` scores a
  direct (explicit) category match at +50 and a possible (inferred) match at +25
  (related categories +10/+5), so the matcher widens its net when no exact
  provider exists. Auto-invite emails still respect `auto_invite_opt_out`, so
  imported unclaimed businesses surface as suggestions but are not auto-emailed.

## [Unreleased] — National provider import (unclaimed listings)

### Added
- **`database/seeds/national_import.json`** + **`tools/extract-canvas.js`**: the
  researched, public-source business list (all states/territories) extracted from
  the planning canvas into a committed dataset (8 states, 51 regions, 333
  businesses).
- **Migration `016_unclaimed_listings.sql`** (additive): `providers` gains
  `is_unclaimed`, `claimed_at`, `claim_token`, `source_note`, `source_url`.
- **`App\Services\NationalImportSeeder`** — imports the dataset as clearly-marked
  **unclaimed** directory listings (`status='active'`, `is_verified=0`,
  `is_unclaimed=1`), creating any missing states/regions/towns. Idempotent; runs
  in `Seeder::seedAll()` and standalone via `php scripts/seed.php --national`.
- **Service categories** “Mechanical repairs” and “Trailer and engineering” added
  to map the canvas category buckets cleanly.
- **Public UI**: “Unclaimed listing” badge + provenance notice and a “Claim this
  listing” call-to-action on provider profiles and the directory.

### Decisions
- Imported businesses are **opted out of automated invite emails**
  (`auto_invite_opt_out=1`) so the auto-matcher never emails a business that has
  not opted in; they stay manually invitable. Only publicly-advertised contact
  details (phone/website) are shown; scraped emails are kept private for outreach.
- See `docs/national-provider-import.md`, including how to install/seed the live
  database (the previously empty site was simply un-seeded).

## [Unreleased] — Auto-matching & dispatch

### Added
- **Migration `015_auto_matching.sql`** (additive/reversible): automation
  metadata on top of the existing matching model — `service_request_matches`
  gains `auto_invited`, `invited_at`, `match_reasons`, `released_at`,
  `release_reason`; `service_requests` gains `auto_match_state`
  (`pending`/`done`/`fallback_admin`/`locked`/`off`), `auto_matched_at`,
  `interested_count`; `providers` gains `auto_invite_opt_out`, `notify_channel`;
  plus an append-only `auto_match_log`. See `docs/auto-matching-implementation.md`.
- **`App\Services\AutoMatchService`** — when the `auto_matching` flag is on:
  approved requests are scored and the strongest providers are auto-invited
  (moving the request to `matching` with no admin action); requests with no
  suitable provider are flagged `fallback_admin` and emailed to the admin rather
  than stalling; an invited provider expressing interest auto-releases the
  customer's contact (subject to consent + a cap); silent invites escalate to the
  next batch after an urgency-based delay.
- **Smarter scoring** (`MatchingService`): great-circle distance bonuses, stated
  travel-range, provider availability windows, and persisted human-readable match
  reasons; auto-invite respects provider opt-out and a per-provider daily cap.
- **`config/matching.php`** tunables (env- and `site_settings`-overridable):
  invite caps, minimum score, contact-release cap, consent requirement,
  escalation windows by urgency.
- **Feature flag** `auto_matching` (seeded **off**) — master switch; flip off to
  revert to the manual matching console instantly.
- **Cron**: `update_match_suggestions` now runs the auto-match backlog (was a
  no-op); `provider_followups` now escalates silent invites. Both no-op while the
  flag is off.
- **Admin visibility**: matching console shows `Auto` / `Needs you` / `Locked`
  badges and per-match invite reasons.

### Decisions
- Full auto-invite with caps (top N, per-provider daily cap) so providers aren't
  flooded; only post-moderation `open` requests are ever auto-matched.
- Customer contact auto-releases on provider interest **only** with the customer's
  share consent (configurable) and up to `contact_release_max_providers` (default
  2), after which the request locks to further releases.

## [Unreleased] — Demand Analytics: funnel foundation (Stage 1)

### Added
- **Migration `014_demand_analytics.sql`** (additive/reversible): the
  demand-to-outcome measurement layer built **on top of** the existing
  `service_requests` / `service_request_matches` model — `tracking_sessions`,
  `analytics_events`, `provider_searches`, `provider_search_results`,
  `provider_contact_actions`, `service_outcomes` (status + confidence),
  `outcome_confirmations`, `demand_gap_feedback`, `customer_followups`,
  `provider_daily_metrics`, `demand_daily_metrics`, `admin_reporting_snapshots`.
  High-volume tables intentionally carry no FKs (like `page_views`) for insert
  speed; relational tables use FKs. See `docs/demand-analytics-implementation.md`.
- **Services** (`App\Services\Demand\`): `TrackingSession` (first-party,
  cookieless-of-third-parties anonymous identity with sign-in linking; no IP
  stored) and `ActivityTracker` (central, validated funnel-event recorder that
  no-ops unless the `demand_analytics` flag is on and never breaks the page).
- **Feature flag** `demand_analytics` (seeded **off**) gates all tracking and
  the forthcoming dashboards — zero behaviour change until enabled.
- **Permissions** `demand.view`, `demand.export` (administrator +
  super-administrator).
- **Scheduled tasks** `aggregate_daily_metrics` (daily rollups into the metric
  tables), `customer_followups`, `analytics_retention` (purge raw analytics past
  a configurable retention window) — registered in `CronRunner`, safe no-ops
  while the flag is off.

### Decisions
- Confirmed provider **use** is evidenced (`service_outcomes.confidence`), never
  inferred from raw clicks; admin/staff and bot traffic are excluded from
  customer-facing metrics.

## [Unreleased] — Owner Finance: general-ledger foundation

### Added
- **Finance module foundation** (`/admin/finance`) — the VanAssist platform-owner
  double-entry general ledger, layered **on top of** the existing 012 billing
  tables (which remain the sales/AR + marketplace subledger) rather than
  duplicating them. See `docs/owner-finance-implementation.md`.
- **Migration `013_owner_finance.sql`** (additive/reversible): `owner_finance_`
  `accounts`, `tax_codes`, `financial_periods`, `journal_entries`,
  `journal_lines` (with DB CHECK: debit XOR credit, non-negative),
  `source_events` (idempotent posting), `audit_events`. Ledger money uses
  `DECIMAL(19,4)`.
- **Seeded chart of accounts** (~85 accounts, incl. agent-model control accounts
  Provider Funds Held / Settlements Payable / Payment Gateway Clearing / GST
  Control) and seven tax codes, via `database/seeds/owner_finance.php`
  (`Seeder::seedOwnerFinance`). Current monthly financial period seeded.
- **Permissions** `owner_finance.*` (view, manage_accounts, manage_journals,
  view_reports, export, manage_settings, view_audit, close_period, reopen_period)
  granted to administrator and super-administrator.
- **Domain services** (`App\Services\Finance\`): `JournalPostingService`
  (balanced, idempotent, period-gated, immutable posts + reversal),
  `ChartOfAccounts`, `FinancialPeriodService`, `FinanceReport` (trial balance +
  dashboard aggregates from posted lines), `FinanceAudit` (append-only log).
- **Admin screens**: finance dashboard (metrics + live trial balance + recent
  journals), chart-of-accounts management, and journals (list/detail, manual
  balanced entry, audited reversal). Added a **Finance** nav group.

### Decisions
- Marketplace funds use **agent treatment** (provider gross is a liability until
  settled); **GST not registered** by default (invoices labelled "Invoice").

## [Unreleased] — Phase 10: Reports, audit & launch tools

### Added
- **Reports** (`/admin/reports`, `Admin\ReportsController`): request conversion
  funnel, demand by town and by category, provider/run/park summaries, email
  queue health, and a 30-day top-pages traffic table. Each table exports to CSV
  (plus raw provider/request/run exports) via the new `CsvExport` helper.
- **Audit log viewer** (`/admin/audit`, `Admin\AuditController`): filter the
  immutable `audit_logs` by action and free-text, paginated, with CSV export.
- **Settings & launch tools** (`/admin/settings`, `Admin\SettingsController`):
  edit general, contact and **business-identity** settings (legal name, ABN,
  address — now editable), launch mode, maintenance mode/message and first-party
  analytics; remove demonstration data (`DemoSeeder::remove`); and a
  **production-readiness checklist** (app key, debug off, HTTPS URL, SMTP,
  demo data removed, super admin present, backup has run, indexing decision,
  legal review).
- **Feature flags** (`/admin/feature-flags`, `Admin\FeatureFlagsController` +
  new cached `FeatureFlag` service): toggle the database `feature_flags` table.
  The master billing switch remains in `.env` (`ENABLE_BILLING`).
- **Backups UI** (`/admin/backups`, `Admin\BackupsController`) — super
  administrators only: list, generate, download and delete database dumps from
  `storage/backups` (built on the existing `Backup` service), with strict
  filename validation to prevent path traversal.
- **First-party analytics** (`Analytics` service): privacy-friendly, cookie-free
  and CSP-safe page-view recording invoked from the kernel. Records only the
  route and a coarse referrer source, and is off unless `analytics_enabled` is
  turned on in Settings (zero overhead by default).

### Changed
- Removed the `/admin/reports`, `/admin/audit`, `/admin/settings`,
  `/admin/feature-flags` and `/admin/backups` placeholders now that all are
  implemented. (`/admin/users` and `/admin/customers` remain placeholders.)

## [Unreleased] — Phase 9: Notifications & cron

### Added
- **Email template editor** (`/admin/email-templates`, `Admin\EmailTemplatesController`):
  edit the seeded transactional templates (name, subject, HTML/text body,
  enabled), see a live preview rendered with sample placeholder data, view the
  available `{{placeholders}}`, and queue a **test email** to any address.
- **Targeted broadcasts** (`/admin/notifications`, `Admin\NotificationsController`):
  compose an email broadcast to a chosen audience, preview the recipient count,
  then save as a draft, schedule for later, or send now. A queue summary
  (pending/sent/failed) is shown on the index.
- **`BroadcastAudience` service**: resolves recipients for an audience —
  everyone opted in, all active providers, customers with open requests, or by
  town/region/category (combining opted-in customers with relevant providers) —
  de-duplicated by email, with customer audiences gated on `marketing_opt_in`.
- **`NotificationService` service**: dispatches a notification by resolving its
  audience at send time, recording `notification_recipients`, wrapping the body
  in the standard email shell and queueing each message for the `Mailer` cron.
- **Implemented cron tasks** (previously stubbed): `process_notifications`
  (dispatch scheduled broadcasts that are due), `send_run_reminders` (remind
  booked customers a configurable number of days before a run), `document_expiry`
  (remind providers about verified licences expiring in 30/14/7 days), and
  `provider_followups` (record expiring unaccepted invitations and stale pending
  applications to `system_health_logs` for team follow-up).

### Changed
- Removed the `/admin/email-templates` and `/admin/notifications` placeholders
  now that both are implemented.

## [Unreleased] — Phase 8: CMS & SEO

### Added
- **Shared SEO meta partial** (`partials/seo-meta`): centralises the document
  title (single site-name suffix, no double-suffixing), meta description,
  canonical, robots, Open Graph + Twitter cards and JSON-LD blocks. Wired into
  the public layout `<head>`.
- **Master indexing switch**: `seo_allow_indexing` site setting (defaults off
  until launch mode is `live`). While off, every public page emits
  `noindex, nofollow` and `robots.txt` disallows all crawling.
- **Dynamic SEO endpoints** (`Site\SitemapController`): `/sitemap.xml` built from
  published pages, active categories, regions, towns, active providers, public
  service runs and public caravan parks; `/robots.txt` that blocks private areas
  (admin/account/provider/park/install/billing) and references the sitemap.
- **Public FAQ page** (`/faqs`, `Site\FaqController`): active FAQs grouped by
  category with `FAQPage` structured data for rich results.
- **Structured data**: `Organization` + `WebSite` JSON-LD on the homepage and
  `LocalBusiness` JSON-LD (opted-in contact details only) on provider profiles.
- **Admin content** (`/admin/content`, `Admin\ContentController`): editable
  **pages** with per-page SEO (title/description/canonical/noindex), Open Graph
  fields and custom JSON-LD, plus publish control and create/delete for
  non-system pages; **homepage blocks** CRUD (ordering, active toggle); and
  **FAQs** CRUD. Tabbed sub-navigation across the three.
- **Admin SEO settings** (`/admin/seo`, `Admin\SeoController`): site name,
  default meta description, default social-share image and the indexing switch,
  with quick links to the generated sitemap and robots files.

### Changed
- Public layout `<head>` now renders via the SEO meta partial and advertises the
  sitemap with a `<link rel="alternate" type="application/xml">`.
- `/faqs` is now a dedicated controller route (removed from the generic CMS slug
  fallback list).
- Removed the `/admin/content` and `/admin/seo` placeholders now that both are
  implemented.

## [Unreleased] — Phase 7: Caravan park partners

### Added
- **`CaravanPark` model + `QrCode` service**: park listings/lookups, nearby-run
  discovery, and a self-contained QR Code generator (byte mode, EC level M, no
  external libraries) that outputs an SVG / `data:` URI.
- **Public park application** (`/caravan-parks/apply`): a park manager applies,
  which creates their login (`caravan-park-partner` role) and a `pending` park
  record, links them as owner and signs them in. Honeypot + validation +
  confirmation email.
- **Public park page** (`/caravan-parks/{slug}`): shown once a park is active and
  has enabled its public page — logo, details, a prefilled "request assistance"
  call-to-action (attributed to the park) and service runs forming nearby.
- **Park portal** (`/park`): dashboard with profile checklist and recent guest
  requests; editable public **profile** (logo upload, SEO, public-page toggle);
  **documents** (secure upload/download/delete via `FileStorage`); **register a
  guest request** on a visitor's behalf (`park` source, straight to moderation);
  **nearby runs**; **request a service day**; and **QR code & printable
  materials** (park-specific QR embedded as a `data:` URI, print stylesheet).
- **Admin caravan parks** (`/admin/parks`): list with filters, detail view,
  edit, approve/reject/suspend (emails the park), document review and
  service-day-request triage.
- Guest requests now carry a `park_id` and `park`/`park_qr` source when a guest
  arrives via a park QR link or is registered by park staff. The public request
  form shows a "referred by" banner and carries the park through submission.

### Changed
- `/admin/parks` and the caravan-park sections are now functional (removed from
  the placeholder lists). "For caravan parks" now links to the live application.
- New upload paths: `park_documents` (private) and `park_logos` (public).

## [Unreleased] — Phase 6: Service runs

### Added
- **`ServiceRun` model + `RunWorkflow` service**: admin/provider/public listings,
  per-run lookups (stops, services, registrations, linked requests), unique slug
  generation, status transitions recorded to `service_run_status_history`, and
  automatic capacity bookkeeping (`recalcCapacity` recounts active registrations
  and auto-advances to/from `fully_booked`).
- **Public service runs** (`/service-runs`): filterable listing of runs that are
  forming/confirmed/limited, plus a per-run page (`/service-runs/{slug}`) with a
  capacity progress bar, stops, services and a **join-run flow**. Registering
  interest requires a free account (guests are routed to sign in and returned to
  the run), de-duplicates per customer, optionally links an existing request and
  preferred stop, and emails a confirmation.
- **Admin runs console** (`/admin/runs`): list with status/search filters,
  create/edit form (provider, dates, region, capacity, minimum-to-go-ahead,
  travel note, public/featured), status control with notes, add/remove stops and
  services, link/unlink matched requests (also sets `service_request_matches.run_id`),
  and manage individual registrations (with capacity recalculation).
- **Provider runs self-service** (`/provider/runs`): providers create and edit
  their own runs, manage stops and services, set status (excluding the automatic
  `fully_booked`) and review registrations. New "Service runs" tab in the
  provider sub-nav.

### Changed
- `/admin/runs` and `/service-runs` are now functional (removed from the
  placeholder lists). The public header "Service runs" link now resolves to the
  live listing.

## [Unreleased] — Phase 5: Matching

### Added
- **`MatchingService`**: transparent scoring of active providers against a
  request — service-category match (primary + related), location (same town /
  service-area town/region/state), service-model compatibility and trust
  signals (verified/insured/featured). Each suggestion carries a score and
  human-readable reasons.
- **Admin matching console** (`/admin/matching`): urgency-ordered queue of
  requests in the pipeline; per-request page (`/admin/matching/request`) showing
  current matches and scored suggestions. Admins can add a provider as a
  suggestion or **add & invite** (emails `provider_match_invitation`), change a
  match's status, **release customer contact** to a provider (emailed), and the
  request status auto-syncs (matching / provider_interested /
  information_requested / offered_appointment / accepted) with history + emails
  to the customer.
- **Provider incoming requests** (`/provider/requests`): list of matched
  requests, detail view with job info and photos (limited until contact is
  released), and one-click **I'm interested / ask for more info / decline** with
  an optional note. Interest/more-info notify the customer and advance the
  request. Provider can view request photos for their own matches only.
- "Match providers" shortcut on the admin request detail page; "Incoming
  requests" tab added to the provider sub-nav.

### Changed
- `/admin/matching` is now functional (removed from the placeholder list).

## [Unreleased] — Phase 4: Customer requests

### Added
- **Public request flow** (`/request-assistance`): a single sectioned form
  (location → vehicle → service → fault → photos → contact/consent) with
  honeypot, full server-side validation and category multi-select. Guests get a
  double opt-in email verification step (`/request/verify`); signed-in customers
  skip straight to review. Confirmation page shows the `VA-XXXXXX` reference.
- **Secure image processing** (`ImageProcessor`): real MIME validation via
  `finfo`, GD re-encode to strip EXIF/metadata, downscale to a max width,
  thumbnail generation, random opaque filenames, stored outside the web root;
  oversized/invalid files are skipped without failing the request.
- **Customer dashboard**: requests list (`/account/requests`), detail with
  status timeline and photos (`/account/requests/{reference}`), and
  authenticated image serving with an ownership check. Account home now lists
  recent requests.
- **Admin moderation** (`/admin/requests`): filter/search list, full detail,
  approve (→ open, emails the customer), reject, mark/clear spam, arbitrary
  status changes with notes, internal notes, and authenticated image viewing.
- `ServiceRequest` model and `RequestWorkflow` service (status labels +
  immutable status-history recording). Request images reuse the existing
  `uploads.*` image settings (max size, max width, thumbnail width, allowed
  MIME types).

### Changed
- `/request-assistance` and `/admin/requests` are now functional (removed from
  the coming-soon / placeholder lists).

## [Unreleased] — Phase 3: Provider management (part 2)

### Added
- **Provider self-service dashboard** (`/provider`): real status, verification
  badges and a profile-completeness checklist, with a shared provider sub-nav.
- **Business profile editor** (`/provider/profile`): edit details, public-contact
  opt-ins, service model, base town/region, travel radius and description
  (status/verification stay admin-controlled).
- **Services** (`/provider/services`) and **service areas** (`/provider/areas`)
  self-management (add/remove town/region/radius).
- **Verification documents** (`/provider/documents`): secure upload (server-side
  finfo MIME validation, size cap, random opaque filenames, stored outside the
  web root), authenticated download, and removal (blocked once verified).
- **Licences** (`/provider/licences`) and **availability** (`/provider/availability`)
  management.
- New `FileStorage` service for validated private uploads + authenticated
  serving; admins can now download documents from the provider screen.
- Config: `uploads.max_document_mb` + `uploads.allowed_document_mimes`
  (PDF/JPG/PNG/WEBP); `MAX_DOCUMENT_UPLOAD_MB` env var.

## [Unreleased] — Phase 3: Provider management (part 1)

### Added
- Model `Provider` with admin-listing, public-directory and profile lookups,
  plus service and service-area helpers.
- **Admin providers** (`/admin/providers`): filtered + paginated list; create/
  edit details; approval workflow (approve / reject / suspend / reactivate)
  with provider notification emails; verified / insured / featured flag toggles;
  add/remove services and service areas; verify or reject uploaded documents and
  licences; internal notes. New providers are provisioned with the dormant
  billing records via `SubscriptionService`. `providers.manage` /
  `providers.approve` / `documents.verify`.
- **Provider prospect CRM** (`/admin/prospects`): outreach pipeline with status
  filter + search, create/edit, contact-log notes, CSV export and CSV import
  (de-duplicated on email), and tokenised registration invitations.
  `prospects.manage`.
- **Provider invitations**: admins generate a 14-day signed invitation link
  (emailed via the new `provider_invitation` template). Public acceptance flow
  (`/provider/join/{token}`) creates the user + provider profile, provisions
  billing as a founding provider, marks the prospect `registered`, and signs the
  new provider in.
- **Public provider directory** (`/providers`) with town/service/search filters
  and pagination, and **public profiles** (`/providers/{slug}`) showing services,
  service areas, verified credentials and upcoming service runs. Contact details
  are shown only where the provider opted in (`show_public_phone/email`).

### Changed
- `/admin/providers` and `/admin/prospects` are now functional (removed from the
  placeholder module list); `/providers` now serves the live directory instead
  of the coming-soon placeholder.

## [Unreleased] — Phase 2: Locations & service categories

### Added
- Models `State`, `Region`, `Town`, `ServiceCategory` with admin-listing and
  public-lookup helpers.
- **Admin locations** (`/admin/locations`): manage states, regions and towns —
  create/edit, active/featured/launch flags, town geo + primary postcode, SEO
  title/description, public content; filtered + paginated town list; unique
  slug generation scoped per state; audit-logged. `locations.manage`.
- **Admin service categories** (`/admin/categories`): nestable categories with
  parent selection, icon, sort order, descriptions, customer guidance, typical
  issues, SEO fields, show/hide toggle; audit-logged. `categories.manage`.
- **Public pages generated from the database**: service-category index
  (`/services`) + detail (`/services/{slug}`), region index (`/regions`) +
  detail (`/regions/{slug}`), and town detail (`/towns/{slug}`) with
  breadcrumbs, nearby-town links and request CTAs.
- SEO: public layout now emits `<link rel="canonical">` and a `robots` meta
  tag; town pages honour their `noindex` flag.
- Public header/footer link to the new Services and Regions sections.

### Changed
- `/admin/locations` and `/admin/categories` are now functional (removed from
  the placeholder module list).

## [Unreleased] — Monetisation architecture (dormant)

Implements the subscription-capable commercial architecture described in the
free-launch / future-monetisation spec. **Nothing is charged or shown while
`ENABLE_BILLING=false`** — this is foundation only.

### Added
- Migration `012_billing.sql`: 23 billing tables (`billing_plans`,
  `billing_plan_prices/features/limits`, `provider_subscriptions` +
  `_history`, `provider_plan_overrides`, `provider_entitlements`,
  `provider_usage_counters`, `billing_customers`, `payment_methods`,
  `invoices`, `invoice_items`, `payments`, `refunds`, `discount_codes` +
  `_redemptions`, `billing_events`, `billing_webhook_events`,
  `commission_rules`, `commission_transactions`, `booking_fees`,
  `tax_settings`), all gateway-neutral with external-ref columns. Amounts in
  integer cents.
- `providers` extended with `plan_id`, `subscription_state`,
  `billing_required`, `booking_fee_cents` and the founding-provider snapshot
  fields (`is_founding_provider`, `founding_*`).
- Billing feature flags (`ENABLE_PROVIDER_SUBSCRIPTIONS`, `…_TRIALS`,
  `…_FEATURED_LISTINGS/RUNS`, `…_BOOKING_FEES`, `…_COMMISSIONS`,
  `…_CUSTOMER_PAYMENTS`, `…_PROVIDER_PAYOUTS`, `…_DISCOUNT_CODES`,
  `…_ANNUAL_BILLING`), `BILLING_GATEWAY`, Stripe + GST/ABN env settings;
  `config/billing.php`.
- Gateway abstraction: `BillingGatewayInterface`, `NullGateway`,
  `StripeGateway` (dependency-free webhook signature verification),
  `BillingManager`.
- Central services: `PlanEntitlementService` (single source of truth, fully
  permissive while billing is off), `UsageMeteringService` (server-side
  recalculation), `SubscriptionService` (provisioning, plan assignment, bulk
  migration, grandfathering, complimentary access, overrides, founding
  snapshot, state history), `InvoiceService` (AUD/GST tax invoices),
  `PaymentEventService` (verified, idempotent webhook ingestion).
- Default editable plans seeded (Founding / Free / Standard / Professional /
  Enterprise) with limits and entitlements; GST `tax_settings` seeded and
  marked for accountant review.
- Admin billing management (`/admin/billing` — list + edit plans, view flags;
  `billing.manage` permission) and provider billing portal
  (`/provider/billing`, 404 while disabled). Stripe webhook endpoint
  (`/billing/webhook/stripe`, no CSRF, 404 while disabled).
- Demo provider is now fully provisioned (plan, complimentary subscription,
  entitlement snapshot, usage counters, founding status, billing-customer
  placeholder).
- Unit tests for the disabled-billing contract and Stripe signature checks.

### Notes
- Generated invoices and GST handling are **marked for accountant review** and
  make no compliance claims.
- Subscriptions are only ever updated from verified server-side webhook events,
  never from return-page redirects.

## [0.1.0] — Phase 1: Foundation

### Added
- Lightweight MVC framework: front controller, router (groups, params,
  middleware pipeline), Request/Response, PDO database layer, template engine
  with layout inheritance, config + `.env` loader, file logger, error handler.
- Authentication: registration, login, logout, email verification, password
  reset; secure sessions, CSRF, login rate limiting, honeypot.
- Role-based access control with 7 roles and fine-grained permissions.
- Security middleware: headers + CSP, CSRF verification, auth, role and
  permission gating; admin idle-session timeout.
- Full normalised database schema (all domains) as ordered migrations plus a
  consolidated `database/schema.sql`.
- Idempotent seeders: roles/permissions, Australia → Queensland → 9 regions →
  21 towns, 24 service categories, site settings, feature flags, scheduled
  tasks, email templates, CMS content; separate removable demo data.
- Installation wizard: requirements check, DB connection test, `.env` writing,
  migrate, seed, super-admin creation, install lock.
- Admin shell: dashboard with live stats, navigation, module placeholders.
- Public site: layout, homepage, informational pages, CMS-managed static/legal
  pages, friendly 404/403/500/maintenance pages.
- Customer / provider / caravan-park area shells (role-gated).
- Queued email system (`email_queue`) + PHPMailer-based processor.
- Cron framework with per-task locking, status tracking, and working tasks
  (email queue, session expiry, run capacity, request expiry, cleanup, backup).
- Database backup service (mysqldump with PHP fallback) + retention.
- Audit logging service.
- Documentation: README, architecture + phased plan, cPanel install guide,
  security doc, testing guide; PHPUnit config + unit tests.

### Notes
- Billing is intentionally inactive (`ENABLE_BILLING=false`); provider billing
  fields exist in the schema for future use.
- Location logic is data-driven; no state is hardcoded.
