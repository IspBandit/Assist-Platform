<?php
/** @var \App\Core\View $this */
/** @var array $blocks */
/** @var array $confirmedRuns */
/** @var array $formingRuns */
/** @var array $categories */
/** @var array<string,array<int,array<string,mixed>>> $categoryGroups */
/** @var array $popularCategories */
/** @var array<string,mixed>|null $nearbyTown */
/** @var array<int,array<string,mixed>> $nearbyProviders */
/** @var string $nearbyFindUrl */
/** @var string $nearbyEndpoint */
/** @var array<string,int> $homeEvidence */
$this->extend('layouts.public');
$categoryGroups = $categoryGroups ?? ['Services' => ($categories ?? [])];
$popularCategories = $popularCategories ?? array_slice($categories ?? [], 0, 8);
$evidence = $homeEvidence ?? [];
$directoryCount = (int) ($evidence['directory_listings'] ?? 0);
$verifiedCount = (int) ($evidence['verified_providers'] ?? 0);
$townCount = (int) ($evidence['provider_towns'] ?? 0);
$categoryCount = (int) ($evidence['service_categories'] ?? 0);
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
                <p class="hero-roadline">Wherever the road takes you.</p>
                <p class="lead">Find trusted caravan, motorhome and RV services, places to stay, fuel and local help anywhere in Australia.</p>

                <button class="hero-bookmark" type="button" data-install-app>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1Z"/></svg>
                    Save VanAssist before you go
                </button>

                <ul class="hero-trust">
                    <li>Claimed and verified status shown clearly</li>
                    <li>Location-first results</li>
                    <li>Free to search</li>
                </ul>
            </div>

            <div class="hero-search-panel">
                <div class="mobile-hero-intro">
                    <span>Right across regional Australia</span>
                    <h1>Your travel <strong>companion.</strong></h1>
                    <p>Nearby services, fuel, stays and practical help. Search before you set off or while safely stopped.</p>
                    <button type="button" data-install-app>Save VanAssist to your phone</button>
                </div>
                <form class="search-card" method="get" action="<?= e(url('find')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" data-auto-location>
                    <div class="search-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        Find help for the road ahead
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
                    <details class="search-options">
                        <summary>More search options</summary>
                        <div class="grid grid-2">
                            <div class="form-group mb-0">
                                <label for="timeframe">Preferred timeframe</label>
                                <select id="timeframe" name="timeframe">
                                    <option value="">Any time</option>
                                    <option value="2weeks">Within 2 weeks</option>
                                    <option value="month">Within a month</option>
                                    <option value="flexible">Flexible</option>
                                </select>
                            </div>
                            <?php $this->include('partials.search-distance-filter', [
                                'selected' => null,
                                'disabled' => true,
                            ]); ?>
                        </div>
                    </details>
                    <div class="btn-row home-search-actions">
                        <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary btn-lg']); ?>
                        <button type="submit" class="btn btn-primary btn-lg">Show nearby help</button>
                        <a class="btn btn-secondary btn-lg" href="<?= e(url('request-assistance')) ?>">I can't find the help I need</a>
                    </div>
                </form>
                <nav class="mobile-travel-shortcuts" aria-label="Traveller shortcuts">
                    <a href="<?= e(url('find')) ?>"><strong>Services</strong><span>Repairs &amp; help</span></a>
                    <a href="<?= e(url('stays')) ?>"><strong>Stays</strong><span>Caravan stops</span></a>
                    <a href="<?= e(url('find?category=fuel-and-travel-stops')) ?>"><strong>Fuel</strong><span>Servos nearby</span></a>
                    <a href="<?= e(url('services')) ?>"><strong>Essentials</strong><span>Browse all</span></a>
                </nav>
            </div>

        </div>

        <ul class="hero-capabilities" aria-label="VanAssist travel coverage">
            <li><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3-3a4.2 4.2 0 0 1-5.4 5.4l-6.6 6.6a2.1 2.1 0 0 0 3 3l6.6-6.6a4.2 4.2 0 0 1 5.4-5.4l-3 3"/></svg></span><strong>Trusted services</strong><small>Repairs and mobile help</small></li>
            <li><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-7h18v7M5 11V7h6v4M3 18v3m18-3v3M3 15h18"/></svg></span><strong>Places to stay</strong><small>Caravan-friendly stops</small></li>
            <li><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V4a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v17M4 10h10M7 6h4M14 8h2l3 3v7a2 2 0 0 0 2 2"/></svg></span><strong>Fuel &amp; essentials</strong><small>Useful stops nearby</small></li>
            <li><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><strong>Australia wide</strong><small>Location-first search</small></li>
        </ul>
    </div>

    <div class="hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 42 C 240 84 480 4 720 26 C 960 48 1200 82 1440 40 L1440 80 L0 80 Z" fill="#fbf8f1"/></svg>
    </div>
</section>

<div class="container listing-accuracy-notice-wrap">
    <?php $this->include('partials.listing-accuracy-notice'); ?>
</div>

<section class="evidence-ribbon" aria-label="Current VanAssist directory evidence">
    <div class="container evidence-ribbon-grid">
        <?php if ($directoryCount > 0): ?><div><strong><?= number_format($directoryCount) ?></strong><span>active service listings</span></div><?php endif; ?>
        <?php if ($verifiedCount > 0): ?><div><strong><?= number_format($verifiedCount) ?></strong><span>verified providers</span></div><?php endif; ?>
        <?php if ($townCount > 0): ?><div><strong><?= number_format($townCount) ?></strong><span>provider base towns</span></div><?php endif; ?>
        <?php if ($categoryCount > 0): ?><div><strong><?= number_format($categoryCount) ?></strong><span>service categories</span></div><?php endif; ?>
        <div class="evidence-ribbon-note"><strong>Clear listing labels</strong><span>Verified, featured and unclaimed mean different things</span></div>
    </div>
