# VanAssist production readiness & controlled-release package

**Workstream:** VanAssist reliability — provider + essential traveller-services
locator (production readiness).  
**Date:** 2026-08-02  
**Status:** planning package — **do not enable** public Ask, traveller
facilities, or paid AI in production.  
**Does not expand:** Assist AI product scope (CORE-012 accepted as complete and
gated).  
**Related:** [`PLATFORM_QUALITY_GATE.md`](PLATFORM_QUALITY_GATE.md),
[`DATA_012.md`](DATA_012.md), [`AI_RELEASE_CRITERIA.md`](AI_RELEASE_CRITERIA.md),
[`AI_WORKSTREAM_STATUS.md`](AI_WORKSTREAM_STATUS.md), CORE-011 / DATA-013.

---

## 0. Objective and non-goals

### Objective

Make VanAssist the most reliable Australian caravan/RV **provider** and
**essential traveller-services** locator through evidence-based coverage,
verification, and a controlled release — not by expanding AI features.

### Non-goals (this workstream)

- New AI capabilities, models, conversational UX, or public Ask enablement
- Writing facilities into `caravan_parks` (ADR 0032)
- Expanding Admin API OpenAPI beyond inventoried Phase 1 paths without CORE-011
  ownership
- Enabling `assist_ai_search`, `assist_ai_traveller_facilities`,
  `assist_ai_datasets`, or paid AI (`ai_enabled`) in production

---

## 1. Full Platform Quality Gate — execution plan

Gate standard: [`PLATFORM_QUALITY_GATE.md`](PLATFORM_QUALITY_GATE.md) (four
perspectives). Assist AI CONDITIONAL PASS evidence remains a **subset**; this
plan runs the **platform** gate for a VanAssist reliability release candidate.

### 1.1 Scope of candidate under gate

| In scope | Out of scope for this RC |
| --- | --- |
| Structured `/find`, stays, provider directory | Public Ask enablement |
| DATA-012 ingest + facility review (flags off) | Paid AI enablement |
| Knowledge-gap admin + SearchGap JSON export | Polaris public launch |
| CORE-011 `/search-gaps` plan/complete if unlocked | New AI product surface |

### 1.2 Architecture checks

| ID | Check | Evidence artefact |
| --- | --- | --- |
| A1 | ADRs 0015–0017, 0027, 0029 respected | Diff review + ADR index |
| A2 | Facilities not in `caravan_parks` | Adapter/migration review + unit tests |
| A3 | Forward-only migrations through current head | `php scripts/migrate.php` dry log |
| A4 | Brand/tenancy server-enforced for VanAssist | Existing brand tests + spot check |
| A5 | No vendor AI from controllers | Grep `OpenAiProvider` call sites |
| A6 | Flags default off in seeds | `database/seeds/data.php` |

### 1.3 UX checks

| ID | Check | Evidence artefact |
| --- | --- | --- |
| U1 | `/find` desktop + mobile rendered review | Screenshots in evidence pack |
| U2 | Facility review admin usable | Operator walkthrough notes |
| U3 | Ask UI remains 404 when flag off | Manual + `AssistSearchFlagOffTest` |
| U4 | No unsupported “AI answers” claims in copy | RELEASE_NOTES / public copy review |

### 1.4 Engineering checks

| ID | Check | Command / artefact |
| --- | --- | --- |
| E1 | Full unit suite | `vendor/bin/phpunit` (or CI) |
| E2 | Integration suite (disposable DB) | `vendor/bin/phpunit --testsuite Integration` |
| E3 | PHPStan / `composer analyse` | CI log |
| E4 | `composer validate --strict` | CI log |
| E5 | `composer audit` | CI log |
| E6 | Production dependency build | `composer install --no-dev` on staging |
| E7 | AiSearch + DataSources suite | `vendor/bin/phpunit tests/Unit/AiSearch tests/Unit/DataSources` |
| E8 | Health / backup / rollback notes executable | Ops sign-off |
| E9 | Batehaven acceptance scenario (flags on **non-prod only**) | §3 spec results |

