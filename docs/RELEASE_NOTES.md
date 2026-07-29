# Assist Platform Enterprise release notes

This is the chronological release index. Detailed historical deployment records
may remain as dated files and are linked here rather than copied.

## Unreleased

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
