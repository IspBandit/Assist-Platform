# Search pipeline

**Status:** implemented in `App\Platform\AiSearch\SearchOrchestrator`.  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md) §4–§8.

## Ordered steps (runtime)

1. **Validate** — `max_query_length`, brand context, request ID, optional lat/lng
   bounds; public rate limit + honeypot on `/ask`.
2. **Deterministic keyword/pattern engine** — `IntentRuleEngine` /
   `IntentNormaliser` (`intent_rules_v1`). Obvious queries resolve without AI.
3. **Intent cache** — `IntentCache` keyed by brand + normalised query + taxonomy /
   rules / schema / model versions.
4. **AI interpretation** — `IntentInterpreter` via `AiProviderInterface` only when
   rules confidence is insufficient; subject to `AIBudgetService` hard stops.
5. **Search routing** — `SearchRouter` → providers / stays / traveller_facilities /
   datasets (flags apply).
6. **Result aggregation** — `ResultAggregator` dedupes, ranks, attaches provenance.
7. **Surrounding-town fill** — when fewer than 3 category matches remain for a
   resolved town, merge providers that serve immediate neighbouring towns
   (`town_neighbours`), still category-scoped and labelled.
8. **Places rescue (optional)** — when `provider_places_rescue` is on and provider
   results are zero/weak, `ZeroResultProviderRescueService` may call Google Places
   (budget-gated), surface labelled `external_live` cards, and auto-create
   unclaimed listings (ADR 0042). Not general browsing.
9. **Knowledge-gap processing** — `KnowledgeGapService::observe` for weak/zero/
   unknown; returns `knowledgeGapId` for interaction attribution.
10. **Draft-candidate processing** — admin/CLI / dataset jobs via
   `DraftCandidateService` (not live Overpass). Places rescue is a separate path.
11. **Analytics + usage** — `AssistSearchLogger`, `AIUsageService`.

Invalid AI output falls back to deterministic intent or clarification. No
unrestricted conversational text from the intent layer.

Provider category searches without an explicit radius use the shared
`ProviderSearchRadiusLadder` (25 → 75 → 150 → 300 km) before related-category
fallback. When fewer than `geo.provider_search_min_results` (default 3)
category matches remain for a resolved town, Ask and `/find` then fill from
immediate `town_neighbours` using the **same categories** (never an unfiltered
regional pool). Places rescue remains last when the flag is on.

## Town neighbour graph

`town_neighbours` is rebuilt from active towns with measurable coordinates
(same state, within `geo.neighbour_max_km` default 50 km, up to
`geo.neighbour_limit` default 8 nearest). Edges are bidirectional.
`Town::neighbours()` reads that table (ordered by distance, limit 8).

- Automatic: `TownNeighbourGraphBuilder::afterMigrations()` runs from
  `php scripts/migrate.php` after town coordinate activation.
- Manual / after coordinate corrections:
  `php scripts/rebuild-town-neighbours.php` (`--dry-run`, `--force`).
- Migration `140` clears a stale graph fingerprint so the next migrate rebuilds.
