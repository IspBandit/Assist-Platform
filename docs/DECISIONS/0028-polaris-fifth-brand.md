# ADR 0028: Polaris as a fifth Assist Platform brand

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-001
- **Affected brands/modules:** polaris, platform brand registry, admin, TowSmart (read), VanAssist (read)

## Context

Project Polaris needs a premium Australian new-RV decision experience. The
platform already hosts multiple brands in one application. Creating a separate
Polaris application would duplicate auth, admin, media, audit and deployment.

## Decision

Polaris is registered as brand key `polaris` (database ID `5`) with status
`private` until a production domain is confirmed. It enables module
`rv_catalogue` and reuses shared platform services. Detailed product ADRs live
under `docs/polaris/DECISIONS/`.

Tow vehicle authority remains TowSmart. Relevant service providers remain
VanAssist. Polaris owns the new-RV manufacturer/model catalogue only.

## Alternatives considered

- Module under VanAssist or TrailerWise — rejected; product purpose and UX differ.
- Standalone application — rejected; violates ADR 0001.
- Reuse TrailerWise marketplace tables as the catalogue — rejected; those are
  classified listings, not a new-model catalogue with provenance.

## Consequences

- `config/brands.php`, migration `087`, and private host `polaris.test`.
- Home and `/find` dispatch must remain brand-aware.
- Production launch requires domain, Quality Gate and honest catalogue readiness.

## Quality Gate impact

- Architecture: accepted multi-brand extension
- UX: Polaris extends shared design system
- Engineering: forward migration + tests required
- Business: private until launch criteria met

## Validation and rollback

Validate via brand registry tests and Polaris route gating. Rollback: disable
brand status and stop routing; do not delete applied migration — add a forward
migration if schema must be retired.
