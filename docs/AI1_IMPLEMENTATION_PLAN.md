# Phase AI-1 file-level plan

**Status:** implemented on `feature/core-012-ai-1-deterministic` (AI-0 approved 2026-08-01).  
AI-2 cache/budget is **not** in this increment.

## Goal (AI-1) — done

Deterministic orchestrator foundation: validation, keyword intent, taxonomy
mapping, search logging, provider + stay routing, feature flag **off**. No
external datasets, no paid AI.

## Files delivered

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
  Adapters/TravellerFacilitySearchAdapter.php  # stub
  Adapters/DatasetSearchAdapter.php           # stub
  Aggregate/ResultAggregator.php
  Logging/AssistSearchLogger.php
  Support/AiSearchFeature.php

app/Controllers/Site/AssistSearchController.php
app/Views/public/assist-search.php
app/Views/partials/ask-vanassist.php
config/ai_search.php
database/migrations/085_assist_ai_search.sql
tests/Unit/AiSearch/*
```

## Touched existing files (additive)

| File | Change |
| --- | --- |
| `routes/web.php` | `GET /ask` |
| `app/Views/public/home.php` | Ask teaser partial |
| `database/seeds/data.php` | `assist_ai_search` => false |

## Exit evidence

- Flag off ⇒ `/ask` 404; structured `/find` unchanged  
- Flag on + rules ⇒ golden queries route to provider/stay adapters  
- Tests: 34 unit tests green; PHPStan clean on AI-1 code  
- No OpenAI dependency; cost impact = zero paid AI  
