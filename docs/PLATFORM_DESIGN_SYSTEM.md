# Assist Platform Design System

## Mandate

The current UX redesign is the starting point for the official Assist Platform
Design System. This work is evolutionary: do not replace it with a competing
theme, framework or component set. Existing patterns are promoted into shared
components only after rendered, responsive and accessibility verification.

## Experience principles

- Location and primary intent appear early on public marketplace pages.
- TowSmart prioritises clear inputs, units, assumptions, limits and warnings.
- Lists remain usable without maps or JavaScript-heavy interactions.
- Provider and admin tasks work on mobile, not only desktop.
- Empty, loading, success and error states are designed states.
- Paid and sponsored content is visibly labelled.
- No fake counts, reviews, urgency, availability or endorsements.

## Foundations

Shared tokens cover typography, spacing, breakpoints, radii, elevation, focus,
motion and status. Semantic tokens cover surfaces, text, borders, actions,
success, warning and danger. Brand token maps provide identity without changing
shared interaction behaviour.

The system must include and document:

- page shell, header, footer and navigation;
- admin shell and brand switcher;
- buttons, links, form controls and validation;
- cards, badges, tables, tabs, pagination and filters;
- provider results and profiles;
- maps with list alternatives;
- notices, dialogs, drawers and toasts;
- loading, empty, error and unavailable states;
- content, calculator and data-entry patterns;
- Social Studio templates and export-safe areas.

## Brand expression

- **VanAssist:** travel confidence, regional utility and caravan/RV assistance.
- **TowSmart:** technical clarity, safety, measured inputs and explanations.
- **TrailerWise:** capable, practical trailer-industry and ownership experience.
- **LocalTorque:** credible, direct automotive workshop and specialist discovery.

Brand expression changes tokens, imagery, voice and relevant content—not basic
usability conventions.

### Typography-first brand identity

Public, authentication, provider and admin navigation use typographic wordmarks
without illustrative vehicle or service icons. Brand colour is a restrained
wordmark accent; product purpose is carried by a compact descriptor. No generic
or provisional mark is presented as finished brand identity. A future symbol
requires professionally reviewed identity work, distinctive geometry, small-size
testing and explicit owner approval before it enters a product surface.

### Enterprise admin shell

The admin shell uses a neutral charcoal navigation surface and raised white
work surfaces. Brand colour is limited to active/focus accents. The workspace
selector shows the active brand name and platform context; available
brands keep server-authorised POST switching. It supports Escape, Home, End and
arrow-key movement, visible focus and 44px-class touch targets. At 720px and
below navigation collapses and top-bar actions remain horizontally reachable.
Motion is restrained and disabled when `prefers-reduced-motion` is requested.

### Immersive discovery and provider command centre

High-intent public pages use editorial-scale type, strong photography and a
single dominant task surface. VanAssist places location-aware discovery over an
immersive service scene while preserving the complete accessible form. Provider
pages use a command-centre hierarchy: business status, demand indicators,
market intelligence and clearly ranked management actions. Responsive layouts
collapse to one task per row without hiding core data or actions.

## UX change workflow

1. Inventory the existing implementation before editing.
2. Identify the owning pattern and affected journeys.
3. Reuse or extend an existing component where practical.
4. Document new tokens, variants and responsive behaviour.
5. Verify keyboard, focus, semantics, contrast and reduced motion.
6. Render representative desktop and mobile views.
7. Compare for regressions before removing superseded code.

## Social Studio standard

Social Studio generates individual production assets at exact channel dimensions.
It must use approved templates, brand assets, safe areas, typography and copy.
Contact sheets, mock-up boards and crops containing adjacent artwork are not
production deliverables. Generated assets remain drafts until editorial approval.

See `docs/SOCIAL_STUDIO_DESIGN_SYSTEM.md` for template-specific requirements.

## Ownership and change control

Material changes to navigation, layout foundations, colour semantics, typography
or interaction conventions require Experience workstream review and the UX part
of the platform quality gate. Brand-only visual changes must still use shared
tokens and components.
