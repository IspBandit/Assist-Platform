# ADR 0036: Google Routes for public road-distance search

- **Status:** accepted
- **Date:** 2026-08-11
- **Owners:** Glen Cendron / Assist Platform engineering
- **Backlog item:** VAN-011 / CORE-012
- **Affected brands/modules:** VanAssist Find, Ask, services, fuel and stays

## Context

VanAssist previously used great-circle distance for filtering and display. That
was a safe geographic prefilter but it was not the actual distance a traveller
would drive. The owner requires real road distances across provider, stay,
fuel and traveller-facility discovery and already operates Google Maps Platform.

## Decision

Use Google Routes API `computeRouteMatrix` as a shared server-side enrichment
step after the existing geographic prefilter. One origin and a bounded set of
deduplicated destination coordinates are sent per search. Returned road distance
is used for the final radius boundary, sorting and display; drive duration is
displayed as an estimate. Results without a returned route are not mixed into a
successfully routed result set.

Use `TRAFFIC_UNAWARE` because discovery needs stable distance rather than live
traffic and this keeps requests in the lower-cost Routes Essentials tier. The
API key is a separate server-only `GOOGLE_ROUTES_API_KEY`, restricted to Routes
API and the production server. Google route results are not persistently cached.

## Alternatives considered

- Keep great-circle distance: rejected because it does not meet the customer
  requirement and can materially understate travel distance.
- Google Places Distance Matrix: rejected because Places is for place discovery;
  Routes API is Google's current route-matrix service.
- Add another routing vendor or self-host OSRM: deferred because it adds another
  data processor or operational service when Google is already approved.

## Consequences

Road-distance searches incur a billable element per unique destination. Requests
deduplicate identical coordinates, cap destinations with
`GOOGLE_ROUTES_MAX_DESTINATIONS`, and do not request traffic-aware routing. A
temporary API outage leaves the existing straight-line results available and
honestly labelled; it never labels fallback data as road distance. Public copy
attributes road distance and estimated duration to Google Maps.

Typed town/suburb/postcode input takes precedence over hidden device coordinates
across Find, service category and stays searches. The preliminary great-circle
filter remains useful because road distance cannot be shorter than the direct
geodesic for normal road routing; the final boundary is re-applied using Google.

## Quality Gate impact

- Architecture: one shared route-matrix service; no second search stack.
- UX: cards show `km by road` and estimated drive time; mobile remains compact.
- Engineering: deterministic fake-client tests cover parsing, deduplication,
  strict road radius and outage fallback.
- Business: more trustworthy nearby results with bounded Google element usage.

## Validation and rollback

Validate the key with one production-like route, run unit/static/full CI, then
verify Find, Ask, fuel and stays on phone and desktop after release. Rollback is
the preceding immutable release and removal of `GOOGLE_ROUTES_API_KEY`; there is
no migration and no stored route data to undo.