**Baseline captured 2026-08-02 (local):** Unit suite **681** tests / **78493**
assertions OK; `composer validate --strict` OK; AI flags seeded **false**.

### 1.5 Business checks

| ID | Check | Evidence |
| --- | --- | --- |
| B1 | Measurable VanAssist reliability outcome stated | This package §7 |
| B2 | Dataset licences / attribution recorded | Catalogue rows + approve UI |
| B3 | Analytics for release metrics defined | §7 |
| B4 | External-service prerequisites or feature remains disabled | Flags off in prod |

### 1.6 Gate recording

Use PR template from `PLATFORM_QUALITY_GATE.md`. Store dated pack under:

`docs/evidence/vanassist-readiness-YYYY-MM-DD/`

Containing: command logs, screenshots, Batehaven results, flag matrix snapshot,
rollback rehearsal notes, approver signature block.

### 1.7 Allowed gate outcomes for this workstream

| Result | Meaning |
| --- | --- |
| **CONDITIONAL PASS** | Expected while Ask/facilities/paid AI stay disabled in production |
| **PASS** | Only if owner explicitly authorises a production enablement RC (separate decision) |
| **FAIL** | Blocks even non-prod beta if security/integrity broken |

---

## 2. DATA-012 dataset / import plan

Priority order (mandatory):

1. Public toilets  
2. Dump points  
3. Drinking water  
4. Rest areas  
5. Visitor information centres  
6. Caravan parks / campgrounds (**stays**, not `traveller_facilities`)  
7. LPG and fuel **only** where reliable licensed sources exist  

### 2.1 Facility types vs stays

| Priority item | Storage | Source strategy |
| --- | --- | --- |
| Public toilets | `traveller_facilities` (`public_toilet`) | National Public Toilet Map CKAN (`au_national_public_toilet_map`, mig `094`) |
| Dump points | `traveller_facilities` (`dump_point`) | Toilet Map dump flag rows (`au_national_toilet_map_dump_points`) + demo GeoJSON |
| Drinking water | `traveller_facilities` (`drinking_water`) | State/council open data when licence OK; demo CSV if needed for staging |
| Rest areas | `traveller_facilities` (`rest_area`) | State road authority / open data; catalogue row TBD per licence |
| Visitor information | `traveller_facilities` (`visitor_information`) | Tourism NSW / council; catalogue row TBD |
| Caravan parks / campgrounds | **`caravan_parks` stays** | Existing stays directory + authority import paths — **not** facilities table |
| LPG / fuel | Providers and/or facilities only if licensed source | QLD fuel reporting / licensed lists; **no** scrape of unlicensed directories |

### 2.2 Ingest phases (flags remain off in production)

| Phase | Action | Environment |
| --- | --- | --- |
| D0 | Migrations `092`–`094`, `097` applied | all |
| D1 | Demo toilets + dump points import + approve (CLI) | local/staging only |
| D2 | Enable Toilet Map catalogue rows **in staging**, Fetch with capped `limit`, review duplicates, approve NSW subset first | staging |
| D3 | Expand Fetch limits; NSW → ACT → VIC → … by operator decision | staging |
| D4 | Drinking water / rest / visitor catalogues added only after licence + mapping review | staging |
| D5 | Stays coverage for Batehaven/Eurobodalla verified via existing stays search | staging |
| D6 | LPG/fuel: enable only licensed connectors; otherwise leave to provider categories | staging |

### 2.3 Operator commands (non-production)

```bash
php scripts/migrate.php
php scripts/import-demo-traveller-facilities.php --approve
# Staging only after owner allows live CKAN fetch:
# Admin → Government datasets → enable row → Fetch → Facility review → Approve
```

### 2.4 Acceptance filters for publish

