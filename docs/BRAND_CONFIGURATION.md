# Brand Configuration

## Objective

Brand configuration provides one authoritative source for VanAssist, TowSmart,
and TrailerWise identity, presentation, enabled modules, and deployment
behavior. Controllers and templates consume a `BrandContext`; they do not
scatter string comparisons throughout the application.

## Resolution rules

Brand resolution must be deterministic and server-side:

1. `ASSIST_BRAND` for trusted CLI, cron, and single-brand deployments.
2. Exact normalized hostname match from configured brand domains.
3. Development-only route/query fallback when explicitly enabled.
4. `ASSIST_DEFAULT_BRAND`, initially `vanassist`.

Unknown production hosts must be rejected. Forwarded host headers are accepted
only from configured trusted proxies.

## Configuration shape

Each brand defines:

```php
[
    'id' => 'vanassist',
    'name' => 'VanAssist',
    'legal_name' => '...',
    'short_name' => 'VanAssist',
    'status' => 'active',
    'domains' => [
        'primary' => 'vanassist.example.com',
        'local' => ['vanassist.test'],
    ],
    'assets' => [
        'logo' => '/assets/brands/vanassist/logo.svg',
        'icon' => '/assets/brands/vanassist/icon.svg',
        'favicon' => '/assets/brands/vanassist/favicon.svg',
    ],
    'theme' => [
        'brand' => '#...',
        'brand_emphasis' => '#...',
        'accent' => '#...',
        'focus' => '#...',
    ],
    'metadata' => [
        'title_suffix' => '...',
        'description' => '...',
        'social_image' => '...',
    ],
    'contact' => [
        'support_email' => '...',
        'sender_email' => '...',
        'sender_name' => '...',
    ],
    'legal' => [
        'privacy_path' => '/privacy',
        'terms_path' => '/terms',
    ],
    'navigation' => [],
    'footer' => [],
    'features' => [],
    'modules' => [],
    'provider_categories' => [],
    'listing_categories' => [],
    'analytics' => [],
    'search' => [],
    'storage_namespace' => 'vanassist',
    'structured_data' => [],
]
```

The concrete implementation uses immutable PHP value objects and validates all
required keys at startup. Arrays shown above are configuration input, not the
domain API exposed to application code.

## Initial brand states

### VanAssist

- Status: active.
- Existing public routes and portal/admin functionality remain enabled.
- Existing visual values and URLs are preserved.
- Existing data is backfilled to the fixed VanAssist database brand.
- Provider, request, park, run, CMS, analytics, and current billing modules
  remain available according to existing feature flags.

### TowSmart

- Status: coming soon until explicitly enabled.
- Real brand/domain/theme/metadata/navigation/footer configuration.
- Shared identity and admin brand entry prepared.
- Public homepage and future tool-route placeholders may be deployed.
- Towing calculations, compliance guidance, reports, and saved combinations are
  not represented as complete production functionality.

### TrailerWise

- Status: coming soon until explicitly enabled.
- Real brand/domain/theme/metadata/navigation/footer configuration.
- Shared identity and admin brand entry prepared.
- Public homepage and future marketplace-route placeholders may be deployed.
- Trailer listings, dealer/manufacturer workflows, parts, and compliance
  services are not represented as complete production functionality.

## Feature flags

Feature evaluation receives platform, brand, environment, actor, and optional
resource context:

```text
platform default
    -> environment override
        -> brand override
            -> explicitly supported actor/role rollout
```

Flags do not grant permissions. A feature may be enabled while access remains
denied by role, membership, ownership, or subscription policy.

Initial typed keys include:

- `towsmart.enabled`
- `trailerwise.enabled`
- `identity.shared`
- `providers.messaging`
- `reviews.enabled`
- `billing.enabled`
- `advertising.enabled`
- `service_history.enabled`
- `reminders.enabled`
- `search.new`
- `admin.new`

Unknown feature keys fail validation rather than silently evaluating true.

## Presentation rules

- Templates receive a brand view model, not raw environment values.
- CSS uses semantic custom properties populated by the active brand.
- Shared components must not embed brand hex values, names, contacts, or hosts.
- Brand assets live under stable namespaced paths.
- Email templates receive the same brand identity and legal/footer data.
- User-generated and private media use a brand storage namespace.

## Content and SEO

Brand context controls:

- title suffix and default description;
- canonical host;
- Open Graph/Twitter identity;
- organisation and website structured data;
- robots and sitemap output;
- navigation/footer/legal links;
- CMS page lookup and slug uniqueness.

VanAssist canonical paths remain unchanged. TowSmart and TrailerWise content
cannot fall back to VanAssist legal or marketing copy in production.

## Background work

Cron and queue records carry brand attribution. Workers use the record's brand,
not the worker host, when rendering email, selecting storage, writing analytics,
or applying configuration. Platform-wide tasks iterate explicit enabled brands
and include the brand key in locks and logs.

## Authentication

Brand context is recorded for onboarding, consent, notification preferences,
and audit. Global credentials remain in `users`.

Host-only secure cookies remain the safe default. Configuration must not set a
cookie domain spanning unrelated public domains. Future cross-domain SSO must
use a central authorization flow; a shared raw session cookie is prohibited.

## Adding a future brand

1. Add and validate the registry entry and database brand/domain rows.
2. Add namespaced assets and semantic theme values.
3. Define modules, features, categories, search/ranking, and storage namespace.
4. Add legal/contact/sender identity and content.
5. Add domain/deployment configuration.
6. Add brand resolution, metadata, sitemap, robots, email, and isolation tests.
7. Deploy in disabled/private mode.
8. Complete security, accessibility, content, and operational approval.
9. Enable deliberately.

No shared service should require modification merely to recognize a new brand;
brand-specific behavior belongs in a registered policy/module.
