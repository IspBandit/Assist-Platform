# Assist Platform Enterprise release notes

This is the chronological release index. Detailed historical deployment records
may remain as dated files and are linked here rather than copied.

## Unreleased

### RIC everyday management — overview Admin API

- Added `GET /api/v1/admin/overview` and `GET /api/v1/admin/website-insights`
  for Assist RIC operational dashboards (analytics:read).
- Overview reuses `WebsiteInsightsService` so genuine visitors exclude bot/unknown
  page views; queue and AI sections are scope-gated.
- Expanded Assist RIC service-account scopes with `corrections:read`,
  `duplicates:read` and `ai:read` (still no production-danger scopes).
- OpenAPI, LIVE_API and RIC contract tests updated. No production flags changed.

### RIC Directory Management Admin API contract

- Clarified Assist RIC Directory Management uses existing Admin API directory
  and review-queue reads (`/providers`, `/stays`, `/facilities`, `/claims`,
  `/corrections`) with cursor pagination. No new endpoints. Category/location
  taxonomy remains website admin only. Claim/correction mutations stay human-
  session scoped.

### RIC Data Review Admin API contract

- Documented Assist RIC Data Review against existing `/drafts`, `/duplicates`
  and `/recycle-bin` Admin API reads (cursor pagination; OpenAPI list filters
  clarified). No new endpoints for import-candidate or stale/missing queues.
- Added `recycle_bin:restore` to default Assist RIC service scopes so recycle
  bin listing works; purge remains human-only and never in `RIC_SERVICE`.

### RIC Ask Insights Admin API contract

- Documented Assist RIC Ask Insights against `GET /ai/usage/requests`, usage
  summary/costs/cache and dual-source `GET /search-gaps`.
- Knowledge-gap SearchGap `meta` now includes click/contact/`ai_used` counts and
  taxonomy keys (additive). OpenAPI documents the AI usage request feed.

### RIC Operations Admin API contract

- Documented Assist RIC Operations as read-only Admin API visibility:
  `/health`, `/version`, `/capabilities`, dataset `/sync-history`,
  `/sync-conflicts`, `/imports/{id}`, AI usage rollups and `/audit`.
- Explicitly deferred feature-flag reads, import list index, failed-job queues
  and AI budget caps (remain website admin). No production toggles from RIC.

### RIC Operations Admin API gaps (Increment G)

- Added `GET /api/v1/admin/imports` import-job index (`imports:read`, cursor +
  optional status; sparse when jobs table missing).
- Nested read-only `budget` snapshot on `GET /ai/usage/summary` (`ai:read`).
- Added `GET /api/v1/admin/feature-flags` (`flags:read`) catalogue; no Admin API
  write/toggle paths. Scope included in `RIC_SERVICE`.
- OpenAPI, LIVE_API, contract and unit coverage updated. No production flags
  changed.

### Facility import-candidate review (Increment H.1)

- Added human-only Admin API mutations for facility import candidates:
  `POST /facility-import-candidates/{id}/approve` and
  `POST /facility-import-candidates/{id}/reject`
  (`import_candidates:review` in `ALL` and `NEVER_SERVICE`; not in
  `RIC_SERVICE`; requires `admin_api_human`).
- Optional JSON `{ "reason": "..." }` maps to
  `GovernmentDatasetService::reviewCandidate` notes.
- RIC Ops/Data Review service accounts remain read-only on import candidates;
  website admin review remains available.

### Provider import-candidate review (Increment H.2)

- Added human-only Admin API mutations for provider import candidates:
  `POST /provider-import-candidates/{id}/approve` and
  `POST /provider-import-candidates/{id}/reject`
  (same `import_candidates:review` + `admin_api_human`).
- Approve requires `retention_confirmed` and independent `evidence_url`
  (optional `category_id` / notes); delegates to `DataSourceService::review`
  and confirms evidence first when needed. Creates an unclaimed provider on
  success. Reject accepts pending or held candidates.
- Merge/hold/confirm workflow aids remain website admin only.
- Capabilities mark `provider_import_candidates` as `read_write`. Detail
  payloads may include `evidence_url` and `review_notes`.

### Import-candidate review queues (Increment H)

- Added read-only Admin API queues for facility and provider import candidates:
  `GET /facility-import-candidates`, `GET /facility-import-candidates/{id}`,
  `GET /provider-import-candidates`, `GET /provider-import-candidates/{id}`
  (`import_candidates:read` in `ALL` and `RIC_SERVICE`).
