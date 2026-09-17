# CPAQ 2026 directory import (DATA-001 / VAN-001)

Authorised import of the Caravan Parks Association of Queensland (CPAQ)
*Explore Queensland Caravan Parks Directory 2026* into VanAssist stays and
providers. Source URL: https://www.caravanqld.com.au/

This is not operator verification. Imported parks remain `unverified` unless a
stronger verification type already exists. Imported trade businesses are
unclaimed (`is_unclaimed=1`) until a claim is approved.

## Extract

Place the PDF at `storage/imports/cpaq-2026-directory.pdf` (or rely on the
extractor’s Downloads-path fallback), then:

```bash
python tools/cpaq/extract_cpaq_directory.py
```

Outputs under `database/seeds/cpaq-2026/`:

- `parks.json`
- `trade.json`
- `extraction-report.json`
- supporting indexes / residential extracts

## Import

Default mode is a transactional dry run (rolled back). Commit only with
`--apply`:

```bash
php scripts/import-cpaq-2026.php
php scripts/import-cpaq-2026.php --apply
php scripts/import-cpaq-2026.php --parks-only
php scripts/import-cpaq-2026.php --trade-only --apply
```

Implementation: `App\Services\Cpaq2026ImportService`.

### Parks → `caravan_parks`

- Imports tourist / holiday parks (`detail_matched` or
  `listing_kind=caravan_holiday_park`).
- Pure residential without tourist detail is counted as `unresolved` (not an
  overnight stay).
- Idempotent on `(source_type=cpaq, external_id)` and soft-match by normalised
  name + QLD + (postcode OR phone OR website host OR suburb/town).
- Never invents coordinates (no platform geocoder); parks without lat/lng are
  counted under `geocode_skipped` / `geocoding_failures`.
- Facility evidence is written to `stay_facility_claims` with
  `source_type=trusted_import`.
- Never downgrades `operator` / `authority` / `community` verification.

### Trade → `providers`

- Idempotent via `provider_source_records` (`source_key=cpaq-2026`,
  `external_id`).
- Soft-match by slug, phone+name, or website host+name.
- Enriches unclaimed providers only (COALESCE); claimed providers still receive
  a source-record link.
- CPAQ categories map to `service_categories` slugs (`is_inferred=0`).
- **Schema gaps (no service attach):** Finance; Driver Instruction or Training
  (kept in payload only).
- **Weak / documented maps:** Hire → `caravan-and-rv-parts`; sales categories →
  `caravan-and-rv-parts` (no dedicated sales category).
- Ensures an active VanAssist brand listing when the listing is public.

## Provenance constants

| Field | Value |
| --- | --- |
| source_name | CPAQ Explore Queensland Caravan Parks Directory 2026 |
| source_organisation | Caravan Parks Association of Queensland Ltd |
| source_year | 2026 |
| authorised_import | true |
| parks `source_type` | `cpaq` |
| providers `source_key` | `cpaq-2026` |
| source_url | https://www.caravanqld.com.au/ |
| source_note | CPAQ authorised 2026 directory import (…); not shown beyond existing `source_note` behaviour |

## Report counts

The CLI prints JSON including, for parks and trade: `parsed`, `inserted`,
`updated`, `skipped_duplicate`, `unresolved`, `failed`, plus
`multi_category_businesses`, `lacking_websites`, `lacking_addresses`,
`geocoding_failures`, and `schema_fields_unmapped`.
