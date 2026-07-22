<?php
/** @var array<int,array<string,mixed>> $stays */
/** @var array<string,string> $stayTypes */
/** @var array<string,string> $priceTypes */
$this->extend('layouts.public');
$facilityLabels = [
    'powered_sites' => 'Powered sites', 'unpowered_sites' => 'Unpowered sites',
    'toilets' => 'Toilets', 'showers' => 'Showers', 'potable_water' => 'Drinking water',
    'dump_point' => 'Dump point', 'pets_allowed' => 'Pets considered',
];
?>
<?php $this->section('content'); ?>
<section class="hero hero-compact stay-hero">
    <div class="container">
        <div class="eyebrow">VanAssist stays</div>
        <h1>Getting tired? Find a place to stay.</h1>
        <p>Search nearby caravan parks, campgrounds, showgrounds and free or low-cost stays. Always confirm access, fees and restrictions before arrival.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="search-card" method="get" action="<?= e(url('stays')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
            <div class="grid grid-3">
                <div class="form-group mb-0 location-field">
                    <label for="town_search">Town, suburb or postcode</label>
                    <input id="town_search" name="location" value="<?= e_attr((string) $selectedLocation) ?>" placeholder="Start typing a town or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                    <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                    <input type="hidden" id="town_id" name="town_id" value="<?= e_attr((string) ($selectedTownId ?? '')) ?>">
                    <input type="hidden" name="lat" value="<?= e_attr((string) ($_GET['lat'] ?? '')) ?>">
                    <input type="hidden" name="lng" value="<?= e_attr((string) ($_GET['lng'] ?? '')) ?>">
                    <button class="btn btn-secondary use-location-inline" type="button" data-use-location data-select-target="#town_id" hidden>Use my current location</button>
                    <p class="location-status muted" role="status" aria-live="polite" hidden></p>
                </div>
                <div class="form-group mb-0">
                    <label for="stay_type">Stay type</label>
                    <select id="stay_type" name="stay_type"><option value="">Any place to stay</option><?php foreach ($stayTypes as $value => $label): ?><option value="<?= e_attr($value) ?>" <?= $selectedStayType === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group mb-0">
                    <label for="price_type">Cost</label>
                    <select id="price_type" name="price_type"><option value="">Any cost</option><?php foreach ($priceTypes as $value => $label): ?><option value="<?= e_attr($value) ?>" <?= $selectedPriceType === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="actions" style="margin-top:1rem"><button class="btn btn-primary btn-lg" type="submit">Find places to stay</button><a class="btn btn-ghost" href="<?= e(url('stays')) ?>">Clear</a></div>
        </form>

        <div class="section-heading" style="margin-top:2rem"><h2><?= $searched ? count($stays) . ' matching places' : 'Places to stay around Australia' ?></h2><p>Community and operator details can change. Listings show their source and verification status so you can check before travelling.</p></div>
        <?php if ($stays === []): ?>
            <div class="empty-state"><h3>No matching stays found yet</h3><p>Try a nearby larger town or remove a filter. Park operators can add or claim their listing.</p><a class="btn btn-primary" href="<?= e(url('caravan-parks/apply')) ?>">List a park or campground</a></div>
        <?php else: ?>
            <div class="grid grid-3 stay-grid">
                <?php foreach ($stays as $stay): ?>
                    <article class="card stay-card">
                        <div class="badge-row">
                            <?php if (!empty($stay['is_featured'])): ?><span class="badge badge-sponsored">Sponsored</span><?php endif; ?>
                            <span class="badge badge-neutral"><?= $this->e($stayTypes[$stay['stay_type']] ?? 'Place to stay') ?></span>
                            <span class="badge <?= $stay['price_type'] === 'free' ? 'badge-verified' : 'badge-neutral' ?>"><?= $this->e($priceTypes[$stay['price_type']] ?? 'Check cost') ?></span>
                        </div>
                        <h3><a href="<?= e(url('caravan-parks/' . $stay['slug'])) ?>"><?= $this->e((string) $stay['name']) ?></a></h3>
                        <p class="muted"><?php if ($stay['distance_km'] !== null): ?><?= number_format((float) $stay['distance_km'], 1) ?> km away · <?php endif; ?><?= $this->e(trim((string) ($stay['town_name'] ?? '') . (!empty($stay['state_abbr']) ? ' / ' . $stay['state_abbr'] : ''))) ?></p>
                        <?php $facilities = []; foreach ($facilityLabels as $key => $label) { if ((int) ($stay[$key] ?? 0) === 1) { $facilities[] = $label; } } ?>
                        <?php if ($facilities !== []): ?><p><?= $this->e(implode(' · ', array_slice($facilities, 0, 5))) ?></p><?php endif; ?>
                        <?php if (!empty($stay['max_stay'])): ?><p><strong>Stay limit:</strong> <?= $this->e((string) $stay['max_stay']) ?></p><?php endif; ?>
                        <p class="muted small"><?= !empty($stay['verified_at']) ? 'Operator verified' : 'Unverified directory listing—confirm details before arrival' ?></p>
                        <div class="actions"><a class="btn btn-secondary" href="<?= e(url('caravan-parks/' . $stay['slug'])) ?>">View details</a><?php if (!empty($stay['booking_url']) || !empty($stay['website'])): ?><a class="btn btn-ghost" href="<?= e_attr((string) ($stay['booking_url'] ?: $stay['website'])) ?>" target="_blank" rel="noopener noreferrer">Website</a><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
