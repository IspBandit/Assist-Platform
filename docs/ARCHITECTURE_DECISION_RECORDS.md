# Architecture Decision Records

See [ADR 0008](DECISIONS/0008-authoritative-regulatory-library.md) for the authoritative vehicle regulatory source and monitoring decision.
See [ADR 0009](DECISIONS/0009-shared-owner-garage.md) for the shared owner Garage and private compliance wallet decision.
See [ADR 0011](DECISIONS/0011-separate-motorsport-rule-and-venue-catalogue.md) for the motorsport rule, discipline, venue and calendar catalogue decision.
See [ADR 0012](DECISIONS/0012-authoritative-provider-pack-routing.md) for canonical provider-pack ingestion and taxonomy-controlled brand routing.
See [ADR 0013](DECISIONS/0013-staged-consent-gated-marketing-email.md) for consent-gated provider audiences and reviewed campaign limits.

See [ADR 0014](DECISIONS/0014-separate-directory-accuracy-from-marketing.md) for the locked factual listing-notice boundary and type-specific suppression.
See [ADR 0015](DECISIONS/0015-controlled-bulk-provider-review.md) for controlled bulk publication and strong duplicate linking.
See [ADR 0016](DECISIONS/0016-server-owned-provider-import-and-campaign-taxonomy.md) for server-owned import processing and canonical provider-campaign category scope.
See [ADR 0017](DECISIONS/0017-review-first-organisation-pr-outreach.md) for evidence-backed organisation targeting and separate staged PR campaigns.
See [ADR 0018](DECISIONS/0018-admin-api-no-direct-db.md) for Admin API as the only external write path to production.
See [ADR 0019](DECISIONS/0019-stays-vs-traveller-facilities.md) for stays versus narrowly scoped traveller facilities.
See [ADR 0020](DECISIONS/0020-ric-as-management-client.md) for Assist RIC as the initial local management client.
See [ADR 0033](DECISIONS/0033-ric-national-dataset-acquisition.md) for RIC as the national dataset acquisition engine.
See [ADR 0034](DECISIONS/0034-assist-ric-facility-auto-publish.md) for Assist RIC government facility pack auto-publish.
See [ADR 0035](DECISIONS/0035-stay-facility-evidence-and-moderation.md) for source-resolved stay facilities and moderated community evidence.
See [ADR 0036](DECISIONS/0036-google-routes-road-distance.md) for Google Routes road-distance filtering across VanAssist discovery.

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
- 0015: Controlled bulk provider review and strong duplicate linking.
- 0016: Server-owned provider import processing and canonical provider-campaign taxonomy.
- 0017: Review-first organisation PR outreach with source, role and relevance evidence.
- 0018: Admin API is the only external write path to production (no direct DB).
- 0019: Stays vs narrowly scoped traveller facilities.
- 0020: Assist RIC is the initial local management client (Option B).
- 0021: Shared Assist AI Orchestrator (accepted; AI-1 foundation).
- 0022: AI is interpretation, not factual authority (accepted).
- 0023: Provider-neutral AI abstraction (accepted).
- 0024: Deterministic and cache-first routing (accepted).
- 0025: Hard AI budget enforcement (accepted).
- 0026: Natural-language search alongside structured search (accepted).
- 0027: Knowledge-gap-driven database growth (accepted).
- 0028: External results are staged with provenance (accepted).
- 0029: No direct AI publishing (accepted).
- 0030: Traveller facilities remain separate from stays — AI workstream (accepted).
- 0031: Polaris is a fifth Assist Platform brand (private until domain confirmed);
  detail ADRs under `docs/polaris/DECISIONS/`.
- 0032: Stays vs narrowly scoped traveller facilities (AI elaboration; aligns with 0019).
- 0033: RIC is the national dataset acquisition engine; Platform `government_datasets`
  is the catalogue SoR (DATA-011A).
- 0035: source-resolved stay facility evidence and human-moderated community contributions (proposed).
- 0036: Google Routes road-distance filtering and drive-time enrichment for VanAssist public search (accepted).

Gate: `docs/PHASE_AI0_DESIGN.md` (AI-0 approved). Owner brief:
`docs/AI0_OWNER_DECISION_BRIEF.md`. AI-1–AI-7 + DATA-012 complete behind flags;
production Ask requires Platform Quality Gate. CORE-011 Admin API Phase 1 +
Option B A–L are on this tree; production `ADMIN_API_ENABLED` remains off until
staging rehearsal.

The index must be updated whenever an ADR is accepted.
