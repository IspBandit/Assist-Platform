# Sale evidence reconciliation — 7 September 2026

Workstreams: OPS-004 / OPS-005 / COM-005.
Scope: VanAssist, TowSmart and TrailerWise only. Assist RIC is included as
supporting operational tooling, not a public brand.

This record reconciles the 6 September acquisition review with the later successful
production release that was not reflected in the earlier status documents.

## Proven production release

- Release SHA: `4d5a4c957df1e556dc0c26f5345880aaad13277b`
- GitHub Actions production run: `34030818368`
- Result: success
- Reusable validation: success
- Immutable archive/checksum: success
- Pre-release production DB backup: verified
- Containers after release: database healthy, application healthy, Caddy started
- Migrations: none pending
- Google Routes protected credential/provisioning: success
- Protected public three-brand smoke: success

The production log explicitly reports:

`Released 4d5a4c957df1e556dc0c26f5345880aaad13277b successfully.`

## Public smoke coverage in the successful release

VanAssist:

- `/`
- `/providers`
- `/request-assistance`
- `/locations/nearest?lat=-27.4698&lng=153.0251`
- `/login`
- `/register`
- `/forgot-password`
- designated Ask query for Griffiths Creek dump-point evidence
- provider road-distance output near Karratha
- direct provider-name search for Battery World Greenslopes

TowSmart:

- `/`
- `/calculator`
- `/rules`

TrailerWise:

- `/`
- `/marketplace`
- `/rules`

## Data audit observations from the release log

- 17,615 towns; 110 missing postcode/coordinates
- 11,876 active providers
- 3,528 unclaimed providers missing source metadata
- 290 providers missing a brand listing
- 59 duplicate name/town provider candidates
- 10,224 active stay/park records; no missing source field in that audit
- 199 TowSmart vehicle references; 163 missing source URL
- 3,769 TowSmart towable references; 3,769 missing source URL, 5 invalid-weight
  records and 86 duplicate-identity candidates
- provider-brand duplicates: 0
- orphan provider services: 0
- VanAssist provider pack: 9,989 source records, 7,579 public, 2,410 review,
  no required-source-licence omissions for that specific pack and no review rows
  publicly visible

These counts are engineering observations and do not override the acquisition
provenance register. Missing or conflicting commercial reuse rights remain open.

## Earlier recovery evidence retained

The 6 September isolated database restore restored 232 tables in 44 seconds. It
is valid database-recoverability evidence but is not a complete application/media/
configuration or independent off-site recovery rehearsal.

## Later main commit

`5618605ac82cbca6a83343c61c336ffa3634b857` (PR #248) passed CI after the
successful production release. It adds generic shared-host Caddy/network plumbing
for separately deployed products. It does not change the Assist application
feature/data boundary and is not required for the current Assist application sale
baseline.

## Remaining gates

See `../../../PROJECT_STATUS.md`, `../../../docs/PRODUCTION_CURRENT_STATE.md`,
`../../../docs/acquisition/EVIDENCE_REGISTER.md` and GitHub issue COM-005. The
remaining work is primarily authenticated acceptance, off-site/full recovery,
monitoring alerts, security ownership, provenance/IP, invoice/account transfer
records, privacy approval and buyer-style transfer rehearsal.

No secret values, customer records, database dump or private account evidence is
stored in this directory.
