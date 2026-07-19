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
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1><?= $this->e($heading) ?></h1>
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
            <div class="grid grid-3">
                <div class="form-group mb-0">
                    <label for="category">Service category</label>
                    <select id="category" name="category">
                        <option value="">Any service</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e_attr((string) $cat['slug']) ?>" <?= $categorySlug === (string) $cat['slug'] ? 'selected' : '' ?>><?= $this->e((string) $cat['name']) ?></option>
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
                <div class="form-group mb-0">
                    <label for="timeframe">Preferred timeframe</label>
                    <select id="timeframe" name="timeframe">
                        <option value="">Any time</option>
                        <option value="2weeks" <?= ($timeframe ?? '') === '2weeks' ? 'selected' : '' ?>>Within 2 weeks</option>
                        <option value="month" <?= ($timeframe ?? '') === 'month' ? 'selected' : '' ?>>Within a month</option>
                        <option value="flexible" <?= ($timeframe ?? '') === 'flexible' ? 'selected' : '' ?>>Flexible</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-3" style="margin-top:.75rem">
                <?php $this->include('partials.search-distance-filter', [
                    'selected' => $distanceSelection ?? 'town',
                    'townName' => $town['name'] ?? null,
                    'disabled' => empty($hasOrigin),
                ]); ?>
                <div class="form-group mb-0 location-actions" style="align-self:end">
                    <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary']); ?>
                    <button type="submit" class="btn btn-primary btn-lg">Find a service</button>
                </div>
            </div>
        </form>

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

        <?php if (!empty($hasOrigin) && ($matches !== [] || $possible !== [])): ?>
            <p class="muted" style="font-size:.9rem;margin:.5rem 0 0">Distances are approximate straight-line estimates to each provider's base town — not driving distance. <span class="badge badge-confirmed">&#128666; Mobile service</span> providers travel to you.</p>
        <?php endif; ?>

        <?php if ($matches !== []): ?>
            <p class="muted" style="margin-top:.5rem"><strong>Direct matches</strong> explicitly offer the service you searched for. Unclaimed listings were compiled from public sources — confirm details before booking.</p>
            <h2 style="margin-top:1.5rem">Providers<?= $town !== null ? ' in ' . $this->e((string) $town['name']) : '' ?></h2>
            <div class="grid grid-3">
                <?php foreach ($matches as $p): ?>
                    <?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => false]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($possible !== []): ?>
            <h2 style="margin-top:1.5rem">Businesses that may offer this service<?= $town !== null ? ' in ' . $this->e((string) $town['name']) : '' ?></h2>
            <p class="muted">These work in a related trade and <em>may</em> be able to help — they are not verified for this exact service. Confirm before booking; contact details may be limited for unclaimed listings.</p>
            <div class="grid grid-3">
                <?php foreach ($possible as $p): ?>
                    <?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => true]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
            <div class="card">
                <?php if ($town === null && $location === ''): ?>
                    <p style="margin:0">Enter a town or postcode above to find providers near you.</p>
                <?php elseif (!empty($maxDistance) && !empty($hasOrigin)): ?>
                    <p style="margin:0">No providers are listed within <?= (int) $maxDistance ?> km<?= $category !== null ? ' for ' . $this->e((string) $category['name']) : '' ?><?= $town !== null ? ' near ' . $this->e((string) $town['name']) : '' ?>. Try a larger distance or <a href="<?= e(url('find?' . http_build_query(array_filter(['location' => $location, 'category' => $categorySlug ?: null])))) ?>">clear the distance filter</a>.</p>
                <?php else: ?>
                    <p style="margin:0">No providers are listed<?= $category !== null ? ' for ' . $this->e((string) $category['name']) : '' ?><?= $town !== null ? ' in ' . $this->e((string) $town['name']) : '' ?> yet.</p>
                <?php endif; ?>
                <p class="muted" style="margin:.5rem 0 0"><a href="<?= e($requestUrl) ?>">Register a request</a> and we'll notify relevant providers, or <a href="<?= e(url('providers')) ?>">browse the full directory</a>.</p>
            </div>

            <?php if ($town !== null || $category !== null): ?>
                <form class="card stack" method="post" action="<?= e(url('find/feedback')) ?>" style="margin-top:1rem">
                    <?= csrf_field() ?>
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
    </div>
</section>
<?php $this->endSection(); ?>
