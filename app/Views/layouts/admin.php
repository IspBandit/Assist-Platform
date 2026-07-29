<?php
/** @var \App\Core\View $this */
$user = current_user();
$adminBrand = current_brand();
$adminBrandMeta = $adminBrand->metadata();
$adminBrandTheme = $adminBrand->theme();
$adminBrandAssets = $adminBrand->assets();
$adminBrands = $user !== null ? \App\Services\AdminBrandAccess::availableBrands((int) $user['id']) : [];
$permitted = static fn (string $permission): bool => auth()->can($permission);
$platformAdmin = auth()->isSuperAdmin() || auth()->hasAnyRole('administrator', 'platform-administrator');
$nav = [
    'Overview' => [
        ['Dashboard', '/admin'],
        ...($platformAdmin ? [['Launch readiness', '/admin/control-centre']] : []),
    ],
];
$directory = [];
if ($permitted('providers.manage')) {
    $directory[] = ['Providers', '/admin/providers'];
}
if ($permitted('categories.manage')) {
    $directory[] = ['Service categories', '/admin/categories'];
}
if ($permitted('locations.manage')) {
    $directory[] = ['Locations', '/admin/locations'];
}
if ($platformAdmin && $permitted('data_sources.review')) {
    $directory[] = ['Import review', '/admin/data-sources/review'];
}
if ($platformAdmin && $permitted('data_sources.view')) {
    $directory[] = ['Queensland coverage', '/admin/qld-coverage'];
}
if ($adminBrand->moduleEnabled('trailer_marketplace') && $permitted('providers.manage')) {
    $directory[] = ['Trailer listings', '/admin/trailer-listings'];
}
if ($directory !== []) {
    $nav['Directory'] = $directory;
}

$customerOperations = [];
if ($adminBrand->moduleEnabled('requests') && $permitted('customers.manage')) {
    $customerOperations[] = ['Customers', '/admin/customers'];
}
if ($adminBrand->moduleEnabled('requests') && $permitted('requests.manage')) {
    $customerOperations[] = ['Service requests', '/admin/requests'];
}
if ($adminBrand->moduleEnabled('requests') && $permitted('requests.match')) {
    $customerOperations[] = ['Matching', '/admin/matching'];
}
if ($adminBrand->moduleEnabled('service_runs') && $permitted('runs.manage')) {
    $customerOperations[] = ['Service runs', '/admin/runs'];
}
if ($adminBrand->moduleEnabled('parks') && $permitted('parks.manage')) {
    $customerOperations[] = ['Places to stay', '/admin/parks'];
    if ($platformAdmin && $adminBrand->id() === 'vanassist') {
        $customerOperations[] = ['Stay discovery review', '/admin/parks/import'];
    }
}
if ($customerOperations !== []) {
    $nav['Customer operations'] = $customerOperations;
}

$growth = [];
if ($permitted('prospects.manage')) {
    $growth[] = ['Provider outreach', '/admin/prospects'];
}
if ($permitted('content.manage')) {
    $growth[] = ['Social studio', '/admin/social-media'];
}
if ($permitted('notifications.send')) {
    $growth[] = ['PR & outreach hub', '/admin/outreach-hub'];
    $growth[] = ['Provider email campaigns', '/admin/notifications'];
}
if ($growth !== []) {
    $nav['Growth'] = $growth;
}

$insights = [];
if ($permitted('regulatory.manage') || $permitted('campaigns.manage')) {
    $insights[] = ['Trust, rules & growth', '/admin/trust-growth'];
}
if ($permitted('data_intelligence.view')) {
    $insights[] = ['Data Intelligence', '/admin/data-intelligence'];
}
if ($permitted('demand.view')) {
    $insights[] = ['Website insights', '/admin/demand'];
}
if ($insights !== []) {
    $nav['Insights'] = $insights;
}

$content = [];
if ($permitted('content.manage')) {
    $content[] = ['Pages & blocks', '/admin/content'];
}
if ($permitted('email.manage')) {
    $content[] = ['Email templates', '/admin/email-templates'];
}
if ($permitted('seo.manage')) {
    $content[] = ['SEO', '/admin/seo'];
}
if ($content !== []) {
    $nav['Content'] = $content;
}
if ($permitted('billing.manage')) {
    $nav['Commercial'] = [['Plans & invoice exports', '/admin/billing']];
}

$administration = [];
$administration[] = ['Documentation', '/admin/help'];
if ($permitted('users.manage')) {
    $administration[] = ['Users & access', '/admin/users'];
}
if ($permitted('audit.view')) {
    $administration[] = ['Audit log', '/admin/audit'];
}
if ($permitted('settings.manage')) {
    $administration[] = ['Settings', '/admin/settings'];
}
if (auth()->isSuperAdmin()) {
    $administration[] = ['Backups', '/admin/backups'];
    $administration[] = ['Maintenance', '/admin/maintenance'];
}
if ($administration !== []) {
    $nav['Administration'] = $administration;
}
$current = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/admin';
$documentationTarget = \App\Services\Documentation\DocumentationLinkResolver::forRoute($current, 'administrator');
?>
<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Admin') ?> — <?= $this->e($adminBrand->name()) ?> Admin</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php $this->include('partials.brand-theme'); ?>
    <meta name="theme-color" content="<?= e($adminBrandTheme['brand'] ?? '#0f6e6e') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset($adminBrandAssets['favicon'] ?? '/assets/brands/vanassist/mark.svg')) ?>">
