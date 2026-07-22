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

