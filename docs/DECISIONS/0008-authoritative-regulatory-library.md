# ADR 0008: Authoritative vehicle regulatory library

- **Status:** accepted
- **Date:** 2026-07-27
- **Owners:** Assist Platform Enterprise
- **Backlog item:** DATA-008
- **Affected brands/modules:** VanAssist, TowSmart, TrailerWise, LocalTorque, Data Sources, advertising, public content

## Context

Australian roadworthiness, inspection and vehicle-modification requirements are
distributed across Commonwealth, national-regulator, state and territory
authorities. National technical codes do not remove jurisdiction-specific
approval and registration requirements. A static article or copied PDF would
become stale and could imply an authority the platform does not possess.

## Decision

The platform owns a canonical source register containing authority, jurisdiction,
vehicle class, document type, version, effective period, official URL, current
status and source-check evidence. VanAssist, TowSmart, TrailerWise and LocalTorque
publish brand-relevant subsets from the same source register.
Official documents remain owned and served by the issuing authority unless
rehosting permission is expressly recorded.

A scheduled monitor fingerprints official source bytes. An observed change moves
the record to review and removes it from public results; no automated process
rewrites legal guidance or marks changed material current. Current, upcoming and
superseded editions are separate records where effective dates overlap.

Paid provider campaigns may appear after rule results through the shared
advertising tables. They must be labelled Sponsored, matched to explicit
jurisdiction/location and service context, and must not change official document
ordering or organic provider ranking.

## Alternatives considered

- Copying government PDFs into public storage was rejected because copyright,
  provenance and stale-copy risks vary by authority.
- A single national summary was rejected because administrative and inspection
  requirements differ by jurisdiction.
- Automatic AI summaries on source change were rejected because they could
  publish an unreviewed legal interpretation.
- Behavioural or covert-location ad targeting was rejected; location is supplied
  explicitly by the reader.

## Consequences

The library can be kept operationally current and can expose genuine authority
downloads with an auditable check trail. Human review is required whenever a
source changes. Some authority pages do not expose a stable direct PDF; these
records link to the official live page until a durable official download is
verified. LocalTorque remains private until its normal launch blockers pass.

## Quality Gate impact

- Architecture: new shared canonical source tables and forward-only migration.
- UX: authority-first cards, filters, current/upcoming states, empty state and a
  separated sponsored-provider pattern.
- Engineering: source hashing, review-on-change, unit coverage and cron operation.
- Business: provider-funded placements are enabled without compromising source
  trust or organic rankings.

## Quality Gate result — 2026-07-27

- **Architecture: PASS** — one shared source register, explicit per-brand mapping,
  forward-only migration 050 and no duplicate brand service.
- **UX: PASS** — the four brand variants were rendered at 390 px and 1440 px;
  filters, authority-first cards, empty states, current/upcoming states and
  separated Sponsored results use the shared design system.
- **Engineering: PASS** — strict Composer validation, PHPStan, PHP syntax checks,
  118 unit tests and the DATA-008 disposable-database integration test pass. All
  migrations through 050 applied on the disposable database and a live source
  monitor sample completed with zero failures.
- **Business: CONDITIONAL PASS** — campaign selection is relevant, local and
  explicitly labelled without influencing official results. Public LocalTorque
  activation remains blocked by LOC-003; other brands remain subject to the
  normal release process and source-review operations.
- **Overall: CONDITIONAL PASS** — eligible for review and controlled release on
  already-active brands after merge; this decision does not launch LocalTorque.

## Validation and rollback

Validate migration 050 on a disposable database, test filtering and source
change classification, run the monitor against official sources and render
desktop/mobile states. Roll back application code by release; retain source and
check tables for audit. Disable monitoring by removing the scheduled command and
hide the public route if a source-integrity incident occurs.
