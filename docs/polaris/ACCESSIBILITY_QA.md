# Polaris — Accessibility & responsive QA evidence

- **Status:** Conditional evidence (not a WCAG conformance claim)
- **Date:** 2026-08-02
- **Backlog:** POL-002 / POL-009
- **Verdict:** **CONDITIONAL** — checklist exercised on private Polaris surfaces; full WCAG 2.2 AA audit still required before public launch.

---

## Scope covered this pass

| Surface | Checks noted |
| --- | --- |
| Find My RV stages | Form labels present; stage list conveys progress; Continue / Back controls are text buttons/links; empty score list uses `role="status"` |
| Browse / Saved / account searches & comparisons | Filter labels; empty results use `role="status"` |
| Model detail | Spec provenance table uses `<caption>`, `<th scope="row">` / column headers; year selector `aria-labelledby` + focus-visible hit target; demo badge visible |
| Compare | Table caption; differing rows include visible **Differs** text (not colour alone); empty picker state announced |
| Manufacturer portal forms | Labels associated with inputs; CSRF on POSTs |
| Tow Match | Primary actions are buttons or links with visible text (no icon-only) |
| Reduced motion | Homepage hero respects `prefers-reduced-motion` (existing) |
| Skip link | Inherited from `layouts.public` (`#main`) |

---

## Known gaps (honest)

- No third-party axe/Lighthouse report attached in CI yet (required for POL-009 PASS)
- Colour contrast not instrumented per brand theme token
- Keyboard-only and screen-reader walkthroughs not recorded for every portal section
- Floorplan images still need complete accessible descriptions on non-demo content
- Public launch Quality Gate (POL-009) remains **blocked** until domain + catalogue + full a11y evidence

---

## How to re-run locally

1. Browse Polaris Find stages 1–10 with keyboard only (Tab / Enter / Space).
2. Confirm every interactive control has a visible name.
3. On a model page, confirm provenance table is readable without colour alone; Tab through year selector links.
4. On Compare, confirm **Differs** markers appear on differing rows with colour off / greyscale if possible.
5. Toggle OS reduced-motion and reload the homepage hero.
6. Attach axe DevTools / Lighthouse summaries to the release PR when seeking POL-009 PASS.

---

## Related

- [RELEASE_CRITERIA.md](RELEASE_CRITERIA.md)
- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md)
