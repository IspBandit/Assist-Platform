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
$mappedStays=[];
foreach ($stays as $stay) {
    if (!is_numeric($stay['latitude']??null)||!is_numeric($stay['longitude']??null)) continue;
    $id='stay-result-'.(int)$stay['id']; $destination=map_destination($stay['latitude'],$stay['longitude'],[]);
    $mappedStays[]=['id'=>$id,'listId'=>$id,'number'=>count($mappedStays)+1,'name'=>(string)$stay['name'],'location'=>trim((string)($stay['town_name']??'').(!empty($stay['state_abbr'])?', '.$stay['state_abbr']:'')),'lat'=>(float)$stay['latitude'],'lng'=>(float)$stay['longitude'],'profile'=>url('caravan-parks/'.$stay['slug']),'directions'=>$destination!==''?map_directions_url($destination):'','destination'=>$destination,'featured'=>!empty($stay['is_featured']),'possible'=>false];
}
?>
<?php $this->section('content'); ?>
<section class="hero hero-compact stay-hero interior-photo-hero interior-photo-hero--stays">
    <div class="container">
        <div class="eyebrow">VanAssist stays</div>
        <h1>Getting tired? Find a place to stay.</h1>
        <p>Search nearby caravan parks, campgrounds, national-park camping, showgrounds, permitted rest areas and free or low-cost caravan stays. Hotels and motels are not included. Always confirm access, fees and restrictions before arrival.</p>
        <ul class="page-trust-list" aria-label="Search information">
            <li>Choose up to 150 km by default</li>
            <li>Unverified listings are identified</li>
            <li>Directions open in your maps service</li>
        </ul>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="search-card" method="get" action="<?= e(url('stays')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" data-auto-location>
            <div class="grid grid-4">
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
                <div class="form-group mb-0">
                    <label for="distance">Maximum straight-line radius</label>
                    <select id="distance" name="distance"><?php foreach ($distanceOptions as $km): ?><option value="<?= (int) $km ?>" <?= $selectedDistance === $km ? 'selected' : '' ?>>Within <?= (int) $km ?> km radius</option><?php endforeach; ?></select>
                    <p class="muted small">Defaults to 150 km from your chosen location.</p>
                </div>
            </div>
            <div class="actions" style="margin-top:1rem"><button class="btn btn-primary btn-lg" type="submit">Find places to stay</button><a class="btn btn-ghost" href="<?= e(url('stays')) ?>">Clear</a></div>
        </form>

        <?php $this->include('partials.listing-accuracy-notice'); ?>

        <div class="section-heading" style="margin-top:2rem"><h2><?= $searched ? count($stays) . ' places within a ' . (int) $selectedDistance . ' km radius' : 'Choose where you need to stop' ?></h2><p><?= $hasOrigin ? 'Radius filtering is a straight-line estimate. Use Directions for the current road route and driving distance. Community and operator details can change.' : 'Enter a town, suburb or postcode, or use your current location. VanAssist will show stays within the selected radius.' ?></p></div>
        <?php if ($stays === []): ?>
            <div class="empty-state"><h3><?= $hasOrigin ? 'No matching stays found within ' . (int) $selectedDistance . ' km' : 'Start with your location' ?></h3><p><?= $hasOrigin ? 'Try a larger distance or remove a stay-type or cost filter. Park operators can add or claim their listing.' : 'This prevents distant, irrelevant places from appearing before VanAssist knows where you are travelling.' ?></p><?php if ($hasOrigin): ?><a class="btn btn-primary" href="<?= e(url('caravan-parks/apply')) ?>">List a park or campground</a><?php endif; ?></div>
        <?php else: ?>
            <?php $origin=is_numeric($_GET['lat']??null)&&is_numeric($_GET['lng']??null)?['lat'=>(float)$_GET['lat'],'lng'=>(float)$_GET['lng']]:null; $this->include('partials/results-map',['mapItems'=>$mappedStays,'mapOrigin'=>$origin,'mapTitle'=>count($mappedStays).' located places to stay']); ?>
            <div class="provider-card-grid stay-grid">
                <?php foreach ($stays as $stay): ?>
                    <?php $stayId='stay-result-'.(int)$stay['id']; $mapIndex=array_search($stayId,array_column($mappedStays,'id'),true); ?>
                    <?php $mapDestination = map_destination($stay['latitude'] ?? null, $stay['longitude'] ?? null, [$stay['address'] ?? '', $stay['town_name'] ?? '', $stay['state_abbr'] ?? '']); ?>
                    <article id="<?= e_attr($stayId) ?>" class="provider-card provider-card--compact stay-card" tabindex="-1">
                        <div class="badge-row">
                            <?php if (!empty($stay['is_featured'])): ?><span class="badge badge-sponsored">Sponsored</span><?php endif; ?>
                            <span class="badge badge-neutral"><?= $this->e($stayTypes[$stay['stay_type']] ?? 'Place to stay') ?></span>
                            <span class="badge <?= $stay['price_type'] === 'free' ? 'badge-verified' : 'badge-neutral' ?>"><?= $this->e($priceTypes[$stay['price_type']] ?? 'Check cost') ?></span>
                        </div>
                        <h3><?php if ($mapIndex!==false): ?><span class="provider-map-reference" data-number="<?= $mapIndex+1 ?>" aria-label="Map pin <?= $mapIndex+1 ?>"></span><?php endif; ?><a href="<?= e(url('caravan-parks/' . $stay['slug'])) ?>"><?= $this->e((string) $stay['name']) ?></a></h3>
                        <p class="muted"><?php if ($stay['distance_km'] !== null): ?><?= number_format((float) $stay['distance_km'], 1) ?> km straight-line · <?php endif; ?><?= $this->e(trim((string) ($stay['town_name'] ?? '') . (!empty($stay['state_abbr']) ? ' / ' . $stay['state_abbr'] : ''))) ?></p>
                        <?php $facilities = []; foreach ($facilityLabels as $key => $label) { if ((int) ($stay[$key] ?? 0) === 1) { $facilities[] = $label; } } ?>
                        <?php if ($facilities !== []): ?><p class="muted small"><?= $this->e(implode(' · ', array_slice($facilities, 0, 3))) ?></p><?php endif; ?>
                        <?php if (!empty($stay['max_stay'])): ?><p><strong>Stay limit:</strong> <?= $this->e((string) $stay['max_stay']) ?></p><?php endif; ?>
                        <p class="muted small"><?= !empty($stay['verified_at']) ? 'Operator verified' : 'Unverified directory listing—confirm details before arrival' ?></p>
                        <div class="actions"><a class="btn btn-secondary" href="<?= e(url('caravan-parks/' . $stay['slug'])) ?>">View details</a><?php if ($mapDestination !== ''): ?><a class="btn btn-ghost" href="<?= e(map_directions_url($mapDestination)) ?>" data-map-directions data-map-destination="<?= e_attr($mapDestination) ?>" target="_blank" rel="noopener noreferrer">Directions</a><?php endif; ?><?php if (!empty($stay['booking_url']) || !empty($stay['website'])): ?><a class="btn btn-ghost" href="<?= e_attr((string) ($stay['booking_url'] ?: $stay['website'])) ?>" target="_blank" rel="noopener noreferrer">Website</a><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-sand stay-provider-cta">
    <div class="container provider-conversion-inner">
        <div><span class="directory-eyebrow">Park or campground operator?</span><h2>Keep your stay information accurate.</h2><p>Claim or add a listing so travellers can confirm facilities, access, contact details and current restrictions before arrival.</p></div>
        <a class="btn btn-primary" href="<?= e(url('caravan-parks/apply')) ?>">Claim or list a place to stay</a>
    </div>
</section>
<?php $this->endSection(); ?>
