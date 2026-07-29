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

## Dashboard review workflow

After migration `080_national_route_import_review.sql` is deployed:

1. Switch the admin workspace to **VanAssist**.
2. Open **Directory → Import review**.
3. Expand **National caravan-route discovery file** and upload the generated
   `.jsonl.gz` file.
4. Keep the page open while it screens resumable 500-row batches. The process
   survives a refresh and does not publish anything.
5. Filter by state, route hub, suggested service, contact availability,
   evidence status or possible duplicate.
6. Open a candidate, visit the business website or authoritative register,
   correct the service category, record the evidence URL and review note, then
   approve or merge. Google Search/Maps URLs do not qualify as independent
   evidence.
7. Controlled bulk actions can approve independently confirmed, categorised
   records with no duplicate match, or merge exact duplicates into an existing
   unclaimed provider. Exact duplicate merge requires at least 90% confidence,
   the same normalised business name and the same phone or website. Claimed
   providers are excluded. Ineligible records are safely skipped and remain in
   review.
8. Exact duplicates of an existing unclaimed listing in the same workspace are
   linked automatically during import. This only closes the duplicate candidate;
   it does not copy candidate fields over the provider. Ambiguous matches and
   records not yet listed in that workspace remain in review.

Automatic pre-screening holds non-operational businesses, candidates with no
phone or website, and likely retail-only results. All remaining Google candidates
still begin with `evidence required`; a high confidence score is not verification.

All Google-derived candidate details expire after 30 days, regardless of review
status. The screening service also expires abandoned staged files and reports
malformed or failed rows in the job record instead of silently skipping them.
A Place ID may be retained separately as discovery provenance, while public
business details require independent retention evidence or a provider claim.
