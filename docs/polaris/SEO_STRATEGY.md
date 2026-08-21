# Polaris — SEO Strategy

- **Status:** Planned (Phase 2 foundations; Phase 9 launch)
- **Date:** 2026-08-01
- **Backlog:** POL-009

---

## Current state (private brand)

While brand status is **`private`** and domain is unconfirmed:

- All public pages: `noindex, nofollow`
- `X-Robots-Tag: noindex` header
- No sitemap served to crawlers
- robots.txt disallow all for Polaris hosts

**Status:** Planned enforcement in Phase 1 layout.

---

## Launch objectives

1. Index high-quality model and manufacturer pages
2. Win long-tail queries (“2026 {model} specifications Australia”)
3. Avoid thin/duplicate URLs from variant permutations
4. Support rich results where schema validates

---

## URL strategy

| Page type | Canonical pattern |
| --- | --- |
| Model | `/rvs/{manufacturer}/{model}` |
| Manufacturer | `/manufacturers/{slug}` |
| Browse | `/rvs` (+ filters as query params, not separate canonicals) |
| Compare | `/compare?ids=` — `noindex` (utility page) |
| Find results | session-specific — `noindex` |

Model year: prefer query `?year=` or default current year on canonical.

---

## On-page SEO

| Element | Rule |
| --- | --- |
| Title | `{Model} {Year} \| {Manufacturer} \| Polaris` |
| Meta description | First 155 chars of sourced overview; no AI fabrications |
| H1 | Model name + category |
| Internal links | Manufacturer, related models, buying guides |
| Images | Descriptive alt text; floorplan labels |

---

## Structured data (JSON-LD)

Phase 9 target — `Product` or `Vehicle` schema where Google guidelines allow:

- name, brand, category
- offers (if price verified with date)
- weight properties when present
- Do not markup missing specs as known values

Validate with Rich Results Test before launch.

---

## Sitemap

`/sitemap-polaris.xml` (Planned):

- Published manufacturers
- Published model families/variants
- Buying guides
- Exclude compare, find results, account pages

Regenerate on publish/unpublish events.

---

## Performance SEO

- Server-rendered lists (Existing pattern)
- Lazy-load below-fold images per design system
- Avoid JS-only content for core specs

---

## Content programme

Buying guides (`/buying-guides`) provide editorial depth:

- Human-written, fact-checked
- Link to catalogue entities
- Not LLM-generated specification pages

---

## Implementation status

| Item | Status |
| --- | --- |
| noindex while private | Planned |
| Canonical tags | Planned (Phase 2) |
| JSON-LD | Planned (Phase 9) |
| Sitemap generation | Planned (Phase 9) |
| Search Console setup | Blocked (no domain) |

---

## Related documents

- [INFORMATION_ARCHITECTURE.md](INFORMATION_ARCHITECTURE.md)
- [RELEASE_CRITERIA.md](RELEASE_CRITERIA.md)
- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
