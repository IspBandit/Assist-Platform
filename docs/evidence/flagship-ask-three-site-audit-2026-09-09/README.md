# Flagship Ask and three-site audit evidence — 9 September 2026

## Candidate

- Base commit: `a9dda713293cc548ea01b020858b672b7431228b`
- Branch: `feature/flagship-ask-audit-20260909`
- Environment: isolated PHP 8.3.33 container, MariaDB 11.4 disposable database,
  logged mail, paid AI disabled
- Public production checks are read-only; no production form submissions,
  feature changes, imports or paid calls are authorised by this record.
- Production `/readyz` reported release
  `88defe040f664d9430471f5a84db3c31e6e9fd29`. Ask and facility results are
  currently available, but structured search is still first and disabled
  request/run routes are exposed on TowSmart and TrailerWise.

## Implemented controls

- Ask is the primary VanAssist homepage journey when `assist_ai_search` is on.
- Structured `/find` remains an accessible fallback.
- State qualifiers are preserved; duplicate exact town names require a state.
- Unique one/two-character town typos can be corrected and disclosed.
- Explicit request radius overrides defaults and is clamped to 1–500 km.
- Device coordinates must fall within broad Australian bounds.
- Orchestrator brand key/database ID must be VanAssist/1.
- Reviewed facilities require the matching `brand_id`.
- Facility and candidate source links allow HTTP/HTTPS only.
- Emergency wording displays Triple Zero and move-to-safety guidance.
- Paid AI and pending dataset answers remain off for the initial launch.
- Requests, service runs and parks fail closed on brands without those modules.

## Automated evidence

- PHP 8.3 unit suite: 494 tests, 28,034 assertions; pass with no warnings after
  adding explicit nested-suite discovery.
- Focused Ask/routing suite: 200 tests, 1,075 assertions; pass.
- National deterministic intent matrix: 128 questions with town/state assertions;
  pass.
- National disposable-database location matrix: 41 tests, 151 assertions; pass.
- Batehaven full deterministic acceptance: PASS; one reviewed toilet and one
  reviewed dump point returned within 50 km, zero provider/stay/external
  contamination and zero paid-AI use.
- Fresh migration through `122`: pass; repeat migration reports nothing to
  migrate; no dirty migration rows.
- Platform backfill: 9,744/9,744 provider listings and slug compatibility;
  user/profile and ownership checks pass.
- Integration suite: 73 tests, 404 assertions; pass with one deliberately
  skipped Admin API bearer scenario.
- Contract suite: 6 tests, 34 assertions; pass.
- PHPStan: pass with no errors (ephemeral Linux container cache).
- Composer strict validation and dependency audits: pass; no PHP or npm
  vulnerability advisories.
- Ask flag rollback rehearsal: disabling `assist_ai_search` /
  `assist_ai_traveller_facilities` removed homepage Ask and returned `/ask` as
  404; re-enable restored primary Ask and reviewed facilities.
- Authenticated disposable journeys: customer and provider workspace isolation
  passed; fixtures removed afterwards.
- Cross-browser public-route and homepage matrix: 36 passed / 6 skipped after
  locator and abort-noise hardening.

## Matrix coverage

The matrix covers every capital and representative regional/remote locations in
all states and territories. Question families include mobile repair, toilets,
free stays and LPG with qualified locations and 50 km radius. Separate cases
cover duplicate town names, postcodes, minor spelling errors, GPS bounds,
unknown intent, provider failures, malformed AI output, prompt injection,
budget exhaustion and emergency wording.

## Manual/browser evidence

Cross-browser acceptance targets:

- Chromium: 1440×900, 1280×800, 768×1024, 390×844 and 360×800
- Firefox: 1440×900
- WebKit: 390×844

Covered surfaces include VanAssist homepage/Ask/PWA/help, TowSmart calculator,
TrailerWise marketplace, three-brand public route health and wrong-brand module
denials. Production browser findings and final pass/fail totals are recorded in
the release handoff after execution.

## Quality Gate

- Architecture: PASS for the candidate — shared Ask correctness, brand module
  middleware, no paid-AI dependency, forward-only schema posture unchanged.
- UX: PASS for the candidate — Ask is the primary homepage journey with
  structured fallback; cross-browser acceptance and screenshots captured;
  emergency guidance precedes directory results.
- Engineering: PASS for the candidate — unit 494/494, focused Ask suites,
  Composer validate/audit, npm audit, PHPStan, Ask flag rollback rehearsal and
  authenticated disposable journeys passed. Live production remains unchanged
  until the protected release workflow is owner-approved.
- Business: PASS for deterministic Ask as VanAssist flagship with paid AI still
  off; measurable thresholds recorded in the national matrix evidence.
- Overall: CONDITIONAL PASS for merge/staging rehearsal. Production enablement
  of `assist_ai_search` / reviewed facilities still requires the protected
  `DEPLOY` workflow on `main`, live smoke checks and immediate flag rollback if
  thresholds fail.
