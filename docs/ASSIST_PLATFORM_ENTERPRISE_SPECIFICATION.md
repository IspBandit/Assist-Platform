# Assist Platform Enterprise architecture specification

**Status:** Authoritative product and architecture specification  
**Product:** Assist Platform Enterprise  
**Brands:** VanAssist, TowSmart, TrailerWise and LocalTorque

## 1. Purpose and authority

Assist Platform Enterprise is the primary commercial product. The four public
brands are domain-resolved products operated by one platform, not independent
websites or duplicated applications.

This document is the single source of truth for product boundaries, platform
architecture and implementation direction. Executable code, ordered migrations
and automated tests remain authoritative for what is currently implemented.
`docs/PRODUCTION_CURRENT_STATE.md` remains authoritative for what is verified
live. Where an older planning document conflicts with this specification, this
specification governs future work and the conflict must be reconciled rather
than silently ignored.

The current UX redesign is protected work. It must be incorporated into the
official design system through tokens, reusable components, documented patterns
and visual acceptance tests. It must not be discarded or replaced by a parallel
redesign.

## 2. Product proposition

Assist Platform Enterprise is a reusable, multi-brand marketplace, directory,
content and administration platform for location-aware industries. Its saleable
value consists of:

- one maintained codebase and release pipeline;
- one global identity and permission model;
- one canonical provider and organisation dataset;
- shared memberships, billing, reviews, search, maps, CMS, media, email,
  analytics, Social Studio and operational controls;
- four distinct brands with proven domain models and datasets;
- brand configuration, feature flags and controlled cross-brand relationships;
- documented data imports, operations, security, quality gates and recovery.

The platform must be transferable as a complete operating package. Brand data,
configuration, campaign history, audit trails and operational documentation are
part of the product, not incidental project files.

## 3. Platform Charter

All work must satisfy the five principles in `docs/PLATFORM_CHARTER.md`:

1. User Experience First.
2. Platform First.
3. Brand Integrity.
4. Commercial Value.
5. Maintainability.

These principles are evaluated by the production quality gate rather than used
as slogans.

## 4. Brand boundaries

### VanAssist

VanAssist helps caravan, camper and motorhome owners find stays, travel support,
RV services and nearby assistance. The parks/stays dataset belongs to VanAssist.
Its domain includes caravan parks, holiday parks, campgrounds, free/low-cost
stays, rest areas, dump points, RV service providers, mobile assistance and
regional travel support.

VanAssist is not a generic accommodation marketplace and must not absorb the
core product purposes of TowSmart, TrailerWise or LocalTorque.

### TowSmart

TowSmart is the towing intelligence product. It helps Australian users understand
whether a tow vehicle and caravan/trailer combination is within known mass
limits and what information still requires manufacturer, weighing, engineering
or legal confirmation.

Its core includes the vehicle/towable catalogue, manual inputs, ATM, GTM, GVM,
GCM, tare, payload, towball mass, loaded-combination calculations, saved
combinations, comparisons, checklists, education, safety and compliance
guidance. It is not a tow-truck directory and must never present calculations as
certification, engineering approval or legal advice.

### TrailerWise

TrailerWise connects users with trailer manufacturers, dealers, repairers,
mobile services, parts suppliers, inspectors, certifiers, engineers and other
trailer specialists. It also provides trailer ownership, maintenance,
registration and compliance information.

It is not a parks/stays product and is not primarily a classified-advertising
site. Any sales/hire listings remain secondary to service and business
discovery unless an approved ADR changes that product decision.

### LocalTorque

LocalTorque is Australia's automotive workshop and specialist directory and the
canonical automotive-provider discovery brand. It covers data-driven categories
such as mechanics, mobile mechanics, auto electricians, diesel, tyres, brakes,
suspension, fabrication, inspections, fleet, 4WD, agricultural, marine and
motorcycle services.

LocalTorque is built as a first-class production-capable brand. Until its domain,
mail, legal, DNS and launch acceptance are confirmed, production launch remains
disabled. Development must not assume that a pending purchase is complete.

## 5. Shared platform model

The existing server-rendered PHP 8.3/MariaDB application remains the deployment
architecture unless evidence and an accepted ADR justify change. Multi-brand
behaviour is resolved through `App\Platform\Brand` and explicit brand context.

Shared capabilities include:

- global users, authentication and secure cross-domain admin handoff;
- platform, brand, organisation and provider permissions;
- canonical providers with brand-specific listings and category relevance;
- memberships, entitlements, billing identity and future payment processing;
- reviews with explicit brand, service and resource context;
- location, search, maps and cross-brand recommendation services;
- CMS, SEO, media, transactional email and campaign management;
- Social Studio, brand assets and approval history;
- first-party analytics, audit, logs, health and release information;
- imports, duplicate detection, provenance and data-quality workflows.

