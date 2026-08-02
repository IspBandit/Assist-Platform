# Polaris — VanAssist Integration

- **Status:** Partially implemented (related-services surfacing; Phase 8)
- **Date:** 2026-08-01
- **Backlog:** POL-008
- **ADR:** [0006-vanassist-integration.md](DECISIONS/0006-vanassist-integration.md)

---

## Boundary

**VanAssist owns** repairers, mobile mechanics, warranty agents, caravan service
providers and related directory data. Polaris **surfaces** relevant providers on
model and decision pages — it does not create or duplicate provider records.

---

## User value

After researching an RV, buyers often need:

- Service agents for warranty work
- Installers (solar, suspension, towing equipment)
- Regional repair support

Polaris links out to VanAssist discovery with context — not embedded fake listings.

---

## Display surfaces

| Surface | Content | Status |
| --- | --- | --- |
| Model detail — Related services | Up to N provider cards | Planned |
| Manufacturer profile | Preferred service network (if claimed) | Planned |
| Find results footer | “Find service near you” CTA | Planned |
| Buying guides | Editorial links to VanAssist categories | Planned |

---

## Selection logic (v1)

`App\Platform\Polaris\VanAssist\ProviderSurfacingService` (Planned)

Inputs:

- User region (session geo or profile)
- RV category → VanAssist category mapping
- Optional manufacturer partnership flag (future)

Query:

- Read-only VanAssist provider search API or shared repository
- Filter: published, brand-scoped VanAssist, permission-safe fields only
- Order: distance if geo available, else relevance

Outputs:

- Card: name, slug, town, primary category, link to VanAssist profile
- Label: “Service listing on VanAssist”

No phone/email fabrication; empty state if none found.

---

## Category mapping (example)

| Polaris category | VanAssist categories |
| --- | --- |
| caravan | caravan repair, mobile service |
| motorhome | motorhome service |
| camper_trailer | trailer service |
| hybrid | caravan repair |

Mapping table in config — not AI-inferred at runtime.

---

## Sponsored placement

If VanAssist sponsored listings exist, they must follow platform rules:

- Visually labelled “Sponsored”
- Must not replace organic empty states deceptively
- Separate analytics events

---

## Cross-brand links

Pattern: `https://vanassist.com.au/find?category={key}&near={town}`

Use brand URL helper from `Brand` registry — not hard-coded production URLs in views.

---

## AI boundary

NL search must not invent providers (platform ADR 0022, 0027). Provider cards
always originate from VanAssist adapters.

---

## Implementation status

| Item | Status |
| --- | --- |
| Provider surfacing service | Planned |
| Model page block | Planned |
| Category mapping config | Planned |
| Manufacturer preferred network | Planned (post Phase 7) |
| Provider CRUD in Polaris | **Not planned (boundary)** |

---

## Related documents

- [PRODUCT_BOUNDARIES.md](PRODUCT_BOUNDARIES.md)
- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
- [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md)
