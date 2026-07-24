<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $blocks */
/** @var array<int,array<string,mixed>> $confirmedRuns */
/** @var array<int,array<string,mixed>> $formingRuns */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,mixed>|null $nearbyTown */
/** @var array<int,array<string,mixed>> $nearbyProviders */
/** @var string $nearbyFindUrl */
/** @var string $nearbyEndpoint */
/** @var array<string,int> $homeEvidence */
$this->extend('layouts.public');
$evidence = $homeEvidence ?? [];
$directoryCount = (int) ($evidence['directory_listings'] ?? 0);
$verifiedCount = (int) ($evidence['verified_providers'] ?? 0);
$townCount = (int) ($evidence['provider_towns'] ?? 0);
$categoryCount = (int) ($evidence['service_categories'] ?? 0);
?>
<?php $this->section('content'); ?>

<section class="experience-hero" aria-labelledby="home-heading">
    <picture class="experience-hero-media">
        <source media="(max-width: 719px)" srcset="<?= e(asset('img/vanassist-hero-mobile.webp')) ?>">
        <img src="<?= e(asset('img/vanassist-hero-desktop.webp')) ?>" width="1824" height="864"
             alt="A mobile caravan technician helping travellers in regional Australia"
             loading="eager" fetchpriority="high">
    </picture>
    <div class="experience-hero-shade" aria-hidden="true"></div>
    <div class="container experience-hero-layout">
        <div class="experience-hero-copy">
            <p class="experience-kicker">Help for the road ahead</p>
            <h1 id="home-heading">Find the right caravan help, <em>wherever the journey takes you.</em></h1>
            <p>Search local and mobile RV specialists, find places to stay, or tell us what you need when the right help is not listed yet.</p>
        </div>

        <form class="discovery-panel" method="get" action="<?= e(url('find')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
            <div class="discovery-panel-head">
                <div><span>Start here</span><h2>What help do you need?</h2></div>
                <span class="discovery-step">01</span>
            </div>
            <div class="discovery-fields">
                <div class="form-group">
                    <label for="category">Service</label>
                    <select id="category" name="category">
                        <option value="">Choose a service</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e_attr($cat['slug']) ?>"><?= $this->e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group location-field">
                    <label for="location">Town, suburb or postcode</label>
                    <input type="search" id="location" name="location" placeholder="Where do you need help?" autocomplete="off"
                           data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                    <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                    <input type="hidden" name="lat" value="">
                    <input type="hidden" name="lng" value="">
                    <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline']); ?>
                    <p class="location-status muted" role="status" aria-live="polite" hidden></p>
                </div>
            </div>
            <div class="discovery-actions">
                <button type="submit" class="btn btn-primary btn-lg">Find nearby help</button>
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary btn-lg']); ?>
            </div>
            <p class="discovery-fallback">No suitable listing? <a href="<?= e(url('request-assistance')) ?>">Register what you need</a> so the request can be matched or used to identify a coverage gap.</p>
        </form>
    </div>
</section>

<section class="evidence-ribbon" aria-label="Current VanAssist directory evidence">
    <div class="container evidence-ribbon-grid">
        <?php if ($directoryCount > 0): ?><div><strong><?= number_format($directoryCount) ?></strong><span>active service listings</span></div><?php endif; ?>
        <?php if ($verifiedCount > 0): ?><div><strong><?= number_format($verifiedCount) ?></strong><span>verified providers</span></div><?php endif; ?>
        <?php if ($townCount > 0): ?><div><strong><?= number_format($townCount) ?></strong><span>provider base towns</span></div><?php endif; ?>
        <?php if ($categoryCount > 0): ?><div><strong><?= number_format($categoryCount) ?></strong><span>service categories</span></div><?php endif; ?>
        <div class="evidence-ribbon-note"><strong>Clear listing labels</strong><span>Verified, featured and unclaimed mean different things</span></div>
    </div>
</section>

<?php if (!empty($freeMessage)): ?>
<section class="launch-note"><div class="container"><div class="alert alert-info mb-0"><?= $this->e($freeMessage) ?></div></div></section>
<?php endif; ?>