- `status=active`, `verification_status` in `reviewed|verified`
- Provenance `gov:{dataset_key}`, attribution, licence present
- DuplicateMatcher score reviewed for strong matches
- Never auto-`trusted_automatic` without recorded owner decision

### 2.5 Geographic focus for first reliability slice

**Eurobodalla / Batemans Bay / Batehaven, NSW** — mandatory scenario (§3).
National expansion only after this slice passes internal verification.

---

## 3. Batehaven acceptance-test specification

**Scenario ID:** `VA-ACCEPT-BATEHAVEN-001`  
**Query (mandatory):** `public toilets and dump points near Batehaven, NSW`  
**Purpose:** End-to-end proof that VanAssist can locate essential facilities for
a real NSW coastal locality without inventing data.

### 3.1 Preconditions

| Precondition | Notes |
| --- | --- |
| Migrations through `110` (+ `113` if OSM staging used) | |
| Non-production environment | Never production flags |
| Reviewed facilities near Batehaven / Batemans Bay | From demo and/or Toilet Map subset |
| Town resolver knows Batehaven (or Batemans Bay fallback documented) | Record which town_id is used |
| `assist_ai_search` + `assist_ai_traveller_facilities` **on in staging only** for Ask path | Production remains off |
| Paid AI **disabled** for deterministic Ask path | Rules-only |

### 3.2 Paths under test

| Path | Expected |
| --- | --- |
| Structured search (providers dump-points / related) | Still works; unchanged UX |
| Ask VanAssist (staging flags on) | Intent → facilities (+ providers if mapped) |
| Direct facility DB query (admin/SQL) | Active reviewed toilets + dump points within radius |

### 3.3 Steps

1. Confirm ≥1 `public_toilet` and ≥1 `dump_point` active+reviewed within 25–50 km of Batehaven/Batemans Bay coordinates.  
2. Run Ask (staging): query exact string above; location town or lat/lng of Batehaven.  
3. Assert intent includes facility keys `public_toilet` and `dump_point` (and/or mixed).  
4. Assert results section **Traveller facilities** lists toilets and dump points separately from stays.  
5. Assert no facility appears as a caravan park card.  
6. Assert provenance labels present for dataset-sourced rows.  
7. Assert `/find` dump-point / category path still returns without error.  
8. Turn flags **off**; confirm `/ask` 404.  
9. Record distances, counts, town resolution, screenshots, SQL counts.

### 3.4 Pass / fail

| Result | Criteria |
| --- | --- |
| **PASS** | ≥1 toilet and ≥1 dump point shown with provenance; no park misclassification; flags restore off |
| **CONDITIONAL** | Results only via providers (no facility rows) — document coverage gap; do not claim facility PASS |
| **FAIL** | Invented coords/names, park overload, production flags flipped, paid AI called unexpectedly |

### 3.5 Evidence template

```text
Scenario: VA-ACCEPT-BATEHAVEN-001
Date / env / git SHA:
Town resolution:
Facility counts (toilet / dump) within radius:
Ask intent JSON summary:
Result IDs shown:
Screenshots:
Flags after test (must be off in prod):
Tester:
```

---

## 4. Feature-flag release matrix

| Flag | Seed default | Local | Staging verify | Limited beta | Paid AI beta | Production general |
| --- | --- | --- | --- | --- | --- | --- |
| `assist_ai_search` | **off** | optional on | on for Ask tests | on for cohort | on | **off until PASS + owner** |
| `assist_ai_traveller_facilities` | **off** | optional on | on when facilities populated | on for cohort | on | **off until PASS + owner** |
| `assist_ai_datasets` | **off** | optional | optional | optional | optional | **off until PASS + owner** |
| `ai_enabled` / `openai_enabled` | **off** | off for det. tests | **off** stage 2 | **off** | on + caps | **off until PASS + owner** |

**Hard rule:** Production values remain **off** for Ask, facilities, datasets, and
paid AI throughout this workstream unless a separate owner-approved PASS RC
explicitly changes that (out of current authorisation).

