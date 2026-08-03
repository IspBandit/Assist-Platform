# Start here

This is the authoritative orientation page for developers and AI agents.

## Read in this order

1. `AGENTS.md` — mandatory safety and engineering rules.
2. `docs/PRODUCT_BIBLE.md`, `docs/ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md`
   and `docs/PLATFORM_CHARTER.md` — primary product direction, architecture and
   principles.
3. `docs/PRODUCT_AND_FEATURES.md` — intended products versus implemented scope.
4. `docs/PRODUCTION_CURRENT_STATE.md` — what is actually live and what is pending.
5. `PROJECT_STATUS.md` — single authoritative completion-mode status.
6. `docs/CURRENT_ARCHITECTURE.md`, `docs/TARGET_ARCHITECTURE.md` and
   `docs/UNIFIED_ASSIST_PLATFORM.md`.
7. `docs/PLATFORM_DESIGN_SYSTEM.md`, `docs/UX_COMPONENT_INVENTORY.md`,
   `docs/UX_DECISIONS.md`, `docs/PRODUCT_BACKLOG.md`, `docs/ROADMAP.md`,
   `docs/ARCHITECTURE_DECISION_RECORDS.md` and `docs/PLATFORM_QUALITY_GATE.md`.
8. `docs/DATABASE_DICTIONARY.md` and `docs/ROUTES_AND_PERMISSIONS.md`.
9. `docs/DEVELOPER_GUIDE.md`, `docs/CODING_STANDARDS.md`,
   `docs/LOCAL_DEVELOPMENT.md`, `docs/TESTING.md` and `CONTRIBUTING.md`.
10. For releases, `docs/OPERATIONS_MANUAL.md`, `docs/OPERATIONS_RUNBOOK.md`,
    `docs/BACKUP_AND_RESTORE.md`, `docs/ENVIRONMENT_CONFIGURATION.md` and
    `docs/RELEASE_NOTES.md`.
11. For product-specific data, `docs/TOWSMART_CATALOGUE.md`,
    `docs/VANASSIST_STAYS.md`, `docs/LOCALTORQUE.md` and
    `docs/polaris/README.md` and `docs/polaris/IMPLEMENTATION_STATUS.md`
    (Project Polaris — master prompt not complete; private vertical slice).
12. For living customer, provider, administrator, developer and API guidance,
    read `docs/user-guide/README.md` and `docs/user-guide/registry.php`.
13. For the versioned Admin API and Assist RIC synchronisation (CORE-011,
    DATA-011, Option B programme): `docs/OPTION_B_MANAGEMENT_PROGRAMME.md`,
    `docs/LIVE_API.md`, `docs/PHASE1_ADMIN_API_DESIGN.md`,
    ADRs 0018–0020, and the sibling repo `assist-ric`
    (`docs/architecture/adr/0003-sibling-repository.md`).
    National Dataset Catalogue (DATA-011A): `docs/DATA_011A.md`, ADR 0033 —
    catalogue before additional importers; RIC acquires, Platform publishes via
    Admin API only.
14. For Assist AI Search and Knowledge Orchestration (CORE-012 / VAN-011 /
    DATA-013 / DATA-012): `docs/AI_WORKSTREAM_STATUS.md`,
    `docs/DATA_012.md`, `docs/TRAVELLER_FACILITIES.md`,
    `docs/PHASE_AI0_DESIGN.md` (approved design baseline),
    `docs/AI_ORCHESTRATOR.md`, `docs/NATURAL_LANGUAGE_SEARCH.md`,
    `docs/KNOWLEDGE_GAPS.md`, `docs/SEARCH_GAP_DUAL_SOURCE.md` (Option B
    dual-source into inventoried `GET /search-gaps`), ADRs 0021–0030 and 0032.
    AI-1–AI-7 + DATA-012 live in code behind flags; `/ask`, cache/budget,
    `/admin/ai-search`, government datasets → facility review. Do not invent a
    second search-gaps API or expand locked Phase 1 OpenAPI schemas. See
    `docs/OPENAI_INTEGRATION.md` and `docs/AI_RELEASE_CRITERIA.md` before
    enabling paid AI or facilities in production.
15. For VanAssist production readiness / controlled release (no AI scope
    expansion; flags stay off in production):
    `docs/VANASSIST_PRODUCTION_READINESS_PACKAGE.md`,
    `docs/acceptance/VA_ACCEPT_BATEHAVEN_001.md`,
    `docs/evidence/vanassist-readiness-2026-08-02/`,
    `docs/SEARCH_GAP_DUAL_SOURCE.md`, and
    `docs/PLATFORM_QUALITY_GATE.md`. Local S0–S2 + CONDITIONAL PASS evidence
    is recorded; do not enable production Ask/facilities/paid AI.

## Sources of truth

When documents disagree, use this precedence:

1. Executable code, ordered migrations and automated tests.
2. `PRODUCTION_CURRENT_STATE.md` for the last verified live state.
3. `PRODUCT_BIBLE.md` and `ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md` for
   product direction and future architecture.
4. Current architecture/product documents listed above.
5. Historical audit, migration and implementation notes.

Historical documents describe what was true when written; they are not proof
that a feature remains incomplete or has since been delivered.

## Platform summary

Assist Platform Enterprise is the primary product. One server-rendered PHP
application, one MariaDB database and one production deployment serves three
public brands, one private LocalTorque foundation, and Project Polaris as a
private fifth brand until its production domain is confirmed. The hostname
resolves a trusted `Brand` object. Brand context affects presentation, links,
email, features, modules, SEO and data scoping.

Never use production credentials for local development. A code change is not
permission to deploy, migrate live data, change DNS or enable charging.

Assist RIC (Regional Intelligence Collector) is a sibling desktop application
used for research, staging and synchronisation. It must not open the production
database; it talks to Assist Platform only through `/api/v1/admin` once that
API is enabled (ADR 0018).