Shared code never implies unrestricted cross-brand visibility. Brand scope,
provider relevance, privacy and permissions are server-enforced.

## 6. Unified administration

There is one Assist Platform administration product and one authentication
identity. Administrators do not maintain separate accounts for each brand.

The global view provides portfolio metrics, brands, domains, users, providers,
memberships, revenue, campaigns, queues, health, releases, backups, feature
flags and audit information. A brand switcher changes the current brand context
without requiring another password login. Cross-domain switching uses the
existing short-lived, single-use handoff flow; broad cross-domain cookies are
not permitted.

The selected brand controls logo, theme, navigation, data scope, content,
Social Studio, email templates and analytics. Platform-wide views require an
explicit global role. Brand administrators must not gain access by modifying a
hostname or parameter.

## 7. Brand configuration and future brand creation

Brand behaviour should be configuration-driven where behaviour is truly shared:

- identity, approved domains and launch state;
- theme tokens, logos, typography and assets;
- navigation, footer, metadata and legal links;
- feature/module enablement;
- category eligibility and ranking policies;
- sender identity, analytics and storage namespace;
- public, private, preview and maintenance states.

The long-term Brand Builder is an administration workflow over validated brand
configuration. It must not imply that every domain model can be created without
engineering. A new directory-oriented brand may be largely configurable; a new
calculation product like TowSmart requires tested domain logic.

## 8. Membership and commercial model

The shared entitlement architecture supports the agreed provider model:

- Launch Access: temporary Verified-level access at no charge;
- Free Listing: permanent, genuinely useful basic coverage;
- Founding Verified: $10/month or $100/year while continuously active;
- Verified Provider: $15/month or $150/year;
- Featured Provider: $29/month or $290/year;
- approved multi-brand bundles and optional promotional placements.

One provider and billing account can participate in multiple relevant brands.
Entitlements—not scattered plan-name checks—control capabilities. Paid placement
must be labelled and must not override geographic/service relevance or safety.
Live charging remains disabled until credentials, GST/tax handling, terms,
webhooks, cancellation, failed-payment handling and acceptance tests pass.

## 9. Experience and design system

`docs/PLATFORM_DESIGN_SYSTEM.md` governs shared UX. Existing UX redesign work is
adopted through an inventory-and-promotion process:

1. identify implemented tokens and patterns;
2. verify responsive and accessible behaviour;
3. promote stable patterns to shared components;
4. document variants and brand overrides;
5. remove old duplicates only after regression testing.

Brand identity remains distinct while interaction patterns remain consistent.
Social Studio uses controlled templates, approved brand assets and editorial
approval; it must not produce generic collages or low-quality mock-up sheets as
production artwork.

## 10. Data and knowledge relationships

Canonical facts are stored once with provenance. Public visibility is expressed
through explicit brand relationships.

Examples:

- a mobile mechanic can be a LocalTorque business and a relevant VanAssist
  recommendation;
- a trailer fabricator can appear in LocalTorque and TrailerWise;
- a TowSmart result may recommend relevant weighing, brake or suspension
  services without changing TowSmart into a directory;
- a VanAssist stay page may show nearby relevant providers without moving the
  stay record into TrailerWise.

Heuristic category or brand assignments remain unverified until reviewed.
Cross-brand review reuse requires actual service relevance.

## 11. Security, privacy and operations

- Server-side brand scope, permissions and ownership are mandatory.
- Private records, documents, VINs, billing data and tokens are never public.
- Applied migrations are immutable; production changes use forward migrations.
- Releases are immutable, checksummed, backed up, health-checked and reversible.
- Production remains on the documented BinaryLane/Docker/Caddy/PHP/MariaDB
  environment unless an accepted ADR changes it.
- Email sending, billing and destructive operations are fail-closed when their
  production dependencies are not configured.

## 12. Delivery governance

All initiatives are organised into the workstreams in
`docs/PRODUCT_BACKLOG.md`: Platform, Experience, Brands, Data, Infrastructure,
Operations and Commercial.

Material architectural decisions use the ADR process in
`docs/ARCHITECTURE_DECISION_RECORDS.md`. Production candidates must pass
`docs/PLATFORM_QUALITY_GATE.md`. Pull requests must identify the backlog item,
brand impact, UX impact, tests, migrations, business outcome and rollback.

## 13. Current priority sequence

1. Reconcile documentation and implementation with this specification.
2. Complete and verify the unified admin and brand-switching experience.
3. Formalise the current UX redesign as the shared design system.
4. Close launch blockers for the three public brands.
5. Make LocalTorque production-capable while keeping public launch disabled
   until domain and operational prerequisites are supplied.
6. Complete membership entitlements, provider launch email templates and safe
   bulk-campaign preparation before enabling billing.
7. Improve data quality, provider claiming and coverage reporting.
8. Pass the full quality gate before any production release.

New features may not bypass these priorities merely because they are visually
appealing or easy to add.
