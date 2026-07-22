# VanAssist to Assist Platform Migration Plan

> Historical phased plan. Several brand-foundation and deployment steps are now
> implemented. Use `PRODUCTION_CURRENT_STATE.md` for verified completion and this
> document for migration rationale and rollback principles.

## Safety position

The migration is additive and release-based. Existing VanAssist primary keys,
provider slugs, public paths, users, sessions, and business workflows remain the
compatibility baseline. No destructive production migration is run
automatically.

Two issues must be addressed before multi-brand DDL:

1. `database/schema.sql` is stale after migration 012.
2. The migration runner has no advisory lock, checksum, or failed/dirty state.

## Release gates

Every phase requires:

- a database backup and tested restore procedure;
- a production-data preflight report;
- forward and rollback instructions;
- a deploy where old and new application versions can coexist when possible;
- migration validation on an empty database and a VanAssist-shaped fixture;
- unit/integration tests and VanAssist URL smoke tests;
- explicit approval before irreversible cleanup.

## Phase 0: baseline and risk controls

### Deliverables

- Record current architecture, route inventory, schema inventory, and known
  security/operational risks.
- Stop documenting `database/schema.sql` as equivalent to migrations.
- Add CI for syntax, Composer validation/audit, unit tests, secret scanning, and
  migration checks.
- Create a database-backed integration-test harness.
- Fix critical installer, SMTP, outcome/review authorization, and fail-open
  middleware behavior before public multi-brand deployment.

### Rollback

Documentation and CI can be reverted without database impact. Security changes
must preserve compatibility tests for existing flows.

## Phase 1: harden migration execution

Enhance `Migrator` before adding platform migrations:

- acquire a MySQL advisory lock;
- store filename, checksum, status, started/completed timestamps, and failure
  details;
- refuse checksum drift on successfully applied migrations;
- expose failed/dirty state and repair guidance;
- prevent concurrent installer, CLI, and admin migration runs;
- require restartable migration scripts or explicit repair instructions;
- add server capability checks.

Decision implemented: retire `database/schema.sql`. Fresh installs and upgrades
both use the same ordered migration source and compatible migration history.

### Rollback

The runner schema additions are additive. The previous runner may continue to
read successful rows until all environments use the hardened runner.

## Phase 2: brand foundation

Add:

- `brands`;
- `brand_domains`;
- a fixed VanAssist row (ID 1);
- typed PHP brand registry and request/CLI `BrandContext`;
- exact hostname and explicit environment resolution;
- VanAssist configuration matching current presentation and behavior.

TowSmart and TrailerWise rows/configurations are created as disabled or
coming-soon brands. Existing routing still defaults to VanAssist during this
phase.

### Validation

- Existing hosts resolve VanAssist.
- Unknown production hosts fail safely.
- Existing route output, metadata, and links remain unchanged.
- CLI and cron require or obtain an explicit deterministic brand.

### Rollback

Disable brand resolution and continue using the VanAssist default. Additive
brand tables remain unused.

## Phase 3: canonical providers and brand listings

Add:

- `provider_brand_listings`;
- `provider_url_aliases`;
- brand/listing category relationships where brand-specific taxonomy is needed.

Backfill one active VanAssist listing for every existing provider, preserving
`providers.id` and `providers.slug`. Existing `/providers/{slug}` behavior
continues through a compatibility adapter.

### Deployment sequence

1. Deploy schema and backfill code.
2. Verify row counts, slug equality, and duplicate absence.
3. Deploy dual-read logic with fallback to legacy provider columns.
4. Deploy dual-write logic for listing fields.
5. Switch VanAssist reads to listing repositories.
6. Measure legacy fallback use before considering cleanup.

### Rollback

Switch reads to legacy provider columns. Do not delete listing rows.

## Phase 4: provider memberships and organisations

Add `provider_memberships` and backfill each non-null `providers.user_id` as
owner. Introduce provider-scoped roles and ownership middleware while retaining
the legacy pointer.

Add organisation/branch concepts only where actual workflows require them.
Do not infer multiple organisations from existing provider records.

### Preflight checks

- duplicate `providers.user_id` values;
- users assigned to conflicting providers;
- providers without owners;
- orphan role and invitation records.

### Rollback

Continue legacy owner lookup. Membership records remain additive.

## Phase 5: brand attribution

Add nullable indexed `brand_id` to brand-originated records, including:

- service requests, matches/outcomes, reviews, and saved-provider context;
- searches, analytics events, contact actions, and aggregate metrics;
- notifications, email queue/log records, and templates;
- content pages, blocks, FAQs, settings, and feature flags;
- listings, advertising, and brand-priced billing records.

Backfill existing rows to VanAssist in bounded batches. Application writes brand
immediately after the nullable columns exist. Only after integrity checks pass
may selected columns become `NOT NULL`.

### Important rule

Historical events store brand at creation. They do not derive brand later from a
provider that may join or leave brands.

### Rollback

Old code ignores nullable brand columns. New code can temporarily default null
legacy rows to VanAssist. Do not remove columns during emergency rollback.

## Phase 6: scoped authorisation

Introduce:

- `user_brand_profiles`;
- `user_brand_roles`;
- scoped permission and ownership utilities;
- brand predicates in repositories;
- brand-aware admin selection;
- tests proving cross-brand and cross-provider denial.

Existing global admin roles remain during transition. Brand administrator roles
are additive and never imply platform-wide access.

## Phase 7: presentation, content, and SEO

- Move VanAssist presentation values into semantic tokens and brand config.
- Extract reusable partials without changing established VanAssist rendering.
- Scope content, email identity, metadata, sitemaps, robots, storage paths, and
  analytics to brand.
- Add TowSmart and TrailerWise coming-soon layouts and metadata.
- Preserve all existing VanAssist paths; add URL aliases and redirects before
  changing any slug.

## Phase 8: shared domain contracts

Incrementally adapt provider search, reviews, notifications, billing,
service-history, reminders, media, analytics, and audit services to explicit
brand-aware contracts. Use adapters around working VanAssist logic; do not
replace mature behavior solely for structural consistency.

## Phase 9: independent deployments

Produce one immutable release artefact and deploy it with brand-specific
configuration. Use secure transfer, a manifest that detects changed and removed
files, migration preflight, health checks, and rollback to the previous
artefact. Production migrations require manual approval.

## Production data preflight

Before constraints or backfills, report:

- total rows and maximum IDs for all affected tables;
- duplicate provider slugs and provider-user links;
- orphan foreign references;
- duplicate billing customers/subscriptions and service areas;
- invalid ratings, date ranges, negative monetary values, and status values;
- mismatched customer/request/provider outcome and review relationships;
- existing content/settings key collisions under brand scope;
- legacy billing-plan values and incomplete subscriptions;
- token duplicates and expired/unconsumed token volume.

The report must be retained with the release record.

## Post-migration integrity checks

- Every legacy provider has exactly one VanAssist listing.
- Every listing slug equals the legacy VanAssist slug or has a working alias.
- Every backfilled operational row has `brand_id = VanAssist`.
- Counts and key financial totals match preflight values.
- Existing account, provider, request, review, billing, and media foreign
  references remain valid.
- Existing public URLs return the same resource or an intentional redirect.
- Brand-scoped queries cannot return another brand's private records.

## Destructive cleanup policy

Legacy columns, global indexes, and compatibility adapters are removed only in a
later release after:

- all deployed code has stopped reading/writing them;
- telemetry confirms zero fallback use for an agreed observation period;
- backups and rollback scripts are verified;
- the removal is explicitly labeled destructive and manually approved.

No destructive cleanup is part of the initial Assist Platform foundation.
