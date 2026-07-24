# Database dictionary

`database/migrations/` is the authoritative field-level schema. This document is
a domain map, not a substitute for reading the relevant ordered migration.

| Domain | Principal tables | Ownership/scope |
|---|---|---|
| Identity | `users`, `roles`, `permissions`, `user_roles`, sessions, reset/verification/consent/history tables | User/global with explicit role and brand participation extensions |
| Geography | `countries`, `states`, `regions`, `towns`, `postcodes`, `town_neighbours` | Shared reference data |
| Providers | `providers`, prospects, contacts, services, areas, documents, licences, availability, verification, notes | Canonical provider plus explicit owner/membership and brand-listing records |
| Brands | `brands`, `brand_domains`, `provider_brand_listings`, `user_brand_profiles`, role/membership extensions | Every brand-private query must include appropriate brand context |
| Customers | `customers`, saved locations, alerts, saved providers/reviews | User-owned; some records receive brand context in later migrations |
| Assistance | `service_requests`, categories, images, history, notes, matches, messages | Customer/request ownership plus provider match authorisation |
| Service runs | `service_runs`, towns, services, requests, bookings, history | Provider-owned and brand-aware where implemented |
| Parks | `caravan_parks` and user/document/service-day tables | Park membership/ownership |
| Content | `content_pages`, blocks, FAQs, settings and feature flags | Brand scope is mandatory for public content |
| Email/notifications | templates, `email_queue`, `email_log`, notifications and recipients | Queue rows have required `brand_id`; sender must resolve from that brand |
| Analytics | sessions, events, searches/results/contact actions, daily aggregates and follow-ups | Privacy/retention controls apply; reports must respect brand scope |
| Billing | plans/prices/features/limits, subscriptions, entitlements, invoices, payments, refunds, discounts, webhooks, commissions and fees | Dormant until explicitly enabled; financial integrity required |
| Owner finance | accounts, tax codes, periods, journal entries/lines, source/audit events | Platform owner ledger, never provider bookkeeping |
| TowSmart | `tow_vehicles`, `towable_assets`, `towing_combinations` | User-owned and TowSmart brand-scoped |
| TrailerWise | `trailer_listings` | Provider-owned, TrailerWise-scoped, currently secondary product capability |
| Operations | migrations, audit/system logs, tasks, health, exports, rate limits | Administrative/operational data |

## Migration rules

- Never edit a migration recorded in a deployed database.
- Add the next ordered forward migration and make it restartable where data moves.
- Preserve IDs/slugs and use expand/backfill/validate/contract sequencing.
- Add foreign keys, uniqueness and indexes that match real ownership and queries.
- Test on a disposable database and production-shaped restore before live use.

# Data source ingestion (migration 043)

- `data_source_connectors`: connector registry, state and cost/quota guardrails.
- `data_source_credentials`: encrypted connector secrets and non-sensitive hints.
- `data_source_category_mappings`: brand category to connector query mapping.
- `data_source_import_jobs`: immutable execution summaries and failures.
- `data_source_import_candidates`: temporary normalized review records.
- `data_source_usage_daily`: platform-side request/cost estimates.
- `data_source_schedules`: due-scan definitions for the trusted CLI runner.
- `provider_discovery_evidence.connector_key`: generic source provenance.

# Data intelligence (migration 044)

- `data_intelligence_sources`: registry for pluggable metric providers.
- `locality_population_statistics`: sourced, dated population facts by town.
- `data_intelligence_tasks`: brand-scoped coverage, verification and quality actions that hand off to Data Sources.
