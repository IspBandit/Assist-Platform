# Phase AI-0 — Assist AI Orchestrator design package

**Status:** design complete — **approved 2026-08-01**. AI-1 authorised.  
**Phase:** AI-0 complete. Production implementation begins at AI-1 only.  
**Workstream:** Assist AI Search and Knowledge Orchestration (shared platform).  
**Initial brand surface:** VanAssist “Ask VanAssist” alongside structured search.  
**Status note (2026-08):** AI-1–AI-7 and DATA-012 are implemented behind flags;
this document remains the approved AI-0 design baseline. Prefer
`docs/AI_WORKSTREAM_STATUS.md` and `docs/DATA_012.md` for current state.

**Backlog:** CORE-012, VAN-011, DATA-013, DATA-012, DATA-006,
DATA-002, DATA-014, OPS-010 (cost controls reuse), CORE-011 (interface
dependency only).  
**Admin API Phase 1:** locked and preserved.

Related design docs (detail): see Documentation plan (§21).  
Related ADRs: 0018–0027 (accepted), plus accepted 0015–0017 and 0006–0007.

**Owner sign-off:** [`AI0_OWNER_DECISION_BRIEF.md`](AI0_OWNER_DECISION_BRIEF.md)  
**Appendices:** [`AI_INTENT_SCHEMA.md`](AI_INTENT_SCHEMA.md),
[`AI_INTENT_RULES.md`](AI_INTENT_RULES.md),
[`AI_MIGRATIONS_PROPOSAL.md`](AI_MIGRATIONS_PROPOSAL.md),
[`AI1_IMPLEMENTATION_PLAN.md`](AI1_IMPLEMENTATION_PLAN.md).

---

## Gate statement

VanAssist must remain fully operational with AI **entirely disabled**. Existing
dropdown, town, category, Near Me and automatic-location search must be
unchanged for users who prefer them. Natural-language search is a **parallel**
path. AI may interpret intent only; it is never factual authority. Trusted
local knowledge must grow through repeated use without uncontrolled cost.

**AI-0 approved.** Proceed to Phase AI-1 (deterministic foundation only).

---

## 1. Current search architecture audit

### 1.1 What exists today

Public VanAssist search is a **structured, location-first** pipeline:

| Surface | Route / asset | Behaviour |
| --- | --- | --- |
| Find a service | `GET /find` → `SearchController::find` | Category `<select>` + town/postcode or GPS → providers |
| Town typeahead | `GET /locations/towns` | `Town::searchActive` |
| Nearest town | `GET /locations/nearest` | GPS → town; feeds hidden `lat`/`lng` |
| Nearby spotlight | `GET /locations/nearby-providers` | Homepage module |
| Category browse | `/services`, `/category/{slug}` | Filtered provider lists |
| Provider directory | `/providers` | Optional `q` name `LIKE` |
| Stays | `/stays` (VanAssist only) | `stay_type`, `price_type`, distance + location/GPS |
| Feedback | `POST /find/feedback` | Demand-gap reasons + Turnstile |

**Client:** `public/assets/js/app.js` + `use-location-btn` / distance filter
partials. Results are **list-first**; maps are **external directions** only
(`map_directions_url()`), consistent with Product Bible.

### 1.2 Service boundaries (as built)

There is **no** search orchestrator. Controllers call models/helpers directly:

```
Views/JS → Site controllers → Provider / Town / CaravanPark / ServiceCategory
                           → Geo, ProviderCoverage
                           → DemandRecorder / ActivityTracker (optional)
```

| Class | Path |
| --- | --- |
| `SearchController` | `app/Controllers/Site/SearchController.php` |
| `LocationController` | `app/Controllers/Site/LocationController.php` |
| `CategoryController` | `app/Controllers/Site/CategoryController.php` |
| `ParkController` | `app/Controllers/Site/ParkController.php` |
| `Provider` | `app/Models/Provider.php` |
| `CaravanPark` | `app/Models/CaravanPark.php` |
| `Town` | `app/Models/Town.php` |
| `ServiceCategory` | `app/Models/ServiceCategory.php` |
| `Geo` | `app/Helpers/Geo.php` |
| `ProviderCoverage` | `app/Services/ProviderCoverage.php` |
| `DemandRecorder` | `app/Services/Demand/DemandRecorder.php` |

