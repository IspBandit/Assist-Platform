# Polaris — Data Architecture

- **Status:** Scaffolded (migration `087+` planned/in progress)
- **Date:** 2026-08-01
- **Backlog:** POL-003
- **ADR:** [0003-hybrid-specification-storage.md](DECISIONS/0003-hybrid-specification-storage.md),
  [0004-model-year-versioning.md](DECISIONS/0004-model-year-versioning.md),
  [0011-data-lifecycle.md](DECISIONS/0011-data-lifecycle.md)

---

## Design summary

Polaris uses a **hybrid relational model**: stable entities in normalised tables;
specifications via definition + value pattern (EAV-style) for extensibility without
schema churn per new spec label.

All catalogue tables include:

- `brand_id` scoped to Polaris tenant where applicable
- `created_at`, `updated_at`
- soft delete: `deleted_at` (nullable)
- audit references via platform `AuditLog`

---

## Core entities

### `polaris_manufacturers`

| Column | Notes |
| --- | --- |
| `id`, `slug`, `name` | Unique slug globally |
| `country_code` | Optional ISO |
| `website_url` | Validated URL |
| `logo_media_id` | FK platform media |
| `organisation_id` | Nullable link to claimed org |
| `status` | `draft`, `published`, `archived` |
| `deleted_at` | Soft delete |

### `polaris_model_families`

Logical model line (e.g. “Summit 540”).

| Column | Notes |
| --- | --- |
| `manufacturer_id` | FK |
| `slug`, `name` | Unique per manufacturer |
| `category` | Enum: caravan, hybrid, camper_trailer, motorhome, campervan, slide_on |
| `description` | Text; provenance-linked |
| `status`, `deleted_at` | |

### `polaris_model_years`

Versioning anchor per ADR 0004.

| Column | Notes |
| --- | --- |
| `model_family_id` | FK |
| `year` | Integer model year |
| `is_current` | Boolean; one current per family |
| `launch_date` | Optional |
| `status`, `deleted_at` | |

### `polaris_variants`

Trim/layout variant for a model year.

| Column | Notes |
| --- | --- |
| `model_year_id` | FK |
| `slug`, `name` | Unique per model year |
| `sku_code` | Optional manufacturer code |
| `status`, `deleted_at` | |

---

## Specifications (hybrid)

### `polaris_spec_definitions`

Governed dictionary of spec keys.

| Column | Notes |
| --- | --- |
| `key` | Machine key e.g. `atm_kg` |
| `label` | Display label |
| `group` | dimensions, weights, chassis, interior, mechanical |
| `data_type` | decimal, integer, boolean, text, enum |
| `unit` | kg, m, l, etc. |
| `enum_values` | JSON nullable |
| `is_required_for_match` | Boolean for scoring |
| `sort_order` | |

### `polaris_spec_values`

| Column | Notes |
| --- | --- |
| `variant_id` | FK |
| `spec_definition_id` | FK |
| `value_text` | Normalised string storage |
| `value_numeric` | Optional indexed numeric |
| `source_id` | FK provenance |
| `confidence` | verified, imported, inferred, unknown |
| `deleted_at` | |

Unique constraint: `(variant_id, spec_definition_id)` active rows.

---

## Floorplans and media

### `polaris_floorplans`

| Column | Notes |
| --- | --- |
| `variant_id` | FK |
| `name`, `sort_order` | |
| `media_id` | FK |
| `source_id` | FK |
| `deleted_at` | |

---

## Pricing

### `polaris_prices`

| Column | Notes |
| --- | --- |
| `variant_id` | FK |
| `price_type` | rrp, drive_away, indicative |
| `amount_cents` | Integer AUD |
| `effective_from` | Date |
| `source_id` | FK |
| `deleted_at` | |

Display always shows `effective_from` and disclaimer.

---

## Provenance

### `polaris_sources`

| Column | Notes |
| --- | --- |
| `source_type` | manufacturer_site, brochure, admin_manual, ai_extraction |
| `uri` | Nullable |
| `retrieved_at` | |
| `notes` | |
| `deleted_at` | |

See ADR [0010-source-provenance.md](DECISIONS/0010-source-provenance.md).

---

## Import drafts (Phase 6)

### `polaris_import_drafts`

Staging before publish; JSON payload + review state; never public until approved.

---

## Indexing strategy

- List filters: `(category, status)`, `(manufacturer_id, status)`, numeric specs via
  `value_numeric` join on hot keys (`atm_kg`, `length_m`, `berths`).
- Full-text on manufacturer + model names: MariaDB FULLTEXT or prefix index (Phase 2).
- No separate search engine at launch (ADR 0009).

---

## Lifecycle

| Action | Behaviour |
| --- | --- |
| Create | Admin or approved import |
| Update | New spec values versioned by source; price rows append-only |
| Soft delete | Sets `deleted_at`; hidden from public |
| Restore | Clear `deleted_at` via admin (OPS-011 alignment) |
| Purge | Hard delete admin-only; audit required |

---

## Boundaries (no tables here)

- Tow vehicles → TowSmart schema
- Service providers → VanAssist schema
- Users / orgs → platform schema

---

## Implementation status

| Component | Status |
| --- | --- |
| Entity tables migration | Scaffolded |
| Spec definition seeds | Scaffolded |
| Demo seed manufacturers/models | Scaffolded (non-production) |
| Import draft tables | Planned (Phase 6) |
| Duplicate merge audit | Planned (Phase 6) |

---

## Related documents

- [DATA_ACQUISITION.md](DATA_ACQUISITION.md)
- [SEARCH_ARCHITECTURE.md](SEARCH_ARCHITECTURE.md)
- [DECISIONS/0012-duplicate-merging.md](DECISIONS/0012-duplicate-merging.md)
