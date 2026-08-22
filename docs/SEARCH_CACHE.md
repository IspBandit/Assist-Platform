# Search intent cache

**Status:** implemented (Phase AI-2).  
**ADR:** 0021 (accepted).  
**Table:** `ai_intent_cache`

## Purpose

Reuse prior normalised interpretations so identical or near-identical queries
avoid paid AI calls (AI-3+) and skip re-running expensive paths.

## Cache key inputs

- Normalised query text, preserving location semantics such as `near me`
- Brand  
- Locale  
- Taxonomy version  
- Intent-rule version  
- Intent schema version  
- AI model version when the entry was AI-produced  

Precise GPS is **not** part of the key.

## Behaviour

- Lookup after query normalisation; on hit, source=`cache`.  
- On miss, deterministic rules run; successful intents are stored.  
- Unknown / very low-confidence intents are not cached.  
- TTL from `ai_settings.intent_cache_ttl_hours` (default 720 / 30 days).
- Cache failures never break search.

## Bundled question library

`ask_question_library` is pre-seeded from the current deterministic intent
taxonomy with more than 1,000 common Australian traveller question variants.
Exact matches resolve before the expiring intent cache or paid AI. The seed is
idempotent, versioned with the rules engine, contains no user or GPS data, and
records only aggregate hit counts.

## Layers

Intent cache is mandatory before paid AI. Google Routes distance and duration
content is not persisted; identical destinations are deduplicated within each
request and the response is radius-filtered before display.
