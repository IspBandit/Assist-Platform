<?php
/** @var \App\Core\View $this */
$layoutBrand = current_brand();
$layoutBrandAssets = $layoutBrand->assets();
$layoutBrandTheme = $layoutBrand->theme();
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
</head>
<body>
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
