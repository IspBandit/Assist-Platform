<?php
/** @var \App\Core\View $this */
/** @var string $heading */
/** @var string $location */
/** @var string $categorySlug */
/** @var array<string,mixed>|null $category */
/** @var array<string,mixed>|null $town */
/** @var array<int,array<string,mixed>> $alternatives */
/** @var bool $locationNotFound */
/** @var array<int,array<string,mixed>> $matches */
/** @var array<int,array<string,mixed>> $possible */
/** @var string $requestUrl */
/** @var int|null $searchId */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,array<int,array<string,mixed>>> $categoryGroups */
/** @var float|null $lat */
/** @var float|null $lng */
/** @var string $timeframe */
/** @var bool $usedLocation */
/** @var array<int,array<string,mixed>> $nearbyRuns */
/** @var int|null $maxDistance */
/** @var string|null $distanceScope */
/** @var string|int|null $distanceSelection */
/** @var bool $hasOrigin */
/** @var string|null $originLabel */
$this->extend('layouts.public');
$featuredMatches = array_values(array_filter($matches, static fn (array $provider): bool => !empty($provider['is_featured'])));
$organicMatches = array_values(array_filter($matches, static fn (array $provider): bool => empty($provider['is_featured'])));
$allResults = array_values(array_merge($featuredMatches, $organicMatches, $possible));
$stayDistance = in_array((int) ($maxDistance ?? 0), \App\Helpers\Geo::STAY_DISTANCE_OPTIONS, true) ? (int) $maxDistance : 150;
$stayUrl = url('stays?' . http_build_query(array_filter([
    'location' => $location !== '' ? $location : null,
    'lat' => isset($lat) && $lat !== null ? (string) $lat : null,
    'lng' => isset($lng) && $lng !== null ? (string) $lng : null,
    'distance' => (string) $stayDistance,
], static fn ($value): bool => $value !== null && $value !== '')));
$mappedResults = [];
foreach ($allResults as $provider) {
    $providerLat = isset($provider['town_lat']) && is_numeric($provider['town_lat']) ? (float) $provider['town_lat'] : null;
    $providerLng = isset($provider['town_lng']) && is_numeric($provider['town_lng']) ? (float) $provider['town_lng'] : null;
    if ($providerLat === null || $providerLng === null || $providerLat < -90 || $providerLat > 90 || $providerLng < -180 || $providerLng > 180) {
        continue;
    }
    $providerModel = (string) ($provider['service_model'] ?? '');
    $providerDestination = in_array($providerModel, ['workshop', 'both'], true) && is_navigable_street_address($provider['street_address'] ?? '')
        ? map_destination(null, null, [$provider['street_address'] ?? '', $provider['town_name'] ?? '', $provider['state_abbr'] ?? ''])
        : '';
    $mappedResults[] = [
        'id' => (int) ($provider['id'] ?? 0),
        'name' => (string) ($provider['business_name'] ?? 'Provider'),
        'lat' => $providerLat,
        'lng' => $providerLng,
        'location' => trim((string) ($provider['town_name'] ?? '') . (!empty($provider['state_abbr']) ? ', ' . $provider['state_abbr'] : '')),
        'possible' => (int) ($provider['is_inferred'] ?? 0) === 1,
        'featured' => !empty($provider['is_featured']),
        'profile' => url('providers/' . (string) ($provider['slug'] ?? '')) . ($searchId ? '?s=' . (int) $searchId : ''),
        'directions' => $providerDestination !== '' ? url('go/directions/' . (string) ($provider['slug'] ?? '')) . ($searchId ? '?s=' . (int) $searchId : '') : null,
        'destination' => $providerDestination !== '' ? $providerDestination : null,
    ];
}
?>
<?php $this->section('head'); ?>
<?php if ($mappedResults !== []): ?><link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<section class="section search-results-page">
    <div class="container">
        <span class="directory-eyebrow">VanAssist search</span>
        <h1 class="results-heading"><?= $this->e($heading) ?></h1>
        <?php if (!empty($usedLocation) && $town !== null): ?>
            <p class="muted" style="margin:0 0 .5rem">Showing results near your current location — closest area: <strong><?= $this->e((string) $town['name']) ?><?= !empty($town['state_abbr']) ? ', ' . $this->e((string) $town['state_abbr']) : '' ?></strong>.</p>
        <?php elseif (!empty($hasOrigin) && !empty($originLabel)): ?>
            <?php if (($distanceScope ?? '') === 'town' && $town !== null): ?>
                <p class="muted" style="margin:0 0 .5rem">Showing providers in and serving <strong><?= $this->e((string) $town['name']) ?><?= !empty($town['state_abbr']) ? ', ' . $this->e((string) $town['state_abbr']) : '' ?></strong>, sorted by distance from <strong><?= $this->e((string) $originLabel) ?></strong>.</p>
            <?php else: ?>
                <p class="muted" style="margin:0 0 .5rem">Sorted by approximate distance from <strong><?= $this->e((string) $originLabel) ?></strong><?= !empty($maxDistance) ? ' (within ' . (int) $maxDistance . ' km)' : '' ?>.</p>
            <?php endif; ?>
        <?php endif; ?>

        <form class="search-card" method="get" action="<?= e(url('find')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" style="margin:1rem 0 1.5rem">
            <div class="grid grid-2 home-search-primary">
                <div class="form-group mb-0">
                    <label for="category">Service category</label>
                    <select id="category" name="category">
                        <option value="">Any service</option>
                        <?php foreach ($categoryGroups as $groupName => $groupCategories): ?>
                            <optgroup label="<?= e_attr($groupName) ?>">
                                <?php foreach ($groupCategories as $cat): ?>
                                    <option value="<?= e_attr((string) $cat['slug']) ?>" <?= $categorySlug === (string) $cat['slug'] ? 'selected' : '' ?>><?= $this->e((string) $cat['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0 location-field">
                    <label for="location">Town, suburb or postcode</label>
                    <input type="text" id="location" name="location" value="<?= e_attr($location) ?>" placeholder="e.g. Parramatta or 2150" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                    <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                    <input type="hidden" name="lat" value="<?= isset($lat) && $lat !== null ? e_attr((string) $lat) : '' ?>">
                    <input type="hidden" name="lng" value="<?= isset($lng) && $lng !== null ? e_attr((string) $lng) : '' ?>">
                    <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline']); ?>
                    <p class="location-status muted" role="status" aria-live="polite" hidden></p>
                </div>
            </div>
            <details class="search-options" <?= ($timeframe ?? '') !== '' || !empty($maxDistance) ? 'open' : '' ?>>
                <summary>Timeframe and distance</summary>
                <div class="grid grid-2">
                    <div class="form-group mb-0"><label for="timeframe">Preferred timeframe</label><select id="timeframe" name="timeframe"><option value="">Any time</option><option value="2weeks" <?= ($timeframe ?? '') === '2weeks' ? 'selected' : '' ?>>Within 2 weeks</option><option value="month" <?= ($timeframe ?? '') === 'month' ? 'selected' : '' ?>>Within a month</option><option value="flexible" <?= ($timeframe ?? '') === 'flexible' ? 'selected' : '' ?>>Flexible</option></select></div>
                    <?php $this->include('partials.search-distance-filter', ['selected' => $distanceSelection ?? 'town', 'townName' => $town['name'] ?? null, 'disabled' => empty($hasOrigin)]); ?>
                </div>
            </details>
            <div class="search-submit-row"><?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary']); ?><button type="submit" class="btn btn-primary btn-lg">Update results</button></div>
        </form>

        <nav class="search-result-shortcuts" aria-label="Related traveller searches">
            <a href="<?= e($stayUrl) ?>"><strong>Places to stay</strong><span>Caravan-friendly stops near this location</span></a>
            <a href="<?= e(url('find?' . http_build_query(array_filter(['location' => $location ?: null, 'category' => 'fuel-and-travel-stops', 'lat' => $lat ?? null, 'lng' => $lng ?? null])))) ?>"><strong>Fuel nearby</strong><span>Find the next servo</span></a>
        </nav>

        <?php if ($locationNotFound): ?>
            <div class="card" style="border-left:4px solid #c9a227">
                <p style="margin:0"><strong>We couldn't find “<?= $this->e($location) ?>”.</strong> Try a nearby larger town or a 4-digit postcode, or browse by <a href="<?= e(url('regions')) ?>">region</a>.</p>
                <p class="muted" style="margin:.5rem 0 0">You can also <a href="<?= e($requestUrl) ?>">register a request</a> and we'll notify relevant providers for your area.</p>
            </div>
        <?php endif; ?>

        <?php if ($alternatives !== []): ?>
            <p class="muted">Other matches:
                <?php foreach ($alternatives as $i => $alt): ?>
                    <a href="<?= e(url('find?' . http_build_query(array_filter([
                        'location' => (string) $alt['name'],
                        'category' => $categorySlug !== '' ? $categorySlug : null,
                        'max_distance' => !empty($maxDistance) ? (string) $maxDistance : null,
                    ])))) ?>"><?= $this->e((string) $alt['name']) ?><?= !empty($alt['state_abbr']) ? ', ' . $this->e((string) $alt['state_abbr']) : '' ?></a><?= $i < count($alternatives) - 1 ? ' · ' : '' ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <?php if ($mappedResults !== []): ?>
            <section class="results-map-shell" data-results-view-shell data-active-view="list" aria-labelledby="results-map-heading">
                <div class="results-view-switch" role="group" aria-label="Choose results view"><button type="button" data-results-view="list" aria-pressed="true">List</button><button type="button" data-results-view="map" aria-pressed="false">Map</button></div>
                <div class="results-map-heading">
                    <div><span class="directory-eyebrow">Map and list</span><h2 id="results-map-heading"><?= count($mappedResults) ?> located <?= count($mappedResults) === 1 ? 'result' : 'results' ?> near <?= $this->e((string) ($originLabel ?: ($town['name'] ?? 'your search'))) ?></h2></div>
                    <p>Tap a numbered pin to jump to that provider. Pins show the listed business or base locality; confirm the address before travelling.</p>
                </div>
                <div class="results-map" data-results-map hidden aria-label="Map of providers returned by this search">
                    <div class="results-map-canvas" data-results-map-canvas tabindex="0" aria-label="Interactive results map. Drag to move, pinch or use the controls to zoom."></div>
                    <div class="results-map-controls" role="group" aria-label="Map controls">
                        <button type="button" data-results-map-zoom-in aria-label="Zoom in">+</button>
                        <button type="button" data-results-map-zoom-out aria-label="Zoom out">&minus;</button>
                        <button type="button" data-results-map-fit>Fit results</button>
                    </div>
                    <aside class="results-map-summary" data-results-map-summary hidden aria-live="polite">
                        <button type="button" data-results-map-summary-close aria-label="Close provider summary">&times;</button>
                        <div class="results-map-summary-tools">
                            <button type="button" data-results-map-summary-drag aria-label="Move provider summary" title="Drag to move provider summary">Move</button>
                            <button type="button" data-results-map-summary-toggle aria-expanded="true">Collapse</button>
                        </div>
                        <span data-results-map-summary-position></span>
                        <strong data-results-map-summary-name></strong>
                        <div class="results-map-summary-body" data-results-map-summary-body>
                            <small data-results-map-summary-location></small>
                            <div><a class="btn btn-primary btn-sm" data-results-map-summary-profile href="#">Details</a><button class="btn btn-secondary btn-sm" type="button" data-results-map-summary-list>Show in list</button><a class="btn btn-secondary btn-sm" data-results-map-summary-directions href="#" target="_blank" rel="noopener noreferrer">Directions</a></div>
                        </div>
                    </aside>
                    <div class="results-map-key"><span><i class="results-map-key__origin"></i>Your search</span><span><i class="is-featured"></i>Featured</span><span><i></i>Direct match</span><span><i class="is-possible"></i>Related service</span></div>
                    <p class="results-map-attribution"><a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">Map © OpenStreetMap contributors</a></p>
                </div>
                <p class="results-map-status muted" data-results-map-status role="status" aria-live="polite">The provider list below remains available if the map cannot load.</p>
                <script type="application/json" data-results-map-data><?= json_encode([
                    'origin' => !empty($hasOrigin) ? ['lat' => $lat ?? ($town['latitude'] ?? null), 'lng' => $lng ?? ($town['longitude'] ?? null)] : null,
                    'providers' => $mappedResults,
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
            </section>
        <?php endif; ?>

        <div data-results-list>
        <?php if ($featuredMatches !== []): ?>
            <section class="featured-results" aria-labelledby="featured-results-heading">
                <div class="featured-results-heading"><div><span>Featured</span><h2 id="featured-results-heading">Featured providers</h2></div><p>Featured placement is shown separately and does not change the service or location match.</p></div>
                <div class="provider-card-grid provider-result-list">
                    <?php foreach ($featuredMatches as $p): ?>
                        <?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => false, 'compact' => true, 'searchId' => $searchId, 'resultCardId' => 'provider-result-' . (int) $p['id']]); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($organicMatches !== []): ?>
            <p class="muted result-section-note"><strong>Direct matches</strong> list this service. Confirm unclaimed details.</p>
            <h2 style="margin-top:1.5rem">Direct providers<?= $town !== null ? ' in ' . $this->e((string) $town['name']) : '' ?></h2>
            <div class="provider-card-grid provider-result-list">
                <?php foreach ($organicMatches as $p): ?>
                    <?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => false, 'compact' => true, 'searchId' => $searchId, 'resultCardId' => 'provider-result-' . (int) $p['id']]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($possible !== []): ?>
            <h2 style="margin-top:1.5rem">Businesses that may offer this service<?= $town !== null ? ' in ' . $this->e((string) $town['name']) : '' ?></h2>
            <p class="muted result-section-note">Related trades may help, but are not confirmed for this exact service.</p>
            <div class="provider-card-grid provider-result-list">
                <?php foreach ($possible as $p): ?>
                    <?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => true, 'compact' => true, 'searchId' => $searchId, 'resultCardId' => 'provider-result-' . (int) $p['id']]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>

        <?php if (!empty($nearbyRuns)): ?>
            <h2 style="margin-top:1.5rem">Service runs near you</h2>
            <p class="muted">Providers planning grouped visits — register interest to help a run confirm.</p>
            <div class="grid grid-2">
                <?php foreach ($nearbyRuns as $run): ?>
                    <div class="card">
                        <span class="badge badge-<?= $run['status'] === 'confirmed' ? 'confirmed' : 'forming' ?>"><?= $this->e(ucfirst((string) $run['status'])) ?></span>
                        <h3 style="margin-top:.5rem"><a href="<?= e(url('service-runs/' . $run['slug'])) ?>"><?= $this->e((string) $run['title']) ?></a></h3>
                        <p class="muted mb-0"><?= $this->e((string) $run['business_name']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$locationNotFound && $matches === [] && $possible === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">⌕</span>
                <h2>No suitable provider found</h2>
                <?php if ($town === null && $location === ''): ?>
                    <p>Enter a town or postcode above to find providers near you.</p>
                <?php elseif (!empty($maxDistance) && !empty($hasOrigin)): ?>
                    <p>No providers are listed within <?= (int) $maxDistance ?> km<?= $category !== null ? ' for ' . $this->e((string) $category['name']) : '' ?><?= $town !== null ? ' near ' . $this->e((string) $town['name']) : '' ?>. Try a larger distance or <a href="<?= e(url('find?' . http_build_query(array_filter(['location' => $location, 'category' => $categorySlug ?: null])))) ?>">clear the distance filter</a>.</p>
                <?php else: ?>
                    <p>No providers are listed<?= $category !== null ? ' for ' . $this->e((string) $category['name']) : '' ?><?= $town !== null ? ' in ' . $this->e((string) $town['name']) : '' ?> yet.</p>
                <?php endif; ?>
                <div class="btn-row"><a class="btn btn-primary" href="<?= e($requestUrl) ?>">Register a request</a><a class="btn btn-secondary" href="<?= e(url('providers')) ?>">Browse full directory</a></div>
            </div>

            <?php if ($town !== null || $category !== null): ?>
                <form class="card stack" method="post" action="<?= e(url('find/feedback')) ?>" style="margin-top:1rem">
                    <?= csrf_field() ?>
                    <?php $this->include('partials.turnstile'); ?>
                    <input type="hidden" name="town_id" value="<?= (int) ($town['id'] ?? 0) ?>">
                    <input type="hidden" name="region_id" value="<?= (int) ($town['region_id'] ?? 0) ?>">
                    <input type="hidden" name="category_id" value="<?= (int) ($category['id'] ?? 0) ?>">
                    <input type="hidden" name="search_id" value="<?= (int) ($searchId ?? 0) ?>">
                    <input type="hidden" name="location" value="<?= e_attr($location) ?>">
                    <input type="hidden" name="category" value="<?= e_attr($categorySlug) ?>">
                    <input type="hidden" name="max_distance" value="<?= !empty($maxDistance) ? (int) $maxDistance : '' ?>">
                    <h3 style="margin:0">Help us improve coverage</h3>
                    <label>What was missing?
                        <select name="reason">
                            <option value="none_nearby">No provider nearby</option>
                            <option value="none_soon_enough">None available soon enough</option>
                            <option value="no_mobile">No mobile provider</option>
                            <option value="no_workshop">No workshop option</option>
                            <option value="wrong_category">No one offers this service</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label>Anything else? <textarea name="comment" rows="2" maxlength="500"></textarea></label>
                    <div class="btn-row"><button type="submit" class="btn btn-secondary">Send feedback</button></div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($matches !== [] || $possible !== []): ?>
            <div class="card section-sand text-center" style="margin-top:1.5rem">
                <p style="margin:0 0 .5rem">Can't see the right fit?</p>
                <a class="btn btn-primary" href="<?= e($requestUrl) ?>">Request assistance</a>
            </div>
        <?php endif; ?>

        <section class="result-guidance" aria-labelledby="result-guidance-heading">
            <h2 id="result-guidance-heading">Understanding these results</h2>
            <?php if (!empty($hasOrigin) && ($matches !== [] || $possible !== [])): ?>
                <p>Distances are approximate straight-line estimates to each provider's base town, not driving distance. Mobile-service providers travel to customers; a base-town pin is not necessarily a workshop destination.</p>
            <?php endif; ?>
            <p>Direct matches explicitly list the selected service. Related-service results work in an adjacent trade and require confirmation. Unclaimed listings come from public sources; confirm current services and contact details before relying on them.</p>
            <?php $this->include('partials.listing-accuracy-notice'); ?>
        </section>
    </div>
</section>
<?php $this->endSection(); ?>