---

## 5. Production-readiness checklist

### Always required before any beta

- [ ] Platform QG Architecture / UX / Engineering / Business evaluated  
- [ ] Full automated suites green on RC SHA  
- [ ] Migrations applied; no dirty migration state  
- [ ] Seeds confirm AI flags false  
- [ ] `/find` regression signed  
- [ ] DATA-012 D1–D2 done for Batehaven slice  
- [ ] `VA-ACCEPT-BATEHAVEN-001` executed on staging  
- [ ] Rollback rehearsal (§8) executed  
- [ ] RELEASE_NOTES / operator docs updated for operator-visible ingest  

### Still prohibited without separate owner PASS

- [ ] Production `assist_ai_search=1`  
- [ ] Production `assist_ai_traveller_facilities=1`  
- [ ] Production paid AI  
- [ ] `trusted_automatic` without recorded decision  

---

## 6. Staged release plan

| Stage | Name | Flags (non-prod) | Exit criteria |
| --- | --- | --- | --- |
| **S0** | Readiness package + QG baseline | all AI off in prod | **Done** — package accepted 2026-08-02 |
| **S1** | Dataset ingestion & internal verification | facilities flag off in prod; staging fetch/approve | **Done (local)** — demo toilet/dump/water/rest/visitor; Batehaven import+approve; capped CKAN Toilet Map Fetch (`stage-ckan-toilet-map.php`, catalogue re-disabled); LPG/fuel deferred |
| **S2** | Deterministic Ask testing (paid AI off) | staging Ask + facilities on | **Done (local)** — dry-run PASS; full Ask DB PASS (`VA_ACCEPT_BATEHAVEN_001.json`); unit harness PASS; flags restored |
| **S3** | Limited beta | staging/private cohort only | Metrics §7 within thresholds; rollback drilled (**drill PASS** locally) |
| **S4** | Paid AI beta (strict budgets) | staging/private; caps + allowlist | Cost hard-stop tested; no silent upgrade |
| **S5** | General release | production enable | Full Platform QG **PASS** + owner signature |

**Current authorisation:** S0–S2 local complete. Do **not** enter S5. Do
**not** flip production flags. S3+ still need cohort metrics + human QG sign-off.

---

## 7. Release metrics (measurable criteria)

| Metric | Definition | Target before S3 | Target before S5 |
| --- | --- | --- | --- |
| Search success | % Ask/staging queries with ≥1 local result | ≥70% Batehaven suite | ≥85% agreed sample |
| Intent accuracy | Manual grade of intent vs expected taxonomy | ≥90% golden set | ≥95% |
| No-result rate | % queries with zero local+external | ≤20% facility suite | ≤10% |
| Facility coverage | Active reviewed toilets+dumps in Eurobodalla radius | Meets §3 counts | NSW coastal SLA TBD |
| Provenance | % facility results with source + attribution | 100% | 100% |
| Duplicate handling | Strong dups linked or rejected pre-publish | 100% reviewed | 100% |
| Security | Rate limit + Turnstile unlock + no key leakage | Pass | Pass |
| Performance | Ask p95 (rules-only) | ≤2s staging | ≤2s prod SLA TBD |
| AI cost enforcement | Hard stop at cap; $0 when disabled | Pass drill | Pass drill |
| Rollback | Flags off restores `/ask` 404 in &lt;5 min | Pass drill | Pass drill |

Instrumentation: `assist_searches`, `knowledge_gaps`, facility counts SQL,
`ai_usage_*` (must stay ~0 in S2), admin gap export.

---

## 8. Rollback verification plan

### 8.1 Immediate rollback (any stage)

```text
1. Set assist_ai_search = 0
2. Set assist_ai_traveller_facilities = 0
3. Set assist_ai_datasets = 0
4. Set ai_enabled = 0 (and openai_enabled = 0)
5. Confirm GET /ask → 404 on VanAssist
6. Confirm GET /find still healthy
7. Confirm no new ai_usage_events with provider=openai
```

