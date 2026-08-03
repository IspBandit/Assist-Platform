<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,array<int,array<string,mixed>>> $categoryGroups */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <header class="interior-visual-heading interior-visual-heading--services">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> / Services
        </nav>
        <span class="directory-eyebrow">VanAssist directory</span>
        <h1>Help for the vehicle, caravan and journey.</h1>
        <p class="muted service-directory-intro">Browse repairs, roadside help, travel essentials, fuel, charging, inspections and places for travellers to stop. Choose a category, then add a town or use your current location.</p>
        </header>

        <nav class="service-intent-grid" aria-label="Popular service searches">
            <a data-location-link href="<?= e(url('find')) ?>"><strong>Find any service</strong><span>Search the whole provider directory</span><b aria-hidden="true">&rarr;</b></a>
            <a data-location-link href="<?= e(url('find?category=fuel-and-travel-stops')) ?>"><strong>Fuel stations</strong><span>Fuel and practical travel stops</span><b aria-hidden="true">&rarr;</b></a>
            <a data-location-link href="<?= e(url('find?category=ev-charging')) ?>"><strong>EV charging</strong><span>Charging options for the next leg</span><b aria-hidden="true">&rarr;</b></a>
            <a data-location-link href="<?= e(url('stays')) ?>"><strong>Places to stay</strong><span>Search within your chosen radius</span><b aria-hidden="true">&rarr;</b></a>
        </nav>

        <?php if ($categories === []): ?>
            <p class="muted">Service categories are being added.</p>
        <?php else: ?>
            <?php foreach ($categoryGroups as $groupName => $groupCategories): ?>
                <section class="service-category-group<?= $groupName === 'Travel essentials & places' ? ' service-category-group--featured' : '' ?>" aria-labelledby="service-group-<?= e_attr(md5($groupName)) ?>">
                    <h2 id="service-group-<?= e_attr(md5($groupName)) ?>"><?= $this->e($groupName) ?></h2>
                    <div class="service-directory-grid">
                        <?php foreach ($groupCategories as $cat): ?>
                            <a class="card service-directory-card" data-location-link href="<?= e(url('services/' . $cat['slug'])) ?>">
                                <h3><?= $this->e((string) $cat['name']) ?></h3>
                                <?php if (!empty($cat['short_description'])): ?>
                                    <p class="muted mb-0"><?= $this->e((string) $cat['short_description']) ?></p>
                                <?php endif; ?>
                                <span class="service-directory-arrow" aria-hidden="true">&rarr;</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="section section-sand text-center">
    <div class="container">
        <h2>Not sure which service you need?</h2>
        <p class="muted">Describe the problem and VanAssist will help match it to the right specialist.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
    </div>
</section>
<?php $this->endSection(); ?>
