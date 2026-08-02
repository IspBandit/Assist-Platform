# Search intent cache

**Status:** implemented (Phase AI-2).  
**ADR:** 0021 (accepted).  
**Table:** `ai_intent_cache`

## Purpose

Reuse prior normalised interpretations so identical or near-identical queries
avoid paid AI calls (AI-3+) and skip re-running expensive paths.

## Cache key inputs

- Normalised query text  
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
- TTL from `ai_settings.intent_cache_ttl_hours` (default 168).  
- Cache failures never break search.

## Layers

Intent cache is mandatory before paid AI. Result caching for live geo searches
is **not** default (stale distance risk).
