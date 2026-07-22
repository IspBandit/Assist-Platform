<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>

<section class="product-hero product-hero--trailerwise">
    <picture class="product-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" srcset="<?= e(asset('img/trailerwise-hero-mobile.webp')) ?>">
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
        <a href="#trailer-types"><span class="quick-icon">02</span><span><strong>Browse by trailer</strong><small>Commercial and recreational</small></span></a>
        <a href="<?= e(url('for-providers')) ?>"><span class="quick-icon">03</span><span><strong>For businesses</strong><small>Claim or create your profile</small></span></a>
    </div>
</section>

<section class="section product-section" id="services">
    <div class="container">
        <div class="section-heading"><span class="product-kicker dark">Everything trailer-related</span><h2>Start with the help you need.</h2><p>TrailerWise is being built around services and trusted businesses—not just trailers advertised for sale.</p></div>
        <div class="service-tile-grid">
            <?php foreach ([
                ['Repairs & servicing','General repairs, preventative maintenance and mobile help.'],
                ['Roadworthy & inspections','Approved inspections, safety certificates and compliance support.'],
                ['Tyres, wheels & bearings','Tyre shops, hubs, bearings, balancing and roadside tyre help.'],
                ['Brakes, axles & suspension','Electric brakes, controllers, axles, springs and upgrades.'],
                ['Auto electrical','Lighting, plugs, wiring, batteries, breakaway systems and diagnostics.'],
                ['Fabrication & engineering','Welding, chassis work, modifications and specialist fabrication.'],
                ['Parts & accessories','Local and national suppliers for trailer components and upgrades.'],
                ['Manufacturers & dealers','Trailer builders, authorised dealers and product support.'],
            ] as $service): ?><article class="service-tile"><span aria-hidden="true">✓</span><h3><?= $this->e($service[0]) ?></h3><p><?= $this->e($service[1]) ?></p></article><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-ink" id="trailer-types"><div class="container split-feature"><div><span class="product-kicker">Built for more than one kind of trailer</span><h2>From the worksite to the weekend.</h2><p>Search and provider relevance will cover box, boat, car, camper, caravan, horse float, plant, tipper and specialist commercial trailers.</p></div><div class="type-cloud" aria-label="Trailer types"><span>Box</span><span>Boat</span><span>Car</span><span>Camper</span><span>Caravan</span><span>Horse float</span><span>Plant</span><span>Tipper</span><span>Commercial</span></div></div></section>

<?php if (!empty($listings)): ?>
<section class="section secondary-market"><div class="container"><div class="section-heading compact"><span class="product-kicker dark">Secondary marketplace</span><h2>Trailers currently listed</h2><p>Sales and hire are an additional feature, separate from our core service directory.</p></div><div class="grid grid-3"><?php foreach (array_slice($listings, 0, 3) as $listing): ?><article class="card"><span class="badge badge-neutral"><?= $this->e(ucwords(str_replace('_',' ',$listing['trailer_type']))) ?></span><h3><a href="<?= e(url('trailers/'.$listing['slug'])) ?>"><?= $this->e($listing['title']) ?></a></h3><p><?= $this->e($listing['business_name']) ?></p></article><?php endforeach; ?></div><p><a class="btn btn-ghost" href="<?= e(url('marketplace')) ?>">View sale and hire listings</a></p></div></section>
<?php endif; ?>

<section class="section product-cta"><div class="container"><div><span class="product-kicker dark">Know the trailer industry?</span><h2>Help people find your business.</h2><p>Repairers, suppliers, inspectors, manufacturers and specialist trades can register interest now.</p></div><a class="btn btn-primary btn-lg" href="<?= e(url('for-providers')) ?>">Register a business</a></div></section>
<nav class="mobile-action-dock" aria-label="TrailerWise primary action"><a href="<?= e(url('providers')) ?>">Find trailer services</a></nav>
<?php $this->endSection(); ?>
