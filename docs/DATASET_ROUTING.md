# Dataset routing

**Status:** AI-5 Ask dataset adapter live (flag off by default); DATA-012
government catalogue/connectors stage facility candidates for review.  
**Reuse:** [`DATA_SOURCES.md`](DATA_SOURCES.md), [`DATA_012.md`](DATA_012.md), ADR 0006/0007, DATA-006.  
**Do not** invent a second connector framework unless a gap is documented.

## Orchestrator role

`DatasetSearchAdapter` selects enabled connectors by record type, coverage,
licence, trust policy, cost policy and rate limits. Normalised candidates only.

## Source classes

Government/council catalogues, CKAN, ArcGIS Feature Services, Socrata, CSV,
JSON, GeoJSON, XML, KML, shapefile packages, OpenStreetMap, approved commercial
APIs, approved first-party public websites, VanAssist live API (canonical).

## Connector metadata

Connector key, publisher, title, coverage, record types, licence, attribution,
trust policy, update frequency, last checked/imported, fetch method, rate limit,
cache policy, normalisation mapping, duplicate rules, error status, cost policy,
enabled state.

## Trust policies

`trusted_automatic` | `trusted_review` | `community_review` |
`web_research_review` | `prohibited`.

No dataset may use `trusted_automatic` without an explicit owner-approved
recorded decision.

## OpenStreetMap

Configurable **offline seed** adapter (`osm_offline_seed`, migration `097`).
Ask never calls live Overpass. Operators stage from the managed AU seed /
server extract via:

`php scripts/stage-osm-offline-seed.php [--query=…] [--state=…] [--limit=…] [--dry-run]`

Gate with `AI_OSM_OFFLINE_ENABLED=1` (or `--force`). Hits enter DATA-006 as
`trusted_review` candidates through `DraftCandidateService`. Live Overpass
refresh remains an admin/cron path only (`OsmRefreshService`), separate from Ask.

Prefer managed Overpass, AU extract or regional subsets over abusing public
Overpass. Respect ODbL. Mappings documented and configurable.

## Paid APIs

Optional, disabled by default. Google Places may remain via DATA-006 / RIC.
Brave later. No Bing. No silent paid fallback. Hard request and AUD caps.
