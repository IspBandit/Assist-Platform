# AI testing

**Status:** implemented with AI-1–AI-7 (`tests/Unit/AiSearch`,
`tests/Unit/DataSources`).  
**Rule:** no automated test may use or modify production data.

## Coverage map (prompt matrix → tests)

| Area | Covered by |
| --- | --- |
| Deterministic golden intents (toilet, dump, water, LPG, park, mobile, electrician, tyres, towing, brakes, ambiguous) | `IntentRuleEngineTest` |
| 1,000-entry regional Ask corpus (intent routing; regenerate via `tools/generate-ask-question-corpus.php`) | `AskQuestionCorpusTest` |
| Multiple-intent / mixed | `PromptMatrixCoverageTest` + rules engine |
| AI valid / invalid schema / timeout / null parsed / no allowlist / injection | `IntentInterpreterTest` |
| Provider failure (non-timeout) | `PromptMatrixCoverageTest` |
| Low confidence / unsupported unknown AI payload | `PromptMatrixCoverageTest` |
| Budget daily/monthly request+AUD, soft warn, provider disabled, zero caps | `AIBudgetAndCacheTest` |
| Cache key stability; orchestrator budget/cache/no-AI path markers | `AIBudgetAndCacheTest`, `PromptMatrixCoverageTest` |
| No silent model upgrade (allowlist reject) | `PromptMatrixCoverageTest` (OpenAiProvider) |
| Routing provider/stay/facility/mixed/dataset augment | `SearchRouterTest`, `TravellerFacilities*` |
| No Overpass from Ask; offline OSM seed | `OsmOfflineSeedConnectorTest` |
| Gap key/priority/SearchGap export; click/contact scoring | `KnowledgeGapServiceTest` |
| Staging policy / Ask-blocked / empty hits | `DraftCandidateServiceTest` |
| Analytics wiring (routes, `?g=`, unlock, honeypot, logger classes) | `PromptMatrixCoverageTest`, `AiHardeningTest` |
| Location privacy | `AiHardeningTest` |
| Flag off defaults; `/ask` + `/find` registered | `AssistSearchFlagOffTest` |

## Deterministic intent engine

Toilets, dump points, drinking water, LPG, caravan parks, mobile caravan
repair, auto electrician, tyres, towing, ambiguous query, multiple-intent query.

## AI interpreter

Valid structured response, invalid schema, timeout, provider failure, budget
exhausted (orchestrator fallback reason), cache-first ordering, low confidence,
prompt injection attempt, unsupported intent.

## Routing

Provider-only, stay-only, facility-only, mixed; local adequate; external
fallback / unavailable; no result messaging; no live Overpass.

## Cost control

Daily/monthly request and currency limits; hard stop; soft warning; no silent
model upgrade; no paid fallback; graceful no-AI operation.

## Knowledge engine

Gap grouping keys; priority with click/contact; untrusted/prohibited not staged;
Ask-blocked connectors rejected; trusted_automatic never auto-publishes.

## Analytics

Search + usage loggers present; click/contact routes wired; location privacy
applied.

## Tooling

PHPUnit unit tests; never live paid calls in CI by default.
