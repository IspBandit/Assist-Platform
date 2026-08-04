# RIC Dataset Availability

How Assist RIC consumes the VanAssist dataset acquisition pack.

## Principle

- **Assist Platform** `government_datasets` remains the production system of record (ADR 0033 / DATA-011A).
- **Assist RIC** holds a local working catalogue (`dataset_catalogue` SQLite) for acquisition and staging.
- The portable pack under `data_catalogue/` is the offline acquisition index + raw staging store.

## What RIC can do today

| Capability | Where |
| --- | --- |
| List sources | Dataset Catalogue UI + `dataset_catalogue` table |
| Filter by category / cost / jurisdiction | Dataset Catalogue page filters + `DatasetCatalogueService.list_datasets` |
| Open source details | Dataset Catalogue row detail |
| Locate downloaded files | `data_catalogue/raw/<source_key>/` + catalogue `local_raw_path` |
| See licence / attribution | Catalogue fields + per-raw `licence.txt` / `attribution.txt` |
| See update status | `scripts/check_dataset_updates.py` → `data_catalogue/update_check.json`; Toilet Map connector `check_availability` |
| Trigger supported download/check | Dataset Catalogue acquire actions (connectors) + CLI scripts above |
| Optional SoR refresh | Sources → Refresh dataset catalogue → `GET /api/v1/admin/datasets` |

## Import path into RIC

1. Seed / refresh local catalogue (`catalogue_seed.default_catalogue_entries()` and/or Admin API pull).
2. Prefer fixture or `data_catalogue/raw` pack for demos.
3. Acquire → parse → normalise → stage (`staged_dataset_records`).
4. Human review in Staging Review.
5. Build package → `POST /api/v1/admin/imports` (never direct MariaDB).

## Portable pack relationship

| Pack artefact | RIC use |
| --- | --- |
| `data_catalogue/catalogue.json` | Operator index; tests; documentation |
| `data_catalogue/catalogue.csv` | Spreadsheet review |
| `data_catalogue/raw/*` | Offline acquisition inputs |
| `data_catalogue/samples/batehaven_*` | Acceptance evidence |

Do **not** invent a second catalogue subsystem. The pack mirrors and extends the existing Dataset Catalogue; Platform remains authoritative for production publish decisions.

## Paid sources

Paid entries stay `enabled=false` with hard caps. See `PAID_DATA_SOURCES.md`. Owner signup + keyring storage required before enablement.

## Minimum demo path (offline)

1. `python scripts/download_datasets.py` (or use committed fixtures under `data/sources/`).
2. Launch Assist RIC → Dataset Catalogue.
3. Acquire `au_national_public_toilet_map` (fixture or raw pack).
4. Confirm Batehaven toilets + Corrigans Beach dump point with licence fields present.
