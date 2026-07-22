# Assist Platform agent instructions

These instructions apply to every AI coding agent and every directory in this
repository. Read `docs/START_HERE.md` before changing code.

## Product boundary

This repository contains one PHP application serving three brands:

- VanAssist (`vanassist.com.au`): caravan/RV provider marketplace and assistance workflows.
- TowSmart (`towsmart.com.au`): towing calculator, saved combinations and safety guidance.
- TrailerWise (`trailerwise.com.au`): trailer marketplace and listing management.

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

## Required workflow

1. Inspect Git status and preserve unrelated work.
2. Read the relevant architecture, product, schema, permissions and operations docs.
3. Make the smallest coherent change.
4. Add or update tests for changed behaviour.
5. Run `composer validate --strict`, `composer analyse`, relevant PHPUnit tests,
   PHP syntax checks and a production dependency build when applicable.
6. Document migrations, environment changes, security implications, deployment
   steps and rollback.
7. Use a feature/fix branch and pull request. Do not push directly to production.

## Production safety

Production is an Ubuntu 24.04 BinaryLane VPS using Docker Compose, PHP 8.3-FPM,
MariaDB 11.4 and Caddy. Its secrets and credentials are deliberately not stored
in Git. Production changes require an immutable release, checksum verification,
backup, controlled migration, health checks and rollback availability. See
`docs/OPERATIONS_RUNBOOK.md` and `docs/PRODUCTION_CURRENT_STATE.md`.