### 1.3 Natural language / AI today

**None** on the public search path. No OpenAI client in controllers. VAN-011
is backlog-only. LocalTorque docs mark AI search as staged.

### 1.4 Traveller facilities today

Per **ADR 0029**:

- Amenity flags on `caravan_parks` (`toilets`, `dump_point`, …) are **display**,
  not `/stays` filters.
- Some amenity-like needs are modelled as `service_categories` (e.g.
  `dump-points`, `lpg-refills-and-bottle-exchange`) when tagged as providers.
- Dedicated `traveller_facilities` entity is **planned** (DATA-012 / ADR 0029);
  **not** in Admin API Phase 1 schema.

### 1.5 Must remain unchanged

1. `/find` GET contract and category + location UX.
2. GPS → `/locations/nearest` → form behaviour.
3. `/stays` structured filters and VanAssist gate.
4. List-first + external directions presentation (reuse cards for NL results).
5. Model query semantics for structured adapters.
6. AI off/exhausted ⇒ structured search, keyword intent (once shipped), local
   and imported dataset search continue; **no paid calls**.

---

## 2. Current analytics and demand audit

### 2.1 Implemented

| Concern | Location |
| --- | --- |
| Demand funnel | `docs/demand-analytics-implementation.md`, migration `014` |
| Brand scope | migration `077_brand_scoped_website_analytics.sql` |
| Search rows | `provider_searches`, `provider_search_results` |
| Contact actions | `provider_contact_actions` |
| Structured gap feedback | `demand_gap_feedback` via `/find/feedback` |
| Insights | `WebsiteInsightsService`, `ReportingService` |
| Events | `ActivityTracker` vocabulary |

`provider_searches` today records location/category/urgency/radius/result
counts — **not** raw NL query, intent keys, confidence, AI/cache flags or
adapter list.

### 2.2 Gaps for AI workstream

| Need | Status |
| --- | --- |
| NL query + normalised query | Missing |
| Intent / confidence / adapters | Missing |
| Stay-search parity logging | Weak vs providers |
| Knowledge-gap grouping engine | DATA-013 designed, not implemented |
| Admin API `GET /search-gaps` | Phase 1 **inventory only**; capability `planned` |
| Precise location retention policy for NL | Needs explicit design (see §17) |

**Reuse:** extend `DemandRecorder` / analytics tables carefully; do not fork a
second analytics stack.

---

## 3. Data-source and RIC overlap audit

### 3.1 Platform connectors (reuse)

| Piece | Path / note |
| --- | --- |
| ADR 0006 / 0007 | Review-first ingestion; modular intelligence |
| `ConnectorInterface` | `app/Platform/DataSources/` |
| Google Places | Optional, budget-guarded, review-first |
| Candidates | `data_source_import_candidates` + admin queue |
| Secrets | `SecretCipher` / `APP_KEY` |
| Trust | Never auto-publish; claimed providers never overwritten |

### 3.2 Assist RIC sibling

| Piece | Note |
| --- | --- |
| ADR 0015 / 0017 | No production DB; Admin API only; Option B |
| RIC plugins | Discovery, AI classifier, budgets, duplicates |
| RIC AI | Classification **assist** only (RIC ADR 0008) |
| Live API client | Designed; not yet implemented |
| Export packages | Valid until Admin API drafts/imports replace drop path |

### 3.3 Overlap rules (AI-0)

| Concern | Owner |
| --- | --- |
| Public NL intent + search routing | **Platform** Assist AI Orchestrator |
| Research discovery / staging SQLite | **RIC** |
| Production write path | **Admin API** only (ADR 0015) |
| Connector vendors | Platform DATA-006 **or** RIC plugins — same review-first policy; do not build a third connector framework |
| Paid Places | Optional/off; hard caps; no silent fallback (Phase 1 owner policy unchanged) |
| Traveller facilities schema | Deferred to DATA-012 / AI-6; Admin API Phase 1 stays on `/stays` |

