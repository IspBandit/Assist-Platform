# Polaris — Recommendation Engine

- **Status:** Planned (Phase 3)
- **Date:** 2026-08-01
- **Backlog:** POL-004
- **ADR:** [0007-recommendation-engine.md](DECISIONS/0007-recommendation-engine.md)

---

## Purpose

Rank published RV variants for guided **Find My RV** results using deterministic,
explainable rules — not opaque ML or generative recommendations.

---

## Design principles

1. **Hybrid scoring:** Weighted rule engine over structured specs + user answers.
2. **Explainability:** Every ranked result includes human-readable match reasons.
3. **Uncertainty penalty:** Missing required specs reduce score; never imputed.
4. **No AI authority:** LLMs may not compute scores or replace rule engine.
5. **Tow-aware (Phase 4 extension):** Tow compatibility integrates as weighted
   factor after TowSmart boundary service exists.

---

## Inputs

### User profile (from `/find`)

| Field | Type | Required |
| --- | --- | --- |
| `categories` | enum[] | Yes |
| `trip_frequency` | enum | No |
| `regions` | string[] | No |
| `off_road` | bool | No |
| `berths_min` | int | No |
| `length_max_m` | decimal | No |
| `atm_max_kg` | int | No |
| `price_max_cents` | int | No |
| `layout_prefs` | map | No |
| `tow_vehicle_id` | int | No (Phase 4) |

### Variant facts (from catalogue)

Normalised spec values + price + category + status=published.

---

## Scoring model (v1)

```
total_score = Σ (weight_i × factor_i) − penalty_missing − penalty_stale_price
```

Capped 0–100; banded for display:

| Band | Score | Label |
| --- | --- | --- |
| A | 75–100 | Strong fit |
| B | 50–74 | Good fit |
| C | 25–49 | Possible trade-offs |
| D | 0–24 | Unlikely fit |

### Example factors (initial weights TBD in implementation)

| Factor | Weight | Logic |
| --- | --- | --- |
| Category match | high | Exact category in user set |
| Berths | medium | variant ≥ min |
| Length | medium | variant ≤ max if set |
| ATM | high | variant ≤ max if set |
| Price | medium | within band |
| Off-road spec | low | enum match if requested |
| Layout prefs | low | partial keyword match on tags |
| Tow compatibility | high | Phase 4 TowSmart service result |

### Penalties

| Condition | Penalty |
| --- | --- |
| Missing `atm_kg` when user set tow limits | −15 |
| Missing length when user set length max | −10 |
| Price older than 12 months | −5 + stale badge |
| Spec confidence `unknown` on critical field | −10 |

---

## Output shape

```json
{
  "variant_id": 123,
  "score": 82,
  "band": "A",
  "reasons_positive": ["Within your ATM limit", "Sleeps 4 — meets berth need"],
  "reasons_negative": ["Price at top of your band"],
  "data_gaps": ["Tare weight not provided"]
}
```

---

## Service boundary

`App\Platform\Polaris\Matching\RecommendationEngine` (Planned)

- Pure PHP; no I/O inside score function (inject specs DTO)
- Unit tested with fixture variants
- Cache results per session hash optional; not required v1

---

## Non-goals

- Collaborative filtering (“users like you”)
- Sponsored boost in organic Find results (sponsored slots separate, labelled)
- Real-time ML retraining
- Generative natural-language result narratives

---

## Analytics

Log: find_session_id, variant_ids shown, band distribution, click-through.
No score manipulation post-hoc.

---

## Implementation status

| Item | Status |
| --- | --- |
| Find questionnaire persistence | Scaffolded |
| Rule engine core | Planned |
| Weight configuration (admin) | Planned (post v1) |
| Tow factor integration | Planned (Phase 4) |
| A/B weight tuning | Planned (Phase 9) |

---

## Related documents

- [USER_JOURNEYS.md](USER_JOURNEYS.md)
- [TOWSMART_INTEGRATION.md](TOWSMART_INTEGRATION.md)
- [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md)
