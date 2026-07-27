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

## 2026-07-27 — Authority-first rules and separated provider sponsorship

**Context:** DATA-008. Readers need official vehicle rules while relevant local
providers have a legitimate paid-acquisition opportunity. **Decision:** official
sources remain the uninterrupted primary result; active paid campaigns appear
afterwards in a labelled sponsor rail and match only explicit location and rule
context. **Affected journeys:** VanAssist, TowSmart, TrailerWise and LocalTorque
rule discovery and provider acquisition. **Alternatives:** interleaving ads with government sources and
implicit behavioural location were rejected because they weaken trust.
**Accessibility:** semantic cards and sponsor landmark, text status labels,
keyboard-native links and responsive single-column layouts. **Evidence:** unit,
static, database and representative render checks under DATA-008.

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