**Do not** embed OpenAI calls in public controllers, importers, brand modules,
scheduled jobs or RIC sync clients. All Platform AI calls go through the
orchestrator’s provider abstraction. RIC retains its local AI assist for
offline classification, subject to the same “never invent facts / never publish”
rules; production publish remains Platform + Admin API.

---

## 4. Proposed Assist AI Orchestrator architecture

```
User (Ask VanAssist) or internal caller
        |
        v
Request validation (size, rate, brand, request_id)
        |
        v
Assist AI Orchestrator  (shared platform service)
        |
        +-- IntentRuleEngine (deterministic)
        +-- IntentCache
        +-- AIBudgetService
        +-- IntentInterpreter (AI adapter; optional)
        +-- SearchRouter
        |      +-- ProviderSearchAdapter   → existing Provider/Town/Geo
        |      +-- StaySearchAdapter       → CaravanPark::searchStays
        |      +-- TravellerFacilityAdapter (AI-6; stub until DATA-012)
        |      +-- DatasetSearchAdapter    (AI-5; DATA-006 connectors)
        +-- ResultAggregator (dedupe, rank, provenance labels)
        +-- KnowledgeGapService
        +-- DraftCandidateService (policy-gated staging)
        +-- SearchAnalyticsLogger
        +-- Audit / observability
        |
        v
Platform search response (same card/list UX where practical)
```

**Principles**

1. One shared orchestrator for all brands; brand context from trusted host.
2. Every free-text request enters the orchestrator (logging consistency), even
   when resolved without AI.
3. Source priority: canonical DB → trusted government/council imports →
   OSM-derived/approved → approved external APIs → optional paid → approved
   web research workflows.
4. Success metric: reliable answer **and** more complete trusted local knowledge.

---

## 5. Proposed service and class boundaries

