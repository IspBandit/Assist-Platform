# VanAssist radius, residual duplicates and phone containment — Quality Gate

**Date:** 2026-08-11

**Backlog:** VAN-001, EXP-005, DATA-001, DATA-014

**Brand:** VanAssist only

**Candidate:** `fix/search-radius-distance-mobile-duplicates`

## Architecture — PASS

- Existing `Geo`, provider/stay models, Assist AI adapters/aggregator, facility
  provenance, source aliases and audit log are extended; no parallel search,
  identity, moderation or data service is introduced.
- Migration 130 is forward-only. Applied migrations 128 and 129 are unchanged.
- Canonical IDs/slugs survive duplicate merging. Absorbed source identity,
  relationships and evidence are preserved, and missing geospatial fields are
  copied to the trusted survivor before the loser is soft-deleted.
- The no-coordinate rule requires an authority survivor, matching/non-
  contradictory states, the exact full name and that name repeated in the
  authority address; generic same-name rows elsewhere are not merged.
- No environment, route, permission, vendor or runtime dependency changes.
  ADR 0035 remains the governing facility-evidence decision; this bug fix does
  not change the accepted architecture and requires no new ADR.

## UX — PASS

- Existing VanAssist design-system components and the 600 px phone breakpoint
  are reused. Desktop selectors and layouts are unchanged.
- Rendered at 390 × 844 with production HTML and the candidate stylesheet:
  homepage, `/stays?location=Lawgi%20Dawes&distance=50`, and the Griffiths
  Creek facility-suggestion form all reported `clientWidth=390` and
  `scrollWidth=390`, with no genuine overflowing content.
- The 50 km stay render returned 19 measurable in-radius cards. Search actions
  remain touch-sized; secondary phone copy is collapsed rather than deleting
  full detail from desktop or listing pages.
- Copy distinguishes straight-line distance, town-centre estimates and road
  directions. Sponsored/featured labelling and organic grouping are unchanged.

## Engineering — PASS

- PHPStan: no errors (`--memory-limit=512M`).
- Focused VanAssist suite: 25 tests, 169 assertions, no warnings.
- Full PHPUnit suite: 1,109 tests, 80,342 assertions; 32 environment-gated
  skips and 13 existing PHPUnit deprecations; no failures. The full run used
  512 MB because the default 128 MB limit is exhausted by the unrelated large
  LocalTorque fixture export.
- PHP syntax checks and `git diff --check` pass. Regression coverage asserts
  the unrounded radius boundary, final cross-adapter guard, distance basis,
  generic residual merge and phone containment.
- Migration performance remains bounded to the active stay set and indexed
  temporary identity tables. Production backup and the locked migration runner
  remain mandatory.

## Business — PASS

- Intended result: no result outside the selected radius; no misleadingly
  precise provider distance; fewer duplicate stays; richer facility-filtered
  results; cleaner phone completion of search and correction tasks.
- No billing, membership, advertising rank or sponsored behaviour changes.
- Existing first-party search/result analytics measure result counts and zero-
  result gaps; no additional personal data is collected.
- VanAssist remains the only affected brand and retains ownership of stays.

## Release and rollback

- Deploy only from the reviewed exact `main` SHA through the protected
  production workflow with `confirm=DEPLOY`, checksum verification, backup,
  locked migration execution, immutable release switch and all-brand health
  smoke tests.
- Application rollback switches to the preceding immutable release. Migration
  130 is a data transformation and is not reversed ad hoc. A wrong automatic
  merge requires the pre-release database backup or a reviewed forward repair
  that restores the absorbed row and relationships from audit/provenance.
- Verify after release: all `/healthz` and `/readyz` responses; clean migration
  status; canonical Griffiths page 200; absorbed Griffiths duplicate no longer
  public; canonical listing remains in an appropriate nearby stay search with
  its retained point; 25/50 km boundary probes; provider estimate label; and
  390 px home/stays/suggestion renders.

## Overall gate

**PASS for protected production release after CI and exact-head owner landing
confirmation.** Production environment approval remains a separate required
control.