### 8.2 Data rollback

- Do **not** delete reviewed facilities blindly; soft-unpublish
  (`status` not active) if a bad ingest ships.  
- Re-disable `government_datasets.is_enabled` for offending catalogue rows.  
- AuditLog entries retained.

### 8.3 Verification drill (required before S3)

Record time-to-safe, operator, screenshots of `/ask` 404 and `/find` OK.

---

## 9. CORE-011 Admin API — `GET /api/v1/admin/search-gaps`

### 9.1 Current state

- Sibling `Assist-Platform-admin-api`: inventoried endpoint **live** —
  `AdminApiSearchGapService` aggregates `provider_searches` zeros
  (`meta.source = provider_searches`).  
- This AI branch: `KnowledgeGapService::toSearchGapItems` + admin JSON export
  bridge; pure merger `SearchGapDualSource` landed. OpenAPI `/search-gaps` and
  Admin API routes are **absent** here until CORE-011 merge.  
- Authoritative merge plan: `docs/SEARCH_GAP_DUAL_SOURCE.md`.

### 9.2 Plan (Option B preferred)

| Option | When | Work |
| --- | --- | --- |
| **B — Dual-source on inventoried path** | **Preferred** | After merge, wire `AdminApiSearchGapService` to union provider zeros + knowledge gaps via `SearchGapDualSource`; collection `meta.source = dual`; item `meta.source` attribution; OpenAPI description only |
| **A — Provider-only (status quo on admin-api)** | Interim | Keep provider_searches-only until AI branch merge |
| **Bridge** | Until merge | `/admin/ai-search/gaps/export?format=json` (knowledge-only) |

Do not invent a second gap API. Do not enable production AI flags for this.

### 9.3 Contract (inventory-aligned)

- Auth: Admin API bearer + `analytics:read` (as shipped on CORE-011)  
- Query: `from`, `to`, `limit`, `cursor`, `q` (existing)  
- Body: `{ data: SearchGap[], meta, links }` — dual-source fields only in
  `meta` / item `meta` (`additionalProperties` allowed)  
- Capability: `search_gaps: read` (already active on admin-api)  

---

## 10. Exact file-level change list (planned)

### 10.1 This package (S0) — docs only

| File | Action |
| --- | --- |
| `docs/VANASSIST_PRODUCTION_READINESS_PACKAGE.md` | **Add** (this document) |
| `docs/acceptance/VA_ACCEPT_BATEHAVEN_001.md` | **Add** (executable copy of §3) |
| `docs/evidence/vanassist-readiness-2026-08-02/README.md` | **Add** evidence scaffold |
| `docs/START_HERE.md` | **Update** link to readiness package |
| `docs/PRODUCT_BACKLOG.md` | **Update** readiness note / OPS item if needed |
| `CHANGELOG.md` | **Update** readiness package entry |
| `docs/DATA_012.md` | **Update** priority order + Batehaven slice pointer |

### 10.2 S1 (dataset ingestion) — landed (local)

| File / area | Action |
| --- | --- |
| `storage/datasets/demo-*.csv` / geojson | Toilets, dump, water, rest, visitor |
| Migrations `098`, `100` | Catalogue rows (disabled) |
| `scripts/import-demo-traveller-facilities.php` | Import + approve demos |
| `scripts/stage-ckan-toilet-map.php` | Capped CKAN Fetch; restores `is_enabled=0` |
| `docs/DATA_012_LPG_FUEL_DEFERRAL.md` | LPG/fuel deferred (O3) |
| **No** production flag seed changes to `true` | Forbidden |

### 10.3 S2 (deterministic Ask acceptance) — landed (local)

