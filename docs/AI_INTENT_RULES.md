# Deterministic intent rules (v1)

**Status:** implemented (`IntentRuleEngine`, version id `intent_rules_v1`).  
**Principle:** resolve obvious queries without AI; always enter orchestrator for
logging/cache/knowledge growth. Traveller-facility adapters run when
`assist_ai_traveller_facilities` is on.

## Normalisation

1. Trim, collapse whitespace, lowercase.  
2. Strip punctuation except digits in radii (`50km`, `50 km`).  
3. Expand common AU spellings (tyre/tire → tyre family).  
4. Detect `near me` / `nearby` / `closest` → `use_current_location=true`.  
5. Detect `within N km|kilometres` → `radius_km=N` (clamp 1–500).  
6. Remaining location tokens after pattern removal → `location_text` candidate
   (resolved later via `Town::searchActive` / nearest).

## Rule table (initial VanAssist pack)

| Rule id | Patterns (any) | intent_type | provider_category_keys | facility_type_keys | stay_type_keys | adapters (AI-1) | default confidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| R01 | toilet, toilets, public toilet, loo, restroom | find_traveller_facility | — | public_toilet | — | providers* | 0.85 |
| R02 | dump point, dump points, sanitary dump, cassette dump, sullage | find_traveller_facility | dump-points | dump_point | — | providers | 0.92 |
| R03 | drinking water, potable water, water refill, tank water | find_traveller_facility | potable-water-refill | drinking_water | — | providers | 0.9 |
| R04 | lpg, gas bottle, gas refill, bottle exchange, swap cylinder | find_provider | lpg-refills-and-bottle-exchange | lpg_refill | — | providers | 0.92 |
| R05 | caravan park, caravan parks, holiday park | find_stay | caravan-parks-and-campgrounds | — | caravan_park | stays | 0.9 |
| R06 | free camp, free camping, low cost camp | find_stay | free-and-low-cost-camps | — | free_camp | stays | 0.88 |
| R07 | campground, camping ground | find_stay | — | — | campground | stays | 0.85 |
| R08 | rest area (overnight/stay sense) | find_stay | rest-areas-and-rv-friendly-parking | rest_area | rest_area | stays | 0.75 |
| R09 | mobile caravan repair, mobile rv repair | find_provider | general-caravan-repairs, mobile-mechanics | — | — | providers | 0.88 |
| R10 | auto electrician, auto electrical | find_provider | auto-electrical-and-batteries | — | — | providers | 0.92 |
| R11 | tyre, tyres, tire, puncture | find_provider | tyres-and-wheels | — | — | providers | 0.9 |
| R12 | tow, towing, tow truck, vehicle recovery | find_provider | towing-and-vehicle-recovery | — | — | providers | 0.9 |
| R13 | caravan brake, brakes and bearings, wheel bearing | find_provider | brakes-and-bearings | — | — | providers | 0.9 |
| R14 | mechanic, mobile mechanic | find_provider | mobile-mechanics, mechanical-repairs | — | — | providers | 0.8 |
| R15 | diesel mechanic | find_provider | diesel-mechanics | — | — | providers | 0.9 |
| R16 | ev charger, ev charging | find_provider | ev-charging | ev_charging | — | providers | 0.88 |
| R17 | fuel, petrol, diesel (fuel stop) | find_provider | fuel-and-travel-stops | fuel | — | providers | 0.82 |
| R18 | weighbridge, weigh bridge | find_provider | weighbridges-and-mobile-weighing | weighbridge | — | providers | 0.9 |
| R19 | roadside assistance, breakdown | find_provider | roadside-assistance | — | — | providers | 0.88 |

\* AI-1: facility-only intents fall back to matching provider categories when
present; otherwise clarification or weak provider search + knowledge gap.
`traveller_facilities` adapter is live when `assist_ai_traveller_facilities` is on (AI-6).

## Multi-match / ambiguity

- Multiple distinct rule families → `intent_type=mixed`, union keys, confidence
  min(rule confidences) − 0.1.  
- No rule hit → `unknown`, confidence 0 → AI (if enabled) or clarification.  
- Conflicting stay vs facility “rest area” → prefer stay adapter if words imply
  overnight; else facility/provider parking category; may set
  `clarification_required`.

## Urgency

Patterns: `urgent`, `asap`, `stranded`, `broken down` → `urgency=urgent`
(does not invent providers; may prioritise gap scoring later).

## Golden queries for AI-1 tests

See [`AI_TESTING.md`](AI_TESTING.md). Minimum: each R01–R19 with and without
location / near-me / radius variants.
