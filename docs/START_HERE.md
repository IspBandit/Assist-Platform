# Start here

This is the authoritative orientation page for developers and AI agents.

## Read in this order

1. `AGENTS.md` — mandatory safety and engineering rules.
2. `docs/ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md` and
   `docs/PLATFORM_CHARTER.md` — primary product direction and principles.
3. `docs/PRODUCT_AND_FEATURES.md` — intended products versus implemented scope.
4. `docs/PRODUCTION_CURRENT_STATE.md` — what is actually live and what is pending.
5. `docs/CURRENT_ARCHITECTURE.md`, `docs/TARGET_ARCHITECTURE.md` and
   `docs/UNIFIED_ASSIST_PLATFORM.md`.
6. `docs/PLATFORM_DESIGN_SYSTEM.md`, `docs/PRODUCT_BACKLOG.md`,
   `docs/ARCHITECTURE_DECISION_RECORDS.md` and `docs/PLATFORM_QUALITY_GATE.md`.
7. `docs/DATABASE_DICTIONARY.md` and `docs/ROUTES_AND_PERMISSIONS.md`.
8. `docs/LOCAL_DEVELOPMENT.md`, `docs/TESTING.md` and `CONTRIBUTING.md`.
9. For releases, `docs/OPERATIONS_RUNBOOK.md` and `docs/BACKUP_AND_RESTORE.md`.
10. For product-specific data, `docs/TOWSMART_CATALOGUE.md`,
    `docs/VANASSIST_STAYS.md` and `docs/LOCALTORQUE.md`.

## Sources of truth

When documents disagree, use this precedence:

1. Executable code, ordered migrations and automated tests.
2. `PRODUCTION_CURRENT_STATE.md` for the last verified live state.
3. `ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md` for product direction and
   future architecture.
4. Current architecture/product documents listed above.
5. Historical audit, migration and implementation notes.

Historical documents describe what was true when written; they are not proof
that a feature remains incomplete or has since been delivered.

## Platform summary

Assist Platform Enterprise is the primary product. One server-rendered PHP
application, one MariaDB database and one production deployment serves three
public brands and one private LocalTorque foundation. The hostname resolves a
trusted `Brand` object. Brand context affects presentation, links, email,
features, modules, SEO and data scoping.

Never use production credentials for local development. A code change is not
permission to deploy, migrate live data, change DNS or enable charging.

