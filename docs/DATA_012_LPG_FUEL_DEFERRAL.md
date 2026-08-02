# LPG / fuel — DATA-012 priority 7 deferral

**Date:** 2026-08-02  
**Decision:** **Defer** LPG refill and fuel facility catalogue rows until a
**licensed** Australian source (or provider-category mapping) is approved.

## Why

Priority order in `VANASSIST_PRODUCTION_READINESS_PACKAGE.md` requires LPG/fuel
**only where reliable licensed sources exist**. Scraping unlicensed directories
is prohibited. No licensed CKAN/ArcGIS connector path is wired yet.

## Interim coverage

- Fuel / LPG service demand continues via **provider** categories and structured
  `/find` (unchanged).
- Do **not** invent `traveller_facilities` rows for fuel/LPG without licence +
  attribution.

## Unblock criteria (owner O3)

1. Named licensed dataset or authority feed  
2. Licence + attribution recorded on `government_datasets`  
3. Field mapping reviewed  
4. Catalogue row added **disabled**; Fetch + review-first approve in staging  

Until then: no demo LPG/fuel fixtures and no production enablement.