</head>
<body<?= $current === '/admin' ? ' data-auto-refresh-seconds="10"' : '' ?>>
<div class="admin-body">
    <aside class="admin-sidebar">
        <a class="brand brand-admin" href="<?= e(url('admin')) ?>" aria-label="Assist Platform admin home">
            <img class="brand-mark" src="<?= e(asset($adminBrandAssets['logo'] ?? '/assets/brands/vanassist/mark.svg')) ?>" alt="" width="40" height="40">
            <span class="brand-copy"><span class="brand-name">Assist Platform</span><span class="admin-brand-context"><?= $this->e($adminBrand->name()) ?> workspace</span></span>
        </a>
        <button type="button" class="admin-nav-toggle" aria-controls="admin-nav" aria-expanded="false"><svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Menu</button>
        <p class="admin-sidebar-label">Enterprise administration</p>
        <nav id="admin-nav" aria-label="Admin">
            <?php foreach ($nav as $group => $links): ?>
                <p class="admin-nav-group"><?= $this->e($group) ?></p>
                <?php foreach ($links as [$label, $href]): ?>
                    <?php $active = rtrim($href, '/') === $current ? ' active' : ''; ?>
                    <a class="<?= trim($active) ?>" href="<?= e(url(ltrim($href, '/'))) ?>"<?= $active !== '' ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <div class="admin-page-heading"><span><?= $this->e($adminBrand->name()) ?></span><strong><?= $this->e($title ?? 'Admin') ?></strong></div>
            <div class="admin-topbar-actions">
                <?php if (!str_starts_with($current, '/admin/help') && $documentationTarget !== null): ?>
                    <a class="btn btn-ghost admin-context-help" href="<?= e(url('admin/help/' . $documentationTarget['guide'] . '/' . $documentationTarget['slug'])) ?>"><span aria-hidden="true">?</span><span class="admin-context-help-label">Help</span></a>
                <?php endif; ?>
                <?php if (count($adminBrands) > 1): ?>
                    <div class="admin-brand-switcher">
                        <button class="btn btn-ghost admin-brand-switcher__trigger" type="button" aria-expanded="false" aria-controls="admin-brand-menu">
                            <img src="<?= e(asset($adminBrandAssets['icon'] ?? $adminBrandAssets['logo'] ?? '/assets/brands/vanassist/mark.svg')) ?>" alt="" width="28" height="28">
                            <span class="admin-brand-trigger-copy"><small>Workspace</small><strong><?= $this->e($adminBrand->name()) ?></strong></span><span class="admin-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div class="admin-brand-menu" id="admin-brand-menu" hidden>
                            <p class="admin-brand-menu__label">Switch workspace</p>
                            <?php if (auth()->hasAnyRole('super-administrator', 'administrator', 'platform-administrator')): ?><a href="<?= e(url('admin/control-centre')) ?>"><span class="admin-platform-icon" aria-hidden="true">AP</span><span><strong>All brands</strong><small>Platform control centre</small></span></a><?php endif; ?>
                            <?php foreach ($adminBrands as $brandKey => $switchBrand): ?>
                                <?php $switchAssets = $switchBrand->assets(); ?>
                                <?php if ($switchBrand->id() === $adminBrand->id()): ?><span class="is-current" aria-current="true"><img src="<?= e(asset($switchAssets['icon'] ?? $switchAssets['logo'] ?? '/assets/brands/vanassist/mark.svg')) ?>" alt="" width="32" height="32"><span><strong><?= $this->e($switchBrand->name()) ?></strong><small>Current workspace</small></span><span class="admin-current-mark" aria-hidden="true">✓</span></span>
                                <?php else: ?><form method="post" action="<?= e(url('admin/switch-brand')) ?>"><?= csrf_field() ?><input type="hidden" name="brand" value="<?= e($brandKey) ?>"><input type="hidden" name="return_path" value="/admin"><button type="submit"><img src="<?= e(asset($switchAssets['icon'] ?? $switchAssets['logo'] ?? '/assets/brands/vanassist/mark.svg')) ?>" alt="" width="32" height="32"><span><strong><?= $this->e($switchBrand->name()) ?></strong><small><?= $this->e(ucfirst($switchBrand->status())) ?> workspace</small></span></button></form><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!str_ends_with((string) parse_url($adminBrand->url(), PHP_URL_HOST), '.test')): ?><a class="btn btn-ghost admin-view-site" href="<?= e($adminBrand->url()) ?>" target="_blank" rel="noopener"><span class="admin-view-site-label">View site</span><span aria-hidden="true">↗</span></a><?php endif; ?>
                <span class="admin-user"><?= $this->e($user['name'] ?? '') ?></span>
                <form class="admin-signout" method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><span class="admin-signout-label">Sign out</span><svg class="admin-signout-icon" aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M9 12h9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                </form>
            </div>
        </div>
        <div class="admin-content">
            <?php $this->include('partials.flash'); ?>
            <?= $this->yield('content') ?>
        </div>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin-platform.js')) ?>" defer></script>
</body>
</html>
