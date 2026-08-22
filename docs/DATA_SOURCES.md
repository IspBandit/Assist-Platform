# Data Sources module

The Data Sources module is the Platform Control Centre entry point for external
provider discovery and enrichment. It is platform-owned, brand-aware and
review-first: a connector can propose records, but it cannot publish them.

## Access and secrets

Only `administrator`, `platform-administrator` and `super-administrator` roles
may enter the module. Fine-grained permissions separate view, configuration,
execution and review. Credentials are encrypted with `SecretCipher` (AES-256-GCM
using `APP_KEY`), displayed only as a last-four hint, omitted from logs, and
decrypted only immediately before a connector request. Rotating `APP_KEY`
requires re-encrypting saved connector credentials.

## Connector contract

Connectors implement `App\Platform\DataSources\ConnectorInterface`. They accept
a generic search request plus credentials/settings and return normalized
candidates. Vendor URLs, request bodies, field masks and response parsing live
inside the connector. Platform services operate only on normalized fields.

To add a connector:

1. Implement `ConnectorInterface` under `app/Platform/DataSources/Connectors`.
2. Register it with `ConnectorRegistry` at composition time.
3. Add a `data_source_connectors` record naming the key and class.
4. Define its credential schema and category mappings in the Control Centre.
5. Add contract, error, quota and normalization tests.

Apple Maps, OpenStreetMap, government datasets, CSV and AI-assisted discovery
must follow this path. Core provider code must not branch on their vendor keys.

## Google Places connector

Google Places uses Text Search (New) and an explicit field mask. Configure a
server-restricted Places API key in the admin screen; never commit it. The daily
request limit and estimated AUD budget are application guardrails, not billing
caps imposed by Google. Configure Google Cloud quotas and budget alerts as the
authoritative protection.

Google Places content is subject to Google Maps Platform terms. The system keeps
review candidates for no more than 30 days and permanently retains the Place ID
as discovery provenance. Before publishing a provider, an administrator must
ensure the listing may be retained from an independent/authoritative source or
the business's claim/consent. Place IDs may be stored and should be refreshed
when stale. Public use must meet Google attribution, privacy and terms duties.

References:

- https://developers.google.com/maps/documentation/places/web-service/text-search
- https://developers.google.com/maps/documentation/places/web-service/policies
- https://developers.google.com/maps/documentation/places/web-service/place-id
- https://developers.google.com/maps/billing-and-pricing/manage-costs

## Workflow

1. Platform Admin saves encrypted credentials and quota/budget guards.
2. Admin maps brand categories to connector-specific search language.
3. Gap Finder runs a focused category/location query.
4. Candidates receive duplicate scores from name, phone and website signals.
5. Admin approves a new unclaimed provider, merges into an existing provider,
   or rejects it.
6. Approval creates one canonical provider, one brand listing, its brand-category
   assignment and connector provenance. The existing provider claim workflow
   then handles ownership and verification.
7. Every configuration, import and review decision is written to the audit log.

### National route review

The VanAssist import-review screen accepts the budget-capped national caravan
route discovery pack as JSONL or compressed JSONL. Uploads are stored in private
staging and screened in resumable 500-row batches. State, route, category,
contact, evidence and duplicate filters keep large batches manageable.

Google-discovered candidates cannot be approved or merged until an administrator
records a non-Google independent evidence URL, selects the confirmed service and
checks the retention confirmation. Bulk actions are restricted to holding,
rejecting or restoring candidates; there is no bulk-publication path.

## Government datasets (DATA-012)

Catalogue table `government_datasets` plus review-first facility ingest into
`traveller_facility_import_*` (migration `093`). Connectors reuse the DATA-006
contract:

| Key | Class | Fetch |
| --- | --- | --- |
| `gov_ckan` | `CkanDatasetConnector` | CKAN resource URL |
| `gov_arcgis` | `ArcGisFeatureConnector` | ArcGIS Feature Service query |
| `gov_csv` | `CsvDatasetConnector` | CSV payload or staged path |
| `gov_geojson` | `GeoJsonDatasetConnector` | GeoJSON payload or staged path |

Admin UI: `/admin/data-sources/datasets` (add/edit), and
`/admin/data-sources/facilities/review`. Demo fixtures and curated National
Public Toilet Map rows (migration `094`) ship **disabled**. Use “Import fixture”
or enable + Fetch (row-capped) for review batches. Approved candidates publish
into `traveller_facilities` (never `caravan_parks`). `trusted_automatic` is not
enabled from this UI. HTTP connectors use SSRF host/IP guards.

## Scheduled work

Run `php scripts/run-data-source-schedules.php` from the trusted scheduler. It
executes due schedules subject to the same quotas/budgets and purges expired
pending candidates. It must not be exposed as a public web endpoint.

## Operational checks

- `APP_KEY` is present and protected.
- Connector key is restricted to the server and required APIs.
- Cloud-provider quota and billing alerts are enabled.
- Pending candidates expire and the review queue is actively managed.
- Imports never overwrite claimed or verified provider data.
- Merge decisions preserve the canonical provider and audit history.

