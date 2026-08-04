# Dataset Source Audit

Owner: Assist RIC Dataset Catalogue (DATA-011A acquisition path)  
Method: official portal pages + Assist Platform catalogue seeds (`DATA_011A`,
`DATA_012`, migration `110` / `117`) + connector suitability review.  
Date: 2026-08-04

## Priority legend

- **Priority:** critical / high / medium / low
- **Difficulty:** low / medium / high
- **Value:** traveller usefulness for VanAssist

## Federal / national

| Source | Signup | API key | Pricing / free allowance | Licence / reuse | Attribution | Coverage | Update | Bulk | Automated access | RIC method | Priority | Difficulty | Value |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| [National Public Toilet Map](https://data.gov.au/data/dataset/national-public-toilet-map) | No for portal download; accept portal terms | No | Free open data | CC BY 3.0 AU + portal terms | © Commonwealth of Australia — Toilet Map | AU national | Published monthly on data.gov.au ([toiletmap.gov.au/copyright](https://toiletmap.gov.au/copyright)) | Yes (CSV; URL rotates) | CKAN API + resource download | `national_public_toilet_map` | critical | low | critical |
| [data.gov.au](https://data.gov.au/) | Optional account for some features | No for most open datasets | Free | Per-dataset | Per-dataset | AU | Varies | Often | CKAN | portal discovery + child connectors | high | medium | high |
| Geoscience Australia catalogues | Usually no | Varies | Free where CC-licensed | Per-dataset — verify | Required when stated | AU | Varies | Often | Prefer bulk/API | catalogue only until licensed child selected | medium | medium | medium |
| Infrastructure / transport open catalogues | Varies | Varies | Free open datasets common | Per-dataset | Per-dataset | AU / corridors | Varies | Often | API/bulk | theme connectors after licence check | medium | medium | high |

## States and territories

| Source | Official URL | Notes for RIC | Priority |
| --- | --- | --- | --- |
| Queensland Open Data | https://www.data.qld.gov.au/ | CKAN-style open data; verify each dataset licence | high |
| NSW Open Data | https://data.nsw.gov.au/ | State facilities / tourism / transport datasets | high |
| Transport for NSW Open Data | https://opendata.transport.nsw.gov.au/ | Roads/rest-related feeds; API registration may be required per product | high |
| DataVic | https://www.data.vic.gov.au/ | Victoria open data portal | high |
| WA Open Data | https://catalogue.data.wa.gov.au/ | CKAN catalogue | medium |
| SA Data Directory | https://data.sa.gov.au/ | State directory | medium |
| Tasmania Open Data | https://www.data.tas.gov.au/ | State open data | medium |
| ACT Open Data | https://www.data.act.gov.au/ | Territory open data | medium |
| NT Open Data | https://data.nt.gov.au/ | Territory open data | medium |

For every state row: signup/API-key/pricing/licence/attribution/update frequency
must be taken from the **specific child dataset**, not assumed from the portal.
RIC stores portal entries as discovery + sample packs until a child dataset is
enabled with cited terms.

## Local government

| Pattern | Examples | Access | Integration |
| --- | --- | --- | --- |
| Council CKAN | Many QL/NSW councils | Often free CSV/API | `council_portal_discovery` + CKAN |
| ArcGIS Hub / FeatureServer | Council facility layers | Free query endpoints common; respect query limits | ArcGIS REST connector (extend from Platform `ArcGisFeatureConnector` pattern) |
| Socrata | Some AU councils/cities | Free SODA; app token sometimes required | Socrata connector (planned) |
| Tourism / facilities feeds | VIC / parks pages | Mixed HTML and open data — prefer open data | Manual import / pack |

## Open data community

| Source | Licence | Notes | Priority |
| --- | --- | --- | --- |
| OpenStreetMap | ODbL 1.0 — https://www.openstreetmap.org/copyright | Share-Alike; attribution required | critical |
| Geofabrik Australia extracts | OSM ODbL | Prefer extracts over unrestricted Overpass | critical |
| Targeted Overpass | OSM ODbL + Overpass etiquette | Limited targeted queries only; identifiable User-Agent | medium |

## Commercial / paid (disabled by default)

See `PAID_DATA_SOURCES.md`. Pricing must be confirmed on the vendor portal at
enablement time; seed caps are conservative operator guardrails, not quotes.

| Source | Official docs | RIC posture |
| --- | --- | --- |
| Google Places API | https://developers.google.com/maps/documentation/places/web-service | Disabled; key in vault; hard caps |
| Brave Search API | https://brave.com/search/api/ | Disabled until owner enablement |
| HERE Places/Search | https://www.here.com/docs/bundle/places-api-developer-guide | Disabled |
| TomTom Search | https://developer.tomtom.com/search-api | Disabled |
| Mapbox Search | https://docs.mapbox.com/api/search/ | Disabled |
| OpenChargeMap | https://openchargemap.org/site/develop | Prefer free tier terms review before enable |
| Fuel price APIs / state fuel feeds | Per jurisdiction | Verify redistribution rights |
| Tourism / campsite vendors | Per contract | Licence-first; no scrape |

## Category coverage matrix

Catalogue themes currently seeded for: public toilets, dump points, drinking
water, rest areas, visitor information, caravan parks, campgrounds, public
showers, laundries, fuel, LPG, hospitals, medical centres, pharmacies,
emergency services, boat ramps, picnic areas, barbecues, waste disposal, EV
charging, weighbridges, roadside stopping, national parks, RV repair, auto
electricians, mobile mechanics, tyre services, towing/recovery, caravan parts.

## Verified child datasets (2026-08-04)

| Dataset ID | Official source | Bulk verified | Licence |
| --- | --- | --- | --- |
| `au_national_public_toilet_map` | data.gov.au National Public Toilet Map | Yes (~12 MB CSV) | CC BY 3.0 AU |
| `nsw_rest_areas` | TfNSW / data.gov.au `nsw-2-nsw-rest-areas` | Yes | CC BY 3.0 AU |
| `nsw_boat_ramps` | TfNSW Maritime boat ramps | Yes | CC BY 3.0 AU |
| `nsw_ev_charging_locations` | TfNSW EV charging | Yes | CC BY 3.0 AU |
| `gold_coast_caravan_parks` | City of Gold Coast caravan parks | Yes (GeoJSON) | CC BY 3.0 AU |
| `sa_rest_areas_state_maintained` | SA DIT rest areas SHP ZIP | URL verified; not always auto-fetched | CC BY 3.0 AU |
| `wa_major_rest_areas` | Main Roads WA ArcGIS Hub | Manual review (HTML hub URL) | CC BY 3.0 AU |
| `portal_osm_australia` | Geofabrik Australia extract | Metadata + optional PBF | ODbL 1.0 |

Portable index: `data_catalogue/catalogue.json` (see `DATASET_DOWNLOADS.md`).

## Audit approach used

1. Start from Assist Platform `government_datasets` / DATA-011A keys.
2. Confirm Toilet Map resource id + licence from data.gov.au.
3. Seed RIC local catalogue with portals, themes and paid stubs.
4. Implement working connectors only where licence and access path are clear.
5. Download permitted first-wave bulk files into `data_catalogue/raw/`.
6. Mark remaining portals as discovery entries pending child-dataset citation.
