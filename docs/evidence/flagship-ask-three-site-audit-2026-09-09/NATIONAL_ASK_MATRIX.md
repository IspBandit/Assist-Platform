# National Ask matrix

## Result

- Intent cases: 128/128 correct (100%; threshold 95%).
- Qualified database locations: 32/32 correct state and coordinates (100%).
- Duplicate/state disambiguation cases: 5/5 correct (100%).
- Unique minor typo cases: 4/4 corrected to the intended town (100%).
- Reviewed facility provenance: 12/12 complete (100%).
- Wrong-brand reviewed facilities: 0.
- Paid AI calls during deterministic acceptance: 0; estimated cost AUD 0.
- Full Batehaven scenario: PASS.

## Locations

Capitals: Sydney, Melbourne, Brisbane, Perth, Adelaide, Hobart, Darwin and
Canberra.

Regional and remote: Batehaven/Batemans Bay, Dubbo, Broken Hill, Ballarat,
Mildura, Mallacoota, Emerald, Roma, Longreach, Birdsville, Cairns, Port Augusta,
Coober Pedy, Mount Gambier, Albany, Broome, Kununurra, Kalgoorlie, Launceston,
Strahan, Queenstown, Alice Springs, Katherine and Tennant Creek.

## Question families

Each location is exercised with:

- `mobile caravan repair near {place}`;
- `public toilet near {place}`;
- `free camp near {place}`;
- `LPG refill within 50 km of {place}`.

The broader unit matrix also covers batteries, tyres, brakes, refrigeration,
air conditioning, water leaks, gas, towing, mechanics, dump points, drinking
water, showers, laundries, visitor information, medical centres, pharmacies,
boat ramps, powered sites, showgrounds, station stays, national parks,
colloquial terms, missing location, GPS, unsupported intent, injection-shaped
input and degraded AI/provider dependencies.

## Interpretation rules

- State suffixes remain part of the interpreted location.
- A postcode is resolved directly and may retain a preceding state.
- Two exact unqualified town matches produce clarification rather than choosing.
- Fuzzy correction is limited to one clearly best candidate within edit
  distance two, matching first letter and near-equal length.
- An unresolved named place produces no provider, stay or facility fallback.
- Explicit request radius wins over text/default radius and is clamped to
  1–500 km.
- Coordinates outside broad Australian territory bounds are discarded.

## Performance

The 128-case deterministic intent matrix completed in 0.416 seconds in the PHP
8.3 audit container (about 3.3 ms per case including PHPUnit overhead). The
41-case database location matrix completed in 0.613 seconds (about 15 ms per
case including PHPUnit overhead).
