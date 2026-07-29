<?php

declare(strict_types=1);

$allBrands = ['all'];
$updated = '2026-07-30';

$guides = [
    ['slug' => 'customer-guide', 'title' => 'Customer Guide', 'summary' => 'Accounts, saved providers, assistance requests, Garage assets and compliance tools.', 'audiences' => ['customer'], 'brands' => $allBrands, 'module' => 'customer', 'order' => 10],
    ['slug' => 'provider-guide', 'title' => 'Provider Guide', 'summary' => 'Provider profile, service coverage, documents, requests, runs and growth tools.', 'audiences' => ['provider'], 'brands' => $allBrands, 'module' => 'provider', 'order' => 20],
    ['slug' => 'administrator-guide', 'title' => 'Administrator Guide', 'summary' => 'Permission-scoped operation of the unified Assist Platform administration product.', 'audiences' => ['administrator'], 'brands' => $allBrands, 'module' => 'admin', 'order' => 30],
    ['slug' => 'developer-guide', 'title' => 'Developer Guide', 'summary' => 'Repository orientation, implementation boundaries and validation expectations.', 'audiences' => ['developer'], 'brands' => $allBrands, 'module' => 'engineering', 'order' => 40],
    ['slug' => 'api-guide', 'title' => 'API Guide', 'summary' => 'Current first-party JSON endpoints and the boundary around any future public API.', 'audiences' => ['developer', 'integrator'], 'brands' => $allBrands, 'module' => 'api', 'order' => 50],
    ['slug' => 'release-notes', 'title' => 'Release Notes', 'summary' => 'Operator-facing release status, deployment evidence and known limitations.', 'audiences' => ['administrator', 'developer', 'operator'], 'brands' => $allBrands, 'module' => 'releases', 'order' => 60],
    ['slug' => 'changelog', 'title' => 'Changelog', 'summary' => 'Repository change history linked to the canonical project changelog.', 'audiences' => ['developer', 'operator'], 'brands' => $allBrands, 'module' => 'releases', 'order' => 70],
];

$common = static fn (
    string $guide,
    string $slug,
    string $title,
    string $summary,
    array $audiences,
    array $brands,
    string $module,
    int $order,
    array $permissions,
    array $routes,
    array $related,
    array $sourceFiles,
): array => [
    'id' => $guide . '.' . $slug,
    'guide' => $guide,
    'slug' => $slug,
    'title' => $title,
    'summary' => $summary,
    'audiences' => $audiences,
    'brands' => $brands,
    'module' => $module,
    'order' => $order,
    'status' => 'published',
    'version_introduced' => 'Current repository baseline',
    'last_updated' => $updated,
    'owner' => 'Assist Platform product and engineering',
    'permissions' => $permissions,
    'routes' => $routes,
    'related' => $related,
    'source_files' => $sourceFiles,
    'file' => "docs/user-guide/{$guide}/{$slug}.md",
];

