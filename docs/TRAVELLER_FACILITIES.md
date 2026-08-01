# Traveller facilities

**Status:** design for AI workstream; **production entity not authorised** in
AI-0.  
**Authoritative entity decision:** [ADR 0016](DECISIONS/0016-stays-vs-traveller-facilities.md).  
**AI reinforcement:** ADR 0027 (proposed).  
**Backlog:** DATA-012, DATA-014, VAN-001.  
**Admin API Phase 1:** uses `/stays` for `caravan_parks`; capabilities advertise
`traveller_facilities: planned`.

## Do not

- Store standalone toilets, dump points, drinking taps, etc. in `caravan_parks`.
- Add the traveller-facility migration until DATA-012 / AI-6 is approved.
- Treat amenity flags on parks as a national facility search index.

## Design taxonomy (facility type keys)

`public_toilet`, `dump_point`, `drinking_water`, `public_shower`, `laundry`,
`rest_area`, `visitor_information`, `fuel`, `lpg_refill`, `hospital`,
`medical_centre`, `pharmacy`, `emergency_services`, `boat_ramp`, `picnic_area`,
`barbecue`, `waste_disposal`, `ev_charging`, `weighbridge`, `other_essential`.

## Future entity fields (summary)

Canonical ID, facility type, name, coordinates, address/locality, operating
status, opening hours (when sourced), accessibility, source provenance and
licence, attribution, last checked, verification, confidence, brand visibility,
archive/recycle lifecycle, duplicate relationships.

Providers remain businesses. Stays remain accommodation. Facilities remain point
amenities. Canonical links (DATA-014) join places that are both.
