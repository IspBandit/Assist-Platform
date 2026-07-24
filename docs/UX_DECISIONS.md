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
