<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>

<section class="product-hero product-hero--localtorque">
    <div class="product-hero-shade"></div>
    <div class="container product-hero-content">
        <div class="product-hero-copy">
            <span class="product-kicker">Australian automotive directory</span>
            <h1>Find the right workshop.<br><span>Close to home.</span></h1>
            <p>Search mechanics, mobile repairers, auto electricians, tyre shops, inspectors and specialist automotive businesses by location and category.</p>
            <form class="hero-search" method="get" action="<?= e(url('providers')) ?>">
                <label class="sr-only" for="localtorque-business">Business or service</label>
                <input id="localtorque-business" name="q" type="search" placeholder="Business or service" autocomplete="off">
                <label class="sr-only" for="localtorque-location">Town, suburb or postcode</label>
                <span class="location-field">
                    <input id="localtorque-location" type="search" placeholder="Town / State or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                    <input type="hidden" id="town_id" name="town" value="">
                    <span id="town-suggest" class="town-suggest" role="listbox" hidden></span>
                </span>
                <button class="btn btn-light btn-lg" type="submit">Search businesses</button>
            </form>
            <ul class="product-proof"><li>Workshop and mobile services</li><li>Data-driven specialist categories</li><li>Claimable business profiles</li></ul>
        </div>
    </div>
</section>

<section class="quick-paths" aria-label="LocalTorque quick actions">
    <div class="container quick-paths-grid">
        <a href="<?= e(url('providers')) ?>"><span class="quick-icon">01</span><span><strong>Find a workshop</strong><small>Search by business, service or location</small></span></a>
        <a href="<?= e(url('services')) ?>"><span class="quick-icon">02</span><span><strong>Browse specialists</strong><small>From mechanics to vehicle inspections</small></span></a>
        <a href="<?= e(url('for-providers')) ?>"><span class="quick-icon">03</span><span><strong>List your business</strong><small>Claim or create a shared provider profile</small></span></a>
    </div>
</section>

<section class="section product-section">
    <div class="container">
        <div class="section-heading"><span class="product-kicker dark">Automotive help by specialty</span><h2>Start with the work you need.</h2><p>LocalTorque uses the Assist Platform's shared provider, location, verification, review and membership foundations.</p></div>
        <div class="service-tile-grid">
            <?php foreach ([
                ['Mechanics & mobile mechanics','General servicing, diagnosis and repairs at a workshop or your location.'],
                ['Auto electrical & batteries','Electrical diagnosis, wiring, charging, batteries and accessories.'],
                ['Tyres, wheels & alignment','Tyres, punctures, balancing, alignment and wheel services.'],
                ['Brakes, suspension & steering','Safety-critical chassis, handling and braking specialists.'],
                ['Diesel & driveline','Diesel, transmission, differential and engine specialists.'],
                ['4WD & accessories','Touring preparation, suspension lifts, bullbars, canopies and accessories.'],
                ['Inspections & roadworthy','Pre-purchase, safety and registration inspection providers.'],
                ['Body, fabrication & paint','Panel, rust, welding, fabrication and refinishing services.'],
            ] as $service): ?><article class="service-tile"><span aria-hidden="true">✓</span><h3><?= $this->e($service[0]) ?></h3><p><?= $this->e($service[1]) ?></p></article><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section product-cta"><div class="container"><div><span class="product-kicker dark">Run an automotive business?</span><h2>Be found across the Assist Platform.</h2><p>One provider identity can power relevant listings on LocalTorque, VanAssist, TowSmart and TrailerWise without duplicated business records.</p></div><a class="btn btn-primary btn-lg" href="<?= e(url('for-providers')) ?>">List or claim a business</a></div></section>
<nav class="mobile-action-dock" aria-label="LocalTorque primary action"><a href="<?= e(url('providers')) ?>">Find automotive help</a></nav>
<?php $this->endSection(); ?>
