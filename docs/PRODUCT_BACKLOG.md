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
| CORE-005 | Shared provider launch email templates and campaign segmentation | in progress | Brand-scoped full candidate review, audited add/remove controls, documented consent, suppression, unsubscribe and test/pilot/50/100 staged limits implemented; production pilot acceptance remains |
| CORE-007 | Platform Control Centre for domains, launch state, features and operational status | in progress | Super-admin-only acceptance and audit coverage |
| CORE-008 | Controlled Brand Builder over validated configuration | in progress | ADR and private blueprint preview complete; persistence/promotion automation remains |
| CORE-009 | Shared My Garage for vehicles, trailers, caravans and motorhomes | done | Owner isolation, mobile asset wallet, private document storage, expiry delivery and brand-aware actions |
| CORE-010 | Cross-brand vehicle and journey handoffs without duplicate profiles | done | Explicit consent, limited preserved context, source/destination brand audit and private-field exclusion |
| CORE-011 | Versioned Admin API (`/api/v1/admin`) for RIC and trusted management clients | ready | OpenAPI contract, auth/scopes, providers/stays lifecycle, draft ingest, recycle bin, contract tests; no direct DB access from clients; `GET /search-gaps` live on admin-api (`provider_searches`); AI branch has dual-source glue — merge per `docs/SEARCH_GAP_DUAL_SOURCE.md` |
| CORE-012 | Shared Assist AI Orchestrator (NL search + knowledge growth) | done | AI-0–AI-7 + DATA-012/013 on branch; flags off; Quality Gate CONDITIONAL PASS (`docs/AI_QUALITY_GATE_EVIDENCE.md`) |

## Experience

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| EXP-001 | Promote current UX redesign into shared tokens/components | in progress | Component inventory documented; semantic-token migration and regression renders remain |
| EXP-002 | Unified admin information architecture and brand switcher | in progress | Desktop/mobile rendered acceptance and keyboard tests |
| EXP-003 | Production-grade Social Studio templates and individual exports | in progress | Exact-size assets, editorial approval and no mock-up/crop contamination |
| EXP-008 | Review-first Facebook Page publishing from Social Studio | in progress | Approved-asset publishing, brand/page isolation and post audit implemented; Meta Page credentials and live publish acceptance remain |
| EXP-004 | WCAG 2.2 AA critical journeys | ready | Automated checks plus manual keyboard/screen-reader review |
| EXP-005 | Mobile-first provider, search, calculator and admin journeys | in progress | Rendered acceptance on representative widths |
| EXP-006 | Guided rule-to-action journeys from official requirement to compliant next step | done | Vehicle/jurisdiction/job guide, official citations, limitations, saved outcome and consented provider handoff |
| EXP-007 | Mobile trip and compliance wallet | done | Private mobile document access/download, expiry preferences and dispatch, source alerts and representative owner journeys |

## Brands

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| VAN-001 | Accurate national stays directory belongs only to VanAssist | in progress | Data-quality reports and public search acceptance |
| VAN-002 | Provider claims, assistance and nearby-help launch readiness | ready | End-to-end provider/customer acceptance |
| VAN-010 | Claim-first provider onboarding before new listing creation | ready | Search-before-create UX, “Is this your business?”, claim priority, duplicate hold, linked to VAN-002 and DATA-002 |
| VAN-011 | AI-assisted natural-language search (intent → location/category) | done | Ask VanAssist `/ask` alongside structured search; flag off by default; CORE-012 |
| TOW-001 | TowSmart catalogue provenance and calculation review | in progress | Domain review, formula tests and honest limitation copy |
| TOW-002 | Rich saved combination edit/compare/report workflow | ready | Owner-isolation and calculation snapshot tests |
| TRL-001 | Service-first trailer business discovery | in progress | Manufacturer/dealer/repair/parts/certifier journey tests |
| TRL-002 | Trailer ownership and compliance content system | done | Shared Garage, trailer source/jurisdiction guide, freshness alerts and specialist handoff pass |
| LOC-001 | LocalTorque first-class private brand foundation | done | Private render, categories, sitemap and enrichment report |
| LOC-002 | LocalTorque national coverage, claims and search readiness | in progress | Coverage report, duplicate review and claim acceptance |
| LOC-003 | LocalTorque production launch | blocked | Domain purchase, DNS, email, legal and launch acceptance |
| LOC-004 | LocalTorque complete motorsport rule, venue and calendar discovery | in progress | Explicit national discipline taxonomy, official rule layers, verified venue websites/calendars, source freshness and mobile journey acceptance |
| POL-001 | Polaris foundation: brand, docs, homepage, catalogue schema, browse/detail/find shell, admin nav | in progress | Private vertical slice; master prompt not complete — `docs/polaris/IMPLEMENTATION_STATUS.md` |
| POL-002 | Polaris catalogue browse completeness (filters, provenance UI, SEO) | in progress | Filters/sort + model provenance table/`099`; a11y CONDITIONAL |
| POL-003 | Guided matching and transparent recommendation engine | in progress | Find stages 1–10 + NL hints + MatchScorer + preference save |
| POL-004 | TowSmart compatibility service boundary for Polaris | in progress | `TowCompatibilityService` + `/tow-match` UX |
| POL-005 | Multi-model comparison experience | in progress | Up to four models, diffs/winners, shareable `/compare/{token}` (`095`) |
| POL-006 | Draft-first data acquisition and extraction review | in progress | CSV/JSON/XLSX + brochure text extract flags; AI import still off |
| POL-007 | Manufacturer portal (claim-first) | in progress | Claim + model edit + profile/media/dealer/team write paths (`096`) |
| POL-008 | VanAssist provider surfacing on Polaris pages | in progress | Related services block; no provider duplication |
| POL-009 | Polaris production hardening and public launch | blocked | Domain, Quality Gate, real catalogue; see `docs/polaris/RELEASE_CRITERIA.md` |

