# Search intent cache

**Status:** design (Phase AI-0).  
**ADR:** 0021 (proposed).

## Purpose

Reuse prior normalised interpretations so identical or near-identical queries
avoid paid AI calls.

## Cache key inputs

- Normalised query text  
- Brand  
- Locale  
- Location context where relevant (prefer town_id; avoid raw GPS)  
- Taxonomy version  
- Intent-rule version  
- AI model version when the entry was AI-produced  

## Safety

- Do not cache unsafe or injection-tainted interpretations.  
- Short TTL for failures.  
- Invalidate on taxonomy/rules version bumps.  
- Prefer MariaDB `ai_intent_cache` initially for auditability.

## Layers

Intent cache is mandatory before AI. Result caching for live geo searches is
**not** default (stale distance risk).
