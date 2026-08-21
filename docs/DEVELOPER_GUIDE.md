# Developer guide

## Orientation

1. Read `../AGENTS.md` and `START_HERE.md`.
2. Read the Product Bible, Enterprise Specification, Charter and current backlog.
3. Check `PRODUCTION_CURRENT_STATE.md`; documentation is not proof a feature is live.
4. Inspect Git status and preserve unrelated work, especially active UX changes.

## Choose the work correctly

- Assign one backlog ID in Platform, Experience, Brands, Data, Infrastructure,
  Operations or Commercial.
- Identify affected brands and the shared-versus-brand-specific owner.
- Create an ADR for material architecture, data ownership, vendor, security,
  billing, domain or release decisions.
- Extend the current Design System; do not create a new theme or admin shell.

## Implement and verify

- Make the smallest coherent change and use a forward migration when needed.
- Add tests for behaviour, brand scope, resource ownership and compatibility.
- Run the validation baseline in `../CONTRIBUTING.md` plus affected journey checks.
- Update API, database, UX and operations documents in the same change.
- Record Architecture, UX, Engineering and Business evidence in the pull request.

## Local and production operation

- Local setup: `LOCAL_DEVELOPMENT.md`.
- Tests: `TESTING.md`.
- APIs: `API.md`.
- Deployments: `DEPLOYMENT.md`, `OPERATIONS_MANUAL.md` and `OPERATIONS_RUNBOOK.md`.
- Never use production data or credentials in local development.
- Never deploy directly from an unreviewed feature branch.

## Brand boundaries

- VanAssist owns stays, travel support and caravan/RV services.
- TowSmart owns towing intelligence, matching, weights and safety guidance.
- TrailerWise owns trailer businesses, services, parts and ownership knowledge.
- LocalTorque owns canonical automotive workshop/specialist discovery.

Related data is connected through explicit relevance rules, not copied between
projects. There is one repository, database model, admin product and release path.
