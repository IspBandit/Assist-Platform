<?php
/** @var \App\Core\View $this */
$user = current_user();
$adminBrand = current_brand();
$adminBrandMeta = $adminBrand->metadata();
$adminBrandTheme = $adminBrand->theme();
$nav = [
    'Overview' => [
        ['Dashboard', '/admin'],
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
        ['Demand overview', '/admin/demand'],
        ['Provider usage', '/admin/demand/providers'],
        ['Conversion funnel', '/admin/demand/funnel'],
        ['Coverage gaps', '/admin/demand/coverage'],
        ['Demand map', '/admin/demand/map'],
    ],
    'Catalogue' => [
        ['Locations', '/admin/locations'],
        ['Service categories', '/admin/categories'],
    ],
    'Content' => [
        ['Pages & blocks', '/admin/content'],
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
        <a class="brand" href="<?= e(url('admin')) ?>"><?= $this->e($adminBrandMeta['wordmark_prefix'] ?? $adminBrand->name()) ?><span class="assist"><?= $this->e($adminBrandMeta['wordmark_accent'] ?? '') ?></span></a>
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
</body>
</html>
