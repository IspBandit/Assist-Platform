# VanAssist stays directory

`/stays` is the location-aware directory for caravan parks, campgrounds,
national-park camping, showgrounds, permitted rest areas, council camps,
station/farm stays and free or low-cost overnight locations. Hotels and motels
are outside this directory.

An appearance in the directory never means overnight parking is automatically
legal or currently available. Users must see the source, verification level,
access/vehicle limits and a reminder to confirm current rules before arrival.

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

When duplicate stays are merged, the higher-trust authority/operator record
remains canonical and its ID and slug are preserved. Source identities become
aliases so later imports cannot recreate the absorbed listing. Linked claims,
facility evidence and operational records move to the survivor. If the trusted
record has an address but no point coordinate, a usable coordinate and locality
from the absorbed geospatial record are retained so the canonical stay remains
available in radius search. The absorbed row is audit logged and soft-deleted,
not erased.

All location searches use the stay's point coordinate and an unrounded
great-circle boundary comparison. Results describe the value as straight-line;
current road distance is available only after opening Directions.

## Queensland caravan-route discovery

`tools/qld-caravan-stays-gap-fill.js` discovers caravan-suitable overnight
options along likely Queensland touring routes under one hard A$100 total cap.
It searches for caravan parks, campgrounds, national-park camping, showgrounds,
council camps, lawful free/low-cost camps, station/farm stays and RV-accessible
overnight stops. Hotels, motels and ordinary accommodation are excluded.

The output is a private review file only: it must not publish automatically.
Every candidate requires type, access, vehicle limits, current overnight legality,
source rights and duplicate review. Missing phone, website, price, hours or access
details remain unknown rather than being invented.

## Council data

Import an authorised CSV with:

`php scripts/import-authority-stays.php council-stays.csv`

Required columns are `external_id,name,town,state,source_url`; supported optional columns are `latitude,longitude,address,website,stay_type,price_type`. The importer refuses authority status unless `source_url` is a `.gov.au` host.

## Claims and monetisation

Unmanaged public listings show a claim action. Admin approval links the claimant to the existing Park Partner dashboard, preventing duplicates. Plans are `free`, `verified`, `premium` and `featured`; featured placements must always display the **Sponsored** badge. Payment activation remains controlled by the platform billing configuration.
