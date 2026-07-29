# QLD Places gap-fill — SEQ + Central Queensland ($50 AUD)

## What “apply” means (plain English)

**Apply does not put businesses on the live public website.**

It only inserts rows into the **Admin → Import review queue** so you can
approve, merge, reject or hold each one. Nothing becomes a public provider until
an administrator clicks approve/merge after confirming independent retention
rights (especially for Google Places discovery rows).

This agent machine currently has **no `.env` and no Places API key**, so apply
and Places calls cannot run from here until you provide those locally.

## Your decisions (locked in)

| Setting | Value |
| --- | --- |
| Places budget | **A$50** |
| Priority regions | **SEQ** (Brisbane / Moreton / Gold Coast / Sunshine Coast hubs) then **Central Queensland** (`cq` + `fitzroy`) |
| Apply | **Yes** — review-queue only, local/test DB |

## Where to get the API key

1. Open [Google Cloud Console](https://console.cloud.google.com/)
2. Create or select a project
3. Enable **Places API (New)**
4. Create an API key → restrict to **Places API** and your server IP if possible
5. Set a Google Cloud **budget alert** at A$50
6. On your PC (never commit the key):

```powershell
$env:GOOGLE_PLACES_API_KEY="your-key-here"
```

Also set Admin → Data Sources daily budget to **50** AUD if using the in-app connector.

## Local DB (needed for --apply)

```powershell
copy .env.example .env
# edit DB_* , APP_ENV=local , APP_KEY
composer install
php scripts/migrate.php
php scripts/seed.php
```

## Budget plan (conservative)

Google Text Search (New) with phone/website fields is typically billed at a
**Pro/Enterprise** SKU (~US$32–35 per 1,000 after free monthly allowance). Treat
A$50 as a hard ceiling.

Recommended first run (~hundreds of requests, not thousands):

- **max-pages = 1** (≤20 results per query)
- **~20 hub towns** (not every suburb)
- **8 VanAssist-critical queries** (not all 134 categories)
- Hard stop when estimated spend approaches A$50

Hub towns (SEQ then CQ):

SEQ: Brisbane, Ipswich, Loganholme, Caboolture, Redcliffe, Cleveland, Strathpine,
Beenleigh, Capalaba, Springfield, Gold Coast / Southport, Nerang, Robina,
Maroochydore, Caloundra, Noosa Heads

CQ/Fitzroy: Rockhampton, Yeppoon, Gladstone, Emerald, Biloela, Agnes Water,
Blackwater, Mount Morgan

Queries:

1. caravan repairs  
2. mobile caravan technician  
3. auto electrician caravan  
4. mobile caravan gas fitter  
5. trailer brakes bearings  
6. roadworthy safety certificate  
7. diesel mechanic  
8. towbar fitting  

## Commands (on your machine)

```powershell
# 1) Preview Places spend/shape (no writes)
node tools/qld-places-gap-fill.js --dry-run

# 2) Spend budget; write discovery candidates under storage/imports (not production DB)
node tools/qld-places-gap-fill.js --write

# 3) Shape existing publishable pack into review-queue files (no DB)
php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay
php scripts/qld-coverage-import-dry-run.php --batch central-queensland

# 4) Apply to LOCAL review queue only (APP_ENV=local|test)
php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay --apply
php scripts/qld-coverage-import-dry-run.php --batch central-queensland --apply
```

Then open **Admin → Import review** and process candidates. Places-provenance
rows stay held until you confirm an independent source or claim.

## After you have the key

Reply with:

1. “Key is in `$env:GOOGLE_PLACES_API_KEY` on this machine” (do **not** paste the key), and  
2. “Local `.env` exists with APP_ENV=local”

Then the agent can run the dry-run Places pass and local `--apply` for SEQ + CQ.
