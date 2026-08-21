# Polaris — Design System

- **Status:** Partially implemented (CSS variables scaffolded)
- **Date:** 2026-08-01
- **Backlog:** POL-002 (extends EXP-001)
- **Parent:** [docs/PLATFORM_DESIGN_SYSTEM.md](../PLATFORM_DESIGN_SYSTEM.md)

Polaris extends the shared Assist Platform design system. It does **not** introduce
a parallel component library. Shared shells, form controls, tables, cards and
admin patterns remain platform-standard.

---

## Brand identity

Polaris targets **informed, calm decision-making** — expedition-ready without
macho clichés. Avoid purple gradients, cream-terracotta “AI product” palettes,
and generic chatbot aesthetics.

### Colour tokens

Register in brand theme map (`config/brands.php` → CSS custom properties):

| Token | Value | Usage |
| --- | --- | --- |
| `--brand-primary` | `#1a3a4a` | Deep slate — headers, primary buttons, nav |
| `--brand-accent` | `#c4a574` | Warm brass — highlights, active chips, badges |
| `--brand-surface` | `#f7f5f2` | Warm off-white page background |
| `--brand-primary-contrast` | `#ffffff` | Text on primary |
| `--brand-accent-contrast` | `#1a3a4a` | Text on accent |
| `--brand-muted` | `#5a6b75` | Secondary text |
| `--brand-border` | `#d8d4cf` | Dividers, card borders |

Semantic tokens (`--color-success`, `--color-warning`, `--color-danger`) remain
**platform shared** — do not fork.

### Typography

- Inherit platform stack (system UI + defined fallbacks from `app.css`).
- Headings: semibold; sentence case for UI labels, title case for marketing hero only.
- Spec tables: tabular numerals where available (`font-variant-numeric: tabular-nums`).

### Spacing and layout

- Follow platform spacing scale (4px base).
- Content max-width: match public layout (`--content-max-width`).
- Model detail uses two-column desktop (main + sidebar CTA); single column ≤979px.

---

## Components (brand extensions)

Reuse platform components; Polaris-specific variants:

| Component | Notes |
| --- | --- |
| `polaris-hero` | Art-directed photography + live HTML headline; AVIF/WebP per platform contract |
| `spec-table` | Label / value / provenance chip columns |
| `provenance-chip` | Source name + confidence colour (verified, imported, unknown) |
| `find-stepper` | Horizontal progress + stage panel |
| `match-score-badge` | Strong / Moderate / Weak — not percentage-only |
| `compare-column` | Sticky header; diff highlight row |
| `tow-guidance-panel` | Warning-first; links TowSmart |

No new button primitives — use `.btn-primary` mapped to `--brand-primary`.

---

## Imagery

- Subject: Australian RV travel — caravan on sealed or gentle unsealed road, realistic lighting.
- No baked-in text, logos or UI in image assets.
- Desktop 1824×864 + mobile 720×960 crops per platform premium visual contract.
- Floorplans: neutral background; manufacturer watermark allowed if supplied.

---

## Motion

- Subtle transitions only (150–200ms) on filter drawer and compare add.
- Respect `prefers-reduced-motion`.

---

## Accessibility

- Contrast: deep slate on surface meets AA for body text combinations.
- Brass accent on white: use for non-critical highlights only; primary actions use slate.
- Spec tables: `<th scope="row">` for labels.
- Find stepper: announce step changes via `aria-live="polite"`.

---

## Admin

Polaris admin sections use **shared admin shell** — no separate admin theme.
Brand switcher shows Polaris label when module enabled.

---

## Implementation status

| Item | Status |
| --- | --- |
| Brand colour tokens in `brands.php` | Scaffolded |
| Public layout brand branch | Scaffolded |
| Hero photography assets | Planned |
| Spec table component | Scaffolded |
| Find stepper styles | Scaffolded |
| Provenance chip | Planned (Phase 2) |
| Semantic token migration (EXP-001) | Existing (platform-wide incomplete) |

---

## Anti-patterns

- Purple/violet primary gradients
- Cream-terracotta “warm AI” backgrounds as default surface
- Chat bubble UI for structured Find (use stepped form)
- Star ratings without verified review product
- Hidden spec gaps (always show unknown state)

---

## Related documents

- [UX_SPECIFICATION.md](UX_SPECIFICATION.md)
- [docs/PLATFORM_DESIGN_SYSTEM.md](../PLATFORM_DESIGN_SYSTEM.md)
