# ADR 0005: Brand Builder starts with reviewed private blueprints

- **Status:** accepted
- **Date:** 2026-07-24
- **Owners:** Product owner and platform maintainers
- **Backlog item:** CORE-008
- **Affected brands/modules:** Brand registry, Platform Control Centre, deployment

## Context

Runtime host resolution and security depend on a typed, reviewed configuration
registry, while relational brand IDs and domains are introduced through forward
database migrations. Allowing an admin form to mutate production host routing,
DNS or source configuration would create drift and an unsafe false promise that
a public brand can be launched by one click.

## Decision

The first Brand Builder stage creates a validated private blueprint. It checks
brand key, domain uniqueness, theme values and module selection against the
runtime registry and displays the explicit launch prerequisites. It does not
write runtime configuration, allocate a database ID, change DNS or activate a
public brand.

Promotion from blueprint to a configured private brand is a reviewed engineering
change containing registry configuration, a forward migration, tests and launch
documentation. Public activation is a later Platform Quality Gate decision.

## Consequences

- Administrators can explore and validate a brand without creating infrastructure
  drift.
- Brand creation is slower than an unsafe direct database insert, but remains
  reproducible and auditable.
- Later versions may persist blueprint drafts and automate pull-request creation;
  they must retain review and launch gates.

## Quality Gate impact

- Architecture: one typed registry remains the runtime source of truth.
- UX: the builder clearly distinguishes preview, configuration and launch.
- Engineering: domain collision and configuration validation are testable.
- Business: a blueprint cannot be represented as a launched commercial brand.

## Validation and rollback

Unit tests cover accepted and rejected blueprint inputs. The preview makes no
persistent change, so rollback is leaving the page. Any later promoted brand uses
its normal migration and release rollback documentation.

