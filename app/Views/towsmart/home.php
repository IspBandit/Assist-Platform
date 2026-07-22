<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>

<section class="product-hero product-hero--towsmart">
    <picture class="product-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" srcset="<?= e(asset('img/towsmart-hero-mobile.webp')) ?>">
        <img src="<?= e(asset('img/towsmart-hero-desktop.webp')) ?>" width="1824" height="864" alt="" fetchpriority="high">
    </picture>
    <div class="product-hero-shade"></div>
    <div class="container product-hero-content">
        <div class="product-hero-copy">
            <span class="product-kicker">Australian towing confidence</span>
            <h1>Know your limits.<br><span>Tow with confidence.</span></h1>
            <p>Turn loaded vehicle and trailer figures into a clear, practical weight check before you travel.</p>
            <div class="product-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('calculator')) ?>">Check my combination</a>
                <?php if (auth()->check()): ?>
                    <a class="btn btn-glass btn-lg" href="<?= e(url('account/towing-combinations')) ?>">View saved checks</a>
                <?php else: ?>
                    <a class="btn btn-glass btn-lg" href="<?= e(url('register')) ?>">Create a free account</a>
                <?php endif; ?>
            </div>
            <ul class="product-proof" aria-label="TowSmart benefits">
                <li>Five critical limits</li><li>Plain-English results</li><li>Built for Australian towing</li>
            </ul>
        </div>
    </div>
</section>

<section class="quick-paths" aria-label="TowSmart quick actions">
    <div class="container quick-paths-grid">
        <a href="<?= e(url('calculator')) ?>"><span class="quick-icon">01</span><span><strong>Run a weight check</strong><small>Enter your loaded figures</small></span></a>
        <a href="<?= e(url('account/towing-combinations')) ?>"><span class="quick-icon">02</span><span><strong>Save combinations</strong><small>Keep checks for later</small></span></a>
        <a href="#understand"><span class="quick-icon">03</span><span><strong>Understand the result</strong><small>See what each limit means</small></span></a>
    </div>
</section>

<section class="section product-section" id="understand">
    <div class="container">
        <div class="section-heading"><span class="product-kicker dark">One check, five limits</span><h2>See the pressure points before they become problems.</h2><p>TowSmart compares one loaded combination against the headline ratings that matter most.</p></div>
        <div class="metric-grid">
            <?php foreach ([
                ['GVM','Vehicle loaded mass','Your vehicle including towball download.'],
                ['GCM','Combined mass','Vehicle and trailer working together.'],
                ['TOW','Braked capacity','The vehicle manufacturer’s towing limit.'],
                ['ATM','Trailer loaded mass','The trailer’s maximum aggregate mass.'],
                ['TBM','Towball download','The load transferred to the tow vehicle.'],
            ] as $metric): ?>
                <article class="metric-card"><span><?= $this->e($metric[0]) ?></span><h3><?= $this->e($metric[1]) ?></h3><p><?= $this->e($metric[2]) ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-ink">
    <div class="container split-feature">
        <div><span class="product-kicker">Designed for real figures</span><h2>Brochure numbers aren’t your travelling weights.</h2><p>Passengers, accessories, fuel, water, tools and towball download all matter. Use compliance-plate limits and current loaded scale readings for a meaningful result.</p></div>
        <ol class="premium-steps"><li><strong>Gather the ratings</strong><span>Use the vehicle and trailer plates and manufacturer information.</span></li><li><strong>Measure the load</strong><span>Use current loaded weights wherever possible.</span></li><li><strong>Review every margin</strong><span>A green result is guidance—not certification.</span></li></ol>
    </div>
</section>

<section class="section product-cta"><div class="container"><div><span class="product-kicker dark">Ready before the road</span><h2>Check your towing combination now.</h2><p>It takes only a few minutes when you have the figures ready.</p></div><a class="btn btn-primary btn-lg" href="<?= e(url('calculator')) ?>">Start the calculator</a></div></section>

<nav class="mobile-action-dock mobile-action-dock--dual" aria-label="TowSmart primary actions"><a href="<?= e(url('calculator')) ?>">Check combination</a><a href="<?= e(url('providers')) ?>">Find a specialist</a></nav>
<?php $this->endSection(); ?>
