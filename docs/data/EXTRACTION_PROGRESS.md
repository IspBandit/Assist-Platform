# VanAssist Industry Source Extraction Progress

**Last Updated:** 2026-09-17 22:12 UTC  
**Status:** 🟢 Active - Browser agent scraping BIG4 parks

## Real-Time Status

| Source | Method | Status | Progress | Next Action |
| --- | --- | --- | --- | --- |
| **BIG4 Parks** | Web | 🤖 **Scraping now** | 21/312 parks (6.7%) - Full scrape in progress | Await completion → geocode → validate → import |
| **CMCA Dump Points** | Web | 🟡 Queued | Awaiting BIG4 completion | Test if publicly accessible |
| **NT Gov Campgrounds** | Web | 🟡 Queued | Awaiting testing | Run scraper |
| **Drive QLD** | PDF | ⚪ Ready | Template complete | Obtain PDF archive |
| **Barcoo** | PDF | ⚪ Ready | Template complete | Obtain PDF archive |
| NSW CCIA | PDF | ⚪ Planned | High priority | Obtain PDF → create extractor |
| WA Guide | PDF | ⚪ Planned | Medium priority | Obtain PDF → create extractor |
| TAS Guide | PDF | ⚪ Planned | Medium priority | Obtain PDF → create extractor |
| SA Guide | PDF | ⚪ Planned | Medium priority | Obtain PDF → create extractor |
| VIC Guide | PDF | ⚪ Planned | Medium priority | Obtain PDF → create extractor |
| NT Guide | PDF | ⚪ Planned | Medium priority | Obtain PDF → create extractor |

**Legend:**
- 🟢 Complete / Live
- 🤖 Actively running
- 🟡 Ready to run / Queued
- ⚪ Awaiting prerequisites

## Extraction Statistics

### Current Coverage (Before New Imports)
- **CPAQ 2026:** ~530 QLD parks + trade
- **GREEN Sources:** ~33,468 government facilities
- **Total:** ~34,000 facilities

### Expected After Current Wave
- **+BIG4:** ~200 parks (scraping now)
- **+CMCA:** ~50-100 dump points (if accessible)
- **+NT Gov:** ~20-50 campgrounds
- **Projected Total:** ~34,300-34,400 facilities

### Future Wave (PDF Archive Required)
- **NSW CCIA:** ~100-200 NSW parks
- **Other States:** ~300-500 parks total
- **Final Projected:** ~35,000+ facilities

## Tools Implemented

### Extractors/Scrapers (7)
- ✅ `scrape_big4_web.py` — BIG4 web scraper
- ✅ `scrape_cmca_dump_points.py` — CMCA scraper
- ✅ `scrape_nt_gov_campgrounds.py` — NT Gov scraper
- ✅ `extract_drive_qld_2026.py` — Drive QLD PDF
- ✅ `extract_barcoo_guide.py` — Barcoo PDF
- ✅ `extract_big4_2026.py` — BIG4 PDF (backup)
- ✅ `import-industry-guide.php` — Generic importer

### Helper Tools (2)
- ✅ `geocode_facilities.py` — Add coordinates
- ✅ `validate_extraction.py` — Quality checks

## Active Tasks

### Browser Agent (bc-dd4d9d27-815e-5f45-abe5-b6ad5562f0e0)
**Task:** Scrape BIG4 parks directory  
**Started:** 2026-09-17 22:08 UTC  
**Actions:**
1. Navigate to https://www.big4.com.au/caravan-parks
2. Inspect HTML structure
3. Update scraper with correct selectors
4. Run extraction (limit 10 first, then full)
5. Report statistics

**Expected Output:**
- `database/seeds/industry_big4_2026/parks.json`
- `database/seeds/industry_big4_2026/extraction-report.json`

### Next in Queue
1. Geocode BIG4 facilities
2. Validate BIG4 data
3. Test CMCA scraper
4. Test NT Gov scraper

## Quality Metrics

### Target Quality Standards
- **Coordinates:** >80% with lat/lng
- **Contact:** >60% with phone or email
- **Validation:** 100% pass before import
- **Deduplication:** 0 within-source duplicates

### Geocoding Strategy
- Primary: Nominatim (OpenStreetMap)
- Rate limit: 1 request/second
- Fallback: Manual review for high-value facilities
- Expected time: ~200 seconds for 200 facilities

### Validation Checks
- Required fields present
- Data types correct
- Coordinates in Australia bounds
- External IDs unique
- Contact details well-formed

## Deduplication Safety

**Guaranteed by `GovernmentDatasetService`:**
- Source-based identity: `(source_key, source_record_id)` unique
- Re-import updates existing (never creates duplicate)
- Cross-source duplicates detected and linked
- Database constraints enforce uniqueness

**Verified:**
- Code review: Lines 656-659, 710-722
- Unit tests: `tests/Unit/DataSources/GovernmentDatasetServiceTest.php`
- Production tested: CPAQ import (530 parks, 0 duplicates)

## Import Readiness Checklist

### Per Source
- [x] Permission granted and documented
- [x] Extractor/scraper implemented
- [ ] Data extracted (in progress - BIG4)
- [ ] Coordinates geocoded (if needed)
- [ ] Validation passed
- [ ] Dataset registered in `government_datasets`
- [ ] Import tested on staging
- [ ] Production import approved

### BIG4 Current Status
- [x] Permission: ✅ Granted 2026-09-17
- [x] Scraper: ✅ `scrape_big4_web.py`
- [~] Extraction: 🤖 In progress (browser agent)
- [ ] Geocoding: Pending completion
- [ ] Validation: Pending completion
- [ ] Import: Pending validation

## Timeline

**2026-09-17:**
- ✅ 18:00 - Permission granted for 15 sources
- ✅ 19:45 - Registry updated (16 PERMISSION_GRANTED)
- ✅ 20:02 - Ask input UX improved
- ✅ 20:05 - Web scrapers implemented (BIG4, CMCA, NT)
- ✅ 21:00 - PDF extractors implemented (Drive QLD, Barcoo)
- ✅ 21:30 - Helper tools added (geocode, validate)
- 🤖 22:08 - **BIG4 scraper launched (browser agent)**
- ⏳ 22:30 - BIG4 extraction expected complete
- ⏳ 22:45 - BIG4 geocoding + validation
- ⏳ 23:00 - CMCA scraper test
- ⏳ 23:15 - NT Gov scraper test

**2026-09-18 (Expected):**
- Import BIG4 to staging
- Import CMCA (if accessible)
- Import NT Gov
- Request NSW CCIA PDF (high priority)

## Links

- **PR:** https://github.com/IspBandit/Assist-Platform/pull/274
- **Branch:** `cursor/grant-amber-source-permissions-75e9`
- **Backlog:** DATA-012, VAN-001
- **Architecture:** ADR 0034

## Commands Reference

```bash
# Monitor browser agent (if needed)
# Agent ID: bc-dd4d9d27-815e-5f45-abe5-b6ad5562f0e0

# Once extraction complete:
python tools/industry_guides/geocode_facilities.py database/seeds/industry_big4_2026/parks.json
python tools/industry_guides/validate_extraction.py database/seeds/industry_big4_2026/parks.json
php scripts/import-industry-guide.php industry_big4_holiday_guide_2026 --apply
```
