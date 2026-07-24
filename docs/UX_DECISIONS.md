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
