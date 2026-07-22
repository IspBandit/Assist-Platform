# TowSmart catalogue gap audit

Updated: 23 July 2026

## Current catalogue

- 179 tow-vehicle reference records after adding separate Prado model years plus current Tasman, Shark 6, JAC T9, Terron 9 and Cannon Alpha variants.
- 3,769 caravan, camper, hybrid and trailer reference records recovered from the original TowWise application.
- Catalogue values are starting points only. Users must confirm the exact vehicle plate, handbook and manufacturer documentation.

## Gaps found

### Critical data-quality gaps

1. Most vehicle records combine many model years and grades into one representative specification. GVM, kerb mass, GCM, axle limits and towing limits can vary by engine, body, transmission, seating and grade.
2. Most recovered records do not include a source URL, publication date, market, variant code or date last verified.
3. Array-position IDs are stable only while new records are appended. The catalogue needs permanent string IDs before records can safely be reordered or removed.
4. Several entries are grey-import or overseas-only vehicles rather than confirmed Australian-delivered variants. They need an explicit market/approval label.
5. The catalogue does not yet distinguish factory specifications from post-registration GVM/GCM upgrades.

### Current Australian range gaps

The next manufacturer-verification pass should prioritise current high-interest tow vehicles and every Australian grade/body configuration, including:

- BYD Shark 6 2026 Performance and cab-chassis variants once final Australian manufacturer specifications are published;
- Ford Ranger PHEV and current Ranger/Everest grade-specific payloads;
- current GWM Cannon variants;
- Isuzu D-MAX and MU-X current grade/body configurations;
- newer JAC T9 cab-chassis and Osprey variants;
- Kia Tasman cab-chassis variants by tray configuration;
- KGM Musso and Rexton naming/current variants;
- current LDV T60/D90 variants;
- Mazda BT-50 current grade/body configurations;
- Mitsubishi Triton MV and current Pajero Sport variants;
- Nissan Navara current grade/body configurations;
- Toyota HiLux current grade/body configurations, LandCruiser 70/300 grades and Tundra;
- Volkswagen Amarok current grades.

This list identifies the current-priority families, not permission to invent specifications. Each variant must be imported only from an Australian manufacturer specification, approval record or other traceable authoritative source.

### Caravan and trailer gaps

1. Many models lack a model year, floorplan/variant identifier or source publication date.
2. Advertised tare, ATM and ball mass may vary with options and production changes.
3. Duplicate names across years can be difficult to distinguish in search results.
4. Axle configuration, GTM, dimensions, water capacity and payload are incomplete for some records.
5. Current models need an ongoing manufacturer refresh rather than a one-time bulk import.

## Required catalogue upgrade

Each future record should carry:

- permanent catalogue ID;
- Australian make, model, series, grade, body and model-year range;
- engine, transmission, drivetrain and seating where they affect mass;
- kerb/tare mass, GVM, GCM, braked and unbraked towing capacity;
- maximum tow-ball download and front/rear axle limits when published;
- calculated payload with an explicit derivation;
- Australian market/approval status;
- source organisation, source URL, source document version and verified date;
- factory, upgraded or user-entered specification status;
- superseded/current status and review due date.

## Safe implementation order

1. Introduce permanent IDs and provenance fields without changing existing array-position lookups.
2. Add an admin catalogue-review queue and source-expiry reporting.
3. Replace representative current-model entries with grade/body records, appending records to preserve old IDs.
4. Flag overseas/grey-import entries clearly.
5. Add duplicate detection and manufacturer refresh checks.
6. Seek licensed/manufacturer data for a genuinely comprehensive historical catalogue.

No record should be described as certified. TowSmart calculations remain informational and must continue to direct users to the vehicle plate, handbook, manufacturer and a suitably qualified professional.
