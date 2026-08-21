# DATA-012 — Government dataset catalogue & facility ingest

**Status:** complete (review-first; demo fixtures; live AU catalogue rows optional)  
**Extends:** DATA-006 connector contract (ADR 0006)  
**Feeds:** `traveller_facilities` (AI-6 / ADR 0032)  
**Does not:** invent a second Admin API surface; write amenities into `caravan_parks`  
**Readiness:** [`VANASSIST_PRODUCTION_READINESS_PACKAGE.md`](VANASSIST_PRODUCTION_READINESS_PACKAGE.md)

## Coverage priority (VanAssist reliability)

1. Public toilets  
2. Dump points  
3. Drinking water  
4. Rest areas  
5. Visitor information centres  
6. Caravan parks / campgrounds via **stays** (`caravan_parks`), not facilities  
7. LPG and fuel only from reliable licensed sources  

Mandatory acceptance:
[`acceptance/VA_ACCEPT_BATEHAVEN_001.md`](acceptance/VA_ACCEPT_BATEHAVEN_001.md).

## What shipped

| Piece | Location |
| --- | --- |
| Migration | `database/migrations/109_government_datasets.sql` |
| Catalogue | `government_datasets` |
| Import jobs / candidates | `traveller_facility_import_*` |
| Connectors | `gov_ckan`, `gov_arcgis`, `gov_csv`, `gov_geojson` |
| Service | `app/Services/GovernmentDatasetService.php` |
| Admin API sync | `POST /api/v1/admin/datasets/{id}/sync` → fetch/fixture + sync_runs (`AdminApiDatasetService`) |
| Admin UI | `/admin/data-sources/datasets`, `/admin/data-sources/facilities/review` |
| Demo fixtures | `resources/datasets/demo-dump-points.geojson`, `demo-public-toilets.csv`, `demo-drinking-water.csv`, `demo-rest-areas.csv`, `demo-visitor-information.csv` |
| CLI bootstrap | `scripts/import-demo-traveller-facilities.php` (toilets, dumps, water, rest, visitor) |
| Acceptance | `scripts/acceptance-batehaven-facilities.php` (`--dry-run` or `--import-approve`) |
| Curated AU | Migration `110` National Toilet Map catalogue rows (off by default) |
| Demo water | Migration `114` + `resources/datasets/demo-drinking-water.csv` |
| Demo rest/visitor | Migration `116` |
| Capped CKAN stage | `scripts/stage-ckan-toilet-map.php` (non-prod; restores `is_enabled=0`) |

The National Public Toilet Map import can be run Queensland-first without
truncating the source file:

```bash
php scripts/stage-ckan-toilet-map.php --state=QLD --limit=25000
```

This stages review candidates for public toilets and, where the authoritative
record explicitly flags them, dump points, drinking water and public showers.
It does not publish or enable public facility search by itself. After Queensland
review, omit `--state` to stage the Australia-wide dataset.
| LPG/fuel | Deferred — [`DATA_012_LPG_FUEL_DEFERRAL.md`](DATA_012_LPG_FUEL_DEFERRAL.md) |

## Operator path (non-production)

```bash
php scripts/migrate.php
php scripts/import-demo-traveller-facilities.php --approve
```

Or via admin:

1. Apply migrations through `109`.
2. Open **Admin → Data sources → Government datasets**.
3. **Import fixture** for demo dump points and/or toilets (or enable a real catalogue row and Fetch).
4. Open **Facility review** → approve candidates.
5. Confirm AiReleaseGate shows `traveller_facilities_populated` ready when enabling the facilities flag.
6. Only then enable `assist_ai_traveller_facilities` (and Ask) intentionally.

## Trust

- Staging requires `trusted_review` or `community_review`.
- `trusted_automatic` cannot be enabled from the UI without a recorded owner decision.
- Approve never runs from Ask VanAssist (ADR 0029).
- Published `source_key` is `gov:{dataset_key}` so shared external IDs (e.g. Toilet
  Map FacilityID) do not collide across toilet vs dump-point catalogue rows.

## Production

Requires Platform Quality Gate + release criteria in `docs/AI_RELEASE_CRITERIA.md` (Ask + facilities + non-empty reviewed rows).