## Data

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| DATA-001 | Provider and stay provenance, import history and rollback | in progress | Import reports, coordinate/locality conflict correction, public-visibility release gate and quarantine controls |
| DATA-002 | Duplicate detection and merge with audit preservation | ready | Dry run, merge tests, administrator workflow, Admin API merge/review actions and RIC hand-off (absorbs former DATA-014 request) |
| DATA-003 | Cross-brand recommendation policies | ready | Relevance rules, labelled origin and analytics |
| DATA-004 | Brand-scoped website, provider-interest and coverage-gap reporting | in progress | Shared first-party event scope, admin website-insights summary and zero-result/provider-interest reporting implemented; production data collection and acceptance remain |
| DATA-005 | Data Intelligence, opportunity scoring and action queue | in progress | Modular metric sources, population-aware scoring, verification/import quality and direct Data Sources hand-off |
| DATA-006 | Connector-based discovery, encrypted credentials and review-first imports | done | Migration, connector contract, Google adapter, admin workflow, audit and tests |
| DATA-007 | Maps/geocoding production limits and fallback | ready | Quota, failure and list-view acceptance |
| DATA-008 | Four-brand authoritative Australian vehicle rules library | done | All-jurisdiction official-source catalogue, brand relevance, genuine downloads, source-change review, mobile filters and labelled local sponsorship |
| DATA-009 | Regulatory change alerts and freshness control centre | done | Subscriber scope/consent, reviewer queue, source-health dashboard, notification audit and changed-source fail-closed acceptance |
| DATA-010 | Australian motorsport authority, discipline, venue and calendar catalogue | in progress | All taxonomy families mapped to official rule and venue sources; calendar/source monitoring and representative jurisdiction acceptance |
| DATA-011 | Assist RIC live Admin API synchronisation | ready | RIC pulls canonical records, submits approved export/draft packages, reads sync status; depends on CORE-011 |
| DATA-012 | Government dataset catalogue and import connectors | done | Migrations `093`/`094`; CKAN/ArcGIS/CSV/GeoJSON; admin catalogue add/edit + review; demo fixtures + National Toilet Map rows (disabled) |
| DATA-013 | Search gap and knowledge growth engine | done | AI-4 tables + admin/CSV + SearchGap JSON export + `SearchGapDualSource`; wire dual-source into CORE-011 `GET /search-gaps` on merge (`docs/SEARCH_GAP_DUAL_SOURCE.md`) |
| DATA-014 | Canonical entity and source provenance model | ready | Stable entity IDs, source links, field-level provenance where practical; extends DATA-001 |

## Infrastructure

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| INF-001 | One versioned deployment pipeline and immutable release artefact | done | CI/release workflow, checksum and rollback evidence |
| INF-002 | Domain, proxy, canonical host and brand resolution parity | in progress | All-brand DNS/proxy/asset/sitemap smoke tests |
| INF-003 | Environment, secret and integration configuration contract | done | Fail-closed startup validation, complete variable inventory and documented rotation procedure |
| INF-004 | Capacity, storage and performance baseline | ready | Measured PHP/DB/storage/traffic thresholds and upgrade triggers |

