# Data trust and provenance

Assist must never turn approximate seed data into a precise public claim. Public
records retain their source, licence, confidence and freshness. Missing phone,
email, hours or facilities remain missing until a traceable source supplies them.

## Confidence levels

- **Authoritative** — current Commonwealth, state or territory government register/feed, exact identity match.
- **Statistical** — ABS ASGS locality approximation, exact state/name match; suitable for search/radius estimates but not legal-boundary claims.
- **Unverified** — postcode, open-data or public-source seed that has not passed an authoritative reconciliation. It may resolve a name but cannot generate a precise distance claim.
- **Claimed** — a provider controls its listing. Claimed provider changes are never overwritten by an import; verification of provider-supplied claims is a separate process.

Exact matches are required. Equal-priority duplicates are quarantined. Fuzzy
matching may suggest a review candidate but must never publish or overwrite a
record automatically.

## Current location programme

- Queensland: QLD Place Names Gazetteer (CC BY 4.0). 3,181 of 3,832 seed names matched authoritatively; one ambiguous and 650 unmatched delivery labels are held for review.
- NSW: Geographical Name Register (CC BY), 4,360 accepted; 202 quarantined and 525 unmatched.
- Victoria: VICNAMES (CC BY 4.0), 2,932 accepted; 18 ambiguous and 377 unmatched.
- South Australia: State Gazetteer (CC BY 3.0 AU), 1,891 accepted; 141 unmatched.
- Tasmania: LIST Place Names (CC BY 3.0 AU), 768 accepted; 36 unmatched.
- Western Australia: Composite Gazetteer WA authority records (CC BY 4.0), 1,720 accepted; 80 unmatched.
- Northern Territory: Composite Gazetteer NT authority records (CC BY 4.0), 239 accepted including restored canonical Darwin; 55 ambiguous and 78 unmatched.
- ACT: ACT Government divisions plus Composite Gazetteer (CC BY 4.0), 125 accepted; two quarantined and 37 unmatched.
- National fallback: ABS ASGS Edition 3 Suburbs and Localities 2021. Exact matches receive statistical confidence.
- State-specific reports in `database/seeds/*_coordinate_quality_report.json` record matched, ambiguous and unmatched names.
- A separate Landgate WA audit remains non-production because its default licence is non-commercial. Production uses the lawful Geoscience Australia composite-gazetteer records supplied by the WA naming authority.

The current pack contains 15,216 authoritative, 155 statistical and 2,047
unverified location records. Unverified names remain searchable for exact/local
coverage but are excluded from GPS resolution, radius calculations and precise
distance claims.

Coordinates are activated from the versioned national seed by fingerprint after
migrations. Provider imports resolve exact source town/state before any nearest-
town fallback. Radius and distance calculations reject unverified town centroids.

## Queensland fuel

Queensland Government Fuel Price Reporting is the canonical source for current
unclaimed retail fuel seeds in Queensland (CC BY 4.0). The June 2026 feed contains
1,753 current reporting sites across 762 towns/suburbs, including eight in Emerald.
The feed supplies identity, address, coordinates and reported fuel types; it does
not supply phone, email or opening hours. Those fields remain empty unless an
official operator page is recorded separately.

On activation, current `qld-fuel-reporting` records replace older unclaimed QLD
GA/OSM fuel seeds. Claimed providers and unrelated service assignments are never
changed. A missing station is held for review rather than silently deleted from a
claimed listing.

## Operating rules

1. Retain source URL, licence, external ID and last-seen date.
2. Refresh scheduled feeds and alert on failures, count swings and stale records.
3. Quarantine ambiguous identity/location matches.
4. Never infer contact details, opening hours, facilities or legal status.
5. Preserve claimed-provider control while keeping public-source evidence distinct.
6. Show users when information is government-sourced, provider-confirmed or still unverified.
7. Keep reproducible import scripts and committed quality reports with each release.
