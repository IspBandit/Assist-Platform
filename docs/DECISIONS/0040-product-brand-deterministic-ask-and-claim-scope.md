# ADR 0040: Product-brand deterministic Ask and brand-bound provider claims

- **Status:** proposed
- **Date:** 2026-08-26
- **Owners:** Glen Cendron / Assist Platform engineering
- **Backlog item:** CORE-003 / CORE-012 / TOW-002 / TRL-001
- **Affected brands/modules:** TowSmart, TrailerWise, Assist search, provider claims, Admin API

## Context

VanAssist Ask contains traveller, stay, facility and assistance concepts that do
not belong on TowSmart or TrailerWise. Both product brands still need a plain-
language discovery entry, but renaming the VanAssist experience would breach
the brand boundary and introduce irrelevant fallbacks. Provider claim tokens
were also canonical-provider scoped without recording which brand issued the
link, so cross-brand acceptance relied on the original URL rather than a server-
enforced token boundary.

## Decision

Keep one `/ask` controller and feature gate, but route TowSmart and TrailerWise
through reviewed deterministic intent matrices before any paid-AI path.
TowSmart calculation and education intents lead to its calculator or guide;
TrailerWise ownership intents lead to its rules content; specialist intents
lead to the existing brand-scoped provider directory. Every outcome exposes its
routing source, and an unmatched request asks for clarification without
substituting an unrelated provider.

Add `brand_id` to provider claim tokens through a forward migration. Issue,
resolve and consume each token only in the current brand, and filter Admin API
provider-invite reads by its selected brand. Canonical provider identity and
ownership remain shared; no brand-specific provider or claim subsystem is
created.

## Alternatives considered

- Reuse Ask VanAssist copy and taxonomy: rejected because it leaks stays,
  facilities and assistance intent into product brands.
- Create separate TowSmart and TrailerWise search services: rejected because it
  duplicates routing, location and provider-directory architecture.
- Leave claim tokens host-dependent: rejected because brand scope must be
  enforced by persisted server-side data.

## Consequences

Product-brand Ask works without paid AI and can be reviewed as a finite prompt
matrix. New intents require explicit matrix and outcome tests. The existing
global Assist-search flag still controls rollout. Unused historical claim
tokens are expired because their issuing brand cannot be inferred safely; their
audit rows are retained under VanAssist and administrators can issue correctly
scoped replacements. The migration must run before application code that writes
`brand_id`.

## Quality Gate impact

- Architecture: shared controllers, provider directory and canonical providers
  remain authoritative; only brand intent policy differs.
- UX: users see the understood route, provenance and a safe clarification state.
- Engineering: matrix, ownership, migration and Admin API scope tests are
  required; migration order is forward-only.
- Business: no billing or paid-AI enablement is implied; commercial launch stays
  blocked on its existing approvals.

## Validation and rollback

Run syntax, static analysis, focused and full PHPUnit suites, documentation
governance and the production dependency build. After immutable deployment,
verify `/ask`, claim invitation/acceptance and Admin API invite lists on each
brand. Roll back application and schema through the normal reviewed database
rollback plan; do not drop the populated scope column automatically.
