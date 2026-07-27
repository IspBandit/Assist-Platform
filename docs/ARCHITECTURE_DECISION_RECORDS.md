# Architecture Decision Records

See [ADR 0008](DECISIONS/0008-authoritative-regulatory-library.md) for the authoritative vehicle regulatory source and monitoring decision.
See [ADR 0009](DECISIONS/0009-shared-owner-garage.md) for the shared owner Garage and private compliance wallet decision.
See [ADR 0011](DECISIONS/0011-separate-motorsport-rule-and-venue-catalogue.md) for the motorsport rule, discipline, venue and calendar catalogue decision.

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

The index must be updated whenever an ADR is accepted.
