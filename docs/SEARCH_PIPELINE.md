# Search pipeline

**Status:** design (Phase AI-0).  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md) §4–§8.

## Ordered steps

1. **Validate** — character limit, abuse controls, rate limit, brand context,
   location permission, request ID.
2. **Deterministic keyword/pattern engine** — resolve obvious queries without
   AI when confidence is sufficient. Request still enters the orchestrator.
3. **Intent cache** — reuse safe normalised interpretations.
4. **AI interpretation** — only when deterministic confidence is insufficient
   or the request is complex; subject to budget.
5. **Search routing** — providers, stays, traveller facilities, approved
   datasets, future adapters.
6. **Result aggregation** — dedupe, rank, provenance, verification, distance,
   external labels.
7. **Knowledge-gap processing** — weak/missing coverage.
8. **Draft-candidate processing** — trusted externals only, policy-gated.
9. **Analytics + audit**.

Invalid AI output falls back to deterministic search or clarification. No
unrestricted conversational text from the intent layer.
