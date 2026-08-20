# Polaris — Analytics and Metrics

- **Status:** Partially implemented (event write path; Phase 9); dashboards Planned
- **Date:** 2026-08-01
- **Backlog:** POL-009

Extends platform first-party analytics with Polaris-specific events
(`App\Services\Polaris\AnalyticsService`). Free-text prompts are stripped;
failures never break product flows.

---

## Principles

- First-party events only at launch
- No fake engagement metrics in UI
- Distinguish structured vs NL search (ADR 0026)
- PII minimisation in event payloads

---

## Core events

| Event | Trigger | Key properties |
| --- | --- | --- |
| `polaris.page_view` | Any public page | page_type, variant_id? |
| `polaris.rv_list_view` | `/rvs` | filters_hash, result_count |
| `polaris.rv_detail_view` | Model detail | variant_id, manufacturer_id |
| `polaris.find_start` | `/find` step 1 | session_id |
| `polaris.find_step` | Each step complete | step, session_id |
| `polaris.find_complete` | Results shown | session_id, result_count |
| `polaris.find_result_click` | Click result card | variant_id, band, rank |
| `polaris.compare_add` | Add to compare | variant_id, set_size |
| `polaris.compare_view` | `/compare` | variant_ids |
| `polaris.save_model` | Save shortlist | variant_id, user_id |
| `polaris.tow_match_run` | Tow check | variant_id, tow_vehicle_id?, status |
| `polaris.search_nl` | `/ask` submit | intent_hash, adapter_routes |
| `polaris.provider_click` | VanAssist card | provider_id, variant_id |
| `dealer_enquiry_click` | Contact dealer handoff | dealer_id, model_id?, channel |

---

## Funnel metrics

| Funnel | Stages |
| --- | --- |
| Find | start → step 5 → results → detail click |
| Browse | list → detail → save/compare |
| Tow | open → vehicle selected → result → TowSmart click |

---

## Manufacturer analytics (Phase 7 / 9)

Portal `/portal/manufacturer/analytics` aggregates (manufacturer-scoped):

- Detail views per model (7/30/90 day) — **implemented** (`rv_viewed`)
- Save count — **implemented** (`rv_saved`)
- Find impression count (in results) — Planned (event not wired)
- Dealer enquiry clicks — Planned

No public view counts displayed to visitors.

---

## Knowledge gaps

Feed platform gap pipeline (ADR 0027):

- Zero-result filter sets
- NL intents with no adapter match
- Find sessions with zero band-A results

---

## Dashboards (Planned)

Admin internal:

- Catalogue coverage (% variants with ATM, price, image)
- Import queue SLA
- Search zero-result rate

---

## Implementation status

| Item | Status |
| --- | --- |
| Event catalogue spec | Existing (this doc) |
| Client instrumentation | Planned |
| Server-side page_view | Planned |
| Manufacturer dashboard | Views/saves implemented; find impressions planned |
| Third-party analytics | Not planned at launch |

---

## Related documents

- [RECOMMENDATION_ENGINE.md](RECOMMENDATION_ENGINE.md)
- [SEO_STRATEGY.md](SEO_STRATEGY.md)
- [RELEASE_CRITERIA.md](RELEASE_CRITERIA.md)
