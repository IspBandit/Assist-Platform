# Phase AI-1 file-level plan

**Status:** implemented on `feature/core-012-ai-1-deterministic` (AI-0 approved).  
AI-2 cache/budget is **not** in this increment — deferred until AI-1 is merged.

## Goal (AI-1) — done

Deterministic orchestrator foundation: validation, keyword intent, taxonomy
mapping, search logging, provider + stay routing, feature flag **off**. No
external datasets, no paid AI.

## New files (proposed)

```
app/Platform/AiSearch/
  SearchOrchestrator.php
  Dto/SearchRequest.php
  Dto/SearchResponse.php
  Dto/Intent.php
  Intent/IntentRuleEngine.php
  Intent/IntentNormaliser.php
  Intent/TaxonomyRegistry.php
  Intent/IntentSchemaValidator.php
  Routing/SearchRouter.php
  Adapters/ProviderSearchAdapter.php
  Adapters/StaySearchAdapter.php
  Adapters/TravellerFacilitySearchAdapter.php  # stub throws/returns empty
  Adapters/DatasetSearchAdapter.php           # stub disabled
  Aggregate/ResultAggregator.php
  Logging/AssistSearchLogger.php
  Support/AiSearchFeature.php

app/Controllers/Site/AssistSearchController.php
app/Views/public/assist-search.php            # or partial on home
app/Views/partials/ask-vanassist.php
config/ai_search.php

tests/Unit/AiSearch/IntentRuleEngineTest.php
tests/Unit/AiSearch/IntentNormaliserTest.php
tests/Unit/AiSearch/SearchRouterTest.php
tests/Feature/AiSearch/AssistSearchFlagOffTest.php
```

## Touched existing files (additive only)

| File | Change |
| --- | --- |
| `routes/web.php` | Add Ask VanAssist route(s); keep `/find` |
| `app/Views/public/home.php` | Optional Ask partial behind flag |
| `database/seeds/data.php` | `assist_ai_search` flag false |
| Migration (new) | `assist_searches` per proposal |
| `docs/*` | Mark AI-1 implemented sections |

## Must not change

- `SearchController` behaviour/contract for structured `/find`  
- Location JSON contracts  
- `/stays` structured filters  
- Admin API Phase 1 routes/OpenAPI inventory (CORE-011)  
- Traveller facility production schema  

## Reuse

`Provider::{forCategory,forCategoryNear,inTown}`, `CaravanPark::searchStays`,
`Town`, `Geo`, `ProviderCoverage`, `DemandRecorder` / ActivityTracker where
compatible, existing result card partials.

## Exit evidence for AI-1

- Flag off ⇒ identical public behaviour  
- Flag on + rules ⇒ golden queries route to provider/stay adapters  
- Tests green; no OpenAI dependency  
- Docs updated; cost impact = zero paid AI  
