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

## 2026-07-24 — Typography-first identity and experience-led shell

**Context:** EXP-001/002/004/005. Provisional vehicle illustrations and later
generic symbols did not meet the product's intended identity standard; shell
polish alone also failed to materially improve customer and provider journeys.
**Decision:** remove provisional marks from product navigation, use typographic
wordmarks, and move visual emphasis to immersive discovery and a provider
command-centre hierarchy. The existing switcher remains keyboard-operable and
server-scoped. **Affected journeys:** public discovery, authentication, provider
dashboard and administration. **Alternatives:** iterating generic symbols or
adding an icon dependency were rejected. **Accessibility:** identity no longer
depends on an unlabeled image; current navigation retains `aria-current`, and
all task surfaces preserve names, focus and responsive order. **Evidence:**
PHP/static checks and representative desktop/mobile renders.

## 2026-07-24 — Conversion architecture replaces cosmetic Experience v2

**Context:** EXP-001/004/005 with COM-003. Full-page review showed that an
immersive hero and restyled dashboard did not materially change how a traveller
found help, how a provider understood the commercial proposition, or how a
provider prioritised work. External research was used as directional evidence:
professional presentation is an initial credibility threshold, while useful
information structure, verifiable organisational evidence, findability,
accessibility and performance determine whether the experience continues to
earn confidence.

**Decision:** structure the VanAssist homepage around three explicit customer
intents, database-backed evidence, operating-process explanation and listing
status transparency. Make provider acquisition a dedicated journey. Replace the
dashboard summary-card hierarchy with one ranked next action, current matched
demand, precisely named activity measures and grouped business controls. Retain
typography-first brand identity and remove decorative brand symbols from product
surfaces.

**Affected journeys:** VanAssist discovery, stays/request recovery paths, all
brand provider acquisition, authenticated provider operations and the shared
credibility footer. **Alternatives:** another hero/card visual pass was rejected
because it would not change user comprehension or task success. Introducing a
client-side framework was rejected because the server-rendered platform already
supports the required experience and a new dependency would increase delivery
and performance risk. **Accessibility:** content order follows task order;
interactive elements remain native links, fields and buttons; evidence is not
colour-only; layouts collapse to one column; reduced motion removes the new
transitions. **Measurement:** search, provider profile, contact, request and
outcome events continue through the existing first-party demand analytics
vocabulary. **Evidence:** unit/static analysis, desktop/mobile full-page renders
and quality-gate results recorded in the pull request.
