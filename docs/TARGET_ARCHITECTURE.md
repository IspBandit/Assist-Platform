# Assist Platform Core: Target Architecture

## Decision

Assist Platform will remain one PHP application and one shared codebase. Brand
resolution will happen at the platform boundary, and shared services will accept
an explicit brand context. This fits the current cPanel deployment model and
avoids three copied applications or an unnecessary JavaScript monorepo.

The architecture may later split deployable services when measured operational
needs justify it. That is not required to establish safe multi-brand behavior.

## Principles

1. VanAssist remains the compatibility baseline.
2. Global identity is separate from brand participation.
3. Canonical providers are separate from brand listings.
4. Brand is explicit on brand-originated facts and immutable historical events.
5. Presentation reads from a central brand registry and semantic design tokens.
6. Authorisation is enforced by server-side scope, never by presentation alone.
7. Existing IDs, slugs, routes, and data are preserved through additive changes.
8. Shared abstractions are used only when behavior is genuinely shared.

## Logical layers

```text
HTTP / CLI / Cron
        |
BrandResolver -> BrandContext
        |
Kernel / Router / Middleware
        |
Controllers and Commands
        |
Shared Domain Services
  Identity | Providers | Search | Reviews | Content
  Notifications | Billing | Analytics | Media | Audit
        |
Brand Policies and Brand Modules
  VanAssist | TowWise | TrailerWise
        |
PDO Repositories / Database
```

The current directory layout remains valid. New platform boundaries should be
introduced incrementally:

```text
app/
  Platform/
    Brand/
    Feature/
    Identity/
    Support/
  Brands/
    VanAssist/
    TowWise/
    TrailerWise/
  Services/
  Views/
config/
  brands.php
database/
  migrations/
docs/
tests/
  Unit/
  Integration/
```

`app/Services` is not moved wholesale. Existing services are adapted as their
call paths become brand-aware. New shared contracts live under `app/Platform`;
brand-specific policies and modules live under `app/Brands`.

## Brand registry and context

The typed PHP registry exposes immutable `Brand` value objects. Each brand
configuration includes:

- key, display name, legal name, short name, and status;
- primary and local hostnames;
- logo, icon, favicon, and semantic design tokens;
- typography and default metadata;
- social metadata and structured-data defaults;
- contact, support, sender, and legal-link identities;
- navigation and footer definitions;
- enabled modules, features, provider categories, and listing categories;
- analytics identifiers, search settings, storage namespace, and deployment
  metadata.

`BrandResolver` resolves in this order:

1. an explicit trusted process value for CLI/cron/deployment;
2. an exact normalized hostname from the registry/database;
3. a development-only route or query fallback when enabled;
4. the configured default brand, initially VanAssist.

Untrusted forwarded hosts are not used without a trusted-proxy policy. Unknown
production hosts fail closed rather than silently selecting another brand.

`BrandContext` is created once per request or command and passed through
controllers, services, templates, email rendering, analytics, logging, sitemap
generation, and storage. Domain services must not repeatedly inspect hostnames.

## Deployment model

The same versioned release artefact can be deployed independently for each
brand. Each deployment sets an explicit brand key and canonical host while still
using domain resolution for validation.

- VanAssist: enabled, all existing routes and workflows.
- TowWise: deployable scaffold and coming-soon public module; shared identity
  support prepared, towing calculations not yet implemented.
- TrailerWise: deployable scaffold and coming-soon public module; shared
  provider/listing support prepared, marketplace expansion not yet implemented.
- Admin: remains within the same application initially. Platform administrators
  can select permitted brand scope; brand administrators are restricted.

One deployment may serve multiple domains, or each brand may use an independent
deployment pointing at the shared database and private storage service. Separate
deployments reduce blast radius; the code and schema remain shared.

## Identity and participation

`users` remains the global identity. New relationships express participation:

- `user_brand_profiles` for onboarding, preferences, terms, and status;
- `user_brand_roles` for brand-scoped administrative roles;
- `provider_memberships` for provider owner/manager/staff access;
- future `organisation_memberships` for organisation-level access.

Cross-domain shared browser sessions are not implemented with broad cookies.
Independent brand hosts use normal host-only secure sessions. Future seamless
cross-domain sign-in requires a dedicated central identity flow using short-lived,
single-use authorization codes and strict redirect allowlists.

