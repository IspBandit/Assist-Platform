# UX decisions

The current UX redesign is the official starting point. This document records
experience decisions that do not require a full architectural ADR. Material
navigation, design-foundation or interaction changes still require Experience
review under the Platform Quality Gate.

## Frozen decisions

- One shared interaction system serves all brands; brand identity changes theme,
  imagery, voice and relevant domain patterns, not control meaning.
- There is one admin shell with All Brands and permission-scoped brand contexts.
  Switching context does not require another password login.
- Public discovery remains available without forced registration.
- Search is list-first and remains useful when maps or optional JavaScript fail.
- Mobile, keyboard, focus, long-content, empty, loading and error states are part
  of each component contract.
- Verified, claimed, featured and sponsored states have distinct meanings.
- Social Studio exports individual exact-size production assets. Contact sheets,
  contaminated crops and mock-up boards are never production deliverables.
- TowSmart presents inputs, units, sources, assumptions, missing data and warnings
  clearly and never styles guidance as certification.

## Decision log format

Add future decisions as dated entries containing: context, decision, affected
journeys/components, alternatives considered, accessibility impact, evidence and
backlog ID. Promote architectural decisions to `docs/DECISIONS/`.

## Implementation ownership

Current component evidence lives in `UX_COMPONENT_INVENTORY.md`; shared contracts
live in `PLATFORM_DESIGN_SYSTEM.md`. New interface work extends those documents
instead of creating a parallel design language.

## 2026-07-24 — Cohesive brand marks and enterprise workspace selector

**Context:** EXP-001/002/004/005. Gradient vehicle tiles lacked a shared visual
grammar and the admin brand dropdown did not communicate platform context.
**Decision:** adopt a 64 × 64, 3px rounded-stroke mark family and promote the
existing admin switcher into a branded, keyboard-operable workspace selector
inside the shared shell. **Affected journeys:** public identity and all admin
navigation. **Alternatives:** retaining coloured tiles or introducing an icon
dependency were rejected as visually noisy and unnecessary. **Accessibility:**
marks keep titles, decorative consumers use empty alternatives, current pages
use `aria-current`, and the selector supports focus, Escape and directional
keys. **Evidence:** BrandAssetTest, PHP/static checks and representative renders.

## 2026-07-28 — Replace literal vehicle marks with geometric brand symbols

**Context:** the 2026-07-24 mark family remained visually illustrative and lost
clarity and authority in the public header and browser tab. **Decision:**
supersede the rounded-stroke vehicle drawings with four solid geometric symbols
that share a 64 × 64 grid, deep neutral form and one restrained accent. The
same versioned asset is used in the public shell, authentication shell, admin
workspace selector, control centre and favicon. **Affected journeys:** every
public and administrative entry point across all four brands. **Alternatives:**
removing identity entirely or making another set of detailed vehicle drawings
were rejected because neither creates a distinctive, scalable platform family.
**Accessibility and performance:** SVG titles remain; decorative consumers use
empty alternatives; marks remain below 1.2 KB and are checked down to 16px;
file-version URLs prevent stale browser caches. **Evidence:** BrandAssetTest,
XML validation, 128px/64px/32px/24px/16px contact-sheet inspection and shell
render checks.

## 2026-07-28 — Provider journeys carry equal visual weight on mobile

**Context:** VanAssist hid provider photography on phones and the other brand
provider pages used a generic text-and-card layout. **Decision:** every provider
landing page now opens with relevant full-bleed imagery, live brand-specific
copy, a primary list-or-claim action, sign-in, proof points, a concise value
strip and three-step onboarding explanation. Existing mobile AVIF/WebP crops
are reused for TowSmart, TrailerWise and LocalTorque; VanAssist retains its
provider-specific compressed photograph. **Accessibility:** live text supplies
meaning, decorative imagery stays outside the reading order, contrast is
protected by directional overlays and mobile actions stack to touch width.
**Evidence:** PHP syntax, responsive CSS inspection and representative desktop
and phone renders.

## 2026-07-28 — VanAssist starts with traveller intent, not directory structure

**Context:** EXP-001/005 and VAN-001/002. The premium homepage hero led into a
large service selector, but fuel, EV charging and stays remained easy to miss,
while the trust row implied universal verification and remote coverage that the
mixed claimed/public-source directory could not support. **Decision:** retain
the location-first search and hero assets, then add four concise intent paths
for repairs, fuel, charging and stays before provider results. Reuse those paths
at the service-directory entrance, group the result-page category selector and
add an explicit provider-claim conversion panel. Trust language now explains
listing states and the need to confirm current details. **Alternatives:** a
larger hero form and a carousel were rejected because both increase mobile
effort and hide choices. **Accessibility and performance:** the launcher is
server-rendered text, uses no new image or script dependency, has visible focus,
44px-class targets, one-column phone layouts and disabled motion when requested.
**Evidence:** VanAssistPublicUxTest, PHP syntax, static analysis and responsive
CSS inspection.

## 2026-07-27 — Authority-first rules and separated provider sponsorship

**Context:** DATA-008. Readers need official vehicle rules while relevant local
providers have a legitimate paid-acquisition opportunity. **Decision:** official
sources remain the uninterrupted primary result; active paid campaigns appear
afterwards in a labelled sponsor rail and match only explicit location and rule
context. **Affected journeys:** VanAssist, TowSmart, TrailerWise and LocalTorque
rule discovery and provider acquisition. **Alternatives:** interleaving ads with
government sources and implicit behavioural location were rejected because they
weaken trust. **Accessibility:** semantic cards and sponsor landmark, text status
labels, keyboard-native links and responsive single-column layouts. **Evidence:**
unit, static, database and representative render checks under DATA-008.

