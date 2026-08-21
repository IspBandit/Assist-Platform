<?php
/** @var \App\Core\View $this */
/** @var array $categories */
/** @var array<string,array<int,array<string,mixed>>> $categoryGroups */
$this->extend('layouts.public');
$categoryGroups = $categoryGroups ?? ['Services' => ($categories ?? [])];
?>
<?php $this->section('content'); ?>

<section class="hero hero--visual">
    <picture class="hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" type="image/webp" srcset="<?= e(asset('img/vanassist-coastal-hero-mobile-v1.webp')) ?>">
        <img src="<?= e(asset('img/vanassist-coastal-hero-desktop-v1.webp')) ?>" width="1920" height="800" alt="" fetchpriority="high">
    </picture>
    <div class="hero-media-shade" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-grid">
            <div class="hero-copy">
                <span class="hero-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    Right across regional Australia
                </span>
                <h1><span class="hero-title-primary">Your travel</span> <span class="accent">companion.</span></h1>
                <p class="lead">Find caravan and RV services, places to stay, fuel and practical help by town or current location.</p>
            </div>

            <div class="hero-search-panel">
                <div class="mobile-hero-intro">
                    <h1>Your travel <strong>companion.</strong></h1>
                    <p>Search by town or use your location when safely stopped.</p>
                </div>
                <div class="search-card unified-search-card">
                <form class="structured-search-form home-search-form" method="get" action="<?= e(url('find')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" data-auto-location>
                    <div class="search-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        Find help nearby
                    </div>
                    <div class="grid grid-2 home-search-primary">
                        <div class="form-group mb-0">
                            <label for="category">Service category</label>
                            <select id="category" name="category">
                                <option value="">Any service</option>
                                <?php foreach ($categoryGroups as $groupName => $groupCategories): ?>
                                    <optgroup label="<?= e_attr($groupName) ?>">
                                        <?php foreach ($groupCategories as $cat): ?>
                                            <option value="<?= e_attr($cat['slug']) ?>"><?= $this->e($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0 location-field">
                            <label for="location">Town, suburb or postcode</label>
                            <input type="text" id="location" name="location" placeholder="e.g. Parramatta or 2150" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                            <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                            <input type="hidden" name="lat" value="">
                            <input type="hidden" name="lng" value="">
                            <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline', 'autoSubmit' => 'false']); ?>
                            <p class="location-status muted" role="status" aria-live="polite" hidden></p>
                        </div>
                    </div>
                    <div class="btn-row home-search-actions">
                        <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary btn-lg']); ?>
                        <button type="submit" class="btn btn-primary btn-lg">Show nearby help</button>
                    </div>
                    <p class="home-search-note muted">
                        Claimed, verified and unclaimed listings are labelled clearly. Confirm details before you travel.
                        <a href="<?= e(url('find')) ?>">More search options</a>
                        ·
                        <a href="<?= e(url('request-assistance')) ?>">Request assistance</a>
                    </p>
                </form>
                <?php $this->include('partials.ask-vanassist'); ?>
                </div>
            </div>

        </div>

        <nav class="hero-capabilities" aria-label="Find VanAssist help">
            <a data-location-link href="<?= e(url('find')) ?>"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3-3a4.2 4.2 0 0 1-5.4 5.4l-6.6 6.6a2.1 2.1 0 0 0 3 3l6.6-6.6a4.2 4.2 0 0 1 5.4-5.4l-3 3"/></svg></span><strong>Trusted services</strong><small>Repairs and mobile help</small></a>
            <a data-location-link href="<?= e(url('stays')) ?>"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-7h18v7M5 11V7h6v4M3 18v3m18-3v3M3 15h18"/></svg></span><strong>Places to stay</strong><small>Caravan-friendly stops</small></a>
            <a data-location-link href="<?= e(url('find?category=fuel-and-travel-stops')) ?>"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V4a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v17M4 10h10M7 6h4M14 8h2l3 3v7a2 2 0 0 0 2 2"/></svg></span><strong>Fuel &amp; essentials</strong><small>Useful stops nearby</small></a>
            <a href="<?= e(url('services')) ?>"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><strong>Browse all help</strong><small>Services across Australia</small></a>
        </nav>
    </div>

    <div class="hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 42 C 240 84 480 4 720 26 C 960 48 1200 82 1440 40 L1440 80 L0 80 Z" fill="#fbf8f1"/></svg>
    </div>
</section>

<?php if (!empty($freeMessage)): ?>
<p class="home-launch-note container"><?= $this->e($freeMessage) ?></p>
<?php endif; ?>

<?php $this->endSection(); ?>
