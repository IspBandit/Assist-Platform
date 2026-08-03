# Option B — Management Platform Programme

**Status:** code-complete (Increments A–L) — production Admin API enablement
remains gated on staging rehearsal  
**Architecture:** ADRs 0018–0020 and Assist RIC ADR 0010 (authoritative)  
**Goal:** Functional coverage of the original VanAssist Management Platform
specification via Platform `/api/v1/admin`, PHP HTML admin, and Assist RIC —
not a new Tauri/React/FastAPI stack.

## Architecture lock

- Assist Platform Enterprise is the production system of record.
- `/api/v1/admin` is the only supported external production read/write path.
- Assist RIC is the local research, staging, data-quality and synchronisation client.
- PHP HTML admin remains for browser-based administration.
- No local application may connect directly to production MariaDB.
- Do not create a second SQLite staging model where RIC already has one.
- Do not duplicate RIC discovery connectors, duplicate logic, budget controls or review workflows.
- Stays = `caravan_parks` (`/stays`). Standalone amenity POIs = `traveller_facilities` (`/facilities`) per ADR 0019.

## Increment tracker

| Inc | Name | Status | Backlog |
| --- | --- | --- | --- |
| A | Programme charter | **done** (this doc) | CORE-011 follow-on |
| B | Claims + corrections Admin API | **done** | VAN-002 |
| C | Duplicates review/merge Admin API | **done** | DATA-002 |
| D | Datasets catalogue Admin API | **done** | DATA-012 + **DATA-011A** |
| E | AI usage + search analytics Admin API | **done** | CORE-012, DATA-013, OPS-010 |
| F | Sync conflicts + import lifecycle | **done** | DATA-011 |
| G | Traveller facilities Admin API | **done** | ADR 0019, DATA-012 |
| H | Claim-first onboarding + PHP admin polish | **done** | VAN-010, OPS-010/011 |
| I | RIC canonical pull + sync console | **done** (assist-ric `2c8bf53`) | DATA-011+ |
| J | RIC search-gap research workflow | **done** | DATA-013 |
| K | RIC dataset catalogue + budgets | **done** | DATA-012, OPS-010 |
| L | Staging hardening + Quality Gate | **done** (conditional) | OPS-010 |

## Rollback

Feature flags / `ADMIN_API_ENABLED`; revoke service accounts; disable RIC live
sync; forward-only compensating migrations; claim-first feature flag restore.

## Related docs

- [LIVE_API.md](LIVE_API.md)
- [PHASE1_ADMIN_API_DESIGN.md](PHASE1_ADMIN_API_DESIGN.md)
- [DECISIONS/0018-admin-api-no-direct-db.md](DECISIONS/0018-admin-api-no-direct-db.md)
- [DECISIONS/0019-stays-vs-traveller-facilities.md](DECISIONS/0019-stays-vs-traveller-facilities.md)
- [DECISIONS/0020-ric-as-management-client.md](DECISIONS/0020-ric-as-management-client.md)
- [PRODUCT_BACKLOG.md](PRODUCT_BACKLOG.md)
- [evidence/admin-api-2026-08-02/](evidence/admin-api-2026-08-02/)
- [evidence/option-b-programme-2026-08-02/](evidence/option-b-programme-2026-08-02/)
