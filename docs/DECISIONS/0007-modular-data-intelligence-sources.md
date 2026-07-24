# ADR 0007: Modular Data Intelligence sources

- Status: Accepted
- Date: 2026-07-24
- Backlog: DATA-004, DATA-005

## Context

Coverage decisions combine canonical providers, user demand, optional population facts and import quality. These inputs will change as new datasets and analytics services become available.

## Decision

Data Intelligence is a platform module above Data Sources. Metric producers implement a small source interface and are selected through a registry. Opportunity scoring, task creation and workflow hand-off remain source-independent. All metrics and tasks are brand scoped; population facts retain provenance and are optional.

## Consequences

- New analytics sources do not require controller, route or dashboard architecture changes.
- Source-specific query and licensing rules remain isolated.
- Scores are reproducible and testable.
- Administrators can act on recommendations without bypassing import review.
- Population analysis is unavailable until a properly sourced dataset is loaded; the platform does not infer it.
