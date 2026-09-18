# Industry Guide Extraction Framework

Extraction tools for industry association holiday guides and regional tourism PDFs
that have received VanAssist import permission (2026-09-17).

## Architecture

All extractors follow the same pattern as `tools/cpaq/extract_cpaq_directory.py`:

1. **PDF Input:** Read from `data/sources/vanassist/industry/` or `data/sources/vanassist/regional-guides/`
2. **Structured Extraction:** Parse parks, campgrounds, providers, facilities
3. **JSON Output:** Write to `database/seeds/{source-key}/`
4. **Import:** Route through `GovernmentDatasetService.ingestAssistRicRows()`

## Deduplication Guarantee

The import pipeline guarantees idempotency:
- Same source + external_id always updates the existing facility (never duplicates)
- Cross-source duplicates are detected and linked for review
- See `docs/data/VANASSIST_DEDUPLICATION.md`

## Available Sources

| Source | Method | Script | Status |
| --- | --- | --- | --- |
| **BIG4 Holiday Guide 2026** | Web Scraper | `scrape_big4_web.py` | ✅ Ready (needs selector adjustment) |
| **CMCA Dump Points** | Web Scraper | `scrape_cmca_dump_points.py` | ✅ Ready (check if public) |
| **NT Gov Campgrounds** | Web Scraper | `scrape_nt_gov_campgrounds.py` | ✅ Ready |
| **Drive Queensland 2026** | PDF Extractor | `extract_drive_qld_2026.py` | ✅ Template ready |
| **Barcoo Visitor Guide** | PDF Extractor | `extract_barcoo_guide.py` | ✅ Template ready |
| NSW CCIA Holiday Guide | PDF Extractor | Needs creation | ⏳ Awaiting PDF archive |
| WA Caravan Guide | PDF Extractor | Needs creation | ⏳ Awaiting PDF archive |
| Caravan Tasmania | PDF Extractor | Needs creation | ⏳ Awaiting PDF archive |
| SA Guide | PDF Extractor | Needs creation | ⏳ Awaiting PDF archive |
| VIC Guide | PDF Extractor | Needs creation | ⏳ Awaiting PDF archive |

## Extraction Priority

### 1. BIG4 Holiday Guide 2026 (High Priority)

Major national franchise with hundreds of parks.

**Expected output:**
```python
# database/seeds/industry_big4_2026/parks.json
[
    {
        "external_id": "big4-qld-001",  # Stable ID from guide
        "name": "BIG4 Paradise Beach Holiday Park",
        "formatted_address": "123 Beach Road, Surfers Paradise QLD 4217",
        "locality": "Surfers Paradise",
        "latitude": -28.0023,
        "longitude": 153.4282,
        "facility_type": "caravan_park",
        "source_url": "https://www.big4.com.au/caravan-parks/qld/gold-coast/...",
        "licence": "Permission granted 2026-09-17 for VanAssist directory import",
        "attribution": "BIG4 Holiday Guide 2026 — BIG4 Holiday Parks of Australia. Used with permission.",
        "confidence": 85,
        "raw": { ... }
    }
]
```

**Extractor stub:**
```bash
python tools/industry_guides/extract_big4_2026.py
```

### 2. NSW CCIA Holiday Guide 2026 (High Priority)

NSW state caravan industry directory.

**Extractor stub:**
```bash
python tools/industry_guides/extract_nsw_ccia_2026.py
```

### 3. Regional Guides (Medium Priority)

Drive Queensland, Barcoo, Scenic Rim visitor guides.

**Extractors:**
```bash
python tools/industry_guides/extract_drive_qld_2026.py
python tools/industry_guides/extract_barcoo_guide.py
python tools/industry_guides/extract_scenic_rim_guide.py
```

## Extraction Pattern

```python
#!/usr/bin/env python3
"""Extract parks and providers from [Guide Name] into VanAssist import format."""
from __future__ import annotations

import json
from pathlib import Path
import pymupdf  # pip install pymupdf

ROOT = Path(__file__).resolve().parents[2]
PDF_PATH = ROOT / "data/sources/vanassist/industry/guide-name.pdf"
OUT_DIR = ROOT / "database/seeds/source_key"

def extract_parks(doc: pymupdf.Document) -> list[dict]:
    """Extract park records from PDF."""
    parks = []
    # Parse logic here
    return parks

def main() -> None:
    if not PDF_PATH.exists():
        raise RuntimeError(f"PDF not found: {PDF_PATH}")
    
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
    doc = pymupdf.open(PDF_PATH)
    parks = extract_parks(doc)
    
    with open(OUT_DIR / "parks.json", "w", encoding="utf-8") as f:
        json.dump(parks, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    print(f"✓ Extracted {len(parks)} parks")
    print(f"✓ Output: {OUT_DIR}")

if __name__ == "__main__":
    main()
```

## Import Pattern

Once extracted, import via existing service:

```php
// scripts/import-industry-guide.php
$datasetId = $service->findDatasetByKey('industry_big4_holiday_guide_2026')['id'];
$rows = json_decode(file_get_contents(BASE_PATH . '/database/seeds/industry_big4_2026/parks.json'), true);

$result = $service->ingestAssistRicRows(
    $datasetId,
    $rows,
    $brandId,      // VanAssist brand ID
    $userId,       // Admin user ID
    ['extraction_date' => date('Y-m-d')]
);
```

Or via the existing `import-archived-green-facilities.php` pattern (add industry sources to the pack list).

## Web Extraction (Future)

Sources requiring web scraping:
- CIAA Services Listing (directory web page)
- National B2B directory (web)
- CMCA dump points (web/interactive map)
- NT Government campgrounds (web pages)

These require structured web scrapers rather than PDF extraction.

## Testing Extraction

Before importing to production:

1. **Dry-run extraction:** Check output JSON structure
2. **Validate required fields:** external_id, name, facility_type, attribution
3. **Check coordinates:** Ensure lat/lng are decimal degrees (not DMS)
4. **Test import locally:** Run against local database first
5. **Review duplicates:** Check candidate queue for cross-source matches

## Helper Tools

### Geocoding
Add coordinates to extracted records that don't have them:
```bash
python tools/industry_guides/geocode_facilities.py database/seeds/industry_big4_2026/parks.json
```

Uses Nominatim (OpenStreetMap) with 1 req/sec rate limit. Large datasets take time.

### Validation
Validate extraction quality before import:
```bash
python tools/industry_guides/validate_extraction.py database/seeds/industry_big4_2026/parks.json
```

Checks:
- Required fields present
- Data types correct
- Coordinates in valid range (Australia)
- External IDs unique
- Contact details well-formed
- Ready for import

### Import
Once validated, import via:
```bash
php scripts/import-industry-guide.php {dataset_key} --apply
```

## Related Documentation

- `docs/CPAQ_2026_IMPORT.md` — Full example of PDF extraction → import
- `docs/data/VANASSIST_INDUSTRY_SOURCE_PERMISSIONS.md` — Permission details
- `docs/data/VANASSIST_DEDUPLICATION.md` — Import safety guarantees
- `docs/data/VANASSIST_EXTRACTION_STATUS.md` — Implementation tracking
- `tools/cpaq/extract_cpaq_directory.py` — Reference extractor implementation
