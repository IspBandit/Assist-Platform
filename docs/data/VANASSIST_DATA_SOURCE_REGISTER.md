# VanAssist data source register

**Generated:** 2026-09-17T06:26:53Z  
**Backlog:** DATA-012, DATA-011A, VAN-001  
**Architecture:** Assist RIC acquires; Platform `government_datasets` is SoR (ADR 0033).  
**Raw archive:** `data/sources/vanassist/` (large binaries gitignored; registry + checksums committed).

## Summary

- Sources registered: **37**
- Datasets with archived files: **18**
- PDFs/guides archived: **4**
- GREEN: **18**
- PERMISSION_GRANTED: **1**
- AMBER_PERMISSION_REQUIRED: **15**
- YELLOW_SPECIAL_LICENCE: **1**
- UNKNOWN_LICENCE: **2**
- SKIP: **0**
- Raw records measurable: **33468**

## Reuse rules

| Status | Meaning |
| --- | --- |
| GREEN | Explicit reusable open licence — may import |
| PERMISSION_GRANTED | Owner permission recorded — may import |
| AMBER_PERMISSION_REQUIRED | Useful; archive only until permission |
| YELLOW_SPECIAL_LICENCE | Special obligations (e.g. ODbL) — do not mix |
| UNKNOWN_LICENCE | Archive; do not import |
| SKIP | Intentionally excluded |

## Sources

