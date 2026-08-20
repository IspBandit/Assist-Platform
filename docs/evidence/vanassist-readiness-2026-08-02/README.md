# VanAssist readiness evidence — 2026-08-02

**Package:** [`../VANASSIST_PRODUCTION_READINESS_PACKAGE.md`](../VANASSIST_PRODUCTION_READINESS_PACKAGE.md)  
**Increment:** S0–S2 local complete (CONDITIONAL PASS for Platform QG while prod Ask/facilities/paid AI stay off)  
**Production Ask / facilities / paid AI:** **not enabled**

## Engineering baseline (local, 2026-08-02)

| Check | Result |
| --- | --- |
| `composer validate --strict` | OK |
| `composer audit` | No security vulnerability advisories found |
| `composer analyse` | See `PHPSTAN.txt` / CI log on this date |
| `vendor/bin/phpunit` (Unit suite) | OK earlier baseline 681 tests; targeted readiness suites OK |
| AI feature flags in `database/seeds/data.php` | `assist_ai_search`, `assist_ai_datasets`, `assist_ai_traveller_facilities` all **false** |

## S1 / S2 completed (local)

| Item | Result |
| --- | --- |
| Migrations | Through `100` (incl. `091` repair, rest/visitor demos) |
| Demo toilets / dump / water / rest / visitor | Fixtures + catalogue rows; import `--approve` |
| Dry-run acceptance | **PASS** — `VA_ACCEPT_BATEHAVEN_001_dry_run.json` |
| Full DB Ask acceptance | **PASS** — `VA_ACCEPT_BATEHAVEN_001.json` (toilet+dump, rules-only, flags restored) |
| Unit S2 harness | `BatehavenAcceptanceHarnessTest` |
| CKAN Toilet Map capped Fetch | **PASS** — `CKAN_TOILET_MAP_STAGE.json` (limit 25; catalogue re-disabled; candidates staged, review-first) |
| Rollback drill | **PASS** — `ROLLBACK_DRILL_AI_FLAGS.json` (30ms to safe) |
| SearchGap Option B | Glue + `docs/SEARCH_GAP_DUAL_SOURCE.md`; Admin API wire awaits CORE-011 merge |
| LPG/fuel | Deferred — `docs/DATA_012_LPG_FUEL_DEFERRAL.md` |

## Platform QG checklist (this pack)

- [x] Architecture A1–A6 notes (ADRs + flags off + no caravan_parks facilities) — CONDITIONAL while Ask off in prod  
- [x] Engineering E1 unit (baseline), E4 validate, E5 audit, E7 AiSearch/DataSources targeted, E9 Batehaven  
- [x] Rollback drill notes + JSON  
- [ ] Integration suite on disposable DB (optional before S3)  
- [ ] `/find` desktop+mobile screenshots (manual)  
- [ ] Architecture / UX / Business human sign-off lines  

**Gate outcome for this RC:** **CONDITIONAL PASS** (Ask / facilities / paid AI remain disabled in production).

## Flag snapshot policy

Do not record production enablement in this folder. Staging toggles used by
acceptance/CKAN scripts were restored (`is_enabled=0` on Toilet Map rows;
Ask/facilities flags off after acceptance).
