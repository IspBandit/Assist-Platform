# ADR 0033: RIC is the national dataset acquisition engine

- **Status:** accepted
- **Date:** 2026-08-02
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** DATA-011A, DATA-011, DATA-012, CORE-011
- **Affected brands/modules:** Assist RIC, Admin API `/datasets`, government dataset catalogue

## Context

DATA-012 shipped a review-first government dataset catalogue and connectors.
The programme addendum requires RIC to become the single acquisition engine for
trusted datasets, with a National Dataset Catalogue completed **before** further
dataset-specific importers. Creating a second catalogue table or a parallel
import stack would violate reuse rules and ADR 0018/0020.

## Decision

1. Extend existing `government_datasets` as the National Dataset Catalogue
   (DATA-011A). Do **not** invent a parallel catalogue schema.
2. Assist RIC is the acquisition engine: check for updates, detect new/changed
   datasets, download, validate, normalise, stage, duplicate-check, then push
   approved packages via `/api/v1/admin` only.
3. Assist Platform remains the system of record and the only production write
   path (ADR 0018). No dataset may publish directly to production MariaDB.
4. Every catalogue row must carry provenance-capable fields: licence,
   attribution, source URL, API URL, trust level, entity types, duplicate rules,
   import mapping (`settings_json`), and status.
5. Complete the catalogue (schema + initial national rows) before writing
   additional dataset-specific importers.
6. Auto-update flags on catalogue rows authorise RIC scheduling only; they do
   **not** enable automatic production publish.

## Alternatives considered

- New `dataset_catalogue` table: rejected (duplicates DATA-012).
- Platform-only scheduled importers bypassing RIC: rejected (programme requires
  RIC as acquisition engine).
- Auto-publish trusted portals: rejected (review-first; ADR 0026/0029/0032).

## Consequences

- Migration `117_national_dataset_catalogue.sql` adds catalogue fields and seeds
  portals/themes including Toilet Map enrichment.
- Admin API and HTML admin expose the expanded catalogue contract.
- Further importers must reference a catalogue row and stage via Admin API.

## Quality Gate impact

- Architecture: reuse of `government_datasets` + RIC acquisition.
- Engineering: forward migration; contract/tests for catalogue fields.
- Business: no production auto-publish; staging enablement still OPS-010.
