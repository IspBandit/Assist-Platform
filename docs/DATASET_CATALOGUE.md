# Dataset Catalogue (Assist Platform + RIC pack)

Assist Platform holds the authoritative production catalogue in
`government_datasets` (DATA-011A / ADR 0033). Assist RIC is the acquisition
engine. The portable pack `data_catalogue/` mirrors verified acquisition
metadata for operators and offline download scripts.

## Platform fields (SoR)

See `docs/DATA_011A.md` for the Admin API / MariaDB field map.

Migration seeds:

- `117_national_dataset_catalogue.sql` — portals + core themes
- `122_national_toilet_map_water_showers.sql` — Toilet Map water/shower flags
- `123_dataset_acquisition_pack.sql` — verified children, remaining themes, paid stubs

All new rows ship **disabled** (`is_enabled=0`, `auto_update_enabled=0`).

## Portable pack fields

`data_catalogue/catalogue.json` records every source with:

Dataset ID, Name, Description, Category, Publisher, Jurisdiction, Geographic
coverage, Source URL, API URL, Download URL, Portal type, Format, Licence,
Attribution requirement, Signup required, API key required, Pricing/free
allowance, Update frequency, Last updated, Bulk download available, Automated
access allowed, Recommended RIC integration method, Priority, Expected user
value, Import difficulty, Trust policy, Enabled state, Notes, Current status,
cost type, connector key, local raw path.

## Availability to RIC

1. Admin API: `GET /api/v1/admin/datasets`
2. RIC local seed + Dataset Catalogue UI
3. Offline pack: `data_catalogue/` + download scripts

Do not invent a parallel MariaDB catalogue table.
