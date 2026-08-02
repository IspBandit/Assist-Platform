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
7. **Knowledge-gap processing** — `KnowledgeGapService::observe` for weak/zero/
   unknown; returns `knowledgeGapId` for interaction attribution.
8. **Draft-candidate processing** — admin/CLI / dataset jobs via
   `DraftCandidateService` (not live Ask Overpass / Places).
9. **Analytics + usage** — `AssistSearchLogger`, `AIUsageService`.

Invalid AI output falls back to deterministic intent or clarification. No
unrestricted conversational text from the intent layer.
