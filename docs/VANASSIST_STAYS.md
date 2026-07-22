# VanAssist stays directory

`/stays` is the location-aware directory for caravan parks, campgrounds, showgrounds, farm stays and free or low-cost overnight locations.

## Trust labels

- **Authority confirmed**: imported from a council or road-authority source URL ending in `.gov.au`.
- **Operator verified**: a listing claim was reviewed and approved, or the operator maintains the Park Partner profile.
- **Community sourced**: imported from OpenStreetMap under ODbL; travellers must confirm details before arrival.
- **Unverified**: no reliable verification evidence is stored yet.

Never convert community data to authority/operator verified without preserving evidence and its source URL.

## Refreshing community data

1. Run `node tools/osm-stays-import.js`.
2. Review `database/seeds/stays_osm.json` and its source metadata.
3. Apply migration 040.
4. Run `php scripts/seed-stays.php`.
5. Check a sample in every state and run public render tests.

The import is idempotent through `(source_type, external_id)`.

## Council data

Import an authorised CSV with:

`php scripts/import-authority-stays.php council-stays.csv`

Required columns are `external_id,name,town,state,source_url`; supported optional columns are `latitude,longitude,address,website,stay_type,price_type`. The importer refuses authority status unless `source_url` is a `.gov.au` host.

## Claims and monetisation

Unmanaged public listings show a claim action. Admin approval links the claimant to the existing Park Partner dashboard, preventing duplicates. Plans are `free`, `verified`, `premium` and `featured`; featured placements must always display the **Sponsored** badge. Payment activation remains controlled by the platform billing configuration.
