<?php
/** @var \App\Core\View $this */
/** @var array $blocks */
/** @var array $confirmedRuns */
/** @var array $formingRuns */
/** @var array $categories */
/** @var array<string,mixed>|null $nearbyTown */
/** @var array<int,array<string,mixed>> $nearbyProviders */
/** @var string $nearbyFindUrl */
/** @var string $nearbyEndpoint */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>

<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-copy">
                <span class="hero-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    Right across regional Australia
                </span>
                <h1>Caravan help, <span class="accent">wherever you travel.</span></h1>
                <p class="lead">Find caravan and RV specialists coming to your area, or register the service you need to help bring a provider to town.</p>

                <form class="search-card" method="get" action="<?= e(url('find')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
                    <div class="search-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        Search providers and service runs
                    </div>
                    <div class="grid grid-3">
                        <div class="form-group mb-0">
                            <label for="category">Service category</label>
                            <select id="category" name="category">
                                <option value="">Any service</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e_attr($cat['slug']) ?>"><?= $this->e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0 location-field">
                            <label for="location">Town, suburb or postcode</label>
                            <input type="text" id="location" name="location" placeholder="e.g. Parramatta or 2150" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                            <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                            <input type="hidden" name="lat" value="">
                            <input type="hidden" name="lng" value="">
                            <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline']); ?>
                            <p class="location-status muted" role="status" aria-live="polite" hidden></p>
                        </div>
                        <div class="form-group mb-0">
                            <label for="timeframe">Preferred timeframe</label>
                            <select id="timeframe" name="timeframe">
                                <option value="">Any time</option>
                                <option value="2weeks">Within 2 weeks</option>
                                <option value="month">Within a month</option>
                            <option value="flexible">Flexible</option>
                        </select>
                        <p class="muted" style="font-size:.8rem;margin:.25rem 0 0">Prefills urgency when you register a request from search results.</p>
                    </div>
                    </div>
                    <div class="grid grid-3" style="margin-top:.75rem">
                        <?php $this->include('partials.search-distance-filter', [
                            'selected' => null,
                            'disabled' => true,
                        ]); ?>
                    </div>
                    <div class="btn-row" style="margin-top:1rem">
                        <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary btn-lg']); ?>
                        <button type="submit" class="btn btn-primary btn-lg">Find a service</button>
                        <a class="btn btn-secondary btn-lg" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
                        <a class="btn btn-ghost btn-lg" href="<?= e(url('for-providers')) ?>">Join as a provider</a>
                    </div>
                </form>

                <ul class="hero-trust">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        Verified local providers
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        Coverage in remote towns
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        Free, no-obligation requests
                    </li>
                </ul>
            </div>

            <div class="hero-art">
                <picture>
                    <source media="(max-width: 719px)" srcset="<?= e(asset('img/vanassist-hero-mobile.webp')) ?>">
                    <img class="hero-photo" src="<?= e(asset('img/vanassist-hero-desktop.webp')) ?>" width="1824" height="864"
                         alt="Mobile caravan technician helping travellers with their caravan in regional Australia"
                         loading="eager" fetchpriority="high">
                </picture>
            </div>
        </div>
    </div>

    <div class="hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 42 C 240 84 480 4 720 26 C 960 48 1200 82 1440 40 L1440 80 L0 80 Z" fill="#fbf8f1"/></svg>
    </div>
</section>

<?php $this->include('partials.home-nearby-providers'); ?>

<?php if (!empty($freeMessage)): ?>
<section class="section section-sand" style="padding:1.25rem 0">
    <div class="container"><div class="alert alert-info mb-0"><?= $this->e($freeMessage) ?></div></div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <h2>Upcoming confirmed service runs</h2>
        <?php if ($confirmedRuns === []): ?>
            <p class="muted">No confirmed runs yet. <a href="<?= e(url('request-assistance')) ?>">Register your request</a> to help one form.</p>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($confirmedRuns as $run): ?>
                    <div class="card">
                        <span class="badge badge-confirmed">Confirmed</span>
                        <h3 style="margin-top:.5rem"><a href="<?= e(url('service-runs/' . $run['slug'])) ?>"><?= $this->e($run['title']) ?></a></h3>
                        <p class="muted mb-0"><?= $this->e($run['business_name']) ?> &middot; from <?= $this->e((string) $run['start_date']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-sand">
    <div class="container">
        <h2>Runs currently forming</h2>
        <?php if ($formingRuns === []): ?>
            <p class="muted">No runs forming right now.</p>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($formingRuns as $run): ?>
                    <div class="card">
                        <span class="badge badge-forming">Forming</span>
                        <h3 style="margin-top:.5rem"><a href="<?= e(url('service-runs/' . $run['slug'])) ?>"><?= $this->e($run['title']) ?></a></h3>
                        <p class="muted mb-0"><?= (int) $run['bookings_count'] ?> of <?= (int) $run['min_bookings'] ?> required bookings registered</p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>How VanAssist works</h2>
        <div class="grid grid-3">
            <?php foreach ($blocks as $block): ?>
                <div class="card">
                    <h3><?= $this->e($block['title']) ?></h3>
                    <?php if (!empty($block['subtitle'])): ?><p class="muted"><strong><?= $this->e($block['subtitle']) ?></strong></p><?php endif; ?>
                    <p><?= $this->e($block['body']) ?></p>
                    <?php if (!empty($block['button_label'])): ?>
                        <a class="btn btn-secondary" href="<?= e(url(ltrim((string) $block['button_url'], '/'))) ?>"><?= $this->e($block['button_label']) ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($categories !== []): ?>
<section class="section section-sand">
    <div class="container">
        <h2>Popular service categories</h2>
        <div class="btn-row">
            <?php foreach ($categories as $cat): ?>
                <a class="btn btn-ghost" href="<?= e(url('services/' . $cat['slug'])) ?>"><?= $this->e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section text-center">
    <div class="container">
        <h2>Can't find a provider for your area?</h2>
        <p class="muted">No suitable provider is currently listed for some areas. Register your request and VanAssist will notify relevant providers when assistance becomes available.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
    </div>
</section>

<nav class="mobile-action-dock mobile-action-dock--vanassist" aria-label="VanAssist primary actions">
    <a href="<?= e(url('find')) ?>">Find help</a><a href="<?= e(url('request-assistance')) ?>">Request assistance</a>
</nav>

<?php $this->endSection(); ?>