</section>

<section class="section journey-launcher" aria-labelledby="journey-launcher-heading">
    <div class="container">
        <div class="journey-launcher-head">
            <div>
                <span class="directory-eyebrow">Choose your next stop</span>
                <h2 id="journey-launcher-heading">Start with what you need right now.</h2>
            </div>
            <p>Each search remains free to browse. Location helps VanAssist show useful nearby results instead of a national list.</p>
        </div>
        <div class="journey-launcher-grid">
            <a class="journey-launcher-card" href="<?= e(url('find')) ?>">
                <span class="journey-launcher-number" aria-hidden="true">01</span>
                <span><strong>Repairs &amp; mobile help</strong><small>Caravan, RV, vehicle and roadside services</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
            <a class="journey-launcher-card" href="<?= e(url('find?category=fuel-and-travel-stops')) ?>">
                <span class="journey-launcher-number" aria-hidden="true">02</span>
                <span><strong>Fuel &amp; travel stops</strong><small>Find fuel stations for the next leg</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
            <a class="journey-launcher-card" href="<?= e(url('find?category=ev-charging')) ?>">
                <span class="journey-launcher-number" aria-hidden="true">03</span>
                <span><strong>EV charging</strong><small>Locate charging options along your journey</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
            <a class="journey-launcher-card" href="<?= e(url('stays')) ?>">
                <span class="journey-launcher-number" aria-hidden="true">04</span>
                <span><strong>Places to stay</strong><small>Parks, campgrounds, showgrounds and low-cost stops</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
        </div>
    </div>
</section>

<?php $this->include('partials.home-nearby-providers'); ?>

<section class="section process-section" aria-labelledby="process-heading">
    <div class="container process-layout">
        <div class="process-intro">
            <p class="experience-kicker">Designed for real travel problems</p>
            <h2 id="process-heading">From “who can help?” to a useful next step.</h2>
            <p>VanAssist shows what is known, labels what has been checked and provides a path forward when local coverage is incomplete.</p>
            <a class="text-link" href="<?= e(url('how-it-works')) ?>">Understand the complete process <span aria-hidden="true">→</span></a>
        </div>
        <ol class="process-list">
            <li><span>01</span><div><h3>Describe the need and location</h3><p>Choose a service and search by town, postcode or your current location.</p></div></li>
            <li><span>02</span><div><h3>Review the listing evidence</h3><p>Compare service model, location and clearly labelled verification status.</p></div></li>
            <li><span>03</span><div><h3>Contact, request or register demand</h3><p>Contact a suitable business or record a local coverage gap.</p></div></li>
        </ol>
    </div>
</section>

<section class="section trust-section" aria-labelledby="trust-heading">
    <div class="container trust-layout">
        <div>
            <p class="experience-kicker">Trust through transparency</p>
            <h2 id="trust-heading">Know what each listing label means.</h2>
            <p>Discovered businesses, paid visibility and reviewed provider evidence are not presented as though they are the same thing.</p>
        </div>
        <dl class="trust-definitions">
            <div><dt>Verified</dt><dd>Provider evidence has been reviewed for the status displayed. Confirm suitability for your particular work.</dd></div>
            <div><dt>Featured</dt><dd>A clearly labelled promotional position that does not replace service or location relevance.</dd></div>
            <div><dt>Unclaimed</dt><dd>The business does not yet control the listing. Confirm current details directly before relying on them.</dd></div>
        </dl>
    </div>
</section>

<section class="section section-sand">
    <div class="container"><div class="product-cta">
        <div><div class="eyebrow">Plan a safe stop</div><h2>Getting tired? Find a place to stay.</h2><p>Use your location or search a town for caravan parks, campgrounds, showgrounds and free or low-cost stays nearby.</p></div>
        <a class="btn btn-primary btn-lg" href="<?= e(url('stays')) ?>">Find a stay near me</a>
    </div></div>
</section>

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
            <?php $seenBlocks = []; ?>
            <?php foreach ($blocks as $block): ?>
                <?php
                $blockKey = strtolower(trim((string) ($block['title'] ?? ''))) . '|' . strtolower(trim((string) ($block['body'] ?? '')));
                if ($blockKey === '|' || isset($seenBlocks[$blockKey])) { continue; }
                $seenBlocks[$blockKey] = true;
                ?>
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

<?php if ($popularCategories !== []): ?>
<section class="section section-sand">
    <div class="container">
        <h2>Popular service categories</h2>
        <div class="btn-row">
            <?php foreach ($popularCategories as $cat): ?>
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

<section class="section provider-conversion">
    <div class="container provider-conversion-inner">
        <div>
            <span class="directory-eyebrow">For Australian businesses</span>
            <h2>Help travellers find the right service—not a guessed one.</h2>
            <p>Claim an existing listing or create a provider profile, confirm the services you genuinely offer and keep your contact details current.</p>
        </div>
        <div class="provider-conversion-actions">
            <a class="btn btn-primary btn-lg" href="<?= e(url('for-providers')) ?>">Claim or list a business</a>
            <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Provider sign in</a>
        </div>
    </div>
</section>

<?php $this->endSection(); ?>
