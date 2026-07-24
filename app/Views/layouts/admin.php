<?php
/** @var \App\Core\View $this */
$user = current_user();
$adminBrand = current_brand();
$adminBrandMeta = $adminBrand->metadata();
$adminBrandTheme = $adminBrand->theme();
$adminBrandAssets = $adminBrand->assets();
$adminBrands = $user !== null ? \App\Services\AdminBrandAccess::availableBrands((int) $user['id']) : [];
$nav = [
    'Overview' => [
        ['Dashboard', '/admin'],
        ['All brands', '/admin/control-centre'],
    ],
    'People' => [
        ['Users', '/admin/users'],
        ['Customers', '/admin/customers'],
        ['Providers', '/admin/providers'],
        ['Trailer listings', '/admin/trailer-listings'],
        ['Ad graphics', '/admin/promotions'],
        ['Provider prospects', '/admin/prospects'],
        ['Caravan parks', '/admin/parks'],
    ],
    'Demand' => [
        ['Service requests', '/admin/requests'],
        ['Matching console', '/admin/matching'],
        ['Service runs', '/admin/runs'],
    ],
    'Analytics' => [
        ['Data Intelligence', '/admin/data-intelligence'],
        ['Demand overview', '/admin/demand'],
        ['Provider usage', '/admin/demand/providers'],
        ['Conversion funnel', '/admin/demand/funnel'],
        ['Coverage gaps', '/admin/demand/coverage'],
        ['Demand map', '/admin/demand/map'],
    ],
    'Catalogue' => [
        ['Locations', '/admin/locations'],
        ['Service categories', '/admin/categories'],
        ['Data sources', '/admin/data-sources'],
        ['Import review', '/admin/data-sources/review'],
    ],
    'Content' => [
        ['Pages & blocks', '/admin/content'],
        ['Social studio', '/admin/social-media'],
        ['Email templates', '/admin/email-templates'],
        ['Notifications', '/admin/notifications'],
        ['SEO', '/admin/seo'],
    ],
    'Billing' => [
        ['Plans & billing', '/admin/billing'],
    ],
    'Finance' => [
        ['Finance dashboard', '/admin/finance'],
        ['Chart of accounts', '/admin/finance/accounts'],
        ['Journals', '/admin/finance/journals'],
    ],
    'System' => [
        ['Reports', '/admin/reports'],
        ['Audit log', '/admin/audit'],
        ['System logs', '/admin/logs'],
        ['Settings', '/admin/settings'],
        ['Feature flags', '/admin/feature-flags'],
        ['Backups', '/admin/backups'],
        ['Maintenance', '/admin/maintenance'],
    ],
];
$current = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/admin';
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
</head>
<body>
<div class="admin-body">
    <aside class="admin-sidebar">
        <a class="brand brand-admin" href="<?= e(url('admin')) ?>" aria-label="Assist Platform admin home">
            <img class="brand-mark" src="<?= e(url(ltrim($adminBrandAssets['logo'] ?? '/assets/brands/vanassist/mark.svg', '/'))) ?>" alt="" width="40" height="40">
            <span class="brand-copy"><span class="brand-name">Assist Platform</span><span class="admin-brand-context"><?= $this->e($adminBrand->name()) ?> workspace</span></span>
        </a>
        <button type="button" class="admin-nav-toggle" aria-controls="admin-nav" aria-expanded="false">Menu</button>
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
                <?php if (count($adminBrands) > 1): ?>
                    <div class="admin-brand-switcher">
                        <button class="btn btn-ghost admin-brand-switcher__trigger" type="button" aria-expanded="false" aria-controls="admin-brand-menu">
                            <img src="<?= e(url(ltrim($adminBrandAssets['icon'] ?? $adminBrandAssets['logo'] ?? '', '/'))) ?>" alt="" width="28" height="28">
                            <span class="admin-brand-trigger-copy"><small>Workspace</small><strong><?= $this->e($adminBrand->name()) ?></strong></span><span class="admin-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div class="admin-brand-menu" id="admin-brand-menu" hidden>
                            <p class="admin-brand-menu__label">Switch workspace</p>
                            <?php if (auth()->hasAnyRole('super-administrator', 'administrator', 'platform-administrator')): ?><a href="<?= e(url('admin/control-centre')) ?>"><span class="admin-platform-icon" aria-hidden="true">AP</span><span><strong>All brands</strong><small>Platform control centre</small></span></a><?php endif; ?>
                            <?php foreach ($adminBrands as $brandKey => $switchBrand): ?>
                                <?php $switchAssets = $switchBrand->assets(); ?>
                                <?php if ($switchBrand->id() === $adminBrand->id()): ?><span class="is-current" aria-current="true"><img src="<?= e(url(ltrim($switchAssets['icon'] ?? $switchAssets['logo'] ?? '', '/'))) ?>" alt="" width="32" height="32"><span><strong><?= $this->e($switchBrand->name()) ?></strong><small>Current workspace</small></span><span class="admin-current-mark" aria-hidden="true">✓</span></span>
                                <?php else: ?><form method="post" action="<?= e(url('admin/switch-brand')) ?>"><?= csrf_field() ?><input type="hidden" name="brand" value="<?= e($brandKey) ?>"><input type="hidden" name="return_path" value="<?= e($current) ?>"><button type="submit"><img src="<?= e(url(ltrim($switchAssets['icon'] ?? $switchAssets['logo'] ?? '', '/'))) ?>" alt="" width="32" height="32"><span><strong><?= $this->e($switchBrand->name()) ?></strong><small><?= $this->e(ucfirst($switchBrand->status())) ?> workspace</small></span></button></form><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <a class="btn btn-ghost admin-view-site" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><span class="admin-view-site-label">View site</span><span aria-hidden="true">↗</span></a>
                <span class="admin-user"><?= $this->e($user['name'] ?? '') ?></span>
                <form class="admin-signout" method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary">Sign out</button>
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
