# Polaris — Search Architecture

- **Status:** Scaffolded (MariaDB structured); NL Planned
- **Date:** 2026-08-01
- **Backlog:** POL-006
- **ADR:** [0009-search-technology.md](DECISIONS/0009-search-technology.md)

---

## Strategy

**Structured-first:** MariaDB queries with indexed filters are the primary search
path. Optional natural-language search augments via shared AI orchestrator when
flagged — never replaces structured browse.

This matches platform ADR 0026 (NL alongside structured).

---

## Search surfaces

| Surface | Route | Engine | Status |
| --- | --- | --- | --- |
| Catalogue browse | `/rvs` | SQL filters + sort | Scaffolded |
| Manufacturer search | `/manufacturers` | SQL + name match | Scaffolded |
| Find results | `/find/results` | Recommendation engine | Planned |
| NL search | `/ask` | AI intent → SQL adapters | Planned |
| Compare picker | `/rvs` (modal) | Same as browse | Planned |

---

## Structured query model

### Filter parameters

| Param | Maps to |
| --- | --- |
| `category` | model_families.category |
| `manufacturer` | manufacturer slug |
| `berths_min` | spec join `berths` |
| `length_max` | spec join `length_m` |
| `atm_max` | spec join `atm_kg` |
| `price_max` | latest polaris_prices |
| `q` | FULLTEXT name search (Phase 2) |

### Sort options

- `relevance` (default when `q` set)
- `price_asc` / `price_desc`
- `length_asc`
- `name_asc`

### Pagination

Offset/limit server-side; max 50 per page.

---

## Indexing (MariaDB)

Phase 2 targets:

- FULLTEXT on `(manufacturer.name, model_family.name)`
- Composite indexes on hot spec joins
- Covering index for list cards (variant id, name, thumbnail, key specs)

No Elasticsearch/OpenSearch at launch.

---

## NL search path (optional)

```
User query → Orchestrator → Intent JSON
    → PolarisSearchAdapter
        → Normalised filters
        → SQL (same as /rvs)
    → ResultAggregator (platform pattern)
        → Shared result cards
```

Polaris adapter implements catalogue-only entities — no VanAssist/TowSmart
invention (ADR 0030 boundary).

**Fail closed:** budget denied → hide NL UI message; structured unaffected.

---

## Caching

| Layer | Scope | Status |
| --- | --- | --- |
| HTTP cache | None on authenticated; short CDN for public assets only | Existing |
| Query cache | Optional Redis/file for popular filter combos | Planned |
| AI intent cache | Platform cache-first (ADR 0024) | Scaffolded |

Catalogue mutations invalidate filter count cache keys.

---

## Knowledge gaps

Unmatched NL queries or zero-result filter sets log to knowledge gap table
(platform ADR 0027) for catalogue prioritisation — not user-visible errors.

---

## Security

- Parameterised queries only
- Rate limit `/ask` and `/rvs?q=` endpoints
- No raw SQL from AI intent — whitelist filter keys

---

## Implementation status

| Item | Status |
| --- | --- |
| `/rvs` filter SQL | Scaffolded |
| FULLTEXT name search | Planned (Phase 2) |
| Polaris AiSearch adapter | Planned |
| Filter facet counts | Planned (Phase 2) |
| Search analytics events | Planned (Phase 9) |

---

## Related documents

- [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md)
- [INFORMATION_ARCHITECTURE.md](INFORMATION_ARCHITECTURE.md)
- [DATA_ARCHITECTURE.md](DATA_ARCHITECTURE.md)