- These are separate from `GET /imports` (RIC package jobs / `api_import_jobs`).
- Cursor pagination; default `status=pending`; provider supports `q`/`state`.
- List payloads omit `raw_json`; detail may include a sanitised `raw` summary.
- No approve/reject endpoints this increment (PHP admin retains review writes).

- Ask VanAssist recognises plain-language accommodation requests such as
  “somewhere to stay free near Emerald” and searches both free-camp records
  and stays explicitly marked free.
- Added a regression matrix of realistic traveller questions and expanded
  everyday synonyms across provider faults, stay types and traveller facilities.

### Numbered maps across search results

- Added the existing accessible map/list experience to Ask VanAssist, filtered
  directories, stays, service categories, towns and regions.
- Providers, stays and traveller facilities with reliable coordinates receive
  matching numbered pins and list references. Unmappable records remain usable
  in the list and are never assigned invented coordinates.

### Cleaner, more visual VanAssist journeys

- Made the four homepage capability items direct links and removed the duplicate
  shortcut and statistics sections beneath the hero.
- Added distinct, relevant photo treatments to the main services, search, Ask,
  stays, provider-directory, provider-profile and how-it-works page headings.
- Put the Ask VanAssist plain-language field directly on the homepage when Ask
  is enabled, while retaining the structured category and location search.
- Filtered obvious automated traffic from website insights and rejected common
  abusive scraping tools without blocking recognised search crawlers.
- Phone-save guidance now distinguishes iPhone/iPad, Android and desktop and
  shows only the relevant brief instructions.

### Traveller-facility staging reliability

- Moved immutable acceptance fixtures outside runtime-mounted storage so
  staging releases retain their toilet, dump-point and water test data.
- Acceptance evidence now falls back to writable storage on read-only releases,
  and Linux no longer receives the Windows `NUL` redirect warning.
- Ask VanAssist traveller facilities now use compact mobile-friendly rows.

### Cleaner public provider pages

- Removed decorative business-name initials and the repeated provider name from
  provider-profile breadcrumbs.
- Corrected workspace-help routing so public `/providers/...` pages no longer
  show the private account/provider “Open page guide” strip.

### Provider map-reference icons

- Replaced `Map pin N` text in provider rows with a compact coloured numbered
  pin matching the corresponding marker on the results map.
- Removed the unexplained business-name letter tiles from provider rows.

### Compact provider lists

- Provider collections now use concise rows across public search, directory,
  town, region, service and saved-provider pages.
- Mobile rows prioritise the provider name and location, retain 44-pixel action
  targets, and move full descriptions and details to the provider profile.

### VanAssist map/list matching

- Located provider cards now show `Map pin N`, matching the numbered pin on the
  results map so travellers can identify the corresponding business quickly.
- Corrected the map-marker styling so those numbers are visible inside the pins.

### Isolated staging deployment path

- Added a manually approved GitHub workflow for immutable releases to the
  separate staging runtime and database on the existing server.
- Production deployment, data and feature flags are unchanged.

### POL-008 dealer enquiry handoff

- Model pages show linked published dealers with email/website CTAs via
  `/dealers/{id}/enquire` (tracks `dealer_enquiry_click`, then redirects).
  No platform-sent enquiry email. Demo contacts use `example.invalid` (`120`).

### POL-002 demo catalogue volume

- Migration `119` adds Demo Alpine Family plus six more demo models/variants
  (`is_demo` only). Not national production catalogue data.

### POL-007 manufacturer portal data quality

- Claimed manufacturers see a completeness checklist on
  `/portal/manufacturer/data-quality` (missing ATM, length, berths, price
  guidance, descriptions). Guidance only — not a Quality Gate pass.

### POL-007 manufacturer portal analytics

- Claimed manufacturers see 7/30/90-day detail views and saves for their models
  on `/portal/manufacturer/analytics` (first-party events only). Find impressions
  and dealer enquiry clicks remain planned.

### POL-003 Find preference hydration

- Signed-in users opening Find without preference query fields use saved
  travel preferences; explicit query/POST values still win. Stage list marks
  `aria-current="step"`.

### POL-002 accessibility markup polish

