# Unified Assist Platform

> This is the concise implemented-model companion to
> `ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md`, which governs product direction.

Assist Platform is the saleable product. VanAssist, TowSmart, TrailerWise and
LocalTorque are domain-resolved brands running on its shared application,
database, operational services and administration portal.

Current and future work is organised through `PRODUCT_BACKLOG.md`, material
decisions use the ADR process, and production candidates must pass the Platform
Quality Gate. The current UX redesign is the foundation of the official Platform
Design System.

## Product boundaries

- **VanAssist:** parks, stays, travel support, caravan/RV services and nearby help.
- **TowSmart:** towing knowledge, matching, weights, safety, legality,
  compliance, calculators and guides. It is not a tow-truck directory.
- **TrailerWise:** trailers, manufacturers, dealers, parts, repairs, compliance
  and ownership information. Parks and stays do not belong here.
- **LocalTorque:** the nationwide automotive workshop and specialist directory.

Cross-brand recommendations use shared provider records; they do not transfer
the owning brand's product purpose.

## Administration model

Administrators sign in once. The top-bar brand switcher lists every brand the
user can access and issues a two-minute, single-use, hashed handoff token when
crossing domains. The receiving brand consumes the token, rotates the session
identifier and restores the requested `/admin` path. Passwords, session cookies
and bearer credentials never travel between domains.

`All Brands` opens the platform control centre for super, legacy administrator
and platform-administrator roles. It provides portfolio metrics, brand launch
state, operational queues, migrations and direct brand entry.

Supported roles are Super Admin, Platform Admin, Brand Admin, Moderator,
Editor, Support, Finance and Marketing. Global roles live in `user_roles`;
brand assignments live in `user_brand_roles`. Effective roles and permissions
combine global grants with grants for the hostname-resolved current brand.

## Shared commercial data

A provider exists once in `providers`. `provider_brand_listings` controls its
visibility, copy, status and presentation per brand. Memberships, entitlements,
billing identity, reviews, media, analytics, notifications, email campaigns,
CMS, search and APIs remain shared services with explicit `brand_id` scoping
where presentation or access differs.

## Control-centre ownership

Platform-wide configuration covers brand identity, approved domains, themes,
feature flags, operational health, scheduled tasks, migrations, email queues,
backups and cross-brand reporting. Brand administrators cannot enter unassigned
brands and cannot gain access by changing a query parameter or hostname.

## Migration 042

Migration `042_unified_platform_admin.sql` adds the requested roles and
permissions, professional Social Studio campaign metadata, and secure admin
handoff tokens. It is additive. Rollback normally means restoring the previous
application release while leaving these compatible tables/columns in place.
Do not reverse production schema by hand.
