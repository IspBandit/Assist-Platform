# Dataset Licensing and Attribution

RIC must preserve licence and attribution on every staged record and every Admin
API package item.

## General rules

1. Do not guess licence terms. Cite the official portal page.
2. Keep raw source payload or source reference with the staged row.
3. Refuse auto-publish for ambiguous or prohibited licences.
4. OpenStreetMap requires ODbL attribution and Share-Alike observance.
5. Paid vendor terms may restrict caching/display — review before enablement.

## Cited sources (initial)

### National Public Toilet Map

- Portal: https://data.gov.au/data/dataset/national-public-toilet-map
- Resource: https://data.gov.au/data/dataset/national-public-toilet-map/resource/34076296-6692-4e30-b627-67b7c4eb1027
- Licence field on data.gov.au: **Creative Commons Attribution 3.0 Australia**
- Additional portal terms apply to Database access (see dataset page Terms).
- Website copyright note: https://toiletmap.gov.au/copyright
- Suggested attribution:
  `© Commonwealth of Australia — National Public Toilet Map (data.gov.au)`

### OpenStreetMap

- https://www.openstreetmap.org/copyright
- Licence: ODbL 1.0
- Attribution: `© OpenStreetMap contributors`

### State / council portals

Licence is **per dataset**. Portal entries in the RIC catalogue use placeholder
text until a child dataset is enabled with a cited licence URL in Notes.

### Google Places / other paid APIs

Follow the vendor Maps/Search platform terms linked from
`PAID_DATA_SOURCES.md`. Do not treat paid results as open data.

## Staging fields

| Field | Purpose |
| --- | --- |
| `licence` | Licence short name |
| `attribution` | Required attribution text |
| `raw_payload` | Source row/payload |
| `record_hash` / `normalised_hash` | Integrity |
| `retrieved_at` / `last_checked_at` | Freshness |
