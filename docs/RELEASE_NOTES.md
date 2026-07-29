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