## Provider marketplace

`providers` remains the canonical business identity and retains all current IDs.
`provider_brand_listings` controls participation and presentation per brand:

- brand-specific slug and URL aliases;
- display/SEO content;
- visibility, verification, and featured status;
- category and subscription relationships;
- brand-level contact and search policy.

Existing VanAssist `/providers/{slug}` routes resolve the VanAssist listing and
continue to preserve the current provider slug. Providers may participate in
multiple brands without duplicating the canonical business record.

## Data ownership and isolation

Every brand-originated operational record receives `brand_id` when created.
Existing records are backfilled to the fixed VanAssist brand. Queries in
brand-aware services require a brand scope unless explicitly platform-wide.

Brand access is enforced through:

- route/middleware scope;
- repository predicates;
- ownership/membership checks;
- brand-scoped uniqueness constraints;
- integration tests for cross-brand access denial;
- audit events containing brand context.

Tables containing truly global identity or taxonomy data remain global. Brand
relationships are used instead of adding meaningless brand columns everywhere.

## Roles and permissions

- Existing `user_roles` continues to represent platform-global roles during
  migration.
- `user_brand_roles` represents brand administrator, editor, support, and
  moderator assignments.
- `provider_memberships` represents owner, manager, and staff permissions for a
  canonical provider.
- Permission checks receive an explicit scope object containing platform,
  brand, organisation/provider, and resource ownership information.
- Super-administrator remains a recoverable platform role and every bypass is
  audit-logged.

## Shared services

Brand-aware contracts are introduced around:

- provider directory/listings;
- reviews and reputation;
- search and ranking;
- content and SEO;
- notifications and email templates;
- analytics and audit;
- billing products, subscriptions, and entitlements;
- media/storage namespaces;
- service history and reminders.

Current implementations remain behind these contracts until replacement is
tested. TowWise and TrailerWise modules may contribute brand-specific policies,
routes, schemas, and templates without adding conditionals throughout shared
services.

## Search

A `SearchServiceInterface` accepts a brand-aware search query and returns a
vendor-neutral result. The initial adapter uses MySQL and current provider
coverage logic. Ranking policy is configured per brand. Search analytics record
brand, filters, result count, and anonymized session attribution.

The interface allows a later external index without coupling controllers or
templates to a vendor.

## Reviews

Reviews carry immutable brand attribution and target a provider listing or other
reviewable resource. Moderation, response, report, edit-history, duplicate, and
verified-use policies are explicit. Platform-wide reputation is computed only
when the requesting brand policy permits cross-brand aggregation.

## Content and SEO

Content, metadata, navigation, sitemaps, robots rules, canonical URLs, and
structured data are brand-scoped. Existing VanAssist paths remain unchanged.
Tokenized, authenticated, and transactional pages are explicitly noindex.

Each brand independently emits:

- canonical metadata and social previews;
- robots policy;
- sitemap or sitemap index;
- organisation and applicable page structured data.

## Design system

The design system remains server-rendered and CSS-based:

- base tokens: spacing, typography, radii, breakpoints, focus, and status;
- semantic tokens: surfaces, text, borders, actions, success, warning, danger;
- brand token maps: VanAssist, TowWise, TrailerWise;
- reusable PHP partials for forms, errors, cards, breadcrumbs, notices,
  navigation, tables, pagination, empty/loading states, and provider results.

VanAssist values are migrated into tokens without a redesign. Components must
support WCAG 2.2 AA, keyboard access, visible focus, reduced motion, and mobile
layouts.

## Configuration and observability

Environment parsing is centralized and validated at startup. Brand registry
configuration is versioned; secrets remain environment-only. Logs include a
request ID, brand key, actor/resource context where appropriate, and deployment
version. Health and readiness distinguish application, database, storage, and
mail dependencies without exposing secrets.

## Evolution boundaries

The following are intentionally deferred until the shared foundations are
stable:

- TowWise towing calculators and compliance reports;
- TrailerWise listing and manufacturer workflows;
- external search infrastructure;
- OAuth or a central cross-domain identity service;
- distributed queues, Redis, or microservices;
- production Stripe charging;
- a full frontend-framework rewrite.

These deferrals prevent speculative architecture from destabilizing VanAssist.
