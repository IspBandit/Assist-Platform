# ADR 0023: Natural-language search alongside structured search

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / VanAssist
- **Backlog item:** VAN-011, EXP-005
- **Affected brands/modules:** VanAssist public search UX

## Context

Users already rely on category dropdown, town typeahead, Near Me and automatic
location. Replacing that UX would regress reliability for users who prefer it.

## Decision

Keep existing structured search methods available and unchanged. Add a separate
Ask VanAssist (or equivalent) NL interface that reuses result presentation
where practical. NL must not hide or replace `/find` or `/stays` structured
flows.

## Alternatives considered

- Replace dropdown with chat-only: rejected.
- Hide structured search behind progressive disclosure: rejected for v1.

## Consequences

Two entry points; shared adapters underneath. Analytics must distinguish
channels.

## Quality Gate impact

- UX: explicit dual entry.
- Engineering: additive routes/views only.
- Business: preserves current conversion paths.

## Validation and rollback

Remove/hide Ask UI via flag; structured search unchanged.
