# Polaris — User Journeys

- **Status:** Partially implemented (journeys 1–3 scaffolded; 4–6 planned)
- **Date:** 2026-08-01
- **Backlog:** POL-003, POL-004

---

## Journey 1: Browse and evaluate a model

**Actor:** Anonymous visitor  
**Goal:** Understand whether a specific new RV fits their interest  
**Status:** Scaffolded

| Step | Touchpoint | System behaviour |
| --- | --- | --- |
| 1 | Homepage or `/rvs` | Category chips; filter list |
| 2 | Apply filters | MariaDB structured query; pagination |
| 3 | Open model detail | Load variant + specs + provenance |
| 4 | View floorplan tab | Image gallery; download if permitted |
| 5 | Check price guidance | Show source, as-at date, disclaimer |
| 6 | Optional: save | Redirect to login → `/saved` (Planned) |

**Success:** User leaves with understandable specs and known data gaps.  
**Failure modes:** Missing specs show “Not provided” not blank; stale price shows warning.

---

## Journey 2: Guided Find My RV

**Actor:** Anonymous or registered visitor  
**Goal:** Narrow catalogue to a ranked shortlist  
**Status:** Scaffolded (shell); scoring Planned (Phase 3)

| Stage | Questions (summary) | Status |
| --- | --- | --- |
| 1 — Travel | Trips per year, typical duration, regions | Scaffolded |
| 2 — Setup | Tow vs motorised; off-road intent | Scaffolded |
| 3 — Towing | Has tow vehicle? ATM budget; length limits | Scaffolded |
| 4 — Living | Berths, layout prefs, bathroom/kitchen | Scaffolded |
| 5 — Budget | Price band; new-only confirmation | Scaffolded |
| Results | Ranked models + explain blocks | Planned |

**Success:** At least three relevant models with readable reasons.  
**Registered extension:** Save answers and results (Planned).

---

## Journey 3: Tow compatibility check

**Actor:** Visitor with tow vehicle in mind  
**Goal:** See guidance on whether an RV is plausible for their rig  
**Status:** Planned (Phase 4)

| Step | Touchpoint | System behaviour |
| --- | --- | --- |
| 1 | `/tow-match` or model page block | Entry form |
| 2 | Select tow vehicle | TowSmart catalogue lookup or Garage link |
| 3 | Select RV variant | Polaris catalogue |
| 4 | View result | Guidance panel: ATM vs tow capacity, margins, disclaimers |
| 5 | Deep link | Open TowSmart calculator for full breakdown |

**Success:** User understands guidance is non-certifying; sees confidence label.  
**Boundary:** Polaris never stores authoritative tow ratings.

---

## Journey 4: Compare models

**Actor:** Registered or anonymous visitor  
**Goal:** Side-by-side decision on shortlisted models  
**Status:** Planned (Phase 5)

| Step | Touchpoint | System behaviour |
| --- | --- | --- |
| 1 | Add to compare from list/detail | Cookie or account-backed set (max 4) |
| 2 | `/compare` | Normalised spec rows; highlight diffs |
| 3 | Share | Read-only link (registered) |
| 4 | Proceed | Link to manufacturer/dealer contact (Phase 8) |

**Success:** Comparison handles missing specs asymmetrically (shows gap, not hide row).

---

## Journey 5: Manufacturer maintains catalogue

**Actor:** Verified manufacturer representative  
**Goal:** Keep model year data accurate  
**Status:** Planned (Phase 7)

| Step | Touchpoint | System behaviour |
| --- | --- | --- |
| 1 | Claim organisation | Platform claim workflow |
| 2 | Portal dashboard | Pending reviews, draft models |
| 3 | Edit variant specs | Draft row; validation against spec definitions |
| 4 | Submit for review | Admin queue (until auto-trust tier exists) |
| 5 | Publish | Audit log entry; cache invalidation |

**Success:** Manufacturer cannot edit other brands; changes are traceable.

---

## Journey 6: Administrator imports and merges data

**Actor:** Platform administrator  
**Goal:** Add catalogue rows from external sources safely  
**Status:** Planned (Phase 6)

| Step | Touchpoint | System behaviour |
| --- | --- | --- |
| 1 | Create import job | URL/PDF/CSV upload |
| 2 | AI-assisted extraction (optional) | Draft fields only; flag-gated |
| 3 | Review draft | Side-by-side source snippet |
| 4 | Resolve duplicate | Merge UI per ADR 0012 |
| 5 | Publish | Provenance record; soft-delete available |

**Success:** No import publishes without source attachment and reviewer action.

---

## Journey map summary

| Journey | Primary routes | Phase | Status |
| --- | --- | --- | --- |
| Browse model | `/rvs`, `/rvs/{m}/{model}` | 1–2 | Scaffolded |
| Find My RV | `/find` | 3 | Scaffolded / Planned |
| Tow match | `/tow-match` | 4 | Planned |
| Compare | `/compare` | 5 | Planned |
| Manufacturer maintain | `/portal/manufacturer` | 7 | Planned |
| Admin import | `/admin/polaris/imports` | 6 | Planned |

---

## Related documents

- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
- [RECOMMENDATION_ENGINE.md](RECOMMENDATION_ENGINE.md)
- [DATA_ACQUISITION.md](DATA_ACQUISITION.md)
