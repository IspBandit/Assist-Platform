# Assist Platform acquisition data room

This directory is the buyer-facing technical and operating index for a potential
sale of Assist Platform.

## Acquisition boundary

Included active public products:

1. **VanAssist** — `vanassist.com.au`
2. **TowSmart** — `towsmart.com.au`
3. **TrailerWise** — `trailerwise.com.au`
4. Shared Assist Platform source code, database schema, administration,
   deployment tooling and operational documentation required by those products.
5. **Assist RIC** (`IspBandit/assist-ric`) as proprietary supporting operational
   tooling for controlled research, catalogue management and Admin API data sync.
   RIC is not a fourth public brand.
6. Transferable provider, catalogue, location and content data where provenance
   and licence records support transfer.

Excluded products and experiments:

- **LocalTorque** — retired; not an active product or sale dependency.
- **Polaris** — retired; not an active product or sale dependency.

Historical migrations, ADRs and deployment/audit evidence for excluded work may
remain where required to preserve database upgrade history and due-diligence
integrity.

## Current production evidence

The current verified Assist application baseline is production release
`e2269bf9d072e5877c32129034029dd43e27f3da`, deployed successfully through
GitHub Actions run `34068185858` on 7 September 2026. The release passed reusable
validation, immutable archive/checksum verification, production backup, healthy
container restart, Google Routes provisioning and protected public smoke checks
across VanAssist, TowSmart and TrailerWise.

Main `4905b94e87d6081ec13682a71a0c5bbedf22aeeb` and open PR #251 are
newer than production. Their successful CI does not make their homepage-location,
shared-edge or town-sitemap safeguards live; retain them as unreleased until a
protected production run proves the selected exact commit.

## Buyer review order

Start with [the dated sale review](SALE_REVIEW_2026-09-06.md), then read the
current production/status reconciliation because the dated review predates the
successful corrective production release.

1. `../SALE_READINESS.md` — authoritative pre-sale gate and remaining work.
2. `../PRODUCTION_CURRENT_STATE.md` — current reconciled production baseline.
3. `EVIDENCE_REGISTER.md` — what is closed, partial and still open.
4. `ASSET_REGISTER.md` — included/excluded assets, domains, repositories and
   account-transfer requirements.
5. `PRIVACY_REGISTER.md` — engineering inventory and outstanding seller/legal decisions.
6. `DATA_PROVENANCE_REGISTER.md` — measured source/licence gaps and transfer decisions required.
7. `ANALYTICS_2026-09-06.md` — measured application/production aggregates.
8. `OPERATING_COSTS.md` — invoice-backed monthly/annual cost schedule still to complete.
9. `KNOWN_ISSUES.md` — buyer-facing limitations and remaining gates.
10. `TRANSFER_REHEARSAL.md` — buyer-controlled build/restore/account-transfer acceptance.
11. `DEPENDENCIES.md` — locked runtime/development dependencies and declared licences.
12. `../CURRENT_ARCHITECTURE.md` and `../DATABASE_DICTIONARY.md` — technical architecture/schema.
13. `../OPERATIONS_RUNBOOK.md` and `../BACKUP_AND_RESTORE.md` — operations and recovery.
14. `../SECURITY.md` — application/security posture.
15. `../PRODUCT_AND_FEATURES.md`, `../TOWSMART_CATALOGUE.md` and
    `../VANASSIST_STAYS.md` — implemented product/data capability and limitations.
16. `../RELEASE_NOTES.md` — change history.

## Registers already created

The following acquisition records now exist and should not be described as
missing deliverables merely because individual rows remain open:

- asset register;
- evidence register;
- data/provenance register;
- dependency/licence register;
- measured analytics snapshot;
- known-issues/deferred-work register;
- privacy/data-field/subprocessor working register;
- operating-cost schedule template;
- transfer-rehearsal checklist; and
- current production/recovery review evidence.

Their existence is not the same as completion. Open rows and unknown values must
still be resolved, evidenced, excluded or explicitly accepted/disclosed.

## Remaining data-room evidence before sale-ready sign-off

The principal missing transaction evidence is now:

- invoice-backed monthly/annual operating costs and renewal dates;
- domain, hosting, Cloudflare, mail, analytics, monitoring, maps/API and backup
  account ownership/transfer mechanics;
- independent encrypted off-site backup plus full application/media/config restore
  and rollback evidence;
- authenticated provider/admin/TowSmart owner-isolation acceptance and final
  live-service-worker browser evidence;
- external uptime/error and scheduled-task failure alert receipt;
- administrator MFA, credential-rotation and privileged-access evidence;
- resolved/quarantined/excluded dataset rights for sources with unknown or
  conflicting licences;
- source-code, Assist RIC, logo/image/icon and contributor IP assignment evidence;
- seller-approved privacy retention/export/deletion/subprocessor position and any
  qualified legal review;
- buyer-style clean-host transfer rehearsal; and
- final immutable sale-candidate tag/archive checksum tied to completed quality
  gate evidence.

## Information handling

Never place credentials, password-reset links, API secrets, private customer
data, database dumps or transfer tokens in this repository. Buyer-sensitive
secrets, invoices, legal assignments and account-transfer actions belong in an
access-controlled transaction data room only after the buyer is authorised to
receive them.
