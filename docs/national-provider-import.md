# National provider import (unclaimed listings)

Turns the researched, public-source business list into live directory content:
all states/territories, their regions, ~163 towns, and **333 businesses** as
clearly-marked **unclaimed listings**.

## Why you saw an empty site

The research list lived only in the Cursor **canvas** (`*.canvas.tsx`), which is a
planning artifact — it was never in the website database. The site seed also only
defined **Queensland**. So a freshly installed site showed (at most) Queensland
locations and a single demo provider. This import closes that gap.

## How it works

1. `tools/extract-canvas.js` (Node, run locally) reads the canvas and writes a
   clean dataset to `database/seeds/national_import.json` (states, regions,
   businesses). This JSON is committed and deploys with the app.
2. `App\Services\NationalImportSeeder` reads that JSON during seeding and:
   - ensures all 8 states/territories exist (active),
   - ensures every region exists (Queensland regions reuse the existing ones),
   - creates any missing towns (name/region/state) **with postcode + centre
     coordinates** from `database/seeds/town_details.json`, and backfills those
     fields on any existing town row that is still missing them (never
     overwriting values already set),
   - inserts each business as an **unclaimed** provider (`status = 'active'`,
     `is_verified = 0`, `is_unclaimed = 1`), mapping its categories and adding a
     town service area.
3. It runs automatically as part of `Seeder::seedAll()`, and can be re-run alone
   with `php scripts/seed.php --national`.

## Complete national town coverage

The business import only creates towns where a business exists, so coverage was
sparse outside Queensland. To list **every town in every state/territory**:

1. Download the open `australian_postcodes` dataset
   (`https://github.com/matthewproctor/australianpostcodes`, the
   `australian_postcodes.json` file).
2. Generate the seed:
   `node tools/build-national-towns.js --src "<path>/australian_postcodes.json"`.
   This writes `database/seeds/towns_national.json` (~17,400 localities), each
   assigned to the nearest VanAssist region within its state (centroid-based)
   with postcode + coordinates. The file is committed and deploys with the app.
3. `App\Services\NationalTownSeeder` reads it and bulk-creates towns idempotently
   (`INSERT IGNORE` on `(state_id, slug)`; existing towns/edits are never
   changed). Imported localities are `is_active = 1` but `noindex = 1`, so they
   power search/coverage without flooding the sitemap.
4. It runs as part of `Seeder::seedAll()` (fresh installs) and is exposed for
   existing databases at **Admin → Maintenance → "Import all Australian towns"**.

Effect: postcode/town search resolves anywhere in Australia, and every town
surfaces the relevant regional and statewide providers (e.g. roadside clubs).

Idempotent throughout: providers key on `slug`, locations on `(state, slug)`.

## Unclaimed listing behaviour
- Shown in the public directory and on a profile page, badged **“Unclaimed
  listing”**, with a notice that details came from public sources and a
  **“Claim this listing”** link (currently points at the contact page).
- Only contact details the business already publishes are shown (public phone +
  website). Scraped emails are stored privately for your outreach, not shown.
- **Opted out of automated invite emails** (`auto_invite_opt_out = 1`) so the
  auto-matcher never emails a business that has not opted in. They remain
  manually invitable from the matching console. To let auto-matching email them,
  clear that flag (e.g. `UPDATE providers SET auto_invite_opt_out = 0 WHERE
  is_unclaimed = 1;`) — consider the Spam Act before doing so.

## Matches vs possible matches (trade expansion)

The research records each business's broad **trade** (caravan, auto-electrical,
mechanical, trailer, **plumber, gas-fitter, roadside, roadworthy**), but the site
lists ~29 specific services. So each business is linked to:

- its trade's **headline service** as a **direct match** (`provider_services.is_inferred = 0`), and
- every service that trade plausibly covers as a **possible match**
  (`is_inferred = 1`), e.g. a caravan business is a possible match for brakes,
  bearings, awnings, leaks, gas, solar, etc.

The trade→service map lives in `NationalImportSeeder::TRADE_PRIMARY` /
`TRADE_RELATED` (edit there to widen/narrow what each trade is a possible match
for). The importer self-seeds any referenced service category that is missing, so
new trades work even on an already-seeded database (no full reseed needed).

