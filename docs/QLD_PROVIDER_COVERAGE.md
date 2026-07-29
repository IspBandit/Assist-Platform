# Queensland provider coverage matrix

Offline, evidence-backed Queensland locality × service-category coverage for
Assist Platform / LocalTorque. **Does not write to the production database.**

Backlog: **LOC-002** (coverage readiness) with provenance rules from **DATA-001**.

## Purpose

Answer, for every active Queensland locality and every LocalTorque taxonomy
category:

- which providers are based in the town;
- which are nearby candidates only (straight-line, not claimed coverage);
- brand visibility from `categories.json` → `category.brands`;
- which town/category cells have no adequately verified coverage.

Nearby candidates are never converted into confirmed service-area coverage.

## Batches

| ID | Region |
| --- | --- |
| `brisbane-moreton-bay` | Brisbane and Moreton Bay |
| `gold-coast-scenic-rim` | Gold Coast and Scenic Rim |
| `sunshine-coast-noosa` | Sunshine Coast and Noosa |
| `darling-downs-south-west` | Darling Downs and South West |
| `wide-bay-burnett` | Wide Bay–Burnett |
| `central-queensland` | Central Queensland (`cq` + `fitzroy`) |
| `mackay-whitsunday` | Mackay–Whitsunday |
| `townsville-north-queensland` | Townsville and North Queensland |
| `cairns-far-north` | Cairns and Far North Queensland |
| `gulf-cape-remote` | Gulf, Cape York and remote Queensland |

## Run

```bash
node tools/qld-coverage-matrix.js
node tools/qld-coverage-matrix.js --batch brisbane-moreton-bay
node tools/qld-coverage-matrix.js --resume
node tools/qld-coverage-matrix.js --list-batches
php scripts/validate-qld-coverage.php
php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay
./vendor/bin/phpunit --filter QldCoverage
```

## Import dry-run (review queue shape)

Default mode writes artefacts only — **no database writes**:

```bash
php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay
```

Outputs:

- `storage/imports/qld-coverage/dry-run-{batch}.json` — counts and notes
- `storage/imports/qld-coverage/dry-run-{batch}-candidates.jsonl` — review-queue-shaped rows

Rules:

- source = `providers-publishable.json`
- excludes `regulated-missing-licence.json`
- flags Google Places provenance (skipped on `--apply`)
- never sets marketing consent
- `--apply` is local/test only; inserts pending `data_source_import_candidates`, never publishes providers

## Inputs (permitted sources only)

- `database/seeds/towns_national.json` — QLD localities
- `database/seeds/localtorque/categories.json` — taxonomy + brand routing
- `database/seeds/localtorque/providers-publishable.json` — LocalTorque pack (includes QLD fuel reporting)
- `database/seeds/national_import.json` — researched businesses
- `database/seeds/businesses_locality_businesses.json`
- `database/seeds/businesses_osm.json` — ODbL

Google Places is not used by this tool. Phone/email/hours are never invented.
Public emails never imply marketing consent.

Regulated categories held without licence evidence:
`gas-certification`, `roadworthy`, `engineering-certification`,
`compliance-engineering`.

## Outputs

Committed summaries:

- `database/seeds/qld-coverage/coverage-summary.json`
- `database/seeds/qld-coverage/import-summary.json`
- `database/seeds/qld-coverage/checkpoint.json`
- `database/seeds/qld-coverage/by-batch/*.json`
- `database/seeds/qld-coverage/providers-*.json`
- `database/seeds/qld-coverage/zero-coverage.jsonl` — sample of zero cells (full list in `storage/imports/qld-coverage/`)
- `database/seeds/qld-coverage/weak-coverage.jsonl` — sample of weak cells
- quality lists (`missing-phone`, `missing-email`, `regulated-missing-licence`, …)

Large matrix JSONL (gitignored runtime):

- `storage/imports/qld-coverage/matrix/{batch}.jsonl`
- `storage/imports/qld-coverage/matrix/{batch}-report.jsonl`
- `storage/imports/qld-coverage/zero-coverage.jsonl` (full)
- `storage/imports/qld-coverage/weak-coverage.jsonl` (full)

## Admin integration

Admin → Directory → Queensland coverage (read-only):

- filter gap samples by region batch, town, category and coverage status;
- inspect source licence and category-assignment evidence on review candidates;
- list possible duplicates and regulated providers missing licence evidence;
- hand off approve/merge/reject to the existing import review queue.

Do not auto-approve. Regulated categories remain held without licence evidence.

## Quality rules

- Straight-line km only until an authorised routing source exists.
- Franchise websites are brand-level unless site-specific evidence exists.
- `publishable` requires pack publishable flag, confidence ≥ 80, and no review hold.
- Marketing consent is never inferred from a public email.
- Queensland is not “complete” while zero-coverage cells remain; the matrix
  documents gaps honestly rather than inventing coverage.
