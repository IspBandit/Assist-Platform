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
        <a class="brand brand-admin" href="<?= e(url('admin')) ?>">
            <img class="brand-mark" src="<?= e(url(ltrim($adminBrandAssets['logo'] ?? '/assets/brands/vanassist/mark.svg', '/'))) ?>" alt="" width="40" height="40">
            <span class="brand-name"><?= $this->e($adminBrandMeta['wordmark_prefix'] ?? $adminBrand->name()) ?><span class="assist"><?= $this->e($adminBrandMeta['wordmark_accent'] ?? '') ?></span></span>
        </a>
        <button type="button" class="admin-nav-toggle" aria-controls="admin-nav" aria-expanded="false">Menu</button>
        <p style="font-size:.8rem;color:#9fd0cd;margin:.25rem 0 1rem">Admin portal</p>
        <nav id="admin-nav" aria-label="Admin">
            <?php foreach ($nav as $group => $links): ?>
                <p style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#7fb8b4;margin:1rem 0 .25rem"><?= $this->e($group) ?></p>
                <?php foreach ($links as [$label, $href]): ?>
                    <?php $active = rtrim($href, '/') === $current ? ' active' : ''; ?>
                    <a class="<?= trim($active) ?>" href="<?= e(url(ltrim($href, '/'))) ?>"><?= $this->e($label) ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <strong><?= $this->e($title ?? 'Admin') ?></strong>
            <div class="btn-row" style="margin:0;align-items:center">
                <?php if (count($adminBrands) > 1): ?>
                    <div class="admin-brand-switcher">
                        <button class="btn btn-ghost admin-brand-switcher__trigger" type="button" aria-expanded="false" aria-controls="admin-brand-menu">
                            <span class="admin-brand-dot" style="background:<?= e($adminBrandTheme['brand'] ?? '#0f6e6e') ?>"></span>
                            <?= $this->e($adminBrand->name()) ?> <span aria-hidden="true">▾</span>
                        </button>
                        <div class="admin-brand-menu" id="admin-brand-menu" hidden>
                            <?php if (auth()->hasAnyRole('super-administrator', 'administrator', 'platform-administrator')): ?><a href="<?= e(url('admin/control-centre')) ?>"><strong>All Brands</strong><span>Platform control centre</span></a><?php endif; ?>
                            <?php foreach ($adminBrands as $brandKey => $switchBrand): ?>
                                <?php if ($switchBrand->id() === $adminBrand->id()): ?><span class="is-current"><strong><?= $this->e($switchBrand->name()) ?></strong><small>Current dashboard</small></span>
                                <?php else: ?><form method="post" action="<?= e(url('admin/switch-brand')) ?>"><?= csrf_field() ?><input type="hidden" name="brand" value="<?= e($brandKey) ?>"><input type="hidden" name="return_path" value="<?= e($current) ?>"><button type="submit"><strong><?= $this->e($switchBrand->name()) ?></strong><small><?= $this->e($switchBrand->status()) ?></small></button></form><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <a class="btn btn-ghost" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site</a>
                <span class="muted" style="font-size:.9rem"><?= $this->e($user['name'] ?? '') ?></span>
                <form method="post" action="<?= e(url('logout')) ?>" style="margin:0">
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
