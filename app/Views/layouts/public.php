<?php
/** @var \App\Core\View $this */
$layoutBrand = current_brand();
$layoutBrandAssets = $layoutBrand->assets();
$layoutBrandTheme = $layoutBrand->theme();
$layoutPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$isCustomerWorkspace = $layoutPath === '/account' || str_starts_with($layoutPath, '/account/');
$isProviderWorkspace = $layoutPath === '/provider' || str_starts_with($layoutPath, '/provider/');
$isParkWorkspace = $layoutPath === '/park' || str_starts_with($layoutPath, '/park/');
$dashboardAudience = $isCustomerWorkspace ? 'customer' : (($isProviderWorkspace || $isParkWorkspace) ? 'provider' : null);
$dashboardHelp = $dashboardAudience !== null ? \App\Services\Documentation\DocumentationLinkResolver::forRoute($layoutPath, $dashboardAudience) : null;
?>
<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->include('partials.seo-meta'); ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php $this->include('partials.brand-theme'); ?>
    <meta name="theme-color" content="<?= e($layoutBrandTheme['brand'] ?? '#0f6e6e') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset($layoutBrandAssets['favicon'] ?? '/assets/brands/vanassist/favicon.svg')) ?>">
    <?php if (in_array($layoutBrand->id(), ['vanassist', 'towsmart', 'trailerwise'], true)): ?>
        <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
        <?php if ($layoutBrand->id() === 'vanassist'): ?>
            <link rel="apple-touch-icon" sizes="192x192" href="<?= e(asset('assets/brands/vanassist/install-icon-192.png')) ?>">
        <?php else: ?>
            <link rel="apple-touch-icon" href="<?= e(asset(ltrim((string) ($layoutBrandAssets['favicon'] ?? $layoutBrandAssets['icon'] ?? ''), '/'))) ?>">
        <?php endif; ?>
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="<?= e($layoutBrand->shortName()) ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/xml" title="Sitemap" href="<?= e(url('sitemap.xml')) ?>">
    <?= $this->yield('head') ?>
</head>
<body data-brand="<?= e_attr($layoutBrand->id()) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<?php $this->include('partials.header'); ?>

<?php if ($dashboardHelp !== null): ?>
    <div class="context-help-bar"><div class="container"><span>Need help with this page?</span><a class="btn btn-secondary btn-sm" href="<?= e(url('help/' . $dashboardHelp['guide'] . '/' . $dashboardHelp['slug'])) ?>">Open page guide</a></div></div>
<?php endif; ?>

<main id="main">
    <?php $this->include('partials.flash'); ?>
    <?= $this->yield('content') ?>
</main>

<?php $this->include('partials.footer'); ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
