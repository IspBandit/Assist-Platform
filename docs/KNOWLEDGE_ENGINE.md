# Knowledge engine

**Status:** AI-4 gaps + interaction analytics live; AI-5 staging via
`DraftCandidateService` / DATA-012 / offline OSM CLI.  
**Backlog:** DATA-013, DATA-012, CORE-012.  
**Related:** [`KNOWLEDGE_GAPS.md`](KNOWLEDGE_GAPS.md),
[`RESULT_PROVENANCE.md`](RESULT_PROVENANCE.md),
[`DATA_TRUST_AND_PROVENANCE.md`](DATA_TRUST_AND_PROVENANCE.md).

## Goal

Every natural-language search should improve the platform through one or more of:

- Demand recorded (`assist_searches`)  
- Interpretation cached (`ai_intent_cache`)  
- Locality or category gap identified (`knowledge_gaps`)  
- Dataset / OSM offline candidates staged (DATA-006 review)  
- Duplicate signals captured on stage  
- Useful interaction recorded (click-through / contact counters)  
- Synonym / taxonomy mismatch visible via gap + intent JSON  

## Growth loop

```
User Ask
  → Orchestrator (rules → cache → optional AI)
  → Local + approved adapters
  → Adequate local? return + log + cache hit path next time
  → Weak/zero/unknown? upsert knowledge_gap + priority
  → Operator: dataset fetch / OSM offline stage / RIC research
  → Duplicate check → human approve → publish
  → Future Ask uses local record
```

## Current implementation

| Outcome | Mechanism |
| --- | --- |
| Adequate local | Log success; no gap row |
| Inadequate / unknown | `KnowledgeGapService::observe` |
| Interaction | `recordClickThrough` / `recordContactAction` |
| External stage | `DraftCandidateService` + trust policy |
| RIC hand-off | Admin gaps UI + SearchGap JSON export (locked Admin API path deferred to CORE-011) |

Never blindly store every result. Never treat AI as a live data source. Never
auto-publish from Ask.
