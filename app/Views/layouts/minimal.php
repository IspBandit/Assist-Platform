<?php
/** @var \App\Core\View $this */
$minimalBrand = current_brand();
$minimalBrandMeta = $minimalBrand->metadata();
?>
<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? $minimalBrand->name()) ?> — <?= $this->e($minimalBrand->name()) ?></title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<main id="main" class="auth-wrap">
    <div class="<?= $this->e($cardClass ?? 'auth-card') ?>">
        <div class="text-center" style="margin-bottom:1.5rem">
            <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($minimalBrand->name()) ?> home"><?= e($minimalBrandMeta['wordmark_prefix'] ?? $minimalBrand->name()) ?><span class="assist"><?= e($minimalBrandMeta['wordmark_accent'] ?? '') ?></span></a>
        </div>
        <?php $this->include('partials.flash'); ?>
        <?= $this->yield('content') ?>
    </div>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
