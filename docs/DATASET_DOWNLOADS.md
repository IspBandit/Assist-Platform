# Dataset Downloads

How to acquire and verify local copies for Assist RIC / VanAssist.

## Layout

```text
data_catalogue/
  catalogue.json
  catalogue.csv
  samples/
    batehaven_toilet_map_20km.json
  raw/
    <source_key>/
      <source_file>
      metadata.json
      licence.txt
      attribution.txt
      checksum.sha256
```

Large raw CSVs/PBF files are **gitignored**. Reproduce them with the scripts below.

## Scripts

From Assist RIC (preferred) or Assist Platform repository root:

Assist Platform wrappers live at scripts/download_datasets.py (delegates to scripts/data_catalogue/).

Assist RIC:

```bash
python scripts/download_datasets.py
python scripts/check_dataset_updates.py
python scripts/verify_dataset_checksums.py
```

### Options

| Command | Purpose |
| --- | --- |
| `download_datasets.py --export-only` | Write `catalogue.json` / `catalogue.csv` only |
| `download_datasets.py --force` | Re-download existing raw files |
| `download_datasets.py --include geofabrik_australia` | Also download ~900MB Geofabrik PBF |
| `download_datasets.py --only nsw_rest_areas` | Limit to selected dataset ids |
| `check_dataset_updates.py --offline` | Local presence check only |
| `verify_dataset_checksums.py` | Compare `checksum.sha256` to raw files |

## First-wave downloads (verified 2026-08-04)

| Source key | Approx size | Licence | Notes |
| --- | --- | --- | --- |
| `au_national_public_toilet_map` | ~12 MB | CC BY 3.0 AU | CKAN resource `34076296-6692-4e30-b627-67b7c4eb1027` |
| `nsw_rest_areas` | ~270 KB | CC BY 3.0 AU | TfNSW CSV |
| `nsw_boat_ramps` | ~128 KB | CC BY 3.0 AU | TfNSW CSV |
| `nsw_ev_charging_locations` | ~283 KB | CC BY 3.0 AU | TfNSW CSV |
| `gold_coast_caravan_parks` | ~27 KB | CC BY 3.0 AU | GeoJSON WFS |
| `portal_osm_australia` / Geofabrik | ~912 MB | ODbL 1.0 | Metadata always; PBF optional |

## Batehaven validation sample

After Toilet Map download, `samples/batehaven_toilet_map_20km.json` lists facilities within 20 km of Batehaven NSW with licence/attribution preserved.

Observed from live Toilet Map CSV (2026-08-04 export):

- **44** public toilet facilities within 20 km
- **1** dump-point attribute match: Corrigans Beach, FacilityID `9277` (~0.17 km)

## Do not

- Commit Geofabrik PBF or other bulk files unless repository policy changes
- Store API keys beside downloads
- Alter raw source files after download
- Publish acquired rows to production from this pack