| ID | Source | Org | Juris. | Format | Reuse | Import | Records | SHA256 | Path |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `au_national_public_toilet_map` | National Public Toilet Map | Australian Government / Department of Health, Disability and Ageing | AU | CSV | GREEN | importable | 26777 | `8aaef33e53f0` | `data/sources/vanassist/australia/Toiletmap.csv` |
| `au_wikidata_enrichment` | Wikidata (targeted enrichment only) | Wikimedia Foundation / Wikidata community | AU | SPARQL | GREEN | deferred_targeted_only | — | `` | `—` |
| `au_national_formal_rest_areas` | National Formal Rest Areas | Australian Government (NFDH / data.gov.au) | AU | CSV | UNKNOWN_LICENCE | archived_not_imported | 0 | `6e2d60abc375` | `data/sources/vanassist/australia/national-formal-rest-areas.csv` |
| `portal_osm_australia` | OpenStreetMap Australia extract | OpenStreetMap contributors / Geofabrik | AU | PBF | YELLOW_SPECIAL_LICENCE | not_mixed_into_proprietary_db | — | `` | `—` |
| `qld_roadside_amenities` | Roadside amenities - Queensland | Queensland Government — Transport and Main Roads | QLD | GeoJSON | GREEN | importable | 1066 | `fd61bd293f35` | `data/sources/vanassist/qld/qld_roadside_amenities.geojson` |
| `qld_operational_boat_facilities` | QLD Operational Boat Facilities | Queensland Government — Transport and Main Roads | QLD | GeoJSON | GREEN | importable | 775 | `f542889c61d8` | `data/sources/vanassist/qld/qld_operational_boat_facilities.geojson` |
| `nsw_rest_areas` | NSW Rest Areas | Transport for NSW | NSW | CSV | GREEN | importable | 869 | `4ba831a4cfaf` | `data/sources/vanassist/nsw/rest-areas-csv-format.csv` |
| `nsw_rest_area_temporary_closures` | NSW Rest Area Temporary Closures | Transport for NSW | NSW | CSV | GREEN | operational_overlay_not_facility | — | `` | `—` |
| `nsw_boat_ramps` | NSW Boat Ramps | Transport for NSW | NSW | GeoJSON | GREEN | importable | 680 | `7740740f2fdb` | `data/sources/vanassist/nsw/nsw_boat_ramps.geojson` |
| `vic_recreation_sites` | Recreation Sites (State Forest) | Victorian Government / DataVic | VIC | GeoJSON | GREEN | importable | 532 | `760a6bceea30` | `data/sources/vanassist/vic/recreation_sites.geojson` |
| `vic_recreation_assets` | Recreation Assets | Victorian Government / DataVic | VIC | GeoJSON | GREEN | importable_join_to_sites | — | `` | `—` |
| `parks_victoria_campgrounds` | Parks Victoria Campgrounds | Parks Victoria / DataVic | VIC | SHP/ZIP | GREEN | importable_as_stays | — | `8808bb288420` | `data/sources/vanassist/vic/parks_victoria_campgrounds.zip` |
| `sa_rest_areas_state_maintained` | Rest Areas - State Maintained (SA) | Location SA / Department for Infrastructure and Transport | SA | SHP/ZIP | GREEN | importable_with_age_warning | — | `` | `—` |
| `wa_heavy_vehicle_rest_areas` | Main Roads WA Heavy Vehicle Rest Areas | Main Roads Western Australia | WA | GeoJSON | GREEN | importable_as_hv_rest_area | 237 | `0693544efd8e` | `data/sources/vanassist/wa/heavy_vehicle_rest_area.geojson` |
| `wa_major_rest_areas` | Main Roads WA Major Rest Areas | Main Roads Western Australia | WA | GeoJSON | GREEN | importable_as_rest_area | 126 | `72ca1dbd91db` | `data/sources/vanassist/wa/major_rest_areas.geojson` |
| `wa_minor_rest_areas` | Main Roads WA Minor Rest Areas | Main Roads Western Australia | WA | GeoJSON | GREEN | importable_as_rest_area | 1269 | `fdcce8528709` | `data/sources/vanassist/wa/minor_rest_areas.geojson` |
| `tas_roadside_stops` | Tasmania Roadside Stops | Department of State Growth / LIST | TAS | GeoJSON | GREEN | importable | 81 | `bfe64e7be60a` | `data/sources/vanassist/tas/tas_roadside_stops.geojson` |
| `tas_boat_ramps` | Tasmania Boat Ramps (LIST) | LIST Tasmania | TAS | GeoJSON | GREEN | importable | 297 | `d62f83d8e3dd` | `data/sources/vanassist/tas/tas_boat_ramps.geojson` |
| `tas_list_camping_caravan_layers` | Tasmania LIST camping / caravan / dump layers | LIST Tasmania | TAS | various | UNKNOWN_LICENCE | licence_check_required | — | `` | `—` |
| `nt_campground_web_pages` | NT Government campground web pages | Northern Territory Government | NT | HTML | AMBER_PERMISSION_REQUIRED | not_imported | — | `` | `—` |
| `act_public_toilet_assets` | ACT Public Toilet Assets | ACT Government | ACT | GeoJSON | GREEN | importable | 229 | `e57478389e14` | `data/sources/vanassist/act/act_public_toilet_assets.geojson` |
| `cpaq_explore_qld_2026` | Explore Queensland Caravan Parks Directory 2026 | Caravan Parks Association of Queensland Ltd (CPAQ) / Caravanning Queensland | QLD | PDF + extracted JSON | PERMISSION_GRANTED | importable_authorised | 530 | `a14b2dc1655b` | `data/sources/vanassist/industry/cpaq-2026-directory.pdf` |
| `industry_nsw_ccia_holiday_guide_2026` | 2026 NSW Caravan & Camping Parks & Products Holiday Guide | Caravan & Camping Industry Association NSW | NSW | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_wa_caravan_camping_guide_2026` | Caravan & Camping WA Guide 2026 | Caravan Industry Association Western Australia | WA | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_tas_caravan_guide_2026` | Caravan Tasmania 2026 Guide | Caravanning Tasmania | TAS | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_sa_caravan_camping_guide` | SA Parks / Caravan & Camping SA guide | Caravan & Camping SA / SA Parks | SA | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_nt_caravanning_guide` | Caravanning NT parks map / visitor guide | Caravanning NT | NT | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_vic_caravan_residential_guide` | Caravan & Residential Parks Victoria accommodation guide | Caravan Industry Victoria | VIC | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_big4_holiday_guide_2026` | BIG4 Holiday Guide 2026 | BIG4 Holiday Parks of Australia | AU | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `a2ca3190d8e1` | `data/sources/vanassist/industry/big4-holiday-guide-2026.pdf` |
| `industry_gday_parks_guide` | G'day Parks National/Digital Guide | G'day Group | AU | PDF/web | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_ciaa_services_listing` | Caravan Industry Association of Australia — Services Listing | Caravan Industry Association of Australia | AU | web | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_national_b2b_directory` | National caravan industry B2B directory | Industry association / commercial directory | AU | web | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `industry_cmca_dump_points` | CMCA dump-point list/map | Campervan & Motorhome Club of Australia | AU | PDF/web | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |
| `regional_drive_queensland_2026` | Drive Queensland Drive Guide 2025/2026 | Drive Queensland | QLD | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `fc943886998d` | `data/sources/vanassist/regional-guides/drive-queensland-guide-2026.pdf` |
| `regional_barcoo_visitor_guide` | Visit Barcoo Visitor Guide | Barcoo Shire Council | QLD | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `bfc8f6306fba` | `data/sources/vanassist/regional-guides/barcoo-visitor-guide.pdf` |
| `vic_coastal_places_of_interest` | Coastal Places of Interest | Victorian Government / DEECA / DataVic | VIC | SHP | GREEN | importable_filtered | — | `` | `—` |
| `regional_scenic_rim_visitor_guide` | Scenic Rim Visitor Guide | Scenic Rim Regional Council / tourism | QLD | PDF | AMBER_PERMISSION_REQUIRED | archive_only | — | `` | `—` |

