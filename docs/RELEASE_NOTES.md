# Assist Platform Enterprise release notes

This is the chronological release index. Detailed historical deployment records
may remain as dated files and are linked here rather than copied.

## Unreleased

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
- Migrations `085`–`087` create Admin API credentials, MFA scaffold tables and
  draft/import job storage. The API remains disabled by default
  (`ADMIN_API_ENABLED=false`) until MFA and Platform Quality Gate evidence are
  recorded.
- MFA challenge/verify endpoints are scaffolded; verify still returns 501 while
  `ADMIN_API_MFA_REQUIRED` stays false.

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

## Historical records

- `PRODUCTION_RELEASE_2026-07-22.md`
- `DEPLOYMENT_RECORD_2026-07-23_LOCALTORQUE.md`
- `PRODUCTION_READINESS_AUDIT_2026-07-24.md`

## Entry template

Each release records version/date, commit and checksum, user/provider/admin
changes, brands affected, migrations, environment changes, quality-gate evidence,
validation, deployment result, known issues and rollback target.