$articles = [
    $common('customer-guide', 'finding-nearby-help', 'Finding nearby help', 'Search VanAssist by service and location, compare mapped results and open directions safely.', ['customer'], ['vanassist'], 'search', 5, [], ['/find'], ['customer-guide.requests-and-saved-providers'], ['routes/web.php', 'app/Controllers/Site/SearchController.php', 'app/Views/public/search-results.php', 'public/assets/js/app.js']),
    $common('customer-guide', 'account-and-garage', 'Account and My Garage', 'Manage private account assets, documents and brand-aware actions.', ['customer'], $allBrands, 'garage', 10, ['authenticated owner'], ['/account', '/account/garage', '/account/compliance'], ['customer-guide.requests-and-saved-providers'], ['routes/account.php', 'app/Controllers/GarageController.php', 'app/Controllers/ComplianceController.php']),
    $common('customer-guide', 'requests-and-saved-providers', 'Requests and saved providers', 'Review owned assistance requests and maintain a saved-provider list.', ['customer'], $allBrands, 'requests', 20, ['authenticated owner'], ['/account/requests', '/account/saved', '/account/providers/save'], ['customer-guide.account-and-garage', 'provider-guide.requests-runs-and-growth'], ['routes/account.php', 'app/Controllers/AccountController.php']),

    $common('provider-guide', 'profile-services-and-evidence', 'Profile, services and evidence', 'Maintain an owned provider profile, service areas, documents and licences.', ['provider'], $allBrands, 'provider-profile', 10, ['provider ownership or administrator role'], ['/provider', '/provider/profile', '/provider/services', '/provider/areas', '/provider/documents', '/provider/licences'], ['administrator-guide.providers-and-directory', 'provider-guide.requests-runs-and-growth'], ['routes/provider.php', 'app/Controllers/Provider/ProfileController.php', 'app/Controllers/ProviderController.php']),
    $common('provider-guide', 'requests-runs-and-growth', 'Requests, runs and growth', 'Handle assigned requests and use enabled analytics, service-run and growth modules.', ['provider'], $allBrands, 'provider-operations', 20, ['provider ownership or administrator role'], ['/provider/requests', '/provider/analytics', '/provider/growth', '/provider/runs'], ['provider-guide.profile-services-and-evidence', 'administrator-guide.customer-operations'], ['routes/provider.php', 'app/Controllers/Provider/RequestController.php', 'app/Controllers/Provider/RunController.php', 'app/Controllers/Provider/GrowthController.php']),

    $common('administrator-guide', 'overview-and-workspaces', 'Overview and brand workspaces', 'Use the dashboard, platform control centre and server-enforced brand switcher.', ['administrator'], $allBrands, 'admin', 10, [], ['/admin', '/admin/control-centre', '/admin/switch-brand'], ['administrator-guide.providers-and-directory', 'administrator-guide.users-settings-and-operations'], ['routes/admin.php', 'app/Controllers/Admin/AdminController.php', 'app/Controllers/Admin/PlatformController.php', 'app/Views/layouts/admin.php']),
    $common('administrator-guide', 'providers-and-directory', 'Providers and directory', 'Manage the current global provider, category and location datasets plus explicitly brand-scoped import mappings.', ['administrator'], $allBrands, 'directory', 20, ['providers.manage', 'providers.approve', 'documents.verify', 'categories.manage', 'locations.manage', 'data_sources.view', 'data_sources.review'], ['/admin/providers', '/admin/categories', '/admin/locations', '/admin/data-sources/review'], ['administrator-guide.overview-and-workspaces', 'administrator-guide.insights-and-data', 'provider-guide.profile-services-and-evidence'], ['routes/admin.php', 'app/Controllers/Admin/ProvidersController.php', 'app/Controllers/Admin/CategoriesController.php', 'app/Controllers/Admin/LocationsController.php', 'app/Controllers/Admin/DataSourcesController.php']),
    $common('administrator-guide', 'customer-operations', 'Customer operations', 'Operate the current global customer, request, matching, run and stay datasets when modules are enabled.', ['administrator'], $allBrands, 'customer-operations', 30, ['customers.manage', 'requests.manage', 'requests.match', 'runs.manage', 'parks.manage'], ['/admin/customers', '/admin/requests', '/admin/matching', '/admin/runs', '/admin/parks'], ['administrator-guide.providers-and-directory', 'customer-guide.requests-and-saved-providers'], ['routes/admin.php', 'app/Controllers/Admin/CustomersController.php', 'app/Controllers/Admin/RequestsController.php', 'app/Controllers/Admin/MatchingController.php', 'app/Controllers/Admin/RunsController.php', 'app/Controllers/Admin/ParksController.php']),
    $common('administrator-guide', 'growth-and-campaigns', 'Growth and campaigns', 'Operate global prospects alongside brand-context Social Studio and brand-recorded email campaigns.', ['administrator'], $allBrands, 'growth', 40, ['prospects.manage', 'content.manage', 'notifications.send'], ['/admin/prospects', '/admin/social-media', '/admin/notifications'], ['administrator-guide.content-and-seo', 'administrator-guide.insights-and-data'], ['routes/admin.php', 'app/Controllers/Admin/ProspectsController.php', 'app/Controllers/Admin/SocialMediaController.php', 'app/Controllers/Admin/NotificationsController.php', 'app/Services/NotificationService.php']),
    $common('administrator-guide', 'insights-and-data', 'Insights and data operations', 'Use brand-scoped demand/Data Intelligence, global trust review, and platform-owned brand-mapped Data Sources.', ['administrator'], $allBrands, 'insights', 50, ['demand.view', 'demand.export', 'data_intelligence.view', 'data_intelligence.manage', 'regulatory.manage', 'campaigns.manage', 'data_sources.view'], ['/admin/demand', '/admin/data-intelligence', '/admin/trust-growth', '/admin/data-sources'], ['administrator-guide.providers-and-directory', 'administrator-guide.growth-and-campaigns'], ['routes/admin.php', 'app/Controllers/Admin/DemandController.php', 'app/Controllers/Admin/DataIntelligenceController.php', 'app/Controllers/Admin/TrustGrowthController.php', 'app/Controllers/Admin/DataSourcesController.php']),
    $common('administrator-guide', 'content-and-seo', 'Content, email templates and SEO', 'Manage the current global CMS and email-template records plus mixed global/brand-keyed SEO settings.', ['administrator'], $allBrands, 'content', 60, ['content.manage', 'email.manage', 'seo.manage'], ['/admin/content', '/admin/email-templates', '/admin/seo'], ['administrator-guide.growth-and-campaigns', 'administrator-guide.overview-and-workspaces'], ['routes/admin.php', 'app/Controllers/Admin/ContentController.php', 'app/Controllers/Admin/EmailTemplatesController.php', 'app/Controllers/Admin/SeoController.php']),
    $common('administrator-guide', 'commercial-and-finance', 'Commercial and finance', 'Review plan configuration, invoice exports and permission-scoped owner finance records.', ['administrator'], $allBrands, 'commercial', 70, ['billing.manage', 'owner_finance.view', 'owner_finance.manage_accounts', 'owner_finance.manage_journals'], ['/admin/billing', '/admin/finance', '/admin/finance/accounts', '/admin/finance/journals'], ['administrator-guide.users-settings-and-operations', 'release-notes.current-release-state'], ['routes/admin.php', 'app/Controllers/Admin/BillingController.php', 'app/Controllers/Admin/Finance/DashboardController.php', 'app/Controllers/Admin/Finance/AccountsController.php', 'app/Controllers/Admin/Finance/JournalsController.php']),
    $common('administrator-guide', 'users-settings-and-operations', 'Users, settings and operations', 'Manage global access, audit records and settings plus super-administrator-only recovery tools.', ['administrator', 'operator'], $allBrands, 'administration', 80, ['users.manage', 'users.export', 'audit.view', 'settings.manage', 'super-administrator'], ['/admin/users', '/admin/audit', '/admin/settings', '/admin/backups', '/admin/maintenance'], ['administrator-guide.overview-and-workspaces', 'administrator-guide.system-reports-flags-and-logs', 'release-notes.current-release-state'], ['routes/admin.php', 'app/Controllers/Admin/UsersController.php', 'app/Controllers/Admin/AuditController.php', 'app/Controllers/Admin/SettingsController.php', 'app/Controllers/Admin/BackupsController.php', 'app/Controllers/Admin/MaintenanceController.php']),
    $common('administrator-guide', 'system-reports-flags-and-logs', 'Reports, feature flags and system logs', 'Use permission-gated global reports and feature flags, plus super-administrator-only system logs.', ['administrator', 'operator'], $allBrands, 'system-operations', 90, ['reports.view', 'feature_flags.manage', 'super-administrator'], ['/admin/reports', '/admin/feature-flags', '/admin/logs'], ['administrator-guide.users-settings-and-operations', 'release-notes.current-release-state'], ['routes/admin.php', 'app/Controllers/Admin/ReportsController.php', 'app/Controllers/Admin/FeatureFlagsController.php', 'app/Controllers/Admin/LogsController.php']),

    $common('developer-guide', 'repository-workflow', 'Repository workflow', 'Follow the product boundary, backlog ownership, forward-only schema and validation baseline.', ['developer'], $allBrands, 'engineering', 10, [], [], ['api-guide.current-api-boundary', 'release-notes.current-release-state', 'changelog.project-changelog'], ['AGENTS.md', 'docs/START_HERE.md', 'docs/DEVELOPER_GUIDE.md', 'docs/PRODUCT_BACKLOG.md', 'docs/PLATFORM_QUALITY_GATE.md', 'composer.json']),
    $common('api-guide', 'current-api-boundary', 'Current API boundary', 'Treat current JSON handlers as first-party web endpoints, not a supported public API.', ['developer', 'integrator'], $allBrands, 'api', 10, [], ['/locations/towns', '/locations/nearest', '/locations/nearby-providers', '/calculator/catalogue/{type}'], ['developer-guide.repository-workflow'], ['routes/web.php', 'docs/API.md', 'app/Controllers/Site/LocationController.php', 'app/Controllers/Site/TowSmartController.php']),
    $common('release-notes', 'current-release-state', 'Current release state', 'Read the unreleased status and follow canonical evidence requirements before claiming production delivery.', ['administrator', 'developer', 'operator'], $allBrands, 'releases', 10, [], [], ['changelog.project-changelog', 'developer-guide.repository-workflow'], ['docs/RELEASE_NOTES.md', 'docs/PRODUCTION_CURRENT_STATE.md', 'docs/OPERATIONS_RUNBOOK.md']),
    $common('changelog', 'project-changelog', 'Project changelog', 'Use the root changelog for detailed repository history and release notes for deployment status.', ['developer', 'operator'], $allBrands, 'releases', 10, [], [], ['release-notes.current-release-state', 'developer-guide.repository-workflow'], ['CHANGELOG.md', 'docs/RELEASE_NOTES.md']),
];

return ['guides' => $guides, 'articles' => $articles];
