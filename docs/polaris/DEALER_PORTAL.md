# Polaris — Dealer Portal

- **Status:** Planned (Phase 7)
- **Date:** 2026-08-01
- **Backlog:** POL-008

---

## Purpose

Support **dealer organisations** that represent manufacturers to buyers — without
turning Polaris into a dealer inventory or used-stock system.

---

## Scope (in)

| Capability | Description |
| --- | --- |
| Dealer profile | Business name, regions served, contact preferences |
| Manufacturer associations | Link to authorised manufacturers (admin-approved) |
| Contact routing | “Enquire with dealer” CTA on model pages (Phase 8) |
| Team access | Org users with dealer role |

## Scope (out)

| Excluded | Reason |
| --- | --- |
| Used stock listings | Product boundary |
| Dealer-specific pricing | Unless sourced and provenance-recorded |
| Independent spec authority | Specs live on manufacturer variants |
| Parallel model records | Dealers reference catalogue IDs only |

---

## Access model

Same claim-first pattern as manufacturers:

1. Claim dealer organisation
2. Admin verifies association with manufacturer(s)
3. Portal access via `polaris.dealer.edit` permission

---

## Portal sections

| Section | Status |
| --- | --- |
| Dashboard | Planned |
| Profile & regions | Planned |
| Linked manufacturers | Planned |
| Enquiry settings (email routing) | Planned (Phase 8) — model page uses mailto/website handoff only |
| Analytics (enquiry clicks) | Partially implemented (`dealer_enquiry_click` on handoff) |

---

## Model page integration (Phase 8)

Optional block on model detail:

- Dealer name, town, “Contact dealer” button
- Disclosure: “Dealer response times vary”
- No stock count or “available now” unless fed by verified integration (not v1)

---

## Data model (Planned)

- `polaris_dealer_profiles` → organisation_id, regions JSON
- `polaris_dealer_manufacturer` pivot → approval status
- No `polaris_dealer_stock` table

---

## Implementation status

| Item | Status |
| --- | --- |
| Dealer portal routes | Planned |
| Manufacturer association approval | Planned |
| Model page contact CTA | Planned (Phase 8) |
| Inventory feeds | **Not planned** |

---

## Related documents

- [MANUFACTURER_PORTAL.md](MANUFACTURER_PORTAL.md)
- [PRODUCT_BOUNDARIES.md](PRODUCT_BOUNDARIES.md)
