# ADR 0006: Connector-based, review-first data ingestion

- Status: accepted
- Date: 2026-07-24
- Backlog: DATA-002, DATA-004, DATA-006
- Affected modules: Platform Control Centre, provider directory, claims, audit

## Context

Assist Platform needs national provider coverage without coupling its canonical
provider model to one directory vendor. External credentials are sensitive,
vendor data has different licensing/caching rules, and automatic publication
would create duplicates and low-trust listings.

## Decision

External discovery uses a small connector contract that returns normalized
candidates. Vendor behavior remains inside connector classes. Credentials are
encrypted at rest and restricted to Platform Admins. Imports enter a temporary
review queue, receive deterministic duplicate signals, and require an explicit
approve, merge or reject decision before affecting canonical providers.

The shared provider record remains authoritative. Connector provenance is
retained separately. Scheduled scans use the same service and guards as manual
scans. Google Places is the first connector, not a special platform dependency.

## Consequences

- Future connectors can be added without changing provider or admin core logic.
- A review step and independent data-rights verification add operational work.
- Application budget controls reduce accidental spend but do not replace vendor
  billing quotas/alerts.
- Candidate retention and source-specific compliance must be documented and
  enforced per connector.
- Platform Admin is the only role that can rotate credentials or publish imports.

## Alternatives rejected

- Direct Google-specific import into `providers`: too coupled and unsafe.
- Credentials in source code or ordinary settings: insufficient protection.
- Fully automatic publishing: unacceptable duplicate, quality and licensing risk.
- Separate import applications per brand: duplicates logic and breaks the shared
  provider model.
