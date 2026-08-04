# DATA-011A — National Dataset Catalogue

**Status:** catalogue schema + initial national rows shipped (review-first; no auto-publish)  
**Depends on:** DATA-011 (RIC Admin API sync), DATA-012 (government datasets)  
**ADR:** [0033](DECISIONS/0033-ric-national-dataset-acquisition.md)  
**Rule:** Complete this catalogue before writing additional dataset-specific importers.

## Objective

RIC is the single acquisition engine for trusted datasets. Assist Platform holds
the authoritative catalogue in `government_datasets` and accepts staged packages
only through `/api/v1/admin`.

## Field map

| Requirement | Column / API field |
| --- | --- |
| Dataset Name | `title` (`name` alias on Admin API) |
| Publisher | `publisher` |
| Jurisdiction | `jurisdiction` |
| Source URL | `source_url` |
| API URL | `endpoint_url` (`api_url` alias) |
| Licence | `licence` |
| Attribution | `attribution` |
| Format | `source_format` (+ `fetch_method`) |
| Update Frequency | `update_frequency` |
| Last Checked | `last_checked_at` |
| Last Downloaded | `last_downloaded_at` |
| Last Imported | `last_imported_at` |
| Record Count | `record_count` |
| Trust Level | `trust_policy` (`trust_level` alias) |
| Auto Update Enabled | `auto_update_enabled` (RIC schedule only) |
| Duplicate Rules | `duplicate_rules_json` |
| Entity Types | `record_types_json` (`entity_types` alias) |
| Status | `catalogue_status` |
| Import Mapping | `settings_json` (`import_mapping` alias) |
| Notes | `notes` |

## Initial catalogue keys

| Key | Title |
| --- | --- |
| `au_national_public_toilet_map` | National Public Toilet Map (enriched) |
| `portal_data_gov_au` | data.gov.au |
| `portal_osm_australia` | OpenStreetMap Australia |
| `portal_qld_open_data` | Queensland Open Data |
| `portal_nsw_open_data` | NSW Open Data |
| `portal_vic_open_data` | Victoria Open Data |
| `portal_sa_data_directory` | SA Data Directory |
| `portal_wa_open_data` | WA Open Data |
| `portal_tas_open_data` | Tasmania Open Data |
| `portal_act_open_data` | ACT Open Data |
| `portal_nt_open_data` | NT Open Data |
| `theme_council_open_data_portals` | Council Open Data Portals |
| `theme_visitor_information_centres` | Visitor Information Centres |
| `theme_rest_areas` | Rest Areas |
| `theme_drinking_water` | Drinking Water |
| `theme_dump_points` | Dump Points |
| `theme_caravan_parks` | Caravan Parks (stays) |
| `theme_campgrounds` | Campgrounds (stays) |

Additional keys from migration `123_dataset_acquisition_pack.sql` (all disabled):

| Key | Title |
| --- | --- |
| `portal_transport_nsw` | Transport for NSW Open Data |
| `nsw_rest_areas` | NSW Rest Areas |
| `nsw_boat_ramps` | Maritime NSW Boat Ramps |
| `nsw_ev_charging_locations` | EV Charging Locations (NSW) |
| `gold_coast_caravan_parks` | City of Gold Coast Caravan Parks |
| `sa_rest_areas_state_maintained` | SA Rest Areas — State Maintained |
| `wa_major_rest_areas` | WA Major Rest Areas |
| `theme_public_showers` … `theme_caravan_parts` | Remaining VanAssist facility/provider themes |
| `paid_*` | Paid research API stubs (disabled) |

Portable offline index: `data_catalogue/catalogue.json` (see
`docs/DATASET_DOWNLOADS.md`, `docs/RIC_DATASET_AVAILABILITY.md`).

All seeded rows ship **disabled** (`is_enabled=0`, `auto_update_enabled=0`) except
where an existing DATA-012 demo/indexed Toilet Map row already exists — still
review-first and never auto-publish.

## RIC responsibilities

1. Pull catalogue via `GET /api/v1/admin/datasets`
2. Check updates / detect new or changed catalogue rows
3. Download, validate, normalise, stage locally
4. Duplicate check
5. Push approved packages via Admin API drafts/imports/facilities — **never**
   direct MariaDB

## Platform responsibilities

- Catalogue SoR + Admin API/HTML editing
- Review queues and provenance on published facilities/stays
- Refuse production publish without human review paths already shipped

## Migration

- `database/migrations/117_national_dataset_catalogue.sql`
- `database/migrations/123_dataset_acquisition_pack.sql`

## Non-goals

- New dataset-specific importers in this increment
- Live Overpass from Ask
- Auto-publish to production
- Parallel catalogue tables
