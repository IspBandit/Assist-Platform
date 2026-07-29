# National caravan-route Places gap fill

`tools/national-route-places-gap-fill.js` performs a review-only Google Places
discovery pass across the principal caravan touring corridors outside Queensland.
Queensland is excluded because its route hubs have a separate completed pass.

## Safety and cost controls

- The absolute estimated spend cap is **A$125 for the complete run**, not per state.
- The current route plan contains 216 hubs and 10 searches per hub: 2,160
  requests at an estimated A$118.80.
- The field mask requests business identity, address, coordinates, status, phone
  and website. It deliberately excludes reviews, photos and atmosphere fields.
- The tool writes review candidates only. It does not update the provider master,
  publish listings, create marketing consent or send provider email.
- Google Places provenance must be retained. Category accuracy, retention rights
  and independent public-source evidence must be reviewed before publication.

## Searches

The pass covers caravan/RV repair, mobile caravan technicians, 12-volt and auto
electrical work, mobile/diesel mechanics, gas and appliance work, trailer brakes
and bearings, tyres, roadside/towing, fuel stations and EV charging.

## Usage

```powershell
# Preview only; makes no API calls.
node tools/national-route-places-gap-fill.js --dry-run --budget-aud 125

# Discovery run. GOOGLE_PLACES_API_KEY must be set locally.
node tools/national-route-places-gap-fill.js --write --budget-aud 125
```

Specifying a value above A$125 does not raise the cap. Output is stored beneath
`storage/imports/national-route-coverage/`, which is local/import-review data and
must not contain API credentials.

## 29 July 2026 run

| Measure | Result |
|---|---:|
| Requests | 2,160 |
| Estimated total cost | A$118.80 |
| Unique candidates | 10,646 |
| With phone | 9,948 |
| With website | 8,154 |
| Operational according to Places | 10,635 |
| Failed requests | 0 |

These figures are discovery results, not verified or publishable provider counts.