| File / area | Action |
| --- | --- |
| `scripts/acceptance-batehaven-facilities.php` | Dry-run + full Ask (flags restored) |
| `tests/Unit/AiSearch/BatehavenAcceptanceHarnessTest.php` | Unit harness |
| Evidence `VA_ACCEPT_BATEHAVEN_001*.json` | Recorded |
| `scripts/rollback-drill-ai-flags.php` | Rollback drill JSON |
| **No** AI scope expansion | Forbidden |

### 10.4 CORE-011 `/search-gaps` dual-source (on merge)

| File / area | Action |
| --- | --- |
| `docs/SEARCH_GAP_DUAL_SOURCE.md` | Follow step list (§3) |
| `AdminApiSearchGapService` | Wire `SearchGapDualSource` + knowledge mapper |
| `docs/openapi/admin-v1.yaml` | Description text only (no new path) |
| Contract / unit tests | Assert `meta.source = dual` |
| RIC docs | Filter by item `meta.source` |

---

## 11. Effort estimate

| Increment | Effort | Depends on |
| --- | --- | --- |
| S0 Readiness package + QG baseline scaffold | 0.5–1 d | — |
| S1 Batehaven/NSW toilet+dump ingest + review | 2–4 d | licences, CKAN access |
| S1b Drinking water / rest / visitor catalogue research | 2–5 d | owner source approval |
| S1c Stays coverage verify (parks) | 0.5–1 d | existing stays data |
| S1d LPG/fuel licensed-source decision | 1–2 d | owner |
| S2 Deterministic Ask acceptance automation + evidence | 1–2 d | S1 |
| Full Platform QG pack for CONDITIONAL PASS RC | 1–2 d | S1–S2 |
| S3 Limited beta ops | 2–3 d | QG conditional |
| S4 Paid AI beta | 2–3 d | owner model + caps |
| CORE-011 `/search-gaps` implementation | 1–3 d | CORE-011 unlock |
| S5 General release | 2–4 d | owner PASS |

**Critical path to Batehaven reliability proof:** S0 → S1 (toilets+dumps) → S2.

---

## 12. Blocking decisions requiring owner approval

| ID | Decision | Blocks |
| --- | --- | --- |
| O1 | Authorise staging CKAN Fetch of National Toilet Map (beyond demo fixtures) | **Exercised locally** via `stage-ckan-toilet-map.php` (capped; catalogue left disabled). Staging shared-env still needs operator confirm |
| O2 | Licence/attribution acceptance for each new dataset (water, rest, visitor) | Demo fixtures OK; live gov water/rest/visitor still need licence |
| O3 | Confirm LPG/fuel licensed sources (or explicitly defer) | **Deferred** — see `DATA_012_LPG_FUEL_DEFERRAL.md` |
| O4 | Unlock CORE-011 implementation of `GET /search-gaps` vs defer to admin JSON | RIC live sync |
| O5 | Limited beta cohort definition (who, where, duration) | S3 |
| O6 | Paid AI model allowlist + AUD caps for S4 | S4 |
| O7 | Production enablement of Ask/facilities (S5) — **explicit PASS** | Public launch |
| O8 | Any `trusted_automatic` policy | Auto-publish path |

Until O1–O3, readiness proceeds with **demo fixtures + internal verification
harness** only.

---

## 13. First approved readiness increment (S0 → start S1 prep)

Authorised immediately after this package:

1. Land documentation in §10.1.  
2. Create evidence scaffold and record engineering baseline.  
3. Prepare Batehaven acceptance spec as a standalone executable doc.  
4. **Do not** enable production flags.  
5. **Do not** expand AI scope.  
6. Begin S1 prep: verify demo facility path and town resolution for Batehaven
   without production enablement.

---

## Approver block

```text
Package reviewed by:
Date:
Result: ACCEPT / ACCEPT WITH CONDITIONS / REJECT
Conditions:
Next increment authorised: S0–S2 local complete; S3 cohort / human QG sign-off; CORE-011 SearchGap dual-source wire on merge
```
