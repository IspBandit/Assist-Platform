<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <header class="section-heading"><span class="product-kicker dark">TowSmart knowledge centre</span><h1>Understand the numbers before you tow.</h1><p>Plain-English definitions, calculation logic and authoritative links for Australian towing. Use this guidance with the ratings for your exact vehicle and trailer.</p></header>
    <div class="quick-paths-grid card" style="margin-bottom:2rem"><a href="#definitions"><span class="quick-icon">01</span><span><strong>Definitions</strong><small>ATM, GTM, GVM, GCM and TBM</small></span></a><a href="#calculations"><span class="quick-icon">02</span><span><strong>How calculations work</strong><small>What TowSmart estimates and compares</small></span></a><a href="#rules"><span class="quick-icon">03</span><span><strong>Rules and sources</strong><small>Check current official requirements</small></span></a></div>

    <section id="definitions" class="section"><div class="section-heading"><h2>Towing definitions</h2></div><div class="metric-grid">
    <?php foreach ([
        ['ATM','Aggregate Trailer Mass','The trailer’s maximum permitted mass when standing on its wheels and jockey wheel, uncoupled from the tow vehicle.'],
        ['GTM','Gross Trailer Mass','The maximum mass carried by the trailer wheels when coupled; towball load is carried by the vehicle.'],
        ['Tare','Tare mass','The specified unladen mass. Included fuel, water, gas and accessories can vary—read the applicable definition and plate.'],
        ['Payload','Available payload','The difference between a rated maximum mass and the relevant base or current mass.'],
        ['GVM','Gross Vehicle Mass','The maximum permitted loaded mass of the tow vehicle, including towball download.'],
        ['GCM','Gross Combination Mass','The maximum permitted combined mass specified for the tow vehicle and trailer combination.'],
        ['TBM','Towball Mass','The downward load transferred from the coupled trailer to the tow vehicle.'],
        ['Axles','Axle loads','The mass carried by each vehicle axle or trailer axle group; these limits still matter when GVM is acceptable.'],
    ] as $item): ?><article class="metric-card"><span><?= $this->e($item[0]) ?></span><h3><?= $this->e($item[1]) ?></h3><p><?= $this->e($item[2]) ?></p></article><?php endforeach; ?>
    </div></section>

    <section id="calculations" class="section section-ink"><div class="container split-feature"><div><span class="product-kicker">Calculation explanation</span><h2>TowSmart builds a loaded estimate.</h2><p>It adds passengers, cargo, accessories, fuel and towball download to the vehicle; and cargo, accessories and water to the trailer. It then compares the result with the ratings entered or selected.</p></div><ol class="premium-steps"><li><strong>Select or enter specifications</strong><span>Catalogue values are reference data until confirmed for the exact variant.</span></li><li><strong>Configure the travelling load</strong><span>Water and accessory positions inform the estimated towball effect.</span></li><li><strong>Review every margin</strong><span>A result within the entered limits is not certification and cannot account for every modification or legal requirement.</span></li></ol></div></section>

    <section id="rules" class="section"><div class="section-heading"><h2>Regulations and authoritative sources</h2><p>Towing requirements change and differ by jurisdiction. TowSmart does not replace a road authority, manufacturer, licensed weighbridge or qualified engineer.</p></div><div class="grid grid-3">
        <?php foreach ([
            ['National vehicle standards','https://www.infrastructure.gov.au/infrastructure-transport-vehicles/vehicles/vehicle-design-regulation'],
            ['Queensland towing guidance','https://www.qld.gov.au/transport/vehicle-safety/towing/towing-vehicles-and-trailers'],
            ['NSW towing guidance','https://www.nsw.gov.au/driving-boating-and-transport/roads-safety-and-rules/vehicle-safety-and-compliance/towing-a-caravan'],
            ['Victoria towing guidance','https://transport.vic.gov.au/road-rules-and-safety/caravans-and-towing'],
            ['South Australia towing','https://www.sa.gov.au/topics/driving-and-transport/vehicles/vehicle-standards-and-modifications/towing'],
            ['Western Australia towing','https://transport.wa.gov.au/licensing/vehicle/safety-standards-security/towing'],
        ] as $source): ?><a class="card" href="<?= e_attr($source[1]) ?>" rel="noopener noreferrer" target="_blank"><strong><?= $this->e($source[0]) ?></strong><p class="muted">Open official guidance ↗</p></a><?php endforeach; ?>
    </div></section>
    <section class="product-cta"><div class="container"><div><h2>Ready to build your combination?</h2><p>Select your actual vehicle and trailer, then add the load you carry.</p></div><a class="btn btn-primary btn-lg" href="<?= e(url('calculator')) ?>">Open calculator</a></div></section>
</div></section>
<?php $this->endSection(); ?>