**Roadside assistance** is genuinely statewide, so those listings are given a
**state-level service area** rather than a single town. `Provider::inTown()` and
`forCategory()` honour region- and state-level service areas, so a state's
roadside club surfaces for every town in that state. The 8 state/territory clubs
(RACQ, NRMA NSW + ACT, RACV, RAA, RAC, RACT, AANT) all use **13 11 11** for 24/7
roadside. **Roadworthy / safety-certificate** stations (mobile and workshop) are
listed as normal town/region businesses. Possible matches are shown publicly, clearly labelled **“May offer this
service”** / “May also offer this service”, with a note to confirm before booking.
They appear on:

- **Service pages** (`/services/{slug}`) — direct matches first, then possible
  matches. Supports a **town/area filter** (`?town=<id>`, e.g. “Brakes & bearings
  in Gympie”) that matches providers based in *or* serving the selected town.
- **Town pages** (`/towns/{slug}`) — businesses based in or serving the town.
- **Region pages** (`/regions/{slug}`) — businesses based in the region.

The request **auto-matcher** also uses this: `MatchingService` scores a direct
(explicit) category match higher (+50) than a possible/inferred one (+25), so it
widens the net when no exact provider exists while still ranking exact matches
first. Auto-invite emails continue to respect `auto_invite_opt_out`, so imported
unclaimed businesses appear as suggestions but are not auto-emailed until opted in.

To backfill possible matches on an already-seeded database, apply migrations
`016`/`017` then re-run the import. Two ways:

- **No CLI (recommended):** sign in as super-administrator → **Admin → System →
  Maintenance** → “Apply database updates”, then “Run national import / backfill
  matches”.
- **CLI:** `php scripts/migrate.php` then `php scripts/seed.php --national`.

## Free national provider coverage (OpenStreetMap)

The researched/canvas list is caravan-*specialist* and small. To put **real**
vehicle/caravan/trade businesses in towns across **every state** at no cost (no API
key), `tools/osm-import.js` pulls them from **OpenStreetMap** via the public
**Overpass API** and writes `database/seeds/businesses_osm.json` (~4,100 businesses).

How it works:
1. One Overpass query **per state** (8 total, not one per town) selects relevant
   features: `shop=car_repair|tyres|caravan|trailer`, `craft=caravan|plumber|welder|metal_construction`,
   `amenity=vehicle_inspection`, and `service:vehicle:*` tags (repair, tyres, brakes,
   electrical, air_conditioning, caravan/motorhome repair, etc.). Metro/regional
   radius passes also run name matches (mobile mechanic, gas fitter, trailer repair, …).
