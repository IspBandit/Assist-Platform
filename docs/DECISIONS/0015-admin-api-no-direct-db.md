# ADR 0015: Admin API is the only external write path to production

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** CORE-011, OPS-010, DATA-011
- **Affected brands/modules:** Assist Platform Enterprise, Assist RIC, all brands

## Context

Assist RIC and future management tools need to read and update VanAssist
providers, stays and import drafts. Connecting those tools directly to
production MariaDB would bypass RBAC, audit, brand scoping, validation and
release controls, and would couple desktop tooling to schema internals.

`docs/API.md` already requires a versioned `/api/v1` contract with scoped
credentials. No such product surface exists yet.

## Decision

1. The PHP Assist Platform Enterprise application remains the **system of
   record**.
2. Assist RIC, importers, AI workers and any local management client **must
   never** open a production MariaDB connection.
3. The only supported production read/write path for external management tools
   is the authenticated, versioned **`/api/v1/admin`** interface.
4. Browser session cookies are not a general API credential; human admins and
   service accounts use token credentials with least-privilege scopes.
5. Architecture Option B is confirmed: extend Assist RIC as the initial local
   research/management client; do **not** create a parallel Tauri/React/FastAPI
   management application or a third local staging database in Phase 1.

## Alternatives considered

- Direct SQL from RIC: rejected (audit bypass, credential sprawl, schema lock-in).
- Scraping `/admin` HTML forms: rejected (fragile, CSRF-coupled, not machine-safe).
- New greenfield management stack: deferred; duplicates RIC engines.

## Consequences

- Phase 1 must ship `/api/v1/admin` before broad RIC sync UI work.
- Existing admin HTML controllers remain; API controllers wrap shared services.
- MFA scaffolding is required in Phase 1; MFA enforcement is required before
  general remote administrative API use.
- Export packages from RIC remain valid until live draft submission endpoints
  replace file drop as the preferred path.

## Quality Gate impact

- Architecture: accepted — single system of record, explicit boundary.
- UX: RIC remains operator surface; web `/admin` unchanged initially.
- Engineering: new auth tables, routes, contract tests, OpenAPI.
- Business: enables safe growth tooling without production DB exposure.

## Validation and rollback

- Validate with contract tests against a non-production database.
- Rollback: disable API route registration and revoke credentials; HTML admin
  continues to operate. Forward-only migrations leave tables unused if rolled
  back operationally.
