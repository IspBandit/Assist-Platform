# Assist Platform acquisition data room

This directory is the buyer-facing technical and operating index for a potential
sale of Assist Platform.

## Acquisition boundary

Included active products:

1. **VanAssist** — `vanassist.com.au`
2. **TowSmart** — `towsmart.com.au`
3. **TrailerWise** — `trailerwise.com.au`
4. Shared Assist Platform source code, database schema, administration,
   deployment tooling and operational documentation required by those products.
5. Transferable provider, catalogue, location and content data where provenance
   and licence records support transfer.

Excluded products and experiments:

- **LocalTorque** — retired; not an active product or sale dependency.
- **Polaris** — retired; not an active product or sale dependency.

Historical migrations, ADRs and deployment/audit evidence for excluded work may
remain in the repository where required to preserve database upgrade history and
due-diligence integrity.

## Buyer review order

Start with [the dated sale review](SALE_REVIEW_2026-09-06.md),
[privacy register](PRIVACY_REGISTER.md), [data provenance](DATA_PROVENANCE_REGISTER.md)
and [measured analytics](ANALYTICS_2026-09-06.md). These distinguish current
evidence from the outstanding transaction and production gates.

1. `../SALE_READINESS.md` — authoritative pre-sale gate and remaining work.
2. `ASSET_REGISTER.md` — domains, repositories, infrastructure and account-transfer register.
3. `../PRODUCTION_CURRENT_STATE.md` — last verified production state.
4. `../CURRENT_ARCHITECTURE.md` — current application architecture.
5. `../DATABASE_DICTIONARY.md` — production schema/data model.
6. `../OPERATIONS_RUNBOOK.md` and `../BACKUP_AND_RESTORE.md` — operations and recovery.
7. `../SECURITY.md` — application/security posture.
8. `../PRODUCT_AND_FEATURES.md` — implemented product capability and limitations.
9. `../TOWSMART_CATALOGUE.md` and `../VANASSIST_STAYS.md` — product data notes.
10. `../RELEASE_NOTES.md` — change history.

## Current working registers

- [Evidence register](EVIDENCE_REGISTER.md): three-site acceptance, recovery, monitoring, privacy and provenance evidence still needed.
- [Operating costs](OPERATING_COSTS.md): invoice-backed monthly cost and renewal schedule.
- [Locked dependencies](DEPENDENCIES.md): runtime/development versions and declared licences from the lock file.
- [Transfer rehearsal](TRANSFER_REHEARSAL.md): buyer-controlled build, restore, account transfer and operational acceptance.

These are working records, not completed sale-gate evidence.

## Data-room deliverables still required before sale-ready sign-off

- data/provenance register;
- third-party dependency and licence register;
- monthly operating-cost schedule;
- analytics and traction export;
- known-issues/deferred-features register;
- privacy/subprocessor/retention summary;
- current encrypted backup and isolated restore evidence;
- immutable deployment and rollback evidence for the sale candidate;
- domain/account transfer checklist with owner-console actions;
- IP/source-code assignment schedule;
- excluded-assets schedule confirming LocalTorque and Polaris are not included.

## Information handling

Never place credentials, password-reset links, API secrets, private customer
data, database dumps or transfer tokens in this repository. Buyer-sensitive
secrets and account-transfer actions belong in an access-controlled transaction
data room only after the buyer is authorised to receive them.
