# Traveller facilities

**Status:** AI-6 entity shipped behind `assist_ai_traveller_facilities` (default **off**).  
**Authoritative entity decision:** [ADR 0032](DECISIONS/0032-stays-vs-traveller-facilities.md).  
**AI reinforcement:** [ADR 0030](DECISIONS/0030-traveller-facilities-ai-boundary.md).  
**Ingest:** [DATA-012](DATA_012.md).  
**Backlog:** DATA-012, DATA-014, VAN-001.  
**Admin API Phase 1:** still advertises `traveller_facilities: planned` for OpenAPI inventory; Ask uses the table when the flag is on.

## Do not

- Store standalone toilets, dump points, drinking taps, etc. in `caravan_parks`.
- Auto-publish unverified rows to Ask (adapter requires `status=active` and `verification_status` in `reviewed|verified`).
- Treat amenity flags on parks as a national facility search index.

## Taxonomy (facility type keys)

`public_toilet`, `dump_point`, `drinking_water`, `public_shower`, `laundry`,
`rest_area`, `visitor_information`, `fuel`, `lpg_refill`, `hospital`,
`medical_centre`, `pharmacy`, `emergency_services`, `boat_ramp`, `picnic_area`,
`barbecue`, `waste_disposal`, `ev_charging`, `weighbridge`, `other_essential`.

## Entity (migration `092`)

Canonical ID, facility type, name/slug, coordinates, address/locality, operating
status, opening hours, accessibility notes, source provenance and licence,
attribution, confidence, verification, status lifecycle, optional brand scope,
optional `linked_provider_id`, archive via soft delete.

Providers remain businesses. Stays remain accommodation. Facilities remain point
amenities. Canonical links (DATA-014) join places that are both.

## Ask VanAssist

Flag `assist_ai_traveller_facilities` gates the adapter. Dump/water rules still
fall back to provider categories when useful. Toilet queries use facilities only
(no park invention).

Bootstrap demo rows (non-production):

```bash
php scripts/import-demo-traveller-facilities.php --approve
```
