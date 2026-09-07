<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $providers */
/** @var array{eyebrow:string,heading:string,intro:string,search_placeholder:string} $directoryCopy */
$this->extend('layouts.public');
$pages = (int) ceil(max(1, $total) / $perPage);
$hasFilters = $search !== '' || $location !== '' || $townId !== null || $categoryId !== null || $serviceModel !== '';
$distanceRanked = !empty($distanceRanked);
$usesRoadDistance = !empty($usesRoadDistance);
$mappedProviders = [];
if ($hasFilters) foreach ($providers as $p) {
    if (($p['distance_basis'] ?? '') === 'town_centre') continue;
    $pLat=$p['latitude']??$p['town_lat']??null; $pLng=$p['longitude']??$p['town_lng']??null;
    if (!is_numeric($pLat)||!is_numeric($pLng)) continue;
    $id='directory-provider-'.(int)$p['id'];
    $mappedProviders[]=['id'=>$id,'listId'=>$id,'number'=>count($mappedProviders)+1,'name'=>(string)$p['business_name'],'location'=>trim((string)($p['town_name']??'').(!empty($p['state_abbr'])?', '.$p['state_abbr']:'')),'lat'=>(float)$pLat,'lng'=>(float)$pLng,'profile'=>url('providers/'.$p['slug']),'directions'=>'','destination'=>'','featured'=>!empty($p['is_featured']),'possible'=>false];
}
$qs = static function (array $extra) use ($search, $location, $townId, $categoryId, $serviceModel): string {
    $params = array_filter(array_merge(['q' => $search, 'location' => $location, 'town' => $location === '' ? $townId : null, 'category' => $categoryId, 'service_model' => $serviceModel], $extra), static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<section class="directory-hero interior-photo-hero interior-photo-hero--providers">
    <div class="container directory-hero-inner">
        <div>
            <span class="directory-eyebrow"><?= $this->e($directoryCopy['eyebrow']) ?></span>
            <h1><?= $this->e($directoryCopy['heading']) ?></h1>
            <p><?= $this->e($directoryCopy['intro']) ?></p>
        </div>
        <div class="directory-trust" aria-label="Directory information">
            <span>Australia-wide coverage</span>
            <span>Claimable business profiles</span>
            <span>Verification clearly labelled</span>
        </div>
    </div>
</section>

<section class="section directory-section">
    <div class="container">
        <form method="get" action="<?= e(url('providers')) ?>" class="directory-search" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" data-auto-location>
            <div class="form-group mb-0">
                <label for="q">What do you need?</label>
                <input type="search" id="q" name="q" value="<?= e_attr($search) ?>" placeholder="<?= e_attr($directoryCopy['search_placeholder']) ?>">
            </div>
            <div class="form-group mb-0 location-field">
                <label for="location">Where?</label>
                <input type="text" id="location" name="location" value="<?= e_attr($location) ?>" placeholder="Town, suburb or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                <input type="hidden" name="lat" value="<?= e_attr((string) ($lat ?? '')) ?>">
                <input type="hidden" name="lng" value="<?= e_attr((string) ($lng ?? '')) ?>">
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline']); ?>
                <p class="location-status muted" role="status" aria-live="polite" hidden></p>
            </div>
            <div class="form-group mb-0">
                <label for="category">Service category</label>
                <select id="category" name="category">
                    <option value="">All relevant services</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="service_model">How should they help?</label>
                <select id="service_model" name="service_model">
                    <option value="">Mobile or workshop</option>
                    <option value="mobile" <?= $serviceModel === 'mobile' ? 'selected' : '' ?>>Can come to me</option>
                    <option value="workshop" <?= $serviceModel === 'workshop' ? 'selected' : '' ?>>I can visit a workshop</option>
                </select>
            </div>
            <div class="directory-search-actions">
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary']); ?>
                <button type="submit" class="btn btn-primary">Search directory</button>
            </div>
        </form>

        <div class="directory-summary">
            <div>
                <p class="directory-count"><strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'business' : 'businesses' ?><?= $hasFilters ? ' matching your search' : ' in this directory' ?></p>
                <?php if ($distanceRanked): ?>
                    <p class="muted">Closest measurable provider locations are shown first<?= $usesRoadDistance ? ' using driving distance where available' : ' using straight-line distance while road routing is unavailable' ?>. Direct and verified service evidence is labelled separately; always confirm capability, availability and travel area directly.</p>
                <?php else: ?>
                    <p class="muted">Direct and verified service matches are shown before featured placement. Always confirm capability, availability and travel area directly.</p>
                <?php endif; ?>
            </div>
            <?php if ($hasFilters): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('providers')) ?>">Clear search</a><?php endif; ?>
        </div>

        <?php if ($providers === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">⌕</span>
                <h2><?= !$locationFound ? 'We couldn\'t recognise that location' : 'No matching businesses yet' ?></h2>
                <p><?= !$locationFound ? 'Try entering a nearby Australian town, suburb or four-digit postcode, then choose a suggestion from the list.' : 'Try a nearby town, remove a category, or search using fewer words. The directory is expanding as businesses claim and verify their profiles.' ?></p>
                <div class="btn-row">
                    <?php if ($serviceModel !== ''): ?><a class="btn btn-primary" href="<?= e(url('providers' . $qs(['service_model' => '']))) ?>">Show mobile and workshop options</a><?php else: ?><a class="btn btn-primary" href="<?= e(url('providers')) ?>">View the full directory</a><?php endif; ?>
                    <a class="btn btn-secondary" href="<?= e(url('for-providers/register')) ?>">Suggest or list a business</a>
                </div>
            </div>
        <?php else: ?>
            <?php $this->include('partials/results-map', ['mapItems'=>$mappedProviders,'mapTitle'=>count($mappedProviders).' located directory results']); ?>
            <div class="provider-card-grid">
                <?php foreach ($providers as $p): $mapIndex=array_search('directory-provider-'.(int)$p['id'],array_column($mappedProviders,'id'),true); ?>
                    <?php $this->include('partials.provider-result-card', ['p'=>$p,'isPossible'=>false,'resultCardId'=>'directory-provider-'.(int)$p['id'],'mapResultNumber'=>$mapIndex===false?0:$mapIndex+1]); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Directory pages">
                    <?php if ($page > 1): ?><a class="btn btn-ghost" rel="prev" href="<?= e(url('providers' . $qs(['page' => $page - 1]))) ?>">Previous</a><?php else: ?><span></span><?php endif; ?>
                    <span>Page <strong><?= $page ?></strong> of <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="btn btn-ghost" rel="next" href="<?= e(url('providers' . $qs(['page' => $page + 1]))) ?>">Next</a><?php else: ?><span></span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
