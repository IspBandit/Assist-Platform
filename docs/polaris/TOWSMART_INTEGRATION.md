# Polaris — TowSmart Integration

- **Status:** Planned (Phase 4)
- **Date:** 2026-08-01
- **Backlog:** POL-005
- **ADR:** [0005-towsmart-integration.md](DECISIONS/0005-towsmart-integration.md)

---

## Boundary

**TowSmart owns** tow vehicle catalogues, GVM/GCM/towing capacity data and the
towing calculator. **Polaris owns** RV variant weights and dimensions for new models.

Polaris must **not** duplicate tow vehicle tables or compute certifying legal outcomes.

---

## Integration goals

1. Let buyers check **guidance-level** compatibility between a tow vehicle and RV.
2. Deep-link to TowSmart calculator with sensible prefills.
3. Feed tow-aware factors into Find scoring (Phase 4+).
4. Reuse shared Garage tow vehicle selections where logged in.

---

## Read paths

| Consumer | Data needed | Source |
| --- | --- | --- |
| Tow match UI | Vehicle max tow, payload context | TowSmart service/API |
| Model page | RV ATM, ball weight, tare | Polaris specs |
| Find engine | Compatibility band | Combined service result |
| Garage link | User’s saved tow vehicle | Platform Garage |

---

## Service design (Planned)

`App\Platform\Polaris\TowSmart\CompatibilityService`

```
check(variant_id, tow_vehicle_id): CompatibilityResult
```

`CompatibilityResult` fields:

- `status`: within_guidance | caution | insufficient_data | exceed_guidance
- `margin_kg`: optional numeric
- `assumptions`: string[] (passengers, accessories disclaimer)
- `confidence`: high | medium | low
- `towsmart_calculator_url`: deep link

Pure read — no writes to TowSmart schema from Polaris.

---

## UX patterns

- Always show **guidance disclaimer** (ADR 0013 language).
- Link: “Full breakdown on TowSmart” opens calculator in new tab.
- If tow vehicle unknown: prompt Garage sign-in or manual TowSmart browse.
- Never label result “legal to tow” unless citing specific regulated source outside
  platform scope (default: avoid).

---

## Data Polaris displays from RV side

| Spec | Use |
| --- | --- |
| ATM | Compare to tow capacity |
| Tare | Payload discussion |
| Ball weight | Tow bar/downforce context |
| Length | Separate UX hint (not TowSmart core) |

Missing RV specs → `insufficient_data`; penalise in Find scoring.

---

## TowSmart JSON / provenance debt

Platform backlog `TOW-001` notes incomplete tow catalogue provenance. Polaris
integration must:

- Surface TowSmart confidence where available
- Not upgrade low-confidence tow data silently
- Block “green tick” UI when either side lacks verified specs

---

## Phase delivery

| Phase | Deliverable |
| --- | --- |
| 4a | Tow match page read-only check |
| 4b | Model page towing section + deep links |
| 4c | Find scoring tow factor |
| 4d | Garage prefill |

---

## Implementation status

| Item | Status |
| --- | --- |
| Compatibility service | Planned |
| `/tow-match` route | Planned |
| Calculator deep links | Planned |
| Tow vehicle DB in Polaris | **Not planned (boundary)** |

---

## Related documents

- [PRODUCT_BOUNDARIES.md](PRODUCT_BOUNDARIES.md)
- [RECOMMENDATION_ENGINE.md](RECOMMENDATION_ENGINE.md)
- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
