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
| Email/notifications | templates, `email_queue`, `email_log`, notifications, staged/test deliveries, recipients and `notification_provider_exclusions` | Queue rows have required `brand_id`; marketing requires suppression, documented consent, campaign recipient review, internal test, pilot and reviewed rolling limits |
| Analytics | sessions, events, searches/results/contact actions, daily aggregates and follow-ups | Privacy/retention controls apply; reports must respect brand scope |
| Billing | plans/prices/features/limits, subscriptions, entitlements, invoices, payments, refunds, discounts, webhooks, commissions and fees | Dormant until explicitly enabled; financial integrity required |
| Owner finance | accounts, tax codes, periods, journal entries/lines, source/audit events | Platform owner ledger, never provider bookkeeping |
| TowSmart | `tow_vehicles`, `towable_assets`, `towing_combinations` | User-owned and TowSmart brand-scoped |
| TrailerWise | `trailer_listings` | Provider-owned, TrailerWise-scoped, currently secondary product capability |
| Vehicle regulation | `regulatory_authorities`, `regulatory_documents`, `regulatory_source_checks`, `regulatory_document_brands` | Shared official-source register with brand-relevance mapping for all four platforms; issuing authorities retain document ownership |
| Motorsport | `motorsport_authorities`, `motorsport_families`, `motorsport_disciplines`, `motorsport_documents`, document-family links, `motorsport_venues`, venue-family links, `motorsport_source_checks` | LocalTorque competition-rule and venue catalogue with fail-closed source fingerprints; sanctioning bodies and venue/calendar publishers retain source ownership |
| Owner Garage | `garage_assets`, `garage_documents`, `garage_reminder_preferences`, `garage_brand_activity` | User-scoped vehicles/towables and private compliance wallet shared across brands; origin brand is context, never an access boundary |
| Compliance journeys | `regulatory_journeys`, `regulatory_alert_subscriptions`, `regulatory_alert_deliveries`, `regulatory_provider_handoffs` | Owner-scoped saved pathways, explicit alert/handoff consent and auditable limited context |
| Provider trust | `provider_capability_credentials` | Evidence-backed, expiring capability claims; only reviewed current rows may display publicly |
| Campaign performance | `advertising_campaign_daily_metrics` plus `advertising_campaigns` budget/pricing columns | Campaign-only paid impressions, clicks, attributed contacts and media-value spend; never organic rank |
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

# Vehicle regulatory library (migration 050)

- `regulatory_authorities`: issuing government or national-regulator identity and jurisdiction.
- `regulatory_documents`: official source URL/download, vehicle applicability, version, effective dates, publication state and current fingerprint.
- `regulatory_document_brands`: explicit per-brand relevance mapping for the four public rule-library views.
- `regulatory_source_checks`: append-only HTTP/checksum observations. A changed source moves its document to review before further public display.

# Membership catalogue (migration 045)

- Adds the shared Launch Access, Free Listing, Founding Verified, Verified Provider and Featured Provider catalogue.
- Preserves existing provider assignments and legacy rows for referential integrity; legacy plans cannot accept new signups.
- Seeds plan entitlements, limits and descriptive AUD pricing without enabling billing, checkout or a gateway.
