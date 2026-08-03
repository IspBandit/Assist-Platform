# Platform Quality Gate — VanAssist readiness RC (2026-08-02)

**Candidate:** VanAssist reliability / DATA-012 + deterministic Ask (flags off in prod)  
**Outcome:** **CONDITIONAL PASS**  
**Forbidden in this RC:** production enablement of `assist_ai_search`,
`assist_ai_traveller_facilities`, `assist_ai_datasets`, paid AI.

## Architecture

| ID | Result | Notes |
| --- | --- | --- |
| A1 | PASS | ADRs 0015–0017, 0027, 0029 respected in facility path |
| A2 | PASS | Facilities not written to `caravan_parks` |
| A3 | PASS | Migrations through `100` applied locally |
| A4 | PASS | Brand tenancy via existing brand_id paths |
| A5 | PASS | No vendor AI from controllers in S2 run (`intent_source=rules`) |
| A6 | PASS | Seeds keep AI flags false |

## UX

| ID | Result | Notes |
| --- | --- | --- |
| U1 | PENDING | `/find` screenshots not captured in this pack |
| U2 | PASS | Facility review admin exists; CLI approve used for demos |
| U3 | PASS | Ask remains off after acceptance; rollback drill forces off |
| U4 | PASS | RELEASE_NOTES / readiness docs state gated Ask |

## Engineering

| ID | Result | Notes |
| --- | --- | --- |
| E1 | PASS | Unit baseline + targeted readiness tests |
| E2 | PENDING | Integration suite deferred (disposable DB) |
| E3 | PASS | `composer analyse` OK — `PHPSTAN.txt` |
| E4 | PASS | `composer validate --strict` — `COMPOSER_VALIDATE.txt` |
| E5 | PASS | `composer audit` clean — `COMPOSER_AUDIT.txt` |
| E7 | PASS | AiSearch / DataSources targeted suites |
| E8 | PASS | Rollback drill `ROLLBACK_DRILL_AI_FLAGS.json` |
| E9 | PASS | Batehaven full Ask `VA_ACCEPT_BATEHAVEN_001.json` |

## Business

| ID | Result | Notes |
| --- | --- | --- |
| B1 | PASS | Reliability outcome + metrics in readiness package |
| B2 | PASS | Catalogue licence/attribution on gov + demo rows; LPG deferred |
| B3 | PASS | Metrics defined in package §7 |
| B4 | PASS | External Ask/CKAN remain disabled by default in prod seeds |

## Sign-off

```text
Architecture: __________________ Date: ________
UX: ____________________________ Date: ________
Engineering: ___________________ Date: ________
Business: ______________________ Date: ________
Overall: CONDITIONAL PASS (prod Ask/facilities/paid AI off)
```
