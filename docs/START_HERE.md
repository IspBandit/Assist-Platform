# Start here

This is the authoritative orientation page for developers and AI agents.

## Read in this order

1. `AGENTS.md` — mandatory safety and engineering rules.
2. `docs/PRODUCT_AND_FEATURES.md` — intended products versus implemented scope.
3. `docs/PRODUCTION_CURRENT_STATE.md` — what is actually live and what is pending.
4. `docs/CURRENT_ARCHITECTURE.md` and `docs/TARGET_ARCHITECTURE.md`.
5. `docs/DATABASE_DICTIONARY.md` and `docs/ROUTES_AND_PERMISSIONS.md`.
6. `docs/LOCAL_DEVELOPMENT.md`, `docs/TESTING.md` and `CONTRIBUTING.md`.
7. For releases, `docs/OPERATIONS_RUNBOOK.md` and `docs/BACKUP_AND_RESTORE.md`.
8. For product-specific data, `docs/TOWSMART_CATALOGUE.md` and `docs/VANASSIST_STAYS.md`.

## Sources of truth

When documents disagree, use this precedence:

1. Executable code, ordered migrations and automated tests.
2. `PRODUCTION_CURRENT_STATE.md` for the last verified live state.
3. Current architecture/product documents listed above.
4. Historical audit, migration and implementation notes.

Historical documents describe what was true when written; they are not proof
that a feature remains incomplete or has since been delivered.

## Platform summary

One server-rendered PHP application, one MariaDB database and one production
deployment serve three independently branded domains. The hostname resolves a
trusted `Brand` object. Brand context affects presentation, links, email,
features, modules, SEO and data scoping.

Never use production credentials for local development. A code change is not
permission to deploy, migrate live data, change DNS or enable charging.

