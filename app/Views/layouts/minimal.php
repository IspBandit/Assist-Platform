<?php
/** @var \App\Core\View $this */
$minimalBrand = current_brand();
$minimalBrandMeta = $minimalBrand->metadata();
$minimalBrandAssets = $minimalBrand->assets();
?>
<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? $minimalBrand->name()) ?> — <?= $this->e($minimalBrand->name()) ?></title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php $this->include('partials.brand-theme'); ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<main id="main" class="auth-wrap">
    <div class="<?= $this->e($cardClass ?? 'auth-card') ?>">
        <div class="text-center" style="margin-bottom:1.5rem">
            <a class="brand brand-auth" href="<?= e(url('/')) ?>" aria-label="<?= e($minimalBrand->name()) ?> home">
                <img class="brand-mark" src="<?= e(url(ltrim($minimalBrandAssets['logo'] ?? '/assets/brands/vanassist/mark.svg', '/'))) ?>" alt="" width="44" height="44">
                <span class="brand-copy"><span class="brand-name"><?= e($minimalBrandMeta['wordmark_prefix'] ?? $minimalBrand->name()) ?><span class="assist"><?= e($minimalBrandMeta['wordmark_accent'] ?? '') ?></span></span><span class="brand-descriptor"><?= e($minimalBrandMeta['header_descriptor'] ?? $minimalBrandMeta['tagline'] ?? '') ?></span></span>
            </a>
        </div>
        <?php $this->include('partials.flash'); ?>
        <?= $this->yield('content') ?>
    </div>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