- Compare/model table captions; compare **Differs** text markers (not colour alone);
  empty-state `role="status"` on browse/saved/find/account surfaces; year selector
  focus-visible + labelledby. Evidence remains **CONDITIONAL** — no WCAG PASS /
  no CI axe gate yet.

### POL-002 model year selector

- Model detail resolves published years and filters variants by `?year=YYYY`
  (default = current, else newest). Invalid years fall back with a notice.
  Canonical URL stays without year. Demo migration `118` adds a 2025 Southern
  Cross variant for the selector.

### POL-005 account comparison history

- Signed-in users see shareable comparisons they created on `/account/comparisons`
  (brand-scoped; guest shares remain unlisted). Alert delivery still scaffolded.
- Saved browse searches: capture current `/rvs` filters via `POST /saved/searches`,
  reopen from `/saved` and `/account/alerts`. Email notifications remain off.

### CORE-011 + CORE-012 unification

- Merged Admin API Phase 1 / Option B A–L with Assist AI / Polaris onto one tree.
- AI/Polaris migrations renumbered to `101`–`116`; AI ADRs to `0021`–`0032`.
- Wired dual-source Option B into inventoried `GET /api/v1/admin/search-gaps`
  (`meta.source=dual`). No second API path. Production flags remain off.
- Wired `POST /api/v1/admin/datasets/{id}/sync` to real government dataset fetch
  (review-first facility candidates; optional fixture mode).
- **DATA-011A** National Dataset Catalogue on `government_datasets` (`117` +
  ADR 0033): RIC acquires; Platform catalogue SoR; no direct production publish.

### Government datasets and traveller facilities (DATA-012 / AI-6)

- Added the government dataset catalogue under **Admin → Data sources → Government datasets**, with CKAN / ArcGIS / CSV / GeoJSON connectors, demo fixtures, and curated National Public Toilet Map rows (disabled until an administrator enables Fetch).
- Facility import is review-first: candidates are approved into `traveller_facilities` only — never into caravan parks.
- Ask VanAssist can show a separate **Traveller facilities** section when the `assist_ai_traveller_facilities` flag is on; the flag stays off by default until facilities are populated and Quality Gate evidence is recorded.
- Administrators manage catalogue rows (add/edit), import fixtures or Fetch enabled sources, and bulk-approve or reject facility candidates.
- CLI bootstrap: `php scripts/import-demo-traveller-facilities.php --approve`.
- Knowledge gaps export SearchGap-shaped JSON for RIC (`/admin/ai-search/gaps/export?format=json`).
- Dual-source SearchGap merge helper (`SearchGapDualSource`) and merge plan (`docs/SEARCH_GAP_DUAL_SOURCE.md`) for CORE-011 `GET /api/v1/admin/search-gaps` — no second API; production Ask remains off.
- Quality Gate evidence: **CONDITIONAL PASS** only — `docs/AI_QUALITY_GATE_EVIDENCE.md`. Production Ask remains off until a full Platform Quality Gate pass.
### Option B programme Increments A–L (functional management coverage)

- Programme tracker: `docs/OPTION_B_MANAGEMENT_PROGRAMME.md`.
- Conditional Quality Gate:
  `docs/evidence/option-b-programme-2026-08-02/PLATFORM_QUALITY_GATE.md`.
- Assist RIC Option B sync console shipped on sibling branch
  `feature/option-b-ric-management` (pull providers/stays, conflicts, gap→research,
  dataset catalogue, budget guardrails).
- Production `ADMIN_API_ENABLED` remains off until staging rehearsal is recorded.

### Option B Increment H — claim-first onboarding + PHP admin polish (VAN-010 / OPS-010)

- Added claim-first provider onboarding on `/for-providers/register`: search-before-create,
  “Is this your business?” match step, explicit none-of-these confirmation, second
  duplicate check on submit with pending hold (no publication) and internal note /
  `listing_corrections` row when tables exist. Controlled by `CLAIM_FIRST_ONBOARDING`
  (default true).
- Added PHP admin pages: **Administration → API service accounts** (list/create/rotate/disable
  via `AdminApiServiceAccountService`) and **Directory → Recycle bin** (list/restore via
  `AdminApiRecycleBinService`).
- PHPUnit coverage for duplicate scoring/hold decision logic (`ClaimFirstOnboardingTest`).

### Admin API Option B Increments B–G (CORE-011 / VAN-002 / DATA-002 / DATA-012 / DATA-013)

