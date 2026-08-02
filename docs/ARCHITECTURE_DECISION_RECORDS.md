# Architecture Decision Records

See [ADR 0008](DECISIONS/0008-authoritative-regulatory-library.md) for the authoritative vehicle regulatory source and monitoring decision.
See [ADR 0009](DECISIONS/0009-shared-owner-garage.md) for the shared owner Garage and private compliance wallet decision.
See [ADR 0011](DECISIONS/0011-separate-motorsport-rule-and-venue-catalogue.md) for the motorsport rule, discipline, venue and calendar catalogue decision.
See [ADR 0012](DECISIONS/0012-authoritative-provider-pack-routing.md) for canonical provider-pack ingestion and taxonomy-controlled brand routing.
See [ADR 0013](DECISIONS/0013-staged-consent-gated-marketing-email.md) for consent-gated provider audiences and reviewed campaign limits.

See [ADR 0014](DECISIONS/0014-separate-directory-accuracy-from-marketing.md) for the locked factual listing-notice boundary and type-specific suppression.

ADRs preserve decisions that materially affect platform structure, security,
data ownership, brand boundaries, commercial operation or deployment.

## When an ADR is required

Create an ADR when a change:

- changes multi-brand or tenancy behaviour;
- adds or replaces a shared platform service;
- changes authentication, permissions or cross-domain handoff;
- changes canonical data ownership or cross-brand visibility;
- introduces a new runtime, vendor or infrastructure dependency;
- changes public URL, migration, billing or release strategy;
- changes a frozen brand boundary;
- intentionally accepts a material trade-off or risk.

Routine bug fixes and implementation details do not require an ADR unless they
reverse or amend an existing decision.

## Process

1. Copy `docs/DECISIONS/0000-template.md` to the next four-digit number.
2. Mark it `proposed` and describe context, decision, alternatives and effects.
3. Link the backlog item and affected brands/modules.
4. Obtain Architecture and relevant Quality Gate review.
5. Mark it `accepted`, `rejected`, `superseded` or `deprecated`.
6. Link superseding ADRs in both records; never rewrite accepted history.

## Naming

Use `NNNN-short-kebab-case-title.md`. Numbers are sequential and never reused.

## Decision index

- 0001: one shared multi-brand application.
- 0002: forward-only migrations.
- 0003: brand-attributed email.
- 0004: Assist Platform Enterprise as primary product and governance model.
- 0005: Brand Builder uses reviewed private blueprints.
- 0006: Connector-based, review-first data ingestion.
- 0007: Modular Data Intelligence sources and workflow actions.
- 0008: Authority-linked, monitored vehicle regulatory library.
- 0009: Shared owner Garage and private compliance wallet.
- 0010: Separate authority, owner consent, provider evidence and paid relevance.
- 0011: Separate motorsport rule, discipline, venue and calendar catalogue.
- 0012: Authoritative provider pack with canonical identity and taxonomy-controlled brand routing.
- 0013: Staged, consent-gated marketing email with reviewed daily limits.
- 0014: Separate directory accuracy from marketing outreach.
- 0015: Admin API is the only external write path to production (no direct DB).
- 0016: Server-owned provider import and canonical campaign taxonomy.
- 0017: Assist RIC is the initial local management client (Option B).
- 0018: Shared Assist AI Orchestrator (accepted; AI-1 foundation).
- 0019: AI is interpretation, not factual authority (accepted).
- 0020: Provider-neutral AI abstraction (accepted).
- 0021: Deterministic and cache-first routing (accepted).
- 0022: Hard AI budget enforcement (accepted).
- 0023: Natural-language search alongside structured search (accepted).
- 0024: Knowledge-gap-driven database growth (accepted).
- 0025: External results are staged with provenance (accepted).
- 0026: No direct AI publishing (accepted).
- 0027: Traveller facilities remain separate from stays — AI workstream (accepted).
- 0028: Polaris is a fifth Assist Platform brand (private until domain confirmed);
  detail ADRs under `docs/polaris/DECISIONS/`.
- 0029: Stays vs narrowly scoped traveller facilities (accepted; corrects earlier AI docs that mislabelled this as 0016).

Gate: `docs/PHASE_AI0_DESIGN.md` (AI-0 approved). Owner brief:
`docs/AI0_OWNER_DECISION_BRIEF.md`. AI-1–AI-7 + DATA-012 complete behind flags;
production Ask requires Platform Quality Gate.

The index must be updated whenever an ADR is accepted.
