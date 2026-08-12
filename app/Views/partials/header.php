<?php
/** @var \App\Core\View $this */
use App\Platform\AiSearch\Support\AiSearchFeature;

$headerBrand = current_brand();
$headerBrandMeta = $headerBrand->metadata();
$headerPlatformContext = array_key_exists('header_platform_context', $headerBrandMeta)
    ? (string)$headerBrandMeta['header_platform_context']
    : 'Assist Platform';
?>
<header class="site-header">
    <div class="container">
        <a class="brand brand--wordmark brand--<?= e_attr($headerBrand->id()) ?>" href="<?= e(url('/')) ?>" aria-label="<?= e($headerBrand->name()) ?> home">
            <?php if ($headerBrand->id() === 'vanassist'): ?>
                <img class="vanassist-road-wordmark" src="<?= e(asset('brands/vanassist/wordmark-road.png')) ?>" width="1200" height="340" alt="VanAssist — Find. Connect. Get Assisted.">
            <?php else: ?>
                <span class="brand-copy">
                    <span class="brand-name"><?= e($headerBrandMeta['wordmark_prefix'] ?? $headerBrand->name()) ?><span class="assist"><?= e($headerBrandMeta['wordmark_accent'] ?? '') ?></span></span>
                    <span class="brand-descriptor"><?= e($headerBrandMeta['header_descriptor'] ?? $headerBrandMeta['tagline'] ?? '') ?><?php if ($headerPlatformContext !== ''): ?> · <?= e($headerPlatformContext) ?><?php endif; ?></span>
                </span>
            <?php endif; ?>
        </a>

        <div class="header-actions">
            <?php if (auth()->check()): ?>
                <a class="account-link" href="<?= e(url('account')) ?>" aria-label="My account">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>Account</span>
                </a>
            <?php else: ?>
                <a class="account-link" href="<?= e(url('login')) ?>" aria-label="Sign in to your account">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>Sign in</span>
                </a>
            <?php endif; ?>
            <button class="nav-toggle" aria-expanded="false" aria-controls="main-nav">Menu</button>
        </div>

        <nav class="main-nav" id="main-nav" aria-label="Primary">
            <ul>
                <?php if ($headerBrand->id() !== 'vanassist'): ?>
                    <?php foreach ($headerBrand->navigation() as $link): ?>
                        <li><a href="<?= e(url(ltrim($link['path'], '/'))) ?>"><?= $this->e($link['label']) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (auth()->check()): ?>
                        <li class="nav-auth"><a href="<?= e(url('account')) ?>">My account</a></li>
                    <?php else: ?>
                        <li class="nav-auth"><a href="<?= e(url('login')) ?>">Sign in</a></li>
                    <?php endif; ?>
                <?php else: ?>
                <li><a data-location-link href="<?= e(url('find')) ?>">Find help</a></li>
                <li><a data-location-link href="<?= e(url('stays')) ?>">Places to stay</a></li>
                <?php if (AiSearchFeature::enabled()): ?>
                <li><a href="<?= e(url('ask')) ?>">Ask VanAssist</a></li>
                <?php endif; ?>
                <li><a href="<?= e(url('how-it-works')) ?>">How it works</a></li>
                <li><a href="<?= e(url('rules')) ?>">Rules & safety</a></li>
                <li><a href="<?= e(url('for-providers')) ?>">For businesses</a></li>
                <?php if (auth()->check()): ?>
                    <li class="nav-auth"><a href="<?= e(url('account')) ?>">My account</a></li>
                <?php else: ?>
                    <li class="nav-auth"><a href="<?= e(url('login')) ?>">Sign in</a></li>
                <?php endif; ?>
                <li><a class="btn btn-primary" href="<?= e(url('request-assistance')) ?>">Request help</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
