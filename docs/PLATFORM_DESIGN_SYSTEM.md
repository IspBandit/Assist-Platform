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
- Regulatory content identifies its issuing authority and status; paid provider
  placements are visually separated from official-source cards.
- No fake counts, reviews, urgency, availability or endorsements.

## Premium visual-content contract

Major public journeys use original, subject-relevant hero photography rather
than generic decoration. VanAssist shows regional caravan confidence; TowSmart
shows measured towing safety; TrailerWise shows skilled trailer inspection;
LocalTorque shows genuine automotive expertise; the rules library shows vehicle
engineering/source review; and My Garage shows an organised mixed asset set.

Hero images contain no baked-in words, logos, fake government marks or UI. Live
HTML owns headings and actions. Every hero ships as an art-directed 1824 × 864
desktop image and a 720 × 960 mobile crop, with AVIF first and WebP fallback.
Desktop AVIF must remain at or below 110 KB and mobile AVIF at or below 65 KB;
tests enforce dimensions, formats and transfer budgets. Above-the-fold heroes
use `fetchpriority="high"`; below-the-fold editorial images must be lazy-loaded.
Images never replace a usable text, list, map-fallback or form experience.

LocalTorque motorsport uses the same art-directed contract. Its hero may
combine several credible competition disciplines but carries no sanctioning-body
marks, sponsor liveries or implication that one authority governs every sport.
Disciplines, rulebooks, venues and calendars remain live HTML below the image.

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

### Four-brand mobile rule-library contract

The shared rule library is a required mobile journey for VanAssist, TowSmart,
TrailerWise and LocalTorque. At 619px and below filters form one column, document
metadata forms one column, download actions occupy the available width and
sponsor disclosures precede sponsor cards. Jurisdiction navigation may scroll
horizontally without clipping labels. At 979px and below authority cards and
sponsor cards form a single column. Core official-source access must not depend
on JavaScript; town autocomplete is an optional enhancement.

### Regulatory library pattern

Regulatory results use an authority-first card with jurisdiction, current or
upcoming state, document type, applicable vehicle chips, issuer, version,
effective date and last source check. The primary action opens the official
authority source or download. Changed/unreviewed records are unavailable rather
than shown with a weak warning. Sponsored local specialists appear only after
the official results in a separately bordered region with a persistent
`Sponsored` label and disclosure; they never appear inside a rule card.

### Motorsport rule and venue pattern

The LocalTorque motorsport journey starts with explicit discipline families and
named disciplines, then presents the four rule layers: sanctioning body,
discipline/class, state/series and event/venue. Cards identify the authority,
version, jurisdictions, applicable families and source check. Venue cards
identify permanent, temporary, route-based or club-network status, locality,
official venue website where available, and a separately labelled official
calendar source. A calendar link never implies that entries remain open. At
680px and below family, rule and venue grids form one column and actions use
touch-sized full-width controls where needed.

### Shared Garage contract

My Garage is an account-level experience shared by every brand. Its structure is
consistent while colour, wordmark and relevant next actions inherit the current
brand. The mobile index is a single-column asset wallet; desktop may use a
three-column collection. Technical ratings are progressive disclosure, not a
barrier to adding an asset. Private documents must always be described as
owner-supplied and never receive a verified badge without separate review.

Garage action cards may pass non-sensitive type and jurisdiction context into
official rules or provider search. They do not pass private notes or documents.

### Guided compliance and commercial separation

The guided check uses three questions (jurisdiction, vehicle and job), followed
by a numbered practical sequence and authority-linked cards. The limitation is
always visible with the result. Saving, subscribing and provider handoff are
separate actions; alert and handoff consent use unticked native checkboxes with
plain-language scope.

Verified capability labels require reviewed evidence and show jurisdiction and
validity. They must never use authority seals or claim government endorsement.
Sponsored provider cards remain in a labelled landmark after official results.
Paid dashboards label paid impressions, clicks, attributed contacts and media
spend separately from organic discovery.

The administrative trust dashboard uses the same status vocabulary everywhere:
current, review, overdue, failed, pending, verified, rejected, paused and active.
Colour supplements those words and never replaces them.

## Brand expression

- **VanAssist:** travel confidence, regional utility and caravan/RV assistance.
- **TowSmart:** technical clarity, safety, measured inputs and explanations.
- **TrailerWise:** capable, practical trailer-industry and ownership experience.
- **LocalTorque:** credible, direct automotive workshop and specialist discovery.

Brand expression changes tokens, imagery, voice and relevant content—not basic
usability conventions.

### Brand mark family

The four platform marks use a shared 64 × 64 SVG contract: open geometry, a
3px rounded stroke, no enclosing tile, no gradient or shadow, and at most one
restrained accent. VanAssist combines an RV outline with location confidence;
TowSmart shows a measured towing connection; TrailerWise combines trailer
capability with inspection; LocalTorque combines automotive service with a
specialist tool. Marks must be checked at 24, 32, 40 and 64px. Decorative
images use an empty `alt`; a standalone mark retains its SVG title or receives
an accessible name from its consumer.

### Enterprise admin shell

The admin shell uses a neutral charcoal navigation surface and raised white
work surfaces. Brand colour is limited to active/focus accents. The workspace
selector shows the active brand icon, name and platform context; available
brands keep server-authorised POST switching. It supports Escape, Home, End and
arrow-key movement, visible focus and 44px-class touch targets. At 720px and
below navigation collapses and top-bar actions remain horizontally reachable.
Motion is restrained and disabled when `prefers-reduced-motion` is requested.

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
