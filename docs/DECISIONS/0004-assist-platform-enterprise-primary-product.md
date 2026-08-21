# ADR 0004: Assist Platform Enterprise is the primary product

- **Status:** accepted
- **Date:** 2026-07-23
- **Owners:** Product owner and platform maintainers
- **Backlog item:** GOV-001
- **Affected brands/modules:** Entire repository

## Context

VanAssist, TowSmart, TrailerWise and LocalTorque have evolved on shared
infrastructure. Treating them as separate websites encourages duplicated admin,
inconsistent UX, scattered brand logic and weaker sale readiness. A current UX
redesign and unified administration work already provide a practical platform
foundation that must be preserved.

## Decision

Assist Platform Enterprise is the commercial product. The four brands are
domain-resolved products running on one shared PHP/MariaDB application, identity,
provider model, administration portal and operational platform. Future work is
governed by the Enterprise specification, Platform Charter, Design System, ADR
process, Product Backlog and Platform Quality Gate.

The UX redesign is incorporated into the official design system. It is not
restarted or replaced. LocalTorque is developed as a first-class brand while its
public launch remains disabled until its external prerequisites are confirmed.

## Alternatives considered

- Maintain separate websites and admin areas: rejected because it duplicates
  code, identity, operations and provider data.
- Rewrite into a new framework/monorepo: rejected because the existing unified
  PHP application is deployed and already contains the required foundations.
- Defer governance until after launch: rejected because ongoing parallel work
  would otherwise continue to drift.

## Consequences

All material work must identify shared versus brand-specific ownership. Pull
requests require backlog and quality-gate evidence. Older documents must be
reconciled gradually. Configuration is preferred for shared behaviour, but
distinct domains such as TowSmart calculations remain explicit tested modules.

## Quality Gate impact

- Architecture: central product and brand boundaries become mandatory.
- UX: current redesign is protected and formalised.
- Engineering: shared services, scope and tests are required.
- Business: work must strengthen the whole transferable platform.

## Validation and rollback

Validation consists of documentation consistency, unified-admin tests, brand
isolation tests and continued production operation. This governance decision can
be superseded only by a new accepted ADR; implemented production changes retain
their normal release rollback paths.
