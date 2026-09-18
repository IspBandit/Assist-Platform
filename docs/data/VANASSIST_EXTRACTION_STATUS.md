# VanAssist Industry Source Extraction Status

**Date:** 2026-09-17  
**Backlog:** DATA-012, VAN-001  
**Related:** `docs/data/VANASSIST_INDUSTRY_SOURCE_PERMISSIONS.md`

## Extraction Implementation Status

All 15 industry sources have **permission granted** for data extraction and import.
This document tracks extraction implementation and physical archive status.

## Extraction Methods Available

| Method | Sources | Status |
| --- | --- | --- |
| **Web scraping** | BIG4, CMCA | Implemented (requires HTML selector adjustment) |
| **PDF extraction** | State guides, regional guides | Stub extractors (requires PDF archive) |
| **Web structured data** | CIAA, B2B directory, NT Gov | Requires implementation |

## Implementation Status by Source

### ✅ Web Scrapers Implemented

| Source | Scraper | Status | Notes |
| --- | --- | --- | --- |
| **BIG4 Holiday Guide** | `scrape_big4_web.py` | Ready (needs selector adjustment) | Public directory available |
| **CMCA Dump Points** | `scrape_cmca_dump_points.py` | Ready (may need auth) | Check if members-only; fallback to National Toilet Map |

### 📄 PDF Extractors (Stub - Awaiting Physical Archive)

| Source | Extractor | PDF Status | Priority |
| --- | --- | --- | --- |
| **BIG4 Holiday Guide 2026** | `extract_big4_2026.py` | ❌ Not archived | High (web scraper preferred) |
| NSW CCIA Holiday Guide 2026 | Needs creation | ❌ Not archived | High |
| WA Caravan Guide 2026 | Needs creation | ❌ Not archived | Medium |
| Caravan Tasmania 2026 | Needs creation | ❌ Not archived | Medium |
| SA Parks / Caravan & Camping | Needs creation | ❌ Not archived | Medium |
| Caravanning NT Guide | Needs creation | ❌ Not archived | Medium |
| Caravan & Residential Parks VIC | Needs creation | ❌ Not archived | Medium |
| Drive Queensland 2026 | Needs creation | ✅ Archived | Low (regional guide) |
| Barcoo Visitor Guide | Needs creation | ✅ Archived | Low (regional guide) |
| Scenic Rim Visitor Guide | Needs creation | ❌ Not archived | Low (regional guide) |

### 🌐 Web Structured Data (Not Yet Implemented)

| Source | Method | Status |
| --- | --- | --- |
| G'day Parks Guide | Web scraper or API | Requires implementation |
| CIAA Services Listing | Web scraper | Requires implementation |
| National B2B Directory | Web scraper | Requires implementation |
| NT Government Campgrounds | Web scraper (gov pages) | Requires implementation |

## Physical PDF Archive Requirements

### High Priority PDFs to Obtain

1. **NSW Caravan & Camping Industry Association Holiday Guide 2026**
   - Publisher: Caravan & Camping Industry Association NSW
   - URL: https://www.cciansw.asn.au/
   - Contact method: Request via association website
   - Expected: ~100-200 NSW parks

2. **Caravan & Camping WA Guide 2026**
   - Publisher: Caravan Industry Association Western Australia
   - URL: https://www.caravanwa.com.au/
   - Expected: ~50-100 WA parks

### Medium Priority PDFs

3-7. State association guides (TAS, SA, NT, VIC)
   - See `docs/data/VANASSIST_DATA_SOURCE_REGISTER.md` for URLs
   - Expected: 50-150 parks per state

### Low Priority PDFs (Regional)

8-10. Regional tourism guides (Scenic Rim, etc.)
   - Smaller coverage areas
   - Lower park counts

## Extraction Process

### For Web Sources (BIG4, CMCA)

1. **Adjust selectors:**
   ```bash
   # Inspect actual HTML structure
   curl -s https://www.big4.com.au/caravan-parks | head -200
   
   # Update CSS selectors in scrape_big4_web.py
   # Test with limit flag
   python tools/industry_guides/scrape_big4_web.py --limit=10
   ```

2. **Full scrape:**
   ```bash
   python tools/industry_guides/scrape_big4_web.py
   ```

3. **Import:**
   ```bash
   php scripts/import-industry-guide.php industry_big4_holiday_guide_2026 --apply
   ```

### For PDF Sources (Once Archived)

1. **Archive PDF:**
   ```bash
   # Place PDF in appropriate directory
   cp ~/Downloads/nsw-ccia-guide-2026.pdf \
     data/sources/vanassist/industry/nsw-ccia-holiday-guide-2026.pdf
   ```

2. **Create extractor** (using CPAQ pattern):
   ```bash
   cp tools/cpaq/extract_cpaq_directory.py \
     tools/industry_guides/extract_nsw_ccia_2026.py
   # Adjust parsing logic for NSW guide structure
   ```

3. **Extract:**
   ```bash
   python tools/industry_guides/extract_nsw_ccia_2026.py
   ```

4. **Import:**
   ```bash
   php scripts/import-industry-guide.php industry_nsw_ccia_holiday_guide_2026 --apply
   ```

## Extraction Priority Recommendation

### Immediate (Can Be Done Now)

1. ✅ **BIG4 web scraper** — Adjust selectors and run
   - Public directory available
   - No PDF required
   - High park count (~200+ parks)

2. ✅ **CMCA dump points** — Check if public or members-only
   - If members-only, use National Toilet Map instead (already imported)

### Short Term (Requires PDF Archive)

3. **NSW CCIA Guide** — Obtain and extract
   - High value (NSW is largest state for RV tourism)
   - Expected 100-200 parks

4. **WA Guide** — Obtain and extract
   - Medium-high value (popular RV destination)

### Medium Term

5-8. Other state guides (TAS, SA, NT, VIC)
9-11. Regional guides (Drive QLD, Barcoo, Scenic Rim)

### Lower Priority (Alternative Sources Available)

- CIAA Services: Many providers already in VanAssist via CPAQ or individual claims
- B2B Directory: Primarily for trade, not end-user directory
- G'day Parks: Another franchise like BIG4; lower priority than state guides

## Alternative: Use Existing Data First

Before investing in full extraction of all 15 sources:

1. **Already have:** CPAQ 2026 (~530 QLD parks + trade)
2. **Already have:** 18 GREEN government datasets (~33k facilities)
3. **Can scrape now:** BIG4 (~200+ parks)

This gives comprehensive national coverage. Additional state guides provide:
- More detail for existing parks (cross-source enrichment)
- Parks not yet in CPAQ or government data
- Provider/trade business listings

**Recommendation:** Prioritize BIG4 web scraper → NSW CCIA PDF → assess coverage gaps
before pursuing all remaining sources.

## Related Documentation

- `docs/data/VANASSIST_INDUSTRY_SOURCE_PERMISSIONS.md` — Permission details
- `docs/data/VANASSIST_DEDUPLICATION.md` — Import safety
- `docs/CPAQ_2026_IMPORT.md` — Reference extraction example
- `tools/industry_guides/README.md` — Extraction framework