2. Each business is mapped to VanAssist trade buckets (mechanical, caravan,
   autoelec, trailer, plumber, gasfitter, roadworthy, roadside) by tag + name heuristics,
   then assigned to the **nearest known town** (and that town's region) from
   `towns_national.json`, within a distance cap (`--max-km`, default 60).
3. Listings are deduped against `national_import.json` (by phone, website host and
   name+town) and given a stable id (`osm-<type><id>`) so re-imports only fill gaps.

**Coverage report:** after import, see how many towns have local vs serving providers:
```bash
php scripts/coverage-report.php
php scripts/coverage-report.php --thin 200   # gap-fill queue
```
Also shown on **Admin → Maintenance → Town coverage**.

Generate / refresh:
```bash
node tools/osm-import.js                 # all states (writes businesses_osm.json)
node tools/osm-import.js --states QLD,NSW # subset
node tools/osm-import.js --dry-run        # report only
node tools/osm-import.js --from-cache     # reprocess cached Overpass responses
```

Import into the live database (idempotent, batched so it never times out):
**Admin → Maintenance → "Import all provider data (auto)"** (preferred), or
**"Import OpenStreetMap businesses"**. Batches continue automatically in the
browser until complete. With the `import_osm` cron enabled, deploying a new
`businesses_osm.json` is enough — cron loads it without clicks. CLI:
`php scripts/seed.php --osm` (or `--providers` for the full pipeline).
`App\Services\NationalImportSeeder::seedOsm($offset, $limit)` / `ProviderImportRunner`
backs it, reusing the exact unclaimed-listing path (provider + services + town/area).
Only contact details OSM publishes are stored; listings are badged unclaimed and
prompt users to confirm before booking (OSM data is community-maintained and may be
incomplete). Source: © OpenStreetMap contributors (ODbL).

It is intentionally **not** part of `seedAll()`, so fresh installs stay fast — run
it from Maintenance after install.

## Locality-provider research (Excel matrix)

The three `VanAssist_Locality_Providers_*.xlsx` workbooks map every researched
locality to up to six core service roles (caravan, mechanical, autoelec, roadworthy,
roadside), with provider name, base town, coverage band, phone, email, website
and source URL per cell.

Convert to JSON (requires `pandas` and `openpyxl`):

```bash
pip install pandas openpyxl
python tools/excel-locality-import.py
# or pass explicit paths:
python tools/excel-locality-import.py --file "F:/VanAssist_Locality_Providers_1_NSW_ACT.xlsx" ...
```

Writes split seed files under `database/seeds/`:
`businesses_locality.meta.json`, `businesses_locality_businesses.json`, and
`businesses_locality_coverage.jsonl` (~1,080 businesses, ~92k assignments).
The split format keeps PHP memory low on shared hosting. Each coverage row
carries G-NAF coordinates so towns are created even when absent from
`towns_national.json`.

Import into the live database (idempotent, batched):
**Admin → Maintenance → "Import locality-provider research"**.
`App\Services\NationalImportSeeder::seedLocality($offset, $limit)` creates
unclaimed listings keyed by a stable business id (name + phone/website), links
the relevant trade per row, and records a town service area (or statewide when the
coverage band says so). Like OSM, this is not part of `seedAll()`.

## Going deeper per town (Google Places)

The original canvas was caravan-*specialist* only, so most towns had just a
handful of listings. `tools/places-import.js` deepens coverage with **real**,
caravan-relevant trades (caravan/RV repairers, mobile mechanics, auto
electricians, mobile gas/plumbing, cooling) pulled from the **Google Places API
(New)** and appended to `national_import.json` as the same kind of unclaimed
listing. Only contact details Google publishes are stored, and everything is
deduped against what's already imported (by name+town, phone, and website host).

Setup (one-off): in Google Cloud, enable **Places API (New)**, create an API key
restricted to it, then `set GOOGLE_PLACES_API_KEY=...` (PowerShell:
`$env:GOOGLE_PLACES_API_KEY="..."`).

```bash
# preview (writes nothing) – start small:
node tools/places-import.js --launch --dry-run
# the 12 launch towns (Central QLD / Wide Bay):
node tools/places-import.js --launch --write
# a whole state, or specific towns:
node tools/places-import.js --state QLD --dry-run
node tools/places-import.js --town "Cairns" --town "Mackay" --write
# then deploy the updated JSON and re-run the import on the host:
php scripts/seed.php --national      # (or Admin → Maintenance → Run national import)
```

Region per town is taken from the launch list / `--region`, else from towns
already placed, else the nearest region centroid within the same state. Each
write makes a `national_import.json.bak`. **Cost:** Google bills per Text Search
page + contact fields, so begin with `--dry-run` and a narrow scope. Results are
caravan-*biased* but unverified — listings say so and prompt users to confirm
before booking; spot-check before opting any into auto-invites.

## Refreshing the data
After editing the canvas, regenerate and re-import:

```bash
node tools/extract-canvas.js
# deploy the updated database/seeds/national_import.json, then on the host:
php scripts/seed.php --national
```

## Standing the site up (why nothing shows until you do this)
On the cPanel host, from the project root:

```bash
php scripts/migrate.php      # create/upgrade all tables (001..016)
php scripts/seed.php         # roles, locations, categories, national import, content
php scripts/seed.php --demo  # optional: add the labelled demo provider/runs
```

Or use the web installer at `/install` (first run only). After this you'll see all
states, towns and the 333 listings. Set Admin → Settings → Launch mode to a public
mode and enable indexing in Admin → SEO when you're ready for search engines.

## Files
- `tools/extract-canvas.js` — canvas → JSON extractor (dev-only)
- `tools/places-import.js` — Google Places per-town coverage builder (dev-only)
- `database/seeds/national_import.json` — generated dataset (ships with the app)
- `app/Services/NationalImportSeeder.php` — importer
- `app/Services/Seeder.php`, `scripts/seed.php` — wiring (`--national`)
- `database/migrations/016_unclaimed_listings.sql` — `providers` unclaimed columns
- `database/seeds/data.php` — added “Mechanical repairs”, “Trailer and engineering”
- `app/Views/public/provider-profile.php`, `providers-index.php` — unclaimed UI
