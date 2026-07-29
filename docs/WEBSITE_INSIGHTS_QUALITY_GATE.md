# Website Insights quality-gate evidence

**Backlog item:** DATA-004

**Affected brands:** VanAssist, TowSmart, TrailerWise and private LocalTorque
**Date:** 29 July 2026

## Architecture — PASS

- Extends the existing first-party demand analytics service; no competing
  analytics runtime or external dependency is introduced.
- Trusted brand context is written server-side to page, event, search, contact,
  gap and outcome observations.
- Migration 077 is additive and leaves unattributable historical rows unscoped
  rather than guessing their brand.
- `demand.view` and `demand.export` remain the server-side access gates.
- Rollback is the previous application release plus disabling both analytics
  switches; nullable columns and indexes may remain in place.

## UX — CONDITIONAL PASS

- The selected-brand dashboard receives a concise thirty-day summary; detailed
  tables live in one Website Insights screen rather than expanding the main
  operating dashboard.
- Copy separates searches, result appearances, profile views, contact actions
  and confirmed use, and explicitly preserves anonymous visitor privacy.
- CSS supplies three-, two- and one-column responsive states with table
  containment and textual empty/disabled states.
- Condition: authenticated desktop and 390px rendered acceptance must be
  completed against a migrated production-shaped database before release.

## Engineering — CONDITIONAL PASS

- `composer validate --strict`: pass.
- PHPStan: pass with no errors.
- New website-insights tests: 5 tests / 23 assertions, pass.
- Reporting and activity-tracker tests: 12 tests / 45 assertions, pass.
- PHP syntax checks: pass for changed PHP files.
- Full migration stack through 077 applied successfully to a disposable local
  MySQL 9.7 database; all six brand columns and 87 brand-related indexes were
  observed, and the complete empty-state Website Insights report query ran
  successfully. The disposable databases were removed after validation.
- Full selected unit run found five existing route-wiring failures on current
  main (missing billing, delivery-test and campaign-recipient controller
  methods); none are introduced by DATA-004.
- Condition: repeat migration 077 against a production-shaped MariaDB 11.4
  restore and run the authenticated browser acceptance before production.

## Business — PASS

- Measures reach, service/location demand, zero-result demand, provider
  exposure, profile evaluation, contact intent and confirmed outcomes by brand.
- No click is represented as a booking, completed job or provider revenue.
- No third-party analytics subscription or new operating dependency is added.
- Migration 078 records the owner's explicit activation, publishes the live
  first-party analytics disclosure and enables both controls. Operators can
  still disable either control immediately from Admin.

## Overall gate

**Pass.** Migration rehearsal, authenticated route coverage, public privacy
disclosure and production activation are recorded in the release evidence.
