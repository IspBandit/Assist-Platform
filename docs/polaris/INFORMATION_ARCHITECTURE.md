# Polaris — Information Architecture

- **Status:** Scaffolded (routes specified; partial wiring)
- **Date:** 2026-08-01
- **Backlog:** POL-002, POL-003

Routes follow Assist Platform PHP MVC conventions (`routes/web.php`, brand-scoped
controllers under `app/Controllers/Site/Polaris/` or equivalent).

---

## Public navigation (primary)

| Label | Route | Purpose | Status |
| --- | --- | --- | --- |
| Home | `/` | Hero, category entry, Find CTA | Partially implemented |
| Find My RV | `/find` | Guided matching questionnaire | Scaffolded (brand-dispatched via `SearchController`) |
| Browse RVs | `/rvs` | Filterable catalogue list | Partially implemented |
| Model detail | `/rvs/{manufacturer_slug}/{model_slug}` | Variant/year detail | Partially implemented |
| Compare | `/compare` | Side-by-side comparison | Scaffolded |
| Manufacturers | `/manufacturers` | Manufacturer index | Partially implemented |
| Manufacturer | `/manufacturers/{slug}` | Brand profile + models | Partially implemented |
| Tow match | `/tow-match` | Tow vehicle vs RV check | Scaffolded |
| Floorplans | `/floorplans` | Floorplan gallery/browse | Scaffolded |
| Buying guides | `/buying-guides` | Editorial decision content | Scaffolded |
| Guide article | `/buying-guides/{slug}` | Single guide | Scaffolded |
| Saved | `/saved` | Shortlist (auth required) | Scaffolded |
| Ask Polaris | `/ask` | Optional NL search (flagged) | Planned |

**Route note:** `/find` is shared with VanAssist provider search. Polaris matching
is selected when `current_brand()->id() === 'polaris'`. Do not register a second
`/find` route.

**Secondary footer:** About, contact, privacy, terms (shared platform pages where
applicable), link to TowSmart calculator, VanAssist find services.

---

## Public account routes (shared auth)

| Route | Purpose | Status |
| --- | --- | --- |
| `/account/login` | Sign in | Existing |
| `/account/register` | Create account | Existing |
| `/account/profile` | Profile settings | Existing |
| `/account/garage` | Tow vehicles / assets (shared Garage) | Existing |
| `/account/saved-rvs` | Polaris shortlist alias → `/saved` | Planned |

Account routes remain brand-aware but reuse platform controllers and layouts.

---

## Manufacturer portal (`/portal/manufacturer`)

| Route | Purpose | Status |
| --- | --- | --- |
| `/portal/manufacturer` | Dashboard | Planned (Phase 7) |
| `/portal/manufacturer/models` | Model list | Planned |
| `/portal/manufacturer/models/{id}` | Edit model / variants | Planned |
| `/portal/manufacturer/media` | Floorplans and images | Planned |
| `/portal/manufacturer/team` | Organisation users | Planned |
| `/portal/manufacturer/analytics` | Views and saves | Planned (Phase 9) |

Claim entry reuses platform organisation claim flow before portal access.

---

## Dealer portal (`/portal/dealer`)

| Route | Purpose | Status |
| --- | --- | --- |
| `/portal/dealer` | Dashboard | Planned (Phase 7) |
| `/portal/dealer/profile` | Dealer profile and regions | Planned |
| `/portal/dealer/manufacturers` | Linked manufacturers | Planned |

Dealers do not receive inventory or listing routes.

---

## Administration (`/admin`)

Module gate: `rv_catalogue` (feature flag + permission).

| Section | Path pattern | Status |
| --- | --- | --- |
| Dashboard | `/admin/polaris` | Scaffolded |
| Manufacturers | `/admin/polaris/manufacturers` | Scaffolded |
| Model families | `/admin/polaris/models` | Scaffolded |
| Model years / variants | `/admin/polaris/variants` | Scaffolded |
| Spec definitions | `/admin/polaris/specs/definitions` | Scaffolded |
| Spec values | `/admin/polaris/specs/values` | Scaffolded |
| Floorplans | `/admin/polaris/floorplans` | Scaffolded |
| Import drafts | `/admin/polaris/imports` | Planned (Phase 6) |
| Duplicate review | `/admin/polaris/duplicates` | Planned (Phase 6) |
| Sources | `/admin/polaris/sources` | Planned (Phase 2) |

Admin uses shared layout, brand switcher and audit logging.

---

## URL and slug rules

- Manufacturer slug: lowercase, hyphenated, unique globally in Polaris catalogue.
- Model slug: unique per manufacturer.
- Model detail resolves **current default model year** when year omitted; explicit
  year via query `?year=2026` or variant segment in later phases.
- Canonical URLs on model pages; comparison uses query `?ids=1,2,3` (max four).
- Private brand: `X-Robots-Tag: noindex` until release (see SEO doc).

---

## Content hierarchy

```
Home
├── Find (guided)
├── Browse (/rvs)
│   └── Model detail
│       ├── Specs
│       ├── Floorplans
│       ├── Tow match (link)
│       └── Related providers (VanAssist)
├── Manufacturers
│   └── Manufacturer profile
│       └── Models
├── Compare
├── Tow match
├── Floorplans (cross-model)
├── Buying guides
└── Saved (auth)
```

---

## Cross-brand links (outbound)

| From Polaris | To | Pattern |
| --- | --- | --- |
| Model tow section | TowSmart calculator | Deep link with prefill where supported |
| Service block | VanAssist `/find` | Category + region query params |
| Garage reference | TowSmart / account garage | Shared asset ID |

No inbound requirement for other brands to embed Polaris until API exists.

---

## Search entry points

1. **Structured:** `/rvs` filters (category, manufacturer, length, ATM, price band).
2. **Find:** `/find` staged questionnaire → results.
3. **NL (optional):** `/ask` when `assist_ai_search` or Polaris-specific flag enabled.

Structured search remains primary per platform ADR 0023.

---

## Related documents

- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
- [SEARCH_ARCHITECTURE.md](SEARCH_ARCHITECTURE.md)
- [ADMINISTRATION.md](ADMINISTRATION.md)