## Operations

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| OPS-001 | Immutable gated production releases | done | Production release workflow and runbook |
| OPS-002 | Backup, restore and rollback rehearsal | ready | Fresh machine-readable off-site backup and restore evidence displayed in the launch gate |
| OPS-003 | Monitoring for app, DB, storage, mail and scheduled work | in progress | Health dashboard and alert verification |
| OPS-004 | Full Platform Quality Gate for release candidates | ready | Four-pillar live evidence panel plus signed gate record linked to release |
| OPS-005 | Sale-readiness operational/data room index | later | Architecture, licences, data provenance, runbooks and metrics indexed |
| OPS-010 | Admin API security, service accounts and cost controls | ready | Tokens, scopes, throttling, MFA scaffolding then MFA gate, paid-connector hard limits; pairs with CORE-011 |
| OPS-011 | Record lifecycle and Recycle Bin for providers and stays | ready | Soft delete, restore, retention, purge permission, dependency checks, audited bulk actions |
| OPS-012 | VanAssist reliability release (QG + DATA-012 coverage + controlled Ask) | in progress | Readiness package landed; production Ask/facilities/paid AI remain off; see `docs/VANASSIST_PRODUCTION_READINESS_PACKAGE.md` |

## Commercial

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| COM-001 | Transactional email transport and sender reputation | in progress | Direct shared-mailbox send/receive passes; brand-neutral templates, application probes and central bounce/complaint suppression implemented; production application-probe acceptance remains |
| COM-002 | Consent-aware bulk provider campaign sending | in progress | Brand-scoped audiences, searchable candidate pool, campaign exclusions, documented provider opt-in, bounded queue batches, suppression and signed unsubscribe complete; production throughput acceptance remains |
| COM-003 | Provider launch and founding-membership conversion programme | ready | Templates, segments, transition notices and conversion analytics |
| COM-004 | Safe billing provider integration and GST-ready lifecycle | blocked | Owner gateway choice plus legal/tax and webhook acceptance |
| COM-005 | Sale-readiness product, licence, data and operating package | later | Indexed due-diligence pack and transfer rehearsal |
| COM-006 | Verified provider capability credentials | done | Private evidence, expiry, reviewer audit, public labels and explicit no-endorsement controls |
| COM-007 | Provider campaign relevance and performance workspace | done | Self-service local/context targeting, transparent sponsorship, daily/total budgets, click/contact attribution and organic separation |

## Backlog rules

- New work must use one owning workstream: Platform, Experience, Brands, Data,
  Infrastructure, Operations or Commercial. It may link dependent IDs.
- New features require an outcome, owner, priority, evidence and quality-gate impact.
- A feature cannot be `done` because a table, route or placeholder exists.
- Blocked external prerequisites remain explicit; code must fail closed.
- Production defects can interrupt sequencing, but their resolution must update
  this backlog and the relevant operational record.

## Local management / Admin API ID remapping

Phase 0 requested IDs that collided with existing outcomes. Collision-free IDs:

| Requested | Assigned | Notes |
| --- | --- | --- |
| CORE-010 Versioned Admin API | **CORE-011** | CORE-010 already = cross-brand Garage handoffs |
| DATA-010 RIC Live API Sync | **DATA-011** | DATA-010 already = motorsport catalogue |
| DATA-011 Government datasets | **DATA-012** | Extends DATA-006 |
| DATA-012 Search gaps | **DATA-013** | Extends DATA-004 |
| DATA-013 Canonical entity/provenance | **DATA-014** | Extends DATA-001 |
| DATA-014 Duplicate review/merge | **DATA-002** | Consolidated into existing DATA-002 |
| VAN-010 / VAN-011 / OPS-010 / OPS-011 | unchanged | No collision |

See `docs/PHASE1_ADMIN_API_DESIGN.md` and ADRs 0015–0017.

## Assist AI Orchestration workstream

Separate from Admin API Phase 1 (CORE-011). Gate: `docs/PHASE_AI0_DESIGN.md`
(**AI-0 approved**). AI-1–AI-7 + DATA-012/013 **code-complete** on
`feature/core-012-ai-1-deterministic` (flags off by default). Original prompt
design + implementation satisfied — see `docs/AI_WORKSTREAM_STATUS.md`.
Quality Gate: **CONDITIONAL PASS** — `docs/AI_QUALITY_GATE_EVIDENCE.md`.
Primary IDs: **CORE-012**, **VAN-011**, **DATA-012**, **DATA-013**.
ADRs 0021–030 + **0032** accepted.

## Reconciled experience delivery

- EXP-001: conversion-led customer homepage evidence, process and trust layer — done.
- EXP-004: provider acquisition journey across all brands — done.
- EXP-005: provider command-centre hierarchy and growth workspace — done.

These items retain the current premium symbol family, optimised contextual hero
assets, live location controls and server-side brand scoping.