<section class="section journey-section" aria-labelledby="choose-path-heading">
    <div class="container">
        <div class="editorial-heading">
            <p class="experience-kicker dark">One platform, three useful paths</p>
            <h2 id="choose-path-heading">Start with what matters now.</h2>
            <p>VanAssist separates urgent service discovery, trip planning and unmatched requests so you can move forward without learning the platform first.</p>
        </div>
        <div class="journey-grid">
            <a class="journey-card journey-card-primary" href="<?= e(url('find')) ?>">
                <span class="journey-number">01</span>
                <div><p>Service discovery</p><h3>Find caravan and RV help</h3><span>Search mobile and workshop providers by service and location.</span></div>
                <strong>Search providers <span aria-hidden="true">→</span></strong>
            </a>
            <a class="journey-card" href="<?= e(url('stays')) ?>">
                <span class="journey-number">02</span>
                <div><p>Trip planning</p><h3>Find somewhere to stay</h3><span>Explore caravan parks, campgrounds, rest areas and low-cost stops.</span></div>
                <strong>Explore stays <span aria-hidden="true">→</span></strong>
            </a>
            <a class="journey-card" href="<?= e(url('request-assistance')) ?>">
                <span class="journey-number">03</span>
                <div><p>Coverage request</p><h3>Tell us what is missing</h3><span>Register a real service need when the right local option is not available.</span></div>
                <strong>Request assistance <span aria-hidden="true">→</span></strong>
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
            <p>VanAssist is a discovery and demand-matching platform. It shows what is known, labels what has been checked, and gives you a path forward when coverage is incomplete.</p>
            <a class="text-link" href="<?= e(url('how-it-works')) ?>">Understand the complete process <span aria-hidden="true">→</span></a>
        </div>
        <ol class="process-list">
            <li><span>01</span><div><h3>Describe the need and location</h3><p>Choose a service and search by town, postcode or your current location.</p></div></li>
            <li><span>02</span><div><h3>Review the listing evidence</h3><p>Compare service model, location and clearly labelled verification or listing status.</p></div></li>
            <li><span>03</span><div><h3>Contact, request or register demand</h3><p>Contact a suitable business, lodge a request, or record a local coverage gap.</p></div></li>
        </ol>
    </div>
</section>

<?php if ($categories !== []): ?>
<section class="section service-index-section" aria-labelledby="services-heading">
    <div class="container">
        <div class="editorial-heading editorial-heading-row">
            <div><p class="experience-kicker dark">Explore by need</p><h2 id="services-heading">Specialist help for life on the road.</h2></div>
            <a class="text-link" href="<?= e(url('services')) ?>">View all services <span aria-hidden="true">→</span></a>
        </div>
        <div class="service-index-grid">
            <?php foreach (array_slice($categories, 0, 8) as $index => $cat): ?>
                <a href="<?= e(url('services/' . $cat['slug'])) ?>"><span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><strong><?= $this->e($cat['name']) ?></strong><i aria-hidden="true">↗</i></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($confirmedRuns !== [] || $formingRuns !== []): ?>
<section class="section run-section" aria-labelledby="runs-heading">
    <div class="container">
        <div class="editorial-heading editorial-heading-row">
            <div><p class="experience-kicker dark">Regional service movement</p><h2 id="runs-heading">Service runs taking shape.</h2></div>
            <a class="text-link" href="<?= e(url('service-runs')) ?>">View all service runs <span aria-hidden="true">→</span></a>
        </div>
        <div class="run-grid">
            <?php foreach (array_slice(array_merge($confirmedRuns, $formingRuns), 0, 4) as $run): ?>
                <?php $isConfirmed = (string) ($run['status'] ?? '') === 'confirmed'; ?>
                <a class="run-card" href="<?= e(url('service-runs/' . $run['slug'])) ?>">
                    <span class="badge <?= $isConfirmed ? 'badge-confirmed' : 'badge-neutral' ?>"><?= $isConfirmed ? 'Confirmed' : 'Forming' ?></span>
                    <h3><?= $this->e($run['title']) ?></h3>
                    <p><?= $this->e($run['business_name']) ?></p>
                    <strong><?= $isConfirmed ? 'View confirmed run' : 'View forming run' ?> <span aria-hidden="true">→</span></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section trust-section" aria-labelledby="trust-heading">
    <div class="container trust-layout">
        <div>
            <p class="experience-kicker">Trust through transparency</p>
            <h2 id="trust-heading">Know what each listing label actually means.</h2>
            <p>We do not present discovered businesses, paid visibility and reviewed provider evidence as though they are the same thing.</p>
        </div>
        <dl class="trust-definitions">
            <div><dt>Verified</dt><dd>Provider evidence has been reviewed for the status displayed. Always confirm suitability for your particular work.</dd></div>
            <div><dt>Featured</dt><dd>A clearly labelled promotional position. It does not replace service or location relevance.</dd></div>
            <div><dt>Unclaimed</dt><dd>Directory information has not yet been controlled by the business. Confirm details directly before relying on them.</dd></div>
        </dl>
    </div>
</section>

<section class="provider-conversion" aria-labelledby="provider-cta-heading">
    <div class="container provider-conversion-layout">
        <div class="provider-conversion-copy">
            <p class="experience-kicker">For Australian RV service businesses</p>
            <h2 id="provider-cta-heading">Turn scattered regional demand into a clearer service opportunity.</h2>
            <p>Build a credible profile, define where you work, review matched requests and plan service runs around demand you can actually assess.</p>
            <div class="provider-conversion-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('for-providers')) ?>">See the provider experience</a>
                <a class="btn btn-glass btn-lg" href="<?= e(url('for-providers/register')) ?>">Register your business</a>
            </div>
        </div>
        <div class="provider-conversion-proof" aria-label="Provider platform capabilities">
            <span>01</span><p><strong>Control your presence</strong>Manage services, coverage, credentials and public information.</p>
            <span>02</span><p><strong>Review real demand</strong>Assess matched requests without a promise of guaranteed work.</p>
            <span>03</span><p><strong>Measure useful activity</strong>See profile interest, contact actions and service outcomes.</p>
        </div>
    </div>
</section>

<?php $this->endSection(); ?>