Namespace proposal: `App\Platform\AiSearch\` (shared platform, not VanAssist
controllers).

| Class | Responsibility |
| --- | --- |
| `SearchOrchestrator` | Entry; coordinates pipeline |
| `SearchRequest` / `SearchResponse` | DTOs |
| `IntentRuleEngine` | Keyword/pattern → structured intent |
| `IntentInterpreter` | Calls `AiProviderInterface` when needed |
| `IntentCache` | Normalised interpretation reuse |
| `SearchRouter` | Selects adapters from intent |
| `ProviderSearchAdapter` | Wraps existing provider queries |
| `StaySearchAdapter` | Wraps stays queries |
| `TravellerFacilitySearchAdapter` | Stub until DATA-012 |
| `DatasetSearchAdapter` | DATA-006 connectors (later phases) |
| `ResultAggregator` | Merge, dedupe, provenance |
| `KnowledgeGapService` | Gap upsert / priority |
| `DraftCandidateService` | Stage trusted externals → import candidates |
| `AIBudgetService` | Caps, hard stop |
| `AIUsageService` | Usage rows + admin aggregates |
| `AiProviderInterface` | Vendor-neutral AI port |
| `OpenAiProvider` | First concrete vendor (AI-3) |
| `RulesOnlyProvider` | Zero-cost mode |
| `TaxonomyRegistry` | Stable keys ↔ categories / stay / facility types |
| `IntentSchemaValidator` | Reject invalid model output |

**Public controllers** may only call `SearchOrchestrator` (or a thin facade).
They must not call OpenAI, Places, or Overpass directly.

**Admin API Phase 1 dependency (document only):** gap aggregates and draft
staging eventually use existing designed `/search-gaps`, `/drafts`, `/imports`
surfaces — no new Phase 1 resources required for AI-0 approval.

---

## 6. Proposed taxonomy

### 6.1 Intent types

`find_provider` | `find_stay` | `find_traveller_facility` | `mixed` | `unknown`

### 6.2 Provider category keys

Use existing `service_categories.slug` as stable keys (examples):

| NL examples | Keys |
| --- | --- |
| Mobile caravan repair | `general-caravan-repairs`, `mobile-mechanics` |
| Auto electrician | `auto-electrical-and-batteries` |
| Tyres | `tyres-and-wheels` |
| Towing | `towing-and-vehicle-recovery` |
| Caravan brakes | `brakes-and-bearings` |
| LPG refill | `lpg-refills-and-bottle-exchange` |
| Mechanic | `mechanical-repairs`, `mobile-mechanics`, `diesel-mechanics` |

Display labels never appear in AI schema — only slugs/keys.

### 6.3 Stay type keys

Align with `caravan_parks.stay_type`:
`caravan_park`, `campground`, `free_camp`, `showground`, `rest_area`,
`farm_stay`, `other`.

NL “caravan park nearby” → `find_stay` + `caravan_park` (and optionally
provider category `caravan-parks-and-campgrounds` only when business listing
search is also intended — prefer stay adapter for accommodation).

### 6.4 Traveller facility type keys (future entity)

Design-only until DATA-012 / AI-6:

`public_toilet`, `dump_point`, `drinking_water`, `public_shower`, `laundry`,
`rest_area`, `visitor_information`, `fuel`, `lpg_refill`, `hospital`,
`medical_centre`, `pharmacy`, `emergency_services`, `boat_ramp`, `picnic_area`,
`barbecue`, `waste_disposal`, `ev_charging`, `weighbridge`, `other_essential`.

**Reconciliation:** today’s provider slugs like `dump-points` remain valid for
provider search; facility keys are a separate taxonomy. Orchestrator may map
“dump point near X” to facility adapter when available, else provider category
fallback, never inventing a park row (ADR 0029 / 0027).

---

## 7. Proposed structured AI intent schema

Strict JSON Schema (illustrative; versioned as `intent_schema_v1`):

```json
{
  "intent_type": "find_provider",
  "provider_category_keys": ["dump-points"],
  "stay_type_keys": [],
  "facility_type_keys": ["dump_point"],
  "location_text": "Batehaven",
  "use_current_location": false,
  "radius_km": 25,
  "urgency": "normal",
  "adapter_keys": ["providers", "traveller_facilities"],
  "confidence": 0.86,
  "clarification_required": false,
  "clarification_reason": null
}
```

**Rules**

- Enumerate allowed keys from `TaxonomyRegistry` (reject unknown slugs).
- `confidence` ∈ [0, 1]; below threshold ⇒ clarification or deterministic fallback.
- No free-form “answer” field in the intent layer.
- Invalid / timed-out / budget-blocked ⇒ rules engine or user clarification;
  never invent results.

---

## 8. Proposed deterministic keyword engine

`IntentRuleEngine` runs **before** AI. Pattern packs (versioned
`intent_rules_v1`) include synonyms → taxonomy keys, optional default radius,
urgency hints.

| Pattern family | Example matches | Intent |
| --- | --- | --- |
| Toilets | toilet, public toilet, loo | facility / provider fallback |
| Dump | dump point, sanitary dump, cassette dump | facility / `dump-points` |
| Water | drinking water, potable water | facility / `potable-water-refill` |
| LPG | LPG, gas bottle refill | `lpg-refills-and-bottle-exchange` |
| Parks | caravan park, camp site | stay `caravan_park` |
| Mobile repair | mobile caravan repair | provider repair keys |
| Auto electrical | auto electrician | `auto-electrical-and-batteries` |
| Tyres / towing / brakes | … | matching slugs |

**Ambiguous / multi-intent:** set `intent_type=mixed` or
`clarification_required=true` when confidence low.

All free-text still enters the orchestrator for logging/cache even when rules
fully resolve intent (no AI call).

---

## 9. Proposed cache design

| Layer | Key inputs | Value | TTL (proposal) |
| --- | --- | --- | --- |
| Intent cache | normalised query, brand, locale, taxonomy version, rules version, model id (if AI) | Structured intent | 7–30 days |
| Negative cache | same + failure class | fallback reason | short (minutes) |
| Result cache | **not** default for live geo searches | — | avoid stale distance |

**Safety:** do not cache intents that embed precise GPS; round or drop
coordinates from cache key; prefer town_id when resolved.

Storage options for AI-1/AI-2: MariaDB table `ai_intent_cache` (simple,
auditable) before Redis. Align conceptually with RIC `ai_cache` without sharing
DB.

---

## 10. Proposed AI provider abstraction

```php
interface AiProviderInterface {
    public function name(): string;
    /** @return AiCompletionResult structured payload + usage */
    public function completeStructured(AiCompletionRequest $request): AiCompletionResult;
}
```

Request fields: model allowlist entry, schema id, messages, timeout, max tokens,
correlation id, cache key hint.  
Result fields: parsed array, raw validation status, input/output tokens,
estimated cost AUD, latency, provider request id.

Implementations: `OpenAiProvider`, `RulesOnlyProvider`, future local/hosted.
Switching provider must not change public controllers or result views.

---

## 11. Proposed OpenAI integration approach

**AI-0 constraint:** documentation and owner decision only — **no API calls**,
no key provisioning in this phase.

### 11.1 Official patterns (reviewed for design, re-verify at AI-3)

- Prefer **Structured Outputs** with `json_schema` + `strict: true`
  ([OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs)).
- All schema properties required; `additionalProperties: false`; optional values
  via nullable unions.
- Pin a **snapshot** model id in allowlist settings (alias drift risk).
- Server-side key only; env / secret manager; never MariaDB plaintext; never
  frontend; never logs.
- AI globally disabled until configured (`ai.enabled=false` default).

### 11.2 Model shortlist (candidates — owner must approve before AI-3)

| Candidate class | Rationale |
| --- | --- |
| Lowest-cost mini/nano with strict schema support | Default interpreter |
| Slightly stronger mini | Only if quality gate fails on golden intents |

**Do not hard-code model names in application code.** Store allowlist in
settings. Re-check pricing and schema support on OpenAI’s official docs/pricing
pages at AI-3 start. Prefer cost over capability for intent extraction.

### 11.3 Failure behaviour

Timeout, 4xx/5xx, invalid schema, content refusal → log usage failure →
deterministic fallback → user-visible graceful message if needed → **no paid
retry storm** (bounded retries only).

---

## 12. Proposed cost-control and hard-stop design

Aligned with OPS-010 and DATA-006 budget guards; extend for AI.

| Control | Behaviour |
| --- | --- |
| Global AI enable | Off by default |
| Provider enable | Per vendor |
| Model allowlist | Reject others |
| Daily/monthly request caps | Soft warn + hard stop |
| Daily/monthly AUD budgets | Estimate before call; hard stop |
| Max prompt / output tokens | Enforced |
| Max retries / timeout | Enforced |
| Cache + rules first | Mandatory path |
| No auto model upgrade | Explicit |
| No paid fallback when exhausted | Explicit |

When AI budget exhausted: structured search, keyword intent, cache, local DB,
imported datasets continue; user gets graceful fallback; audit
`ai.budget_blocked`.

---

## 13. Proposed knowledge-gap model

Extends DATA-013. Prefer **grouped** gaps over one row per search.

**Proposed table (AI-4 migration; not applied in AI-0):**
`knowledge_gaps` with fields matching the brief (original/normalised query,
intent, brand, location, radius, taxonomy keys, local/external counts, quality,
AI/external source flags, first/last seen, search count, approx unique users,
CTR/contact, resolution status, priority, assigned research job, resolution
date).

**Grouping key:** hash(brand + normalised_query + intent_type + rounded
location or town_id + taxonomy key set + radius bucket).

**Priority signals:** frequency, urgency, zero-result rate, safety relevance,
remoteness, contact demand, dataset availability.

Admin + future `GET /api/v1/admin/search-gaps` (already in Phase 1 inventory —
**do not rename/remove** in Phase 1; AI-4 fills implementation behind that
contract when Admin API work allows, without expanding Phase 1 locked scope
prematurely).

---

## 14. Proposed external-result and provenance model

Every result card carries:

- `origin`: `canonical` | `imported` | `external_live` | `staged_candidate`
- `source_key`, `source_record_id`, licence, attribution
- `verification_status`, `confidence`, `last_checked`
- `distance_km`, `is_temporary`, `pending_review`

External/unverified results **labelled** distinctly from provider-confirmed
canonical listings (`docs/DATA_TRUST_AND_PROVENANCE.md`, ADR 0025).

Staging: only identifiable source + acceptable trust policy →
`DraftCandidateService` → DATA-006 candidates or Admin API drafts. Duplicate
check via DATA-002 signals; **no auto-merge**; **no AI publish** (ADR 0026).

Trust policies: `trusted_automatic` | `trusted_review` | `community_review` |
`web_research_review` | `prohibited`. No `trusted_automatic` without written
owner decision (Phase 1 policy unchanged).

---

## 15. Proposed RIC hand-off workflow

```
Platform KnowledgeGapService (priority gaps)
    → Admin API GET /search-gaps (when implemented; Phase 1 slot preserved)
    → RIC plans research tasks (budgets, free sources first)
    → Discovery → evidence → classify (RIC AI assist optional)
    → Duplicate proposals (no merge)
    → Human review in RIC
    → Export package and/or POST /drafts|/imports
    → Platform review queue (DATA-006)
    → Publish only via approved Platform workflow
