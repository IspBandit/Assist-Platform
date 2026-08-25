<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
$providerBrand = current_brand();
$brandId = $providerBrand->id();
$brandName = $providerBrand->name();
$providerMessages = [
    'towsmart' => ['Turn towing expertise into qualified demand.', 'Reach people actively checking combinations, weights and safe setup—and show where your products or services fit.'],
    'trailerwise' => ['Put your trailer expertise in front of ready customers.', 'Build a trusted presence across ownership, maintenance, inspection, repair, parts and specialist trailer services.'],
];
[$providerHeading, $providerIntro] = $providerMessages[$brandId] ?? ['Grow your business through the Assist Platform.', 'Build a trusted local presence and connect with customers searching for relevant specialist services.'];
?>
<?php $this->section('content'); ?>
<section class="product-hero provider-product-hero product-hero--<?= e_attr($brandId) ?>">
    <picture class="product-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" type="image/avif" srcset="<?= e(asset('img/' . $brandId . '-hero-mobile.avif')) ?>">
        <source media="(max-width: 719px)" type="image/webp" srcset="<?= e(asset('img/' . $brandId . '-hero-mobile.webp')) ?>">
        <source type="image/avif" srcset="<?= e(asset('img/' . $brandId . '-hero-desktop.avif')) ?>">
        <img src="<?= e(asset('img/' . $brandId . '-hero-desktop.webp')) ?>" width="1824" height="864" alt="" fetchpriority="high">
    </picture>
    <div class="product-hero-shade" aria-hidden="true"></div>
    <div class="container product-hero-content">
        <div class="product-hero-copy">
            <span class="product-kicker">For specialist businesses</span>
            <h1><?= $this->e($providerHeading) ?></h1>
            <p><?= $this->e($providerIntro) ?></p>
            <div class="product-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('for-providers/register')) ?>">List or claim your business</a>
                <a class="btn btn-glass btn-lg" href="<?= e(url('login')) ?>">Provider sign in</a>
            </div>
            <ul class="product-proof" aria-label="Provider benefits">
                <li>One provider identity</li>
                <li>Relevant brand exposure</li>
                <li>Local demand insight</li>
            </ul>
        </div>
    </div>
</section>

<section class="provider-value-strip" aria-label="Provider value">
    <div class="container provider-value-grid">
        <div><span>01</span><strong>Be discovered</strong><small>Appear for relevant services and locations.</small></div>
        <div><span>02</span><strong>Build confidence</strong><small>Present capabilities, credentials and coverage clearly.</small></div>
        <div><span>03</span><strong>Grow deliberately</strong><small>Use demand signals to focus your time and spend.</small></div>
    </div>
</section>

<section class="section provider-conversion-section">
    <div class="container split-feature">
        <div class="section-heading">
            <span class="product-kicker dark">One account. Relevant opportunities.</span>
            <h2>A professional presence without duplicated admin.</h2>
            <p>Your Assist Platform provider identity can support appropriate listings across VanAssist, TowSmart and TrailerWise while each customer sees a focused specialist experience.</p>
        </div>
        <ol class="provider-onboarding-steps">
            <li><span>01</span><div><strong>Tell us what you do</strong><small>Choose real capabilities, service areas and operating details.</small></div></li>
            <li><span>02</span><div><strong>Build trust</strong><small>Add useful business information and optional evidence for review.</small></div></li>
            <li><span>03</span><div><strong>Reach the right audience</strong><small>Participate only in brands and opportunities relevant to your business.</small></div></li>
        </ol>
    </div>
</section>

<section class="section-ink provider-final-cta">
    <div class="container">
        <div><span class="product-kicker">Founding provider access</span><h2>Make <?= $this->e($brandName) ?> part of your growth.</h2><p>Register your business interest now. You stay in control of your profile and participation.</p></div>
        <a class="btn btn-light btn-lg" href="<?= e(url('for-providers/register')) ?>">Get started</a>
    </div>
</section>
<?php $this->endSection(); ?>
