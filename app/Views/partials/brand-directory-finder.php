<?php
/** @var array<int,array<string,mixed>> $categories */
/** @var string $heading */
/** @var string $intro */
/** @var string $servicePlaceholder */
/** @var string $submitLabel */
?>
<section class="section product-directory-finder" aria-labelledby="brand-directory-finder-title">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">Search by service and location</span>
            <h2 id="brand-directory-finder-title"><?= $this->e($heading) ?></h2>
            <p><?= $this->e($intro) ?></p>
        </div>
        <form method="get" action="<?= e(url('providers')) ?>" class="directory-search" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" data-auto-location>
            <div class="form-group mb-0">
                <label for="home-directory-query">What do you need?</label>
                <input type="search" id="home-directory-query" name="q" placeholder="<?= e_attr($servicePlaceholder) ?>">
            </div>
            <div class="form-group mb-0 location-field">
                <label for="home-directory-location">Where?</label>
                <input type="text" id="home-directory-location" name="location" placeholder="Town, suburb or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="home-town-suggest">
                <div id="home-town-suggest" class="town-suggest" role="listbox" hidden></div>
                <input type="hidden" name="lat" value="">
                <input type="hidden" name="lng" value="">
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline']); ?>
                <p class="location-status muted" role="status" aria-live="polite" hidden></p>
            </div>
            <div class="form-group mb-0">
                <label for="home-directory-category">Service category</label>
                <select id="home-directory-category" name="category">
                    <option value="">All relevant services</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= $this->e((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="directory-search-actions">
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary']); ?>
                <button type="submit" class="btn btn-primary"><?= $this->e($submitLabel) ?></button>
            </div>
        </form>
        <p class="muted">Directory information may be unclaimed or unverified. Confirm suitability, licensing, availability and current details directly.</p>
    </div>
</section>