```

Until Admin API search-gaps is live, operators may use admin website insights /
exports. AI-0 does not require changing Phase 1 OpenAPI beyond what’s already
designed.

---

## 16. Proposed public / internal API boundaries

| Surface | Proposal | Phase |
| --- | --- | --- |
| Internal PHP | `SearchOrchestrator::handle(SearchRequest)` | AI-1 |
| Browser form | Parallel “Ask VanAssist” POST/GET under VanAssist web routes | AI-1+ |
| Possible public JSON | `POST /api/v1/search/assist` | **Deferred naming** until `docs/API.md` + routing review; not Phase 1 Admin |
| Admin | Existing Phase 1 `/search-gaps`, `/drafts`, `/imports`, `/audit` | CORE-011 |
| Vendor AI HTTP | **Never** exposed to browsers | — |

Response shape: platform search results envelope (providers/stays/facilities +
meta: intent, confidence, provenance, fallback_reason) — **not** raw model text.

---

## 17. Privacy and security threat analysis

| Threat | Mitigation |
| --- | --- |
| Prompt injection via query/source text | Treat all external text untrusted; structured output only; no tool-calling from user content; system instructions fixed |
| Cost abuse / scraping | Rate limits, anonymous throttles, CAPTCHA escalation, request size caps |
| Secret leakage | Env secrets; redacted logs; no key in JS/DB |
| SSRF via connectors | URL allowlists; block link-local/metadata IPs |
| Location privacy | Do not retain precise GPS longer than needed; round/derive for long-term analytics; document retention |
| Data exfiltration via model | Minimal prompt (taxonomy + query + coarse location); no PII dumps |
| Hallucinated businesses | AI cannot add result rows; adapters only |
| Privilege bypass | Orchestrator does not grant admin; staging uses existing RBAC |
| Cross-brand leakage | Brand from host context only |

Full write-up: `docs/AI_SECURITY.md`.

---

## 18. Database migration proposal

**AI-0:** no migrations applied.

| Phase | Proposed migrations (forward-only; names TBD at implement) |
| --- | --- |
| AI-1 | Optional feature flag rows only if not covered by existing flags; NL search log columns **or** additive `assist_searches` table |
| AI-2 | `ai_settings`, `ai_usage_daily`, `ai_intent_cache`, budget counters |
| AI-3 | none required beyond AI-2 if settings hold model allowlist |
| AI-4 | `knowledge_gaps` (+ events link to searches) |
| AI-5 | provenance columns on staged results if not covered by DATA-006/014 |
| AI-6 | `traveller_facilities` (+ provenance) — **only after DATA-012 approval**; out of Admin API Phase 1 |

Never edit applied migrations. Never store API keys in schema plaintext.

---

## 19. Exact file-level implementation plan

### AI-0 (this phase) — documentation only

| Action | Path |
| --- | --- |
| Create | `docs/PHASE_AI0_DESIGN.md` (this file) |
| Create | `docs/AI_*.md` topic set (§21) |
| Create | `docs/DECISIONS/0018`–`0027` |
| Update | `docs/START_HERE.md`, `docs/ARCHITECTURE_DECISION_RECORDS.md`, `docs/PRODUCT_BACKLOG.md`, `docs/API.md`, `CHANGELOG.md` |

### AI-1+ (after approval) — illustrative; not authorised yet

| Area | Paths |
| --- | --- |
| Orchestrator | `app/Platform/AiSearch/**` |
| Thin web entry | `app/Controllers/Site/AssistSearchController.php` (new) |
| Routes | `routes/web.php` (additive Ask VanAssist; **keep** `/find`) |
| Views | `resources`/`app/Views/public/` Ask partial + reuse `provider-result-card` |
| Tests | `tests/Unit/AiSearch/**`, feature tests |
| Config | `config/ai_search.php` (flags, limits; models via settings) |
| Admin | usage/budget/gap screens under existing admin IA |

**Do not modify** locked Phase 1 Admin API controllers/OpenAPI except when a
later approved increment implements already-inventoried `/search-gaps` behind
CORE-011.

**Preserve:** `SearchController`, location JSON contracts, stays directory.

---

## 20. Testing plan

See `docs/AI_TESTING.md`. Summary matrices:

- Deterministic intents: toilets, dump, water, LPG, parks, mobile repair, auto
  electrician, tyres, towing, ambiguous, multi-intent.
- Interpreter: valid/invalid schema, timeout, provider failure, budget, cache,
  low confidence, injection, unsupported.
- Routing: provider/stay/facility/mixed; local adequate; external fallback;
  source down; zero results.
- Cost: daily/monthly request+AUD; hard stop; soft warn; no upgrade; no paid
  fallback; graceful no-AI.
- Knowledge: gap create/group/priority; stage; duplicate; untrusted reject;
  trusted_automatic only when configured.
- Analytics: search/AI/click/contact logs; location privacy rounding.
- **No test may use or modify production data.**

---

## 21. Documentation plan

| Doc | Role |
| --- | --- |
| `AI0_OWNER_DECISION_BRIEF.md` | Owner approve/amend checklist |
| `AI_ORCHESTRATOR.md` | Shared service overview |
| `AI_SEARCH_ENGINE.md` | Pipeline and adapters |
| `NATURAL_LANGUAGE_SEARCH.md` | Ask VanAssist UX |
| `SEARCH_PIPELINE.md` | Step-by-step request flow |
| `KNOWLEDGE_ENGINE.md` | Growth loop |
| `KNOWLEDGE_GAPS.md` | Gap model + RIC hand-off |
| `TRAVELLER_FACILITIES.md` | Future entity + taxonomy (cross-link ADR 0029) |
| `DATASET_ROUTING.md` | Source priority + DATA-006 reuse |
| `AI_PROVIDER_ABSTRACTION.md` | Vendor port |
| `OPENAI_INTEGRATION.md` | Official API notes; owner approval gate |
| `AI_COST_CONTROLS.md` | Budgets and hard stops |
| `AI_USAGE_AND_OBSERVABILITY.md` | Metrics |
| `SEARCH_CACHE.md` | Intent cache |
| `RESULT_PROVENANCE.md` | Labels and trust |
| `AI_SECURITY.md` | Threat model |
| `AI_OPERATIONS_RUNBOOK.md` | Enable/disable, incidents |
| `AI_TESTING.md` | Test matrices |
| `AI_INTENT_SCHEMA.md` | Strict intent JSON Schema v1 |
| `AI_INTENT_RULES.md` | Deterministic rule pack v1 |
| `AI_MIGRATIONS_PROPOSAL.md` | Forward migration plan by phase |
| `AI1_IMPLEMENTATION_PLAN.md` | File-level AI-1 plan (post-approval) |

Cross-link Assist RIC docs and Phase 1 Admin API; **do not duplicate** their
ownership.

**Note:** CORE-011 Admin API increments (e.g. auth Increment 2) may land in
parallel. AI work must not alter Phase 1 endpoint inventory, stays naming, or
traveller-facility deferral.

---

## 22. Risk register

| ID | Risk | Likelihood | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| R1 | Cost blowout | M | H | Off by default; hard stops; rules-first |
| R2 | Hallucinated listings | M | H | AI never creates result facts |
| R3 | Competing search UX confusion | M | M | Parallel Ask UI; preserve `/find` |
| R4 | Taxonomy collision (dump as provider vs facility) | H | M | Explicit dual mapping; ADR 0029/0027 |
| R5 | Interference with Admin API Phase 1 | M | H | Document-only dependencies; no scope change |
| R6 | Privacy (GPS retention) | M | H | Rounding + retention policy |
| R7 | Prompt injection | M | M | Structured output; no instruction following from sources |
| R8 | Overpass abuse | M | M | Configurable adapter; no public endpoint hammering |
| R9 | Duplicate publish | M | H | DATA-002 before stage/publish |
| R10 | Premature traveller_facilities migration | L | H | Blocked until DATA-012 / AI-6 approval |

---

## 23. Estimated effort by phase

Rough engineering days (one experienced PHP engineer familiar with the repo;
excludes owner review latency and production data work):

| Phase | Scope | Estimate |
| --- | --- | --- |
| AI-0 | Design/audit/docs/ADRs | **Done in this package** |
| AI-1 | Deterministic orchestrator + logging + feature flag | 5–8 d |
| AI-2 | Cache + settings + budget + admin visibility | 4–6 d |
| AI-3 | OpenAI interpreter + validation + fallback | 4–6 d |
| AI-4 | Knowledge gaps + admin + RIC hand-off hooks | 5–8 d |
| AI-5 | Dataset routing + OSM + provenance labels + staging | 8–12 d |
| AI-6 | Traveller facilities (after DATA-012) | 8–14 d |
| AI-7 | Hardening, abuse, cost sim, release gate | 4–6 d |

**Critical path dependency:** CORE-011 Admin API for live RIC sync and
`/search-gaps` consumption; structured search and AI-1–AI-3 can proceed without
blocking on full Admin API if gaps are admin-UI-only initially.

---

## 24. Decisions requiring owner approval

1. Approve this AI-0 package and authorise **AI-1 only** (deterministic, AI off).
2. Confirm Ask VanAssist UX copy and placement (alongside, not replacing `/find`).
3. Confirm initial daily/monthly AI request and AUD hard caps (VanAssist has no
   revenue — recommend conservative caps, e.g. low double-digit AUD/month until
   measured).
4. Approve first OpenAI model snapshot allowlist at AI-3 (after fresh official
   pricing/schema verification).
5. Confirm secret storage approach (env / vault) for `OPENAI_API_KEY`.
6. Confirm no dataset is `trusted_automatic` without a written decision (reaffirm).
7. Confirm Google Places / paid connectors remain disabled by default for NL
   fallback.
8. Confirm precise GPS retention window for NL analytics.
9. Confirm whether `POST /api/v1/search/assist` is desired for mobile later, or
   web-only first.
10. Confirm CORE-012 backlog id and VAN-011 status move to `discovery`/`ready`.
11. Confirm AI-6 / DATA-012 sequencing relative to Admin API Phase 1 completion.
12. Confirm RIC remains sole research client (ADR 0017) for gap fulfilment.

---

## Admin API Phase 1 — dependency map (no scope change)

| Phase 1 item | AI workstream use |
| --- | --- |
| Auth / scopes / MFA scaffolding | Later admin AI settings & gap APIs |
| `/providers`, `/stays` | Canonical truth; adapters read via app models initially |
| `/drafts`, `/imports` | Staging path for trusted externals (AI-5+) |
| `/search-gaps` | DATA-013 / AI-4 implementation target (already planned) |
| `/audit` | AI enablement and budget changes |
| `traveller_facilities: planned` | Unchanged; AI designs adapters only |
| Paid Places policy | Unchanged |

---

## Rollback / non-goals reminder

- Feature flag off ⇒ identical pre-AI public behaviour.
- No general chatbot, trip planner, booking, payments or marketplace in this
  workstream.
- No production implementation, paid API calls or traveller-facility migration
  in AI-0.
