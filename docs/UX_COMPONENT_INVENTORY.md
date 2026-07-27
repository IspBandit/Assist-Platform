# Assist Platform UX component inventory

## Purpose

This inventory promotes the UX redesign already in the repository into the
official Assist Platform Design System. It is an evidence record, not permission
to replace current work. Components are consolidated incrementally after visual,
responsive and accessibility checks.

## Current implementation

The application currently uses server-rendered PHP views, shared layouts and one
lightweight stylesheet at `public/assets/css/app.css`. Brand configuration in
`config/brands.php` supplies runtime theme values through the shared brand-theme
partial. This is the correct migration path: retain the current rendering model
and remove VanAssist-specific presentation assumptions gradually.

### Shared foundations already present

| Area | Current evidence | State | Required promotion work |
| --- | --- | --- | --- |
| Theme injection | `partials/brand-theme.php`, brand registry theme maps | shared | Rename legacy tokens and document semantic aliases |
| Public shell | `layouts/public.php`, header, footer, SEO partials | shared | Verify all four brands and private-brand behaviour |
| Admin shell | `layouts/admin.php`, `admin-platform.js` | shared | Enterprise workspace selector implemented; rendered desktop/mobile and assistive-technology acceptance remains release evidence |
| Minimal/auth shell | `layouts/minimal.php` | shared | Verify brand sender/support/legal states |
| Navigation | shared header plus configuration-driven links | shared | Add active/current semantics and overflow tests |
| Buttons | `.btn` variants and sizes | reusable | Replace brand-colour legacy references with semantic tokens |
| Cards/grids | `.card`, `.grid-*` | reusable | Document density and interactive-card rules |
| Hero/search | `.hero`, `.search-card`, variants | reusable pattern | Separate content hero from intent/search hero variants |
| Provider cards | `partials/provider-result-card.php` | shared domain component | Verify badges, sponsorship labels and brand context |
| Location controls | `use-location-btn`, distance filter | shared domain component | Document permission-denied and unavailable states |
| Status badges | verified/confirmed/neutral and related styles | partially shared | Define one semantic status matrix |
| Social assets | Social Studio templates and brand assets | in progress | Exact-size export and editorial gate remain mandatory |
| Regulatory source card | `localtorque/regulatory-library.php`, `.rule-card` | shared across all four brands | Desktop/mobile render, official-link and long-title acceptance |
| Motorsport discipline, source and venue cards | `localtorque/motorsport.php`, `.motorsport-*` | LocalTorque public journey | Explicit taxonomy, four rule layers, venue website/calendar provenance and single-column mobile acceptance |
| Contextual sponsor rail | `.rules-sponsors` over shared advertising campaigns | reusable commercial pattern | Always labelled; explicit location only; must not alter organic or authority results |
| Garage asset card | `/account/garage`, `.garage-card` | authenticated shared component | Asset type, jurisdiction, document count, next expiry, keyboard focus and mobile stacking |
| Compliance wallet row | `/account/garage/{id}`, `.garage-document` | authenticated shared component | Owner-supplied status, expiry, authenticated download and removal |
| Garage next-action card | `/account/garage/{id}`, `.garage-action-grid` | authenticated shared component | Non-sensitive rules/provider/tool handoff; private fields are never passed |
| Guided compliance builder | `/rules/guided`, `.guided-form`, `.guided-steps` | shared public journey | Three native fields; official sources and limitation precede save/provider actions |
| Owner compliance centre | `/account/compliance`, `.compliance-*` | authenticated shared component | Separate unticked consent for source alerts and limited-context handoff |
| Provider trust and campaign workspace | `/provider/growth`, `.growth-*` | provider-owned component | Pending-by-default evidence and ads; organic analytics remain separate |
| Trust, rules and growth control | `/admin/trust-growth`, `.admin-trust-*` | permission-scoped admin component | Source fail-closed queue, evidence review, pricing/relevance approval and alert audit |

### Current token debt

The stylesheet begins with legacy VanAssist names such as `--teal`, `--sand` and
`--cream`, while later components already refer to brand semantics such as
`--brand-primary`. These values must not be mass-replaced blindly. Introduce and
test semantic aliases first:

