# VanAssist Industry Source Permissions

**Date:** 2026-09-17  
**Backlog:** DATA-012, VAN-001  
**Status:** Permission granted for all 15 industry association and regional guide sources

## Permission Grant

All industry association directories, holiday guides, and regional tourism guides
in the VanAssist source archive have received permission for data extraction and
import into the VanAssist national traveller directory.

### Covered Sources (15)

#### Industry Association Directories (11)

1. **NT Government campground web pages** — Northern Territory Government
2. **2026 NSW Caravan & Camping Parks & Products Holiday Guide** — Caravan & Camping Industry Association NSW
3. **Caravan & Camping WA Guide 2026** — Caravan Industry Association Western Australia
4. **Caravan Tasmania 2026 Guide** — Caravanning Tasmania
5. **SA Parks / Caravan & Camping SA guide** — Caravan & Camping SA / SA Parks
6. **Caravanning NT parks map / visitor guide** — Caravanning NT
7. **Caravan & Residential Parks Victoria accommodation guide** — Caravan Industry Victoria
8. **BIG4 Holiday Guide 2026** — BIG4 Holiday Parks of Australia
9. **G'day Parks National/Digital Guide** — G'day Group
10. **Caravan Industry Association of Australia — Services Listing** — CIAA
11. **National caravan industry B2B directory** — Industry association / commercial directory

#### Regional Tourism Guides (3)

12. **Drive Queensland Drive Guide 2025/2026** — Drive Queensland
13. **Visit Barcoo Visitor Guide** — Barcoo Shire Council
14. **Scenic Rim Visitor Guide** — Scenic Rim Regional Council / tourism

#### Membership-Based Directories (1)

15. **CMCA dump-point list/map** — Campervan & Motorhome Club of Australia

## Scope of Permission

Permission covers:

- **Parks and campgrounds:** Names, locations, contact details, facility descriptions
- **Provider services:** Trade businesses, service providers supporting travellers
- **Dump points and amenities:** Location and access information for traveller facilities
- **Descriptions and feature lists:** Facility descriptions, amenities, and service offerings

## Attribution Requirements

All imported records must carry:

```php
[
    'source_attribution' => '[Guide Name] — [Publisher]. Used with permission.',
    'source_url' => '[Publisher landing page or specific listing URL where available]',
    'source_licence' => 'Permission granted 2026-09-17 for VanAssist directory import',
]
```

## Extraction Status

| Source | Format | Extraction Status | Priority |
| --- | --- | --- | --- |
| BIG4 Holiday Guide 2026 | PDF | Archived, extraction required | High |
| NSW CCIA Holiday Guide 2026 | PDF | Archived, extraction required | High |
| WA Caravan & Camping Guide 2026 | PDF | Awaiting physical archive | High |
| Caravan Tasmania 2026 | PDF | Awaiting physical archive | Medium |
| SA Parks / Caravan & Camping SA | PDF | Awaiting physical archive | Medium |
| Caravanning NT guide | PDF | Awaiting physical archive | Medium |
| Caravan & Residential Parks VIC | PDF | Awaiting physical archive | Medium |
| G'day Parks | Web/PDF | Awaiting physical archive | Medium |
| CIAA Services Listing | Web | Structured extraction required | Medium |
| National B2B directory | Web | Structured extraction required | Low |
| CMCA dump points | Web/PDF | Awaiting physical archive | Medium |
| Drive Queensland 2026 | PDF | Archived, extraction required | Low |
| Barcoo Visitor Guide | PDF | Archived, extraction required | Low |
| Scenic Rim Visitor Guide | PDF | Awaiting physical archive | Low |
| NT Government campgrounds | Web | Structured extraction required | Medium |

## Next Steps

1. **PDF Extraction:** Create extractors for the 4 archived PDFs (BIG4, NSW CCIA, Drive QLD, Barcoo)
2. **Physical Archive:** Obtain and archive the remaining 7 PDF guides
3. **Web Extraction:** Build structured web scrapers for CIAA, B2B directory, CMCA, NT Government
4. **Import Pipeline:** Route extracted data through existing `GovernmentDatasetService` with
   appropriate `source_key` and attribution

## Import Architecture

All industry sources follow the same import path as government datasets:

```
PDF/Web → Extractor → JSON → GovernmentDatasetService.ingestAssistRicRows() →
traveller_facility_import_candidates → Review (if required) → traveller_facilities
```

Deduplication is automatic via source keys (see `docs/data/VANASSIST_DEDUPLICATION.md`).

## Related Documentation

- `docs/data/VANASSIST_DATA_SOURCE_REGISTER.md` — Full source registry
- `docs/data/VANASSIST_DEDUPLICATION.md` — Deduplication guarantees
- `data/sources/vanassist/registry/sources.json` — Machine-readable registry
- `docs/CPAQ_2026_IMPORT.md` — Example PDF extraction and import flow
