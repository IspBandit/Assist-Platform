# Polaris — UX Specification

- **Status:** Partially implemented (homepage hero live; inner pages scaffolded)
- **Date:** 2026-08-01
- **Backlog:** POL-002, POL-003
- **Design system:** [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)

---

## Global UX rules

- Server-rendered PHP views first; progressive enhancement for compare tray and
  Find step persistence.
- All specification values show unit (metres, kg, litres) and provenance chip.
- Empty states are designed — never silent blanks.
- Mobile: filters collapse to drawer; comparison scrolls horizontally per spec group.
- No fake urgency, review counts or “others viewing” patterns.

---

## Homepage

### Hero

**Headline:** Research your next RV with confidence  
**Subhead:** Compare new caravans, hybrids and motorhomes with clear specifications,
honest pricing guidance and tow compatibility — built for Australian travel.

**Primary CTA:** Find My RV → `/find`  
**Secondary CTA:** Browse all models → `/rvs`

**Capability rail (below hero):**

| Tile | Copy | Link |
| --- | --- | --- |
| Find | Answer a few questions; see models that fit | `/find` |
| Browse | Filter by category, manufacturer and specs | `/rvs` |
| Tow match | Check guidance against your tow vehicle | `/tow-match` |
| Compare | Put models side by side | `/compare` |

**Status:** Partially implemented — copy and structure defined; art-directed hero
photography follows platform premium visual contract (Australian RV travel scene,
no baked-in text).

### Category entry

Horizontal chips: Caravan, Hybrid, Camper trailer, Motorhome, Campervan, Slide-on.
Each chip deep-links to `/rvs?category={key}`.

---

## Guided Find (`/find`)

### Stage chrome

- Progress: Step {n} of 5 — {stage title}
- Back / Continue buttons; Continue disabled until required answers valid
- Optional “Skip tow details” only on stage 3 with explicit uncertainty banner

### Stages

| Step | Title | Key inputs |
| --- | --- | --- |
| 1 | How you travel | Trip frequency, trip length, states/regions |
| 2 | What you’re looking for | RV category; motorised vs towable; off-road yes/no |
| 3 | Towing | Tow vehicle known (Y/N); select from list or enter ATM limit; max length |
| 4 | Living space | Berths; fixed bed; bathroom; kitchen layout prefs |
| 5 | Budget | Price band slider; confirm new models only |

### Results (Planned Phase 3)

- Top match card with score badge (e.g. “Strong fit” / “Possible with trade-offs”)
- Explain list: matched rules (+) and gaps (−)
- Actions: View model, Add to compare, Save (auth)
- Footer disclaimer: Recommendations are guidance; verify specs with manufacturer.

**Status:** Scaffolded shell with stages; scoring UI Planned.

---

## Browse list (`/rvs`)

- Sticky filter bar: category, manufacturer, berths, length max, ATM max, price
- Sort: Relevance (default), price, length, manufacturer A–Z
- Card: thumbnail, name, category badge, key specs (length, ATM, berths), price
  from/unknown, provenance summary
- Pagination server-side

**Status:** Scaffolded.

---

## Model detail (`/rvs/{manufacturer}/{model}`)

### Header

- Manufacturer name (link), model name, category, **model year selector** (`?year=YYYY`; shown when ≥2 published years; non-current years labelled)
- Canonical URL remains `/rvs/{manufacturer}/{model}` without the year query
- Hero image or placeholder
- Price guidance block with source + date
- CTAs: Compare, Save, Tow match (Phase 4)

### Sections (anchor nav)

| Section | Content | Status |
| --- | --- | --- |
| Overview | Marketing description (sourced); key highlights | Scaffolded |
| Specifications | Grouped tables: dimensions, weights, chassis, interior | Scaffolded |
| Floorplans | Gallery + variant labels | Planned |
| Towing | ATM, tare, ball weight; link to TowSmart | Planned |
| Running costs | Optional editorial; not AI-fabricated | Planned |
| Manufacturers & dealers | Contact routing (Phase 8) | Planned |
| Related services | VanAssist provider cards (Phase 8) | Planned |
| Provenance | Source list, last reviewed, confidence | Planned (Phase 2) |

### Spec row pattern

```
Label          Value          Source
ATM            2,950 kg       Manufacturer brochure 2026-01
Tare           Unknown        —
```

Missing values render em dash with muted “Not provided” screen-reader text.

---

## Compare (`/compare`)

- Column per model (max 4); sticky model headers
- Rows grouped: Weights, Dimensions, Layout, Price
- Diff highlighting where values differ
- Export/share (registered) Planned

---

## Manufacturer index (`/manufacturers`)

- Search by name; filter by category specialization
- Card: logo, name, model count, country of manufacture if known

---

## Tow match (`/tow-match`)

- Two-column selector: tow vehicle | RV variant
- Result panel uses warning/success semantic colours from design system
- Mandatory disclaimer component before results

---

## Error and empty states

| State | Message tone |
| --- | --- |
| No results | Adjust filters or try Find My RV |
| Model unpublished | This model is not currently available |
| Private brand | Development preview — not indexed |

---

## Related documents

- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)
- [INFORMATION_ARCHITECTURE.md](INFORMATION_ARCHITECTURE.md)
- [RECOMMENDATION_ENGINE.md](RECOMMENDATION_ENGINE.md)