- Added Admin API resources for claims, listing corrections, duplicate review/merge
  (with `dry_run`), government datasets, AI usage summaries, search analytics,
  sync conflicts, traveller facilities (`/facilities`, ADR 0019) and import
  publish/cancel/retry lifecycle actions.
- Extended scopes: `claims:*`, `corrections:*`, `duplicates:read`, `datasets:*`,
  `facilities:*`, `ai:read`; `duplicates:merge` and `drafts:approve` remain
  human-only (`NEVER_SERVICE`). RIC least-privilege adds `claims:read`,
  `datasets:read`, `facilities:read`.
- Forward migrations `088`–`092` create listing corrections, duplicate decisions,
  dataset/facility tables, AI usage reporting tables and sync conflict queue.
- Contract tests and OpenAPI paths updated; apply migrations before enabling new
  routes in staging.

### Admin API Option B closeout (CORE-011 / OPS-010 / DATA-011 / OPS-011)

- Recorded conditional Platform Quality Gate evidence for Admin API + Assist RIC
  client foundation (`docs/evidence/admin-api-2026-08-02/`). Production
  `ADMIN_API_ENABLED` / MFA flags remain off until staging rehearsal is appended.
- Synced Phase 1 design, LIVE_API, OPERATIONS_RUNBOOK and backlog statuses:
  CORE-011 / OPS-011 / DATA-011 client work done; OPS-010 production enablement
  still gated.

### Admin API staging enablement checklist

- Documented the staging-only sequence to migrate Admin API tables, enroll TOTP,
  create a least-privilege RIC service account, and rehearse import submit before
  any production `ADMIN_API_ENABLED` change (`docs/LIVE_API.md`).
- Added CLI helpers `scripts/admin-api-create-ric-service-account.php` and
  `scripts/admin-api-probe.php` for safe staging bootstrap and health checks.

### Admin API TOTP MFA (OPS-010)

- Replaced the MFA verify scaffold with RFC 6238 TOTP validation (pure PHP).
- Added enrollment endpoints `POST /auth/mfa/enroll/begin` and
  `POST /auth/mfa/enroll/confirm` for authenticated humans.
- When `ADMIN_API_MFA_REQUIRED=true`, password login returns a short-lived
  `mfa_token` (`mfa:verify` scope); `POST /auth/mfa/verify` exchanges a valid
  authenticator code for a full session. Enroll MFA before enabling the flag.
- `ADMIN_API_MFA_REQUIRED` remains **false** by default.

### Admin API Phase 1 foundation (CORE-011)

- Added the versioned `/api/v1/admin` surface for Assist RIC and other management
  clients: health/capabilities, human and service-account auth, providers/stays
  read and write, recycle bin, drafts/imports, audit and search-gaps.
- Locked the boundary so external tools must not open production MariaDB
  (ADRs 0018–0020). Stays remain `caravan_parks`; traveller facilities stay out
  of Phase 1. Assist RIC is the initial local management client.
- Migrations `085`–`087` create Admin API credentials, MFA tables and
  draft/import job storage. The API remains disabled by default
  (`ADMIN_API_ENABLED=false`) until staging rehearsal and an updated Quality
  Gate allow production flags.
- TOTP MFA enrollment/verify shipped in a follow-up (OPS-010); enforcement flag
  stays false by default.

### National coverage map

- Replaced the floating heat points with a recognisable Australia map including mainland and Tasmania outlines, state/territory boundaries and labels, an opportunity-score legend, and keyboard-focusable town/category points.

### Free Growth Hub

- Added direct, prominently labelled email campaign actions for caravan/RV clubs, club federations, 4WD/touring groups, publications, tourism organisations, industry bodies, manufacturers, dealers, rental fleets and caravan park networks. Each action shows the reviewed eligible count and opens a correctly targeted, prefilled campaign instead of making administrators reconstruct the audience and message manually.
- Added the real next sending action directly to every existing campaign row, including a pre-addressed internal preview and one-click staged batch controls, while retaining the enforced recipient caps and evidence checks.
- Consolidated provider factual notices, reviewed organisation outreach, Social Studio, Facebook/community sharing, partner referrals, search indexing and Website Insights into one dashboard workflow.
- Added ready-to-copy community, Messenger, club-newsletter and provider/park share messages with distinct tracked links and mobile native sharing.
- Added first-party UTM source capture without collecting visitor IP addresses; Website Insights renders tracked sources in plain English.
- Added live channel status for prepared factual campaigns, reviewed organisation contacts, Facebook connection, indexing and approved social assets.
- Kept Facebook-group posting manual so administrators can follow each group’s rules, and retained consent, suppression, sender-identification and unsubscribe boundaries for electronic messages.

