# Assist Platform Enterprise product backlog

This is the authoritative portfolio backlog. It records outcomes and sequencing;
implementation detail belongs in linked issues and pull requests. Status values:
`done`, `in progress`, `ready`, `blocked`, `discovery`, `later`.

## Platform

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| CORE-001 | One typed brand registry and trusted host resolution | done | Brand/domain tests and current production checks |
| CORE-002 | Unified admin, global view and permission-scoped brand switching | in progress | Cross-domain handoff, RBAC and rendered acceptance |
| CORE-003 | Canonical providers with relevant per-brand listings | in progress | Isolation, relevance and duplicate tests |
| CORE-004 | Shared membership entitlements for Launch, Free, Founding, Verified and Featured | done | Migration 045 catalogue, entitlement tests, provider dashboard states and billing-disabled acceptance |
| CORE-005 | Shared provider launch email templates and campaign segmentation | ready | Preview/test-send, consent, unsubscribe and queue tests |
| CORE-007 | Platform Control Centre for domains, launch state, features and operational status | in progress | Super-admin-only acceptance and audit coverage |
| CORE-008 | Controlled Brand Builder over validated configuration | in progress | ADR and private blueprint preview complete; persistence/promotion automation remains |

## Experience

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| EXP-001 | Promote current UX redesign into shared tokens/components | in progress | Component inventory documented; semantic-token migration and regression renders remain |
| EXP-002 | Unified admin information architecture and brand switcher | in progress | Desktop/mobile rendered acceptance and keyboard tests |
| EXP-003 | Production-grade Social Studio templates and individual exports | in progress | Exact-size assets, editorial approval and no mock-up/crop contamination |
| EXP-004 | WCAG 2.2 AA critical journeys | ready | Automated checks plus manual keyboard/screen-reader review |
| EXP-005 | Mobile-first provider, search, calculator and admin journeys | in progress | Rendered acceptance on representative widths |

## Brands

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| VAN-001 | Accurate national stays directory belongs only to VanAssist | in progress | Data-quality reports and public search acceptance |
| VAN-002 | Provider claims, assistance and nearby-help launch readiness | ready | End-to-end provider/customer acceptance |
| TOW-001 | TowSmart catalogue provenance and calculation review | in progress | Domain review, formula tests and honest limitation copy |
| TOW-002 | Rich saved combination edit/compare/report workflow | ready | Owner-isolation and calculation snapshot tests |
| TRL-001 | Service-first trailer business discovery | in progress | Manufacturer/dealer/repair/parts/certifier journey tests |
| TRL-002 | Trailer ownership and compliance content system | ready | Source, jurisdiction and freshness requirements pass |
| LOC-001 | LocalTorque first-class private brand foundation | done | Private render, categories, sitemap and enrichment report |
| LOC-002 | LocalTorque national coverage, claims and search readiness | in progress | Coverage report, duplicate review and claim acceptance |
| LOC-003 | LocalTorque production launch | blocked | Domain purchase, DNS, email, legal and launch acceptance |

## Data

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| DATA-001 | Provider and stay provenance, import history and rollback | in progress | Import reports and quarantine controls |
| DATA-002 | Duplicate detection and merge with audit preservation | ready | Dry run, merge tests and administrator workflow |
| DATA-003 | Cross-brand recommendation policies | ready | Relevance rules, labelled origin and analytics |
| DATA-004 | Coverage-gap reporting by brand/category/location | ready | Admin reports and zero-result analytics |
| DATA-005 | Data Intelligence, opportunity scoring and action queue | in progress | Modular metric sources, population-aware scoring, verification/import quality and direct Data Sources hand-off |
| DATA-006 | Connector-based discovery, encrypted credentials and review-first imports | done | Migration, connector contract, Google adapter, admin workflow, audit and tests |
| DATA-007 | Maps/geocoding production limits and fallback | ready | Quota, failure and list-view acceptance |

## Infrastructure

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| INF-001 | One versioned deployment pipeline and immutable release artefact | done | CI/release workflow, checksum and rollback evidence |
| INF-002 | Domain, proxy, canonical host and brand resolution parity | in progress | All-brand DNS/proxy/asset/sitemap smoke tests |
| INF-003 | Environment, secret and integration configuration contract | in progress | Validated startup, documented variables and rotation procedure |
| INF-004 | Capacity, storage and performance baseline | ready | Measured PHP/DB/storage/traffic thresholds and upgrade triggers |

## Operations

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| OPS-001 | Immutable gated production releases | done | Production release workflow and runbook |
| OPS-002 | Backup, restore and rollback rehearsal | ready | Timestamped successful rehearsal record |
| OPS-003 | Monitoring for app, DB, storage, mail and scheduled work | in progress | Health dashboard and alert verification |
| OPS-004 | Full Platform Quality Gate for release candidates | ready | Signed gate record linked to release |
| OPS-005 | Sale-readiness operational/data room index | later | Architecture, licences, data provenance, runbooks and metrics indexed |

## Commercial

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| COM-001 | Transactional email transport and sender reputation | blocked | Sender DNS, credentials, delivery and bounce tests |
| COM-002 | Consent-aware bulk provider campaign sending | blocked | COM-001 plus consent, throttling and unsubscribe acceptance |
| COM-003 | Provider launch and founding-membership conversion programme | ready | Templates, segments, transition notices and conversion analytics |
| COM-004 | Safe billing provider integration and GST-ready lifecycle | blocked | Owner gateway choice plus legal/tax and webhook acceptance |
| COM-005 | Sale-readiness product, licence, data and operating package | later | Indexed due-diligence pack and transfer rehearsal |

## Backlog rules

- New work must use one owning workstream: Platform, Experience, Brands, Data,
  Infrastructure, Operations or Commercial. It may link dependent IDs.
- New features require an outcome, owner, priority, evidence and quality-gate impact.
- A feature cannot be `done` because a table, route or placeholder exists.
- Blocked external prerequisites remain explicit; code must fail closed.
- Production defects can interrupt sequencing, but their resolution must update
  this backlog and the relevant operational record.