- `--color-brand`, `--color-brand-emphasis`, `--color-accent`;
- `--color-surface`, `--color-surface-raised`, `--color-text`;
- `--color-text-muted`, `--color-border`, `--color-focus`;
- `--color-success`, `--color-warning`, `--color-danger`;
- shared spacing, radius, elevation, content-width and motion tokens.

Legacy aliases can remain during migration and are removed only after repository
search and representative rendered regression show no consumers.

## Component catalogue target

### Foundations

- colour and theme tokens;
- typography scale and content measure;
- spacing, grid, breakpoints and safe areas;
- radius, border and elevation;
- focus, reduced motion and status semantics;
- icon sizing and accessible-name rules.

### Navigation and shells

- public header, mobile menu and footer;
- authenticated account/provider navigation;
- unified admin sidebar/top bar;
- All Brands/brand switcher;
- breadcrumbs and section navigation;
- skip links and page landmarks.

### Inputs and actions

- primary, secondary, ghost, danger and link buttons;
- text, email, telephone, number, search and address fields;
- select, checkbox, radio, switch and segmented controls;
- autocomplete, current-location permission and radius controls;
- inline validation, error summary and destructive confirmation;
- upload with type, size, privacy and progress states.

### Data and feedback

- cards, provider result cards and business summaries;
- badges with verified, claimed, unclaimed, featured and sponsored meanings;
- tables with mobile alternatives;
- filters, chips, pagination, sorting and zero results;
- alerts, notices, flash messages, toasts and dialogs;
- loading, skeleton, empty, unavailable, partial-data and error states;
- chart summaries that retain accessible text/table alternatives.

### Domain patterns

- VanAssist stay and nearby-help result;
- TowSmart calculator input, assumptions, warning and result summary;
- TrailerWise service/business and ownership-content result;
- LocalTorque workshop/specialist result and category/location search;
- LocalTorque official-rule result, source-freshness state and contextual sponsor rail;
- LocalTorque motorsport discipline, authority source, venue and official-calendar journey;
- claimed/verified provider profile;
- membership comparison and entitlement explanation;
- campaign preview and Social Studio export.

## Brand expression constraints

Brand themes may change colours, imagery, wordmark, voice and relevant domain
patterns. They may not change the meaning of controls, focus visibility, status
colours, sponsorship labels or basic navigation conventions. Every brand must be
usable with missing photography and must never rely on colour alone.

## Acceptance matrix

Every promoted or materially changed component is checked in these states:

| Dimension | Minimum coverage |
| --- | --- |
| Brand | VanAssist, TowSmart, TrailerWise, LocalTorque private preview |
| Width | 360px, 768px, 1280px |
| Input | keyboard, pointer and touch-sized targets |
| Content | short, realistic long text and missing optional data |
| State | default, hover where relevant, focus, disabled, loading, error, empty |
| Access | semantic name/role, logical order, contrast, reduced motion |
| Network | usable core result without map and under failed optional assets |

## Sequencing

1. Stabilise semantic token aliases without visible redesign. **Started:** core
   colour aliases and base/button consumers now use the official names; legacy
   aliases remain for unmigrated components.
2. Verify shared public and admin shells across all brands.
3. Promote buttons, fields, cards, statuses and feedback patterns.
4. Promote brand-specific domain patterns with shared primitives.
5. Remove obsolete selectors only after usage and render verification.
6. Add a maintained component preview/catalogue when the shared component set is
   stable enough to make it useful rather than another parallel UI.

### 2026-07 enterprise shell increment

- Four brand marks now share one tested SVG geometry contract.
- Admin presentation rules moved out of the layout into reusable shell classes.
- Current navigation exposes `aria-current`; the workspace selector supports
  keyboard traversal and retains server-side brand access/scoping.
- Representative acceptance widths are 360px and 1280px, with 768px included
  in the full component acceptance matrix.

## Definition of done

A component is part of the official Design System only when its public contract,
variants, responsive behaviour, accessibility requirements and owning source are
documented and verified. Merely sharing a CSS class does not qualify.
