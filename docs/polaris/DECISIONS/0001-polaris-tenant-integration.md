# ADR 0001: Polaris tenant integration

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-001
- **Affected brands/modules:** polaris, platform core

## Context

Assist Platform Enterprise serves multiple brands from one PHP application with
typed brand resolution. Polaris is a new RV decision product that must reuse auth,
admin, media, audit and deployment infrastructure — not ship as a separate codebase.

## Decision

Register Polaris as the **fifth brand** (`polaris`) following the LocalTorque
private-brand pattern:

- Row in `brands` table with status **`private`** until production domain confirmed
- Entry in `config/brands.php` with theme tokens and module flags
- Brand resolution exclusively via `App\Platform\Brand` — no query-parameter
  tenant switching in production
- Controllers and views under brand-aware routing in shared `routes/web.php`
- Module gate `rv_catalogue` for admin and public catalogue features

Polaris does not receive separate authentication, database or deployment pipeline.

## Alternatives considered

- Standalone microservice or SPA: rejected (duplicates platform capabilities, splits ops).
- Subdomain app without brand registry: rejected (violates charter).
- Public launch before domain decision: rejected (Blocked).

## Consequences

- `HomeController` and admin nav require explicit Polaris branches until policy-based
  routing exists.
- Migration `087+` introduces Polaris-specific tables prefixed or namespaced logically.
- Platform ADR 0031 should record fifth-brand policy for global index consistency.

## Quality Gate impact

- Architecture: shared tenancy preserved.
- UX: consistent shell and auth across brands.
- Engineering: one CI pipeline; brand-scoped tests required.
- Business: faster time-to-market vs greenfield app.

## Validation and rollback

Validate: `ASSIST_BRAND=polaris` resolves; other brands unaffected. Rollback:
disable module flag and brand row; migrations forward-only so tables remain dormant.
