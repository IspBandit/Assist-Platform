<?php
/** @var \App\Core\View $this */
$layoutBrand = current_brand();
$layoutBrandAssets = $layoutBrand->assets();
$layoutBrandTheme = $layoutBrand->theme();
$layoutAnalytics = $layoutBrand->analytics();
$layoutMeasurementId = trim((string) ($layoutAnalytics['measurement_id'] ?? ''));
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
    <link rel="icon" href="<?= e(url(ltrim($layoutBrandAssets['favicon'] ?? '/assets/brands/vanassist/favicon.svg', '/'))) ?>">
    <link rel="alternate" type="application/xml" title="Sitemap" href="<?= e(url('sitemap.xml')) ?>">
    <?= $this->yield('head') ?>
    <?php if (preg_match('/^G-[A-Z0-9]+$/i', $layoutMeasurementId) === 1): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e_attr($layoutMeasurementId) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config',<?= json_encode($layoutMeasurementId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,{anonymize_ip:true});</script>
    <?php endif; ?>
</head>
<body data-brand="<?= e_attr($layoutBrand->id()) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<?php $this->include('partials.header'); ?>

<main id="main">
    <?php $this->include('partials.flash'); ?>
    <?= $this->yield('content') ?>
</main>

<?php $this->include('partials.footer'); ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