### Location-first behaviour across every discovery journey

- Extended optional current-location resolution beyond the homepage to Fuel, EV charging, Places to Stay, the service directory, every service-category page, provider browsing, result refinements and assistance requests.
- Nearby shortcut links reuse a recent device location and carry coordinates into the destination search instead of opening an unfiltered national page.
- Preserved the rule that a typed town, suburb or postcode immediately overrides stored or device coordinates.
- Kept the phone's coordinates as the distance origin for service-category and Places to Stay searches rather than silently replacing them with a town-centre estimate.

### Clear email campaign sending path

- Added a direct **Email campaigns** item to the admin Growth navigation and a prominent dashboard shortcut.
- Reworked the campaign list around a visible four-step path: open, review recipients, email a preview to the administrator, and start staged delivery.
- Added a plain-English next action and **Open & send** control to each active campaign.
- Added an in-campaign **Next step** prompt and direct jump to the delivery controls.
- Renamed the internal test action to **Email preview to me** so it is clear that this is a one-time preflight check, not the provider campaign.

### Website interaction recording repaired

- Made the permission-scoped administrator shell practical on portrait and landscape tablets: navigation now uses a touch-friendly side drawer up to 1100px, supports outside-tap/Escape dismissal and keyboard focus containment, compacts crowded header actions, keeps forms within the viewport and contains wide tables in their own scroll area.
- Corrected the first-party funnel-event insert so searches, provider profile opens, contact actions and confirmed outcomes retain the complete database parameter list instead of failing silently.
- Added regression coverage for the exact placeholder/value mismatch that caused the lost events.
- Made the VanAssist main search location-first on desktop and mobile. It resolves the nearest town without submitting, while any typed town, suburb or postcode immediately clears GPS coordinates and remains authoritative.
- Reordered the administrator dashboard around queues needing action and website demand. Inventory totals, audit detail and scheduled tasks remain available in compact disclosure panels instead of competing with launch work.
- Added data-freshness timestamps, search-success interpretation and a daily traffic pulse to Website Insights; provider reporting is now isolated to the active brand.
- Anchored town/suburb/postcode suggestions directly below their input at the same width, with compact touch-friendly rows, internal scrolling and clear keyboard focus on both hero and standard forms.

### Consolidated enterprise workstream

- Established Assist Platform Enterprise as the single product and governance
  direction for VanAssist, TowSmart, TrailerWise and LocalTorque.
- Consolidated product, architecture, UX, backlog, engineering and operational
  documentation into one version-controlled project memory.
- Continued the current UX redesign as the official Design System foundation.
- Organised delivery into Platform, Experience, Brands, Data, Infrastructure,
  Operations and Commercial streams.
- Continued unified admin, Data Sources and Data Intelligence work without
  creating a competing implementation.
- Completed CORE-004's shared membership catalogue and honest provider dashboard
  state. Paid selection and charging remain disabled pending COM-004 acceptance.
- Completed INF-003's fail-closed environment and integration configuration
  contract, corrected the free-plan example and documented secret rotation.
- Added OPS-006's repository-backed living documentation system with seven
  guide sections, global search and audience/brand/module/version filters,
  contextual dashboard Help links, mobile/accessibility treatment and a What's
  new view. Administrator/developer/API material remains behind the admin role
  gate; public help exposes only customer/provider and release information.
- Added CI and pull-request enforcement so changed public, customer, provider,
  administrator or API behaviour must update the matching registered guide and
  these release notes. No migration or environment change is required.
- Replaced browser-dependent provider import continuation with a locked server
  worker that resumes national screening, merges safe unclaimed duplicates,
  publishes only evidence-confirmed listings and reports processable versus
  review-required counts. Migration 083 registers the scheduled task.
- Corrected provider campaign scope to use the same canonical brand categories
  as the live directory. VanAssist now prepares factual and consent-gated
  marketing drafts for every active service category, validates sendable email
  addresses and exposes up to 250 current campaigns.
