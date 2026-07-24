# Coding standards

These standards complement `AGENTS.md` and `CONTRIBUTING.md`.

## Architecture

- Treat Assist Platform Enterprise as one product and the four sites as brands.
- Resolve brands through `App\Platform\Brand` and pass explicit brand context.
- Put genuinely shared behaviour in platform services; keep TowSmart calculations
  and other distinct domain rules explicit.
- Enforce user, provider, organisation and brand scope on the server and at the
  data-access boundary.
- Prefer additive changes and forward migrations. Never edit an applied migration.

## PHP and database

- Use strict types for new PHP files where compatible with the current runtime.
- Type parameters, returns and properties; avoid mixed/array-shaped contracts when
  a small value object or documented shape is clearer.
- Validate at trust boundaries and use prepared queries for all external values.
- Keep controllers thin; place reusable business rules in services/domain classes.
- Add indexes from observed query patterns and document migration/rollback impact.

## Interface and content

- Extend the existing server-rendered UX and official design tokens/components.
- Use semantic HTML, visible focus, accessible names and useful failure states.
- Use Australian English and preserve the frozen brand boundaries.
- Never introduce fake counts, reviews, availability, verification or guarantees.

## Tests and completion

- Add tests for changed behaviour, permissions, brand isolation and migrations.
- Run Composer validation, static analysis, relevant PHPUnit suites, syntax checks
  and the production dependency build.
- A build is not proof of product completion. Record unavailable checks and risks.
- Update API, schema, operations and user-facing documentation with the code.
