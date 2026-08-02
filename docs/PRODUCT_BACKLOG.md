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
| CORE-011 | Versioned Admin API (`/api/v1/admin`) for RIC and trusted management clients | done | OpenAPI contract, auth/scopes, providers/stays lifecycle, draft ingest, recycle bin, contract tests; no direct DB access from clients (PRs #139–#140) |

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
| VAN-002 | Provider claims, assistance and nearby-help launch readiness | in progress | Admin API claims/corrections shipped (Increment B); end-to-end acceptance remains |
| VAN-010 | Claim-first provider onboarding before new listing creation | done | Search-before-create on `/for-providers/register`, duplicate hold, `CLAIM_FIRST_ONBOARDING` flag |
| VAN-011 | AI-assisted natural-language search (intent → location/category) | later | Optional, budget-capped, off by default; never factual authority; depends on CORE-011 and OPS-010 |
| TOW-001 | TowSmart catalogue provenance and calculation review | in progress | Domain review, formula tests and honest limitation copy |
| TOW-002 | Rich saved combination edit/compare/report workflow | ready | Owner-isolation and calculation snapshot tests |
| TRL-001 | Service-first trailer business discovery | in progress | Manufacturer/dealer/repair/parts/certifier journey tests |
| TRL-002 | Trailer ownership and compliance content system | done | Shared Garage, trailer source/jurisdiction guide, freshness alerts and specialist handoff pass |
| LOC-001 | LocalTorque first-class private brand foundation | done | Private render, categories, sitemap and enrichment report |
| LOC-002 | LocalTorque national coverage, claims and search readiness | in progress | Coverage report, duplicate review and claim acceptance |
| LOC-003 | LocalTorque production launch | blocked | Domain purchase, DNS, email, legal and launch acceptance |
| LOC-004 | LocalTorque complete motorsport rule, venue and calendar discovery | in progress | Explicit national discipline taxonomy, official rule layers, verified venue websites/calendars, source freshness and mobile journey acceptance |

## Data

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| DATA-001 | Provider and stay provenance, import history and rollback | in progress | Import reports, coordinate/locality conflict correction, public-visibility release gate and quarantine controls |
| DATA-002 | Duplicate detection and merge with audit preservation | in progress | Admin API duplicate review/merge + dry run shipped; full merge workflow QA remains |
| DATA-003 | Cross-brand recommendation policies | ready | Relevance rules, labelled origin and analytics |
| DATA-004 | Brand-scoped website, provider-interest and coverage-gap reporting | in progress | Shared first-party event scope, admin website-insights summary and zero-result/provider-interest reporting implemented; production data collection and acceptance remain |
| DATA-005 | Data Intelligence, opportunity scoring and action queue | in progress | Modular metric sources, population-aware scoring, verification/import quality and direct Data Sources hand-off |
| DATA-006 | Connector-based discovery, encrypted credentials and review-first imports | done | Migration, connector contract, Google adapter, admin workflow, audit and tests |
| DATA-007 | Maps/geocoding production limits and fallback | ready | Quota, failure and list-view acceptance |
| DATA-008 | Four-brand authoritative Australian vehicle rules library | done | All-jurisdiction official-source catalogue, brand relevance, genuine downloads, source-change review, mobile filters and labelled local sponsorship |
| DATA-009 | Regulatory change alerts and freshness control centre | done | Subscriber scope/consent, reviewer queue, source-health dashboard, notification audit and changed-source fail-closed acceptance |
| DATA-010 | Australian motorsport authority, discipline, venue and calendar catalogue | in progress | All taxonomy families mapped to official rule and venue sources; calendar/source monitoring and representative jurisdiction acceptance |
| DATA-011 | Assist RIC live Admin API synchronisation | done | RIC live client, package mapper, validate-only submit, search-gaps pull and sync status shipped; staging rehearsal is operational (not a code gap) |
| DATA-012 | Government dataset catalogue and import connectors | in progress | Admin API dataset catalogue + sync-run stub shipped; connector workers remain |
| DATA-013 | Search gap and knowledge growth engine | in progress | Admin API search analytics + gaps shipped; RIC research workflow remains (Increment J) |
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
| OPS-006 | Living user, administrator and API documentation stays synchronized with product behaviour | in progress | Scope-matched guide and release-note updates enforced in pull requests and CI; complete current customer/provider guides and release history remain |
| OPS-010 | Admin API security, service accounts and cost controls | in progress | Tokens, scopes, throttling and TOTP MFA shipped; conditional Quality Gate recorded (`docs/evidence/admin-api-2026-08-02/`); production MFA/enable flags + paid-connector hard limits remain |
| OPS-011 | Record lifecycle and Recycle Bin for providers and stays | done | Soft delete, restore, retention, purge permission and audited recycle APIs shipped in CORE-011 Phase 1 |

## Commercial

| ID | Outcome | Status | Exit evidence |
| --- | --- | --- | --- |
| COM-001 | Transactional email transport and sender reputation | in progress | Direct shared-mailbox send/receive passes; brand-neutral templates, application probes and central bounce/complaint suppression implemented; production application-probe acceptance remains |
| COM-002 | Consent-aware bulk provider campaign sending | in progress | Canonical brand-category drafts, searchable candidate pool, campaign exclusions, factual-source and marketing-consent boundaries, bounded queue batches, suppression and signed unsubscribe complete; production recipient-count and throughput acceptance remains |
| COM-003 | Provider launch and audience-growth programme | in progress | Provider conversion plus evidence-backed organisation PR register, segmented message tracks and staged delivery implemented; first monitored live pilots remain |
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

See `docs/PHASE1_ADMIN_API_DESIGN.md` and ADRs 0018–0020.

## Reconciled experience delivery

- EXP-001: conversion-led customer homepage evidence, process and trust layer — done.
- EXP-004: provider acquisition journey across all brands — done.
- EXP-005: provider command-centre hierarchy and growth workspace — done.

These items retain the current premium symbol family, optimised contextual hero
assets, live location controls and server-side brand scoping.
