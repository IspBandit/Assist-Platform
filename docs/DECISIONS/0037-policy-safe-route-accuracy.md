# ADR 0037: Policy-safe route accuracy and truthful location fallbacks

- **Status:** accepted
- **Date:** 2026-08-16
- **Owners:** Glen Cendron / Assist Platform engineering
- **Backlog item:** VAN-011 / DATA-001
- **Affected brands/modules:** VanAssist Find, Ask, fuel, stays and provider cards

## Context

Google Routes returns genuine road distance and duration when VanAssist has a
reliable destination. Many imported providers currently have only a base town,
not an exact business point. Routing to that town centre produced a valid road
calculation but not the distance to the provider. Persistent storage of Google
route distance/duration is also not an expressly permitted cache under the
current Google Maps Platform service-specific terms; the Routes allowance is
limited to temporary latitude/longitude storage.

## Decision

Google Routes remains the production road-distance provider. Route distance is
requested and displayed only for a provider, stay, fuel stop or facility with a
reliable point. Provider town-centre fallbacks remain useful discovery records,
but show **Exact provider distance unavailable (town-centre estimate)** and do
not consume a route-matrix element. They sort after routed results.

Google distance and duration are not persisted. Identical destinations are
deduplicated inside one request, synthetic release checks are excluded from
analytics, and route calls remain bounded by the configured destination limit.
Provider coordinate enrichment is the durable accuracy fix.

## Alternatives considered

- Persist Google distance/duration: rejected because the current service terms
  do not expressly permit that content cache.
- Present a route to the provider's town centre as the provider distance:
  rejected because it overstates data accuracy.
- Remove every provider without an exact point: rejected because a clearly
  labelled service-area listing remains useful, particularly for mobile work.
- Add OSRM immediately: deferred because it introduces another production
  service and operating burden without fixing weak provider source data.

## Consequences

Users can distinguish genuine road distance from location-only coverage.
Google element use falls because town-centre fallbacks are not routed. Some
providers no longer show a numeric distance until their exact location is
enriched and reviewed. No existing provider, stay or facility is deleted.

## Quality Gate impact

- Architecture: retains the accepted shared Google Routes integration.
- UX: removes false precision and keeps a plain-language fallback.
- Engineering: deterministic tests cover route-element use and mixed exact/
  town-centre result sets.
- Business: more trustworthy results with lower avoidable routing cost.

## Validation and rollback

Run route-service tests, rendered mobile/desktop search acceptance and live
radius smoke tests. Rollback uses the prior immutable release; no stored route
content or destructive migration exists.
