# Assist Platform Enterprise agent instructions

These instructions apply to every AI coding agent and every directory in this
repository. Read `docs/START_HERE.md` before changing code.

## Product and governance boundary

Assist Platform Enterprise is the primary saleable product. Before planning or
implementing work, read `docs/ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md`,
`docs/PLATFORM_CHARTER.md`, `docs/PRODUCT_BACKLOG.md` and
`docs/PLATFORM_QUALITY_GATE.md`.

This repository contains one PHP application serving four brands:

- VanAssist (`vanassist.com.au`): caravan/RV provider marketplace and assistance workflows.
- TowSmart (`towsmart.com.au`): towing calculator, saved combinations and safety guidance.
- TrailerWise (`trailerwise.com.au`): trailer marketplace and listing management.
- LocalTorque (private until its production domain is confirmed): Australian automotive business directory.

NDTFlow and SignConsole are separate products. Never add them here.

## Non-negotiable rules

- Preserve existing VanAssist IDs, provider slugs, routes and data unless a
  reviewed migration and redirect plan explicitly changes them.
- Resolve brands only through `App\Platform\Brand`; do not scatter hostname or
  brand-name conditionals through controllers and views.
- Enforce authentication, permissions, brand scope and resource ownership on
  the server. Hidden controls are not authorisation.
- Treat `database/migrations/` as the authoritative schema. Never edit an
  applied migration; add a forward migration.
- Never reset, truncate or destructively migrate production automatically.
- Never commit `.env`, credentials, database dumps, private uploads or customer data.
- Keep runtime data in shared `storage/`, not immutable release directories.
- TowSmart calculations must be pure, typed, tested and described as guidance,
  never certification or legal advice.
- Sponsored content must be labelled and must not silently alter organic results.
- Do not claim a feature, compliance status or deployment is complete without evidence.
- Preserve the UX redesign already present in the repository. Extend it through
  `docs/PLATFORM_DESIGN_SYSTEM.md`; do not create a competing design system.
- Assign new work to one backlog workstream and reference its backlog ID.
- Create an ADR for material decisions described in
  `docs/ARCHITECTURE_DECISION_RECORDS.md`.
- A production release requires Architecture, UX, Engineering and Business
  evidence under the Platform Quality Gate.

## Required workflow

1. Inspect Git status and preserve unrelated work.
2. Read the relevant architecture, product, schema, permissions and operations docs.
3. Identify the owning backlog item and whether an ADR is required.
4. Make the smallest coherent change without disrupting parallel UX work.
5. Add or update tests for changed behaviour.
6. Run `composer validate --strict`, `composer analyse`, relevant PHPUnit tests,
   PHP syntax checks and a production dependency build when applicable.
7. Document migrations, environment changes, security implications, deployment
   steps and rollback.
8. Record all four Quality Gate results in the pull request.
9. Use a feature/fix branch and pull request. Do not push directly to production.

## Production safety

Production is an Ubuntu 24.04 BinaryLane VPS using Docker Compose, PHP 8.3-FPM,
MariaDB 11.4 and Caddy. Its secrets and credentials are deliberately not stored
in Git. Production changes require an immutable release, checksum verification,
backup, controlled migration, health checks and rollback availability. See
`docs/OPERATIONS_RUNBOOK.md` and `docs/PRODUCTION_CURRENT_STATE.md`.