- Reworked VanAssist's phone first screen around immediate service/location
  search, current-location access and touch-safe traveller shortcuts over the
  approved navy travel treatment. Search results now show returned, located
  providers as numbered OpenStreetMap pins tied to the accessible result list;
  each pin opens the exact provider summary, list link and available directions
  action. Phones default to compact list rows with a List/Map control, preserve
  a prominent nearby Places to stay path, separate Featured and related-service
  results, and keep the list as the no-JavaScript/map-failure fallback.
- Added direct map manipulation after users select Map: touch drag, two-finger
  pinch zoom, mouse-wheel and keyboard pan/zoom, accessible zoom and fit-result
  controls, plus a collapsible and pointer/keyboard-movable provider summary.
  Supporting trust/distance copy now follows the results instead of interrupting
  the compact search and list/map task flow.
- Removed the legacy minimum card height from compact provider results and tightened spacing, avatars, badges and actions on desktop and mobile. Phone actions retain practical touch targets while each result consumes materially less vertical space.
- Added a compact listing-accuracy notice to the VanAssist homepage, results
  and Places to stay, with direct disclaimer and correction links. Public
  contact actions now use only explicitly public phone/email fields; an
  unclaimed listing can no longer expose private canonical contact data.
- Changed unclaimed-profile handoff into a listing-specific claim/correction
  request. Authority remains subject to review. Provider registration now
  separates request-related contact from optional, unticked promotional email
  consent, and the provider workspace uses grouped desktop navigation plus a
  compact phone menu.
- Replaced the provider-acquisition image containing invented-looking business
  signage and contact details with a neutral, unbranded caravan service scene.
- Corrected production and maintenance workflows to run the safeguarded
  provider worker non-interactively inside the existing application container,
  avoiding the remote password prompt that interrupted the previous release.
- Corrected the production release result so it no longer fails after a
  successful live deployment by attempting Docker Compose through the
  deliberately restricted deploy account. Provider imports continue through
  the root-owned five-minute cron task, with current counts in Provider data.
- Added a focused PR & Outreach Hub for clubs, peak bodies, manufacturers,
  dealer/rental networks, park groups, tourism organisations, touring bodies and
  publications. Imports remain research-only until a human verifies the current
  official source, published role, direct relevance and absence of contrary
  warnings. Organisation campaigns select one target type, use role-matched
  copy, retain recipient evidence, honour suppression and progress through the
  existing test, 25-recipient pilot and reviewed daily limits. Automatic sending
  and member/customer-list ingestion are not provided. The controlled release
  now consolidates growth tools behind one navigation entry, preserves review evidence and append-only queue/transport/outcome history, and no longer presents queue completion as proof of delivery.
  loader idempotently places the initial 63 official-source targets directly in
  the production register as research/held records; no manual upload and no
  automatic eligibility or delivery is required.

Production status: not released. A dated release entry requires a passed Platform
Quality Gate and verified deployment record.

### Ask VanAssist reliability candidate (2026-08-03)

- Expanded natural-language validation and routing from a partial allowlist to
  the complete seeded VanAssist service catalogue.
- Added common fault-language coverage across caravan systems, appliances,
  body repairs, roadside help, inspections, parts and travel essentials.
- Added a bounded general-repair fallback for unclear but clearly caravan/RV
  fault requests; unrelated general-AI questions remain rejected.
- Linked privacy-safe Ask questions, interpretations, result counts and returned
  answer summaries through the existing Admin API AI-usage feed for RIC review.
  Raw GPS and provider contact details are not retained, and review never
  changes live routing automatically.
- Added a clearly labelled related-provider fallback over a modestly wider area
  when an understood specialist request has no exact local listing.
- Prevented unresolved or conversationally suffixed town names from degrading
  into Australia-wide results. Proxy-aware rate limits now separate visitors,
  and blocked Ask requests receive a branded recovery page instead of raw text.

## Historical records

- `PRODUCTION_RELEASE_2026-07-22.md`
- `DEPLOYMENT_RECORD_2026-07-23_LOCALTORQUE.md`
- `PRODUCTION_READINESS_AUDIT_2026-07-24.md`

## Entry template

Each release records version/date, commit and checksum, user/provider/admin
changes, brands affected, migrations, environment changes, quality-gate evidence,
validation, deployment result, known issues and rollback target.
