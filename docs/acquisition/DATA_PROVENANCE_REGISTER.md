# Data and asset provenance register

6 September 2026. OPS-005 / COM-005. The aggregate source fields below are stored
metadata, not independent licence verification. See the reproducible SQL in
`../evidence/sale-2026-09-06/provenance.php` and its JSON output.

## Production source observations

| Provider class | Non-deleted records | Missing source URL | Stored licence |
| --- | --- | --- | --- |
| Unclassified | 359 | 91 | Missing |
| national | 3,436 | 3,436 | Missing |
| national | 2,959 | 1 | CC BY 4.0 |
| osm | 6,105 | 0 | Missing |
| osm | 10 | 0 | CC BY 4.0: requires reconciliation against actual source |
| osm | 149 | 0 | ODbL |
| locality | 1,079 | 0 | Missing |
| locality | 1 | 0 | CC BY 4.0 |

There are 10,979 non-deleted provider records with no recorded licence and 3,528
with no source URL. Do not substitute an assumed licence or advertise unrestricted
ownership/transfer of the whole provider database. The raw total of 14,098 also
includes deleted records and is not the verified/public listing count.

Non-deleted stays: 2,093 authority-sourced and 8,131 OpenStreetMap-sourced;
both groups have source URLs. A URL alone does not settle reuse rights.
TowSmart displays 199 vehicles and 3,769 towables as recovered TowWise reference
specifications. Reconcile individual manufacturer/model-year URLs, collection
terms and update ownership with `../TOWSMART_CATALOGUE.md` before transfer.
The `tow_vehicles` table is an owner-garage table and its zero rows are not a
count of the reference catalogue.

## Licence and ownership work

| Asset | Existing basis | Required disposition |
| --- | --- | --- |
| Location gazetteers/ABS | `../DATA_TRUST_AND_PROVENANCE.md`, versioned source reports | Retain source-specific attribution/terms and accepted/quarantined status |
| Providers and stays | Source aggregates and row provenance fields | Link acquisition date, terms version, verification state and permitted transfer; quarantine unresolved rights at transaction time |
| TowSmart catalogue | Catalogue source notes and advertised-specification warning | Source-by-source review and correction/update responsibility |
| Application PHP dependencies | `DEPENDENCIES.md`, composer.lock | Preserve actual package notices; proprietary application label does not replace dependency terms |
| Browser tooling | `../evidence/sale-2026-09-06/npm-licences.csv`, package-lock.json | Apache-2.0 Playwright packages; MIT fsevents; development-only metadata |
| Container OS/database/proxy | Dockerfile/Compose dependencies | Capture image digests, notices and transferable build requirements for final release |
| Logos/photos/icons/marketing | Repository asset files | Creator/source or generator provenance, licences and contributor assignments not supplied; rights remain unverified |
| Source code/contributions | Git history | Seller legal ownership and assignment schedule required; Git authorship is not assignment evidence |
| LocalTorque/Polaris | Disabled historical rows/migrations | Excluded commercial assets; preserve upgrade history and separate non-transferable historical data |

For each unresolved source, the seller/data custodian records: exact source URL,
acquisition/update date, evidence checksum, terms/version, attribution, restrictions,
derivation, verification and buyer transfer decision. Retain originals in the
restricted data room. No source records were relabelled or deleted in this review.