## 2026-07-27 — Explicit motorsport taxonomy and source-owned calendars

**Context:** DATA-010 / LOC-004. “Motorsport” is too broad to be a useful
filter, while copying event dates creates stale-calendar risk. **Decision:**
LocalTorque exposes named disciplines grouped into nine families and always
shows the sanctioning-body, discipline/class, state/series and event/venue rule
layers. Venue cards distinguish permanent, temporary, route-based and club
locations, show the venue website when available and link the official venue,
club or governing-body calendar separately. Mobile collapses family, source and
venue grids to one column. The family cards are closed initially and disclose
their named disciplines on demand, avoiding a wall of more than fifty choices.
The commercial step follows the official answer and venue information, using a
single relevant provider action rather than interruptive advertising.
**Affected journey:** `/motorsport`. **Accessibility:** native `details` and
`summary` controls retain keyboard operation, focus visibility and reduced-motion
support. **Evidence:** catalogue and asset tests, static and database analysis,
plus desktop and phone-width rendering.

## 2026-07-27 — One Garage follows the owner across brands

**Context:** CORE-009 and EXP-007. Owners should not recreate a tow vehicle,
trailer, caravan or motorcycle when moving between specialist Assist brands.
**Decision:** My Garage is a shared account capability. Brand origin is visible
context, not a silo. Basic identity fields come first; plate ratings are optional
progressive disclosure. The detail page starts with useful brand-aware actions,
then the private wallet and editing. Registration numbers and VINs are not
collected in the first release. **Accessibility:** mobile uses one asset column,
stacked actions and full-width document controls; no essential action depends on
hover or a horizontal table. **Evidence:** CORE-009 owner-isolation, upload,
responsive-render and static checks.

## 2026-07-27 — Subject-specific premium hero imagery

**Context:** EXP-001. Brand pages need immediate emotional and practical
relevance without turning the platform into a slow image gallery. **Decision:**
use an original art-directed hero family for each brand and high-value section,
with separate mobile crops, AVIF/WebP sources and live HTML content. The image
communicates the current job: roadside travel for VanAssist, measured towing for
TowSmart, trailer inspection for TrailerWise, workshop discovery for
LocalTorque, mixed ownership for My Garage and technical authority for rules.
Generic stock scenes, baked-in text, third-party logos and decorative imagery
with no page relevance are rejected. **Accessibility:** contextual hero media is
decorative when the adjacent live heading carries the meaning; forms and safety
messages retain contrast and keyboard order. **Evidence:** exact-dimension and
byte-budget tests plus 1440 px and true 390 px render/overflow inspection. Asset
provenance and budgets are recorded in `PREMIUM_VISUAL_ASSETS.md`.

## 2026-07-27 — Guidance ends in consent, not an invisible funnel

**Context:** EXP-006/007, CORE-010, DATA-009 and COM-006/007. An official rule
may lead to alerts or a provider, but those are different decisions. **Decision:**
the guided check shows authority sources and limitations first. Save, email alert
and provider handoff are separate actions. Alert and handoff checkboxes begin
unticked and state the exact scope. Provider credentials are pending until
evidence review; campaigns are pending until relevance, destination, budget and
price review. **Accessibility:** native controls, persistent labels, textual
statuses, keyboard order and single-column mobile fallbacks. **Evidence:** strict
selection tests, changed-source alert integration, campaign-scoped metrics,
owner isolation and representative renders.

## 2026-07-28 — Reconcile conversion journeys with the premium visual release

**Context:** Conversion work began before the premium symbol, hero and location
releases reached production. **Decision:** retain the approved `symbol-v2.svg`
family, responsive contextual hero assets and current search/location behaviour;
layer evidence, process, trust, provider acquisition and provider workspace
components around them. Older typography-only or provisional-icon assumptions
do not supersede the premium visual release. **Accessibility:** primary search
remains early in document order; additions use semantic headings, visible focus,
single-column phone fallbacks and reduced-motion controls. **Evidence:** shared
asset tests, location regression tests, static analysis, PHPUnit and desktop and
mobile render inspection.

## 2026-07-28 — Admin workspace switching stays unified and visibly scoped

The administrator workspace selector now changes a permission-checked session
workspace while retaining the current trusted live admin host. This lets the
owner manage a private brand such as LocalTorque before its public domain exists
and avoids a fragile cross-domain login hop. The override applies only below
`/admin`; public brand resolution remains hostname-authoritative. Selecting a
workspace always opens its dashboard, whose provider, account, email, campaign
and specialist-module metrics are filtered to that brand. The active brand name,
theme and browser icon remain visible throughout the shell.

## 2026-07-28 — Admin is an operating console, not an accounting package

The shared dashboard now exposes only destinations the signed-in staff member
can actually use, and specialist customer modules appear only in the brands that
enable them. Summary counts link directly to their working queues. Audit history
and scheduled-task health are restricted to their matching permissions, while
backup and maintenance tools remain owner-only. The internal general-ledger
routes remain available for compatibility but are removed from primary
navigation. Normal commercial work is plans, invoices and reviewed CSV export
to Xero or MYOB; bookkeeping remains in the external accounting product.

## 2026-07-28 — Social Studio drafts can be permanently removed

**Context:** EXP-003. Generated campaign experiments are not all suitable for a
brand library, and archiving an unusable draft leaves visual clutter and stored
files behind. **Decision:** brand-authorised content staff may permanently
delete a Social Studio asset through a confirmed POST action. The brand-scoped
database record and private image are removed together and deletion metadata is
retained in the immutable audit log. A Facebook post already published from
that asset remains live until separately removed on Facebook. **Accessibility:**
the destructive action uses a labelled button, native confirmation and the
existing success/error announcement path.
