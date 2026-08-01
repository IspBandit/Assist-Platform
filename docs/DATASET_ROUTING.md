# Dataset routing

**Status:** design (Phase AI-0).  
**Reuse:** [`DATA_SOURCES.md`](DATA_SOURCES.md), ADR 0006/0007, DATA-006.  
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

Configurable adapter. Prefer managed Overpass, AU extract or regional subsets
over abusing public Overpass. Respect ODbL. Mappings documented and configurable.

## Paid APIs

Optional, disabled by default. Google Places may remain via DATA-006 / RIC.
Brave later. No Bing. No silent paid fallback. Hard request and AUD caps.
