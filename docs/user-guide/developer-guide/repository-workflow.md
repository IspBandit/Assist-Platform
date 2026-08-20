# Repository workflow

## Purpose

Change Assist Platform Enterprise without breaking its product boundary, multi-brand scope, production safety or existing UX system.

## Intended users

Application developers, reviewers and coding agents working in this repository.

## Permissions

Repository access does not authorise production deployment, DNS changes, billing activation, secret access or destructive data operations. Runtime permissions and ownership checks must remain server-enforced.

## Fields

Every change identifies one backlog item, affected brands, shared-versus-brand ownership, tests, migration and environment impact, quality-gate evidence, deployment considerations and rollback. Use “None on this page” where a pull-request field genuinely does not apply; do not silently omit it.

## Actions

Read the authoritative documents, inspect current code and migrations, preserve unrelated work, implement the smallest coherent change, add tests, update affected documentation, and run the required validation baseline.

## Workflows

Start with `AGENTS.md` and `docs/START_HERE.md`. Select one owning backlog ID. Check whether an ADR is required. Implement through the existing architecture and Design System. Run Composer validation, static analysis, relevant tests, syntax checks and any affected production build, then record all four Platform Quality Gate results.

## Examples

A new admin action belongs to an existing controller and permission model, includes a server-side brand/ownership check, adds route and tests, updates route documentation, and records rollback. A schema change uses a new ordered migration rather than editing an applied one.

## Common mistakes

- Treating a planning document as stronger evidence than executable code.
- Adding hostname conditionals instead of using `App\Platform\Brand`.
- hiding a button without enforcing authorisation in the controller.
- Claiming a production release because local tests pass.

## Related pages

See **Current API boundary**, **Current release state**, the root changelog and the canonical developer documents listed in the article metadata.

## FAQ

**Which source wins when documents disagree?** Executable code, ordered migrations and automated tests define implementation; `PRODUCTION_CURRENT_STATE.md` defines the last verified live state.

**Must every change have an ADR?** No. ADRs are for material architecture, ownership, vendor, security, billing, domain or release decisions.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
