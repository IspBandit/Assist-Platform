# Polaris — AI Architecture

- **Status:** Planned for Polaris-specific use; platform orchestrator Scaffolded
- **Date:** 2026-08-01
- **Backlog:** POL-004, POL-007
- **ADR:** [0008-ai-provider-abstraction.md](DECISIONS/0008-ai-provider-abstraction.md)

Polaris AI usage aligns with platform ADRs **0018–0027**. Polaris does not
operate a separate model stack.

---

## Platform ADR alignment

| ADR | Principle | Polaris application |
| --- | --- | --- |
| 0018 | Shared orchestrator | All vendor calls via `App\Platform\AiSearch` / future orchestrator |
| 0019 | Interpretation not authority | NL maps to filters/intent; never invents specs |
| 0020 | Provider-neutral abstraction | No OpenAI calls in Polaris controllers |
| 0021 | Cache-first routing | Repeat NL queries hit cache before paid AI |
| 0022 | Hard budget enforcement | Fail closed when budget exceeded |
| 0023 | NL alongside structured | `/ask` additive; `/rvs` filters unchanged |
| 0024 | Knowledge gaps | Unmatched queries log gaps for catalogue growth |
| 0025 | External staged provenance | External hits labelled; not mixed as verified specs |
| 0026 | No direct AI publishing | Imports stay draft until admin publish |
| 0027 | Domain boundaries | No AI-generated VanAssist/TowSmart records in Polaris |

---

## Permitted AI uses

| Use case | Phase | Output |
| --- | --- | --- |
| NL search intent parsing | 5+ | Filter object + taxonomy keys |
| Find questionnaire paraphrase helper | 3+ | UI copy suggestions only (optional) |
| Import field extraction | 6 | Draft spec values + confidence |
| Duplicate manufacturer suggestion | 6 | Candidate list for merge UI |
| Conflict summary | 6 | Human-readable diff for reviewer |

---

## Prohibited AI uses

- Generating specification values not present in source material
- Inventing prices, dimensions or tow ratings
- Auto-publishing catalogue entities
- Conversational “which RV should I buy” factual answers without catalogue grounding
- Scraping bypass or credential discovery
- Replacing structured Find scoring (see [RECOMMENDATION_ENGINE.md](RECOMMENDATION_ENGINE.md))

---

## Architecture diagram

```
Public /ask or import job
        │
        ▼
Assist AI Orchestrator (platform)
        │
        ├── IntentNormaliser / SchemaValidator
        ├── Budget + cache layer
        └── Provider adapter (env-configured)
        │
        ▼
Polaris adapter (Planned)
        ├── SearchRouter → MariaDB catalogue adapters
        └── ImportDraftMapper → polaris_import_drafts
```

**Status:** Platform AI-1 module exists (`App\Platform\AiSearch`); Polaris-specific
adapter **Planned**.

---

## Feature flags

| Flag | Default | Scope |
| --- | --- | --- |
| `assist_ai_search` | off | Platform NL search |
| `polaris_ai_import` | off (Planned) | Extraction drafts |
| `polaris_ask` | off (Planned) | Polaris-branded `/ask` |

Polaris public site must function fully with all AI flags off.

---

## Logging and observability

Extend `AssistSearchLogger` pattern:

- Query hash (not raw PII)
- Intent JSON
- Adapter routes taken
- Token/cost estimate
- Cache hit/miss
- Budget denial events

---

## Failure modes

| Condition | User-visible behaviour |
| --- | --- |
| Budget exceeded | “Search temporarily unavailable”; structured search works |
| Provider timeout | Retry once; then graceful degradation |
| Invalid intent schema | Fallback to keyword search on name fields |
| AI disabled | Hide `/ask` entry points |

---

## Testing

- Unit: intent schema validation with Polaris taxonomy extensions
- Integration: mock provider; assert no DB writes on AI path
- Flag-off regression: catalogue browse/find unchanged

See [TESTING_STRATEGY.md](TESTING_STRATEGY.md).

---

## Implementation status

| Item | Status |
| --- | --- |
| Platform orchestrator | Scaffolded (flag off) |
| Polaris taxonomy registry | Planned |
| Polaris search adapter | Planned |
| Import extraction mapper | Planned |
| `/ask` Polaris UI | Planned |

---

## Related documents

- [SEARCH_ARCHITECTURE.md](SEARCH_ARCHITECTURE.md)
- [DATA_ACQUISITION.md](DATA_ACQUISITION.md)
- [docs/DECISIONS/0019-ai-interpretation-not-authority.md](../DECISIONS/0019-ai-interpretation-not-authority.md)
