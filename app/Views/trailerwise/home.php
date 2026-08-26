<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>

<section class="product-hero product-hero--trailerwise">
    <picture class="product-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" type="image/avif" srcset="<?= e(asset('img/trailerwise-hero-mobile.avif')) ?>">
        <source media="(max-width: 719px)" type="image/webp" srcset="<?= e(asset('img/trailerwise-hero-mobile.webp')) ?>">
        <source type="image/avif" srcset="<?= e(asset('img/trailerwise-hero-desktop.avif')) ?>">
        <img src="<?= e(asset('img/trailerwise-hero-desktop.webp')) ?>" width="1824" height="864" alt="" fetchpriority="high">
    </picture>
    <div class="product-hero-shade"></div>
    <div class="container product-hero-content">
        <div class="product-hero-copy">
            <span class="product-kicker">Australian trailer expertise</span>
            <h1>The right trailer specialist.<br><span>Without the guesswork.</span></h1>
            <p>Find repairers, service centres, parts, inspections, certifiers and trusted trailer businesses across Australia.</p>
            <div class="product-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('providers')) ?>">Find trailer services</a>
                <a class="btn btn-glass btn-lg" href="<?= e(url('for-providers')) ?>">List your business</a>
            </div>
            <ul class="product-proof"><li>All trailer types</li><li>Local and mobile specialists</li><li>Business details to verify</li></ul>
        </div>
    </div>
</section>

<section class="quick-paths" aria-label="TrailerWise quick actions">
    <div class="container quick-paths-grid">
        <a href="<?= e(url('providers')) ?>"><span class="quick-icon">01</span><span><strong>Find a specialist</strong><small>Repair, service and compliance</small></span></a>
        <a href="<?= e(url('services')) ?>"><span class="quick-icon">02</span><span><strong>Browse categories</strong><small>Start with the work you need</small></span></a>
        <a href="<?= e(url('marketplace')) ?>"><span class="quick-icon">03</span><span><strong>Sale and hire</strong><small>Secondary marketplace listings</small></span></a>
    </div>
</section>

<section class="section product-section" aria-labelledby="trailer-journeys-title"><div class="container"><div class="section-heading"><span class="product-kicker dark">Start with the job</span><h2 id="trailer-journeys-title">A direct path for common trailer needs.</h2><p>These routes use TrailerWise's curated categories and the same brand-scoped provider directory.</p></div><div class="service-tile-grid">
    <a class="service-tile service-tile-link" href="<?= e(url('services/trailer-repairs')) ?>"><span aria-hidden="true">→</span><h3>Repair or service</h3><p>Workshop and general trailer servicing.</p></a>
    <a class="service-tile service-tile-link" href="<?= e(url('services/mobile-trailer-services')) ?>"><span aria-hidden="true">→</span><h3>Mobile help</h3><p>On-site and roadside trailer specialists.</p></a>
    <a class="service-tile service-tile-link" href="<?= e(url('services/parts-accessories')) ?>"><span aria-hidden="true">→</span><h3>Parts and accessories</h3><p>Replacement components and upgrades.</p></a>
    <a class="service-tile service-tile-link" href="<?= e(url('services/roadworthy-inspections')) ?>"><span aria-hidden="true">→</span><h3>Inspection or certification</h3><p>Roadworthy, safety and compliance services.</p></a>
    <a class="service-tile service-tile-link" href="<?= e(url('services/manufacturers-dealers')) ?>"><span aria-hidden="true">→</span><h3>Manufacturer or dealer</h3><p>Builders, dealers and authorised support.</p></a>
    <a class="service-tile service-tile-link" href="<?= e(url('services/fabrication-engineering')) ?>"><span aria-hidden="true">→</span><h3>Fabrication or engineering</h3><p>Chassis, welding and modification work.</p></a>
</div></div></section>

<?php $this->include('partials.brand-directory-finder', [
    'categories' => $categories,
    'heading' => 'Find the right trailer service nearby.',
    'intro' => 'Search repairers, inspectors, parts, tyres, brakes, electrical, fabrication, manufacturers and mobile services by location.',
    'servicePlaceholder' => 'e.g. trailer bearings or mobile repairs',
    'submitLabel' => 'Find trailer services',
]); ?>

<section class="section product-section" id="services">
    <div class="container">
        <div class="section-heading"><span class="product-kicker dark">Everything trailer-related</span><h2>Start with the help you need.</h2><p>Search by service category, then confirm suitability and current details directly with each business.</p></div>
        <?php if (!empty($categories)): ?>
        <div class="service-tile-grid">
            <?php foreach ($categories as $category): ?>
                <a class="service-tile service-tile-link" href="<?= e(url('services/' . $category['slug'])) ?>">
                    <span aria-hidden="true">→</span>
                    <h3><?= $this->e((string) $category['name']) ?></h3>
                    <p><?= $this->e((string) ($category['description'] ?? '')) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:1.5rem"><a class="btn btn-primary" href="<?= e(url('providers')) ?>">Search with your location</a></p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-ink" id="trailer-types"><div class="container split-feature"><div><span class="product-kicker">Built for more than one kind of trailer</span><h2>From the worksite to the weekend.</h2><p>Use the marketplace to browse sale and hire listings for box, boat, car, camper, caravan, horse float, plant, tipper and commercial trailers.</p><p><a class="btn btn-ghost" href="<?= e(url('marketplace')) ?>">Browse marketplace listings</a></p></div><div class="type-cloud" aria-label="Trailer types"><span>Box</span><span>Boat</span><span>Car</span><span>Camper</span><span>Caravan</span><span>Horse float</span><span>Plant</span><span>Tipper</span><span>Commercial</span></div></div></section>

<?php if (!empty($listings)): ?>
<section class="section secondary-market"><div class="container"><div class="section-heading compact"><span class="product-kicker dark">Secondary marketplace</span><h2>Trailers currently listed</h2><p>Sales and hire are an additional feature, separate from our core service directory.</p></div><div class="grid grid-3"><?php foreach (array_slice($listings, 0, 3) as $listing): ?><article class="card"><span class="badge badge-neutral"><?= $this->e(ucwords(str_replace('_',' ',$listing['trailer_type']))) ?></span><h3><a href="<?= e(url('trailers/'.$listing['slug'])) ?>"><?= $this->e($listing['title']) ?></a></h3><p><?= $this->e($listing['business_name']) ?></p></article><?php endforeach; ?></div><p><a class="btn btn-ghost" href="<?= e(url('marketplace')) ?>">View sale and hire listings</a></p></div></section>
<?php endif; ?>

<section class="section product-cta"><div class="container"><div><span class="product-kicker dark">Know the trailer industry?</span><h2>Help people find your business.</h2><p>Repairers, suppliers, inspectors, manufacturers and specialist trades can register interest now.</p></div><a class="btn btn-primary btn-lg" href="<?= e(url('for-providers')) ?>">Register a business</a></div></section>
<?php $this->endSection(); ?>