## Permission queue (AMBER)

- **NT Government campground web pages** (Northern Territory Government) — https://nt.gov.au/
- **2026 NSW Caravan & Camping Parks & Products Holiday Guide** (Caravan & Camping Industry Association NSW) — https://www.cciansw.asn.au/
- **Caravan & Camping WA Guide 2026** (Caravan Industry Association Western Australia) — https://www.caravanwa.com.au/
- **Caravan Tasmania 2026 Guide** (Caravanning Tasmania) — https://www.caravaningtasmania.com.au/
- **SA Parks / Caravan & Camping SA guide** (Caravan & Camping SA / SA Parks) — https://www.caravanandcampingsa.com.au/
- **Caravanning NT parks map / visitor guide** (Caravanning NT) — https://www.caravannt.com.au/
- **Caravan & Residential Parks Victoria accommodation guide** (Caravan Industry Victoria) — https://www.caravanvictoria.com.au/
- **BIG4 Holiday Guide 2026** (BIG4 Holiday Parks of Australia) — https://www.big4.com.au/
- **G'day Parks National/Digital Guide** (G'day Group) — https://www.gdayparks.com.au/
- **Caravan Industry Association of Australia — Services Listing** (Caravan Industry Association of Australia) — https://www.caravanindustry.com.au/
- **National caravan industry B2B directory** (Industry association / commercial directory) — https://www.caravanindustry.com.au/
- **CMCA dump-point list/map** (Campervan & Motorhome Club of Australia) — https://www.cmca.net.au/
- **Drive Queensland Drive Guide 2025/2026** (Drive Queensland) — https://drivequeensland.com/drive-guide/
- **Visit Barcoo Visitor Guide** (Barcoo Shire Council) — https://www.barcoo.qld.gov.au/council-services/visitor-information-centres
- **Scenic Rim Visitor Guide** (Scenic Rim Regional Council / tourism) — https://www.visitscenicrim.com.au/

## Refresh

```bash
python tools/vanassist_sources/sync_archive.py --refresh-green
```

Assist RIC remains the production acquisition engine (ADR 0033).
This archive is the Platform-side immutable source vault + provenance register.

