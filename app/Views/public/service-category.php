<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $category */
/** @var array<int,array<string,mixed>> $children */
/** @var array<string,mixed>|null $parent */
/** @var array<int,array<string,mixed>> $launchTowns */
/** @var array<int,array<string,mixed>> $matches */
/** @var array<int,array<string,mixed>> $possible */
/** @var string $selectedTownLabel */
/** @var int|null $townId */
/** @var array<string,mixed>|null $selectedTown */
/** @var int|null $maxDistance */
/** @var string|null $distanceScope */
/** @var string|int|null $distanceSelection */
/** @var bool $hasOrigin */
$this->extend('layouts.public');
$inArea = $selectedTown !== null ? (' in ' . (string) $selectedTown['name']) : '';
$mappedProviders=[];
foreach (array_merge($matches,$possible) as $p) { $pLat=$p['latitude']??$p['town_lat']??null; $pLng=$p['longitude']??$p['town_lng']??null; if(!is_numeric($pLat)||!is_numeric($pLng))continue; $id='service-provider-'.(int)$p['id']; $mappedProviders[]=['id'=>$id,'listId'=>$id,'number'=>count($mappedProviders)+1,'name'=>(string)$p['business_name'],'location'=>trim((string)($p['town_name']??'').(!empty($p['state_abbr'])?', '.$p['state_abbr']:'')),'lat'=>(float)$pLat,'lng'=>(float)$pLng,'profile'=>url('providers/'.$p['slug']),'directions'=>'','destination'=>'','featured'=>!empty($p['is_featured']),'possible'=>in_array($p,$possible,true)]; }
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> /
            <a href="<?= e(url('services')) ?>">Services</a> /
            <?php if ($parent): ?><a href="<?= e(url('services/' . $parent['slug'])) ?>"><?= $this->e((string) $parent['name']) ?></a> / <?php endif; ?>
            <?= $this->e((string) $category['name']) ?>
        </nav>

        <h1><?= $this->e((string) $category['name']) ?></h1>
        <?php if (!empty($category['short_description'])): ?>
            <p style="font-size:1.1rem"><?= $this->e((string) $category['short_description']) ?></p>
        <?php endif; ?>

        <div class="grid grid-2" style="margin-top:1.5rem">
            <div class="stack">
                <?php if (!empty($category['public_description'])): ?>
                    <div class="card"><?= nl2br($this->e((string) $category['public_description'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($category['typical_issues'])): ?>
                    <div class="card">
                        <h3 style="margin-top:0">Typical issues</h3>
                        <p class="mb-0"><?= nl2br($this->e((string) $category['typical_issues'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stack">
                <?php if (!empty($category['customer_guidance'])): ?>
                    <div class="card section-sand">
                        <h3 style="margin-top:0">Before you book</h3>
                        <p class="mb-0"><?= nl2br($this->e((string) $category['customer_guidance'])) ?></p>
                    </div>
                <?php endif; ?>
                <div class="card text-center">
                    <h3 style="margin-top:0">Need this service?</h3>
                    <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance?category=' . $category['slug'])) ?>">Request assistance</a>
                </div>
            </div>
        </div>

        <h2 style="margin-top:2rem"><?= $this->e((string) $category['name']) ?> providers<?= $this->e($inArea) ?></h2>
        <form method="get" action="<?= e(url('services/' . $category['slug'])) ?>" class="btn-row" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" data-auto-location style="margin:.5rem 0 1rem;align-items:flex-end;gap:.5rem;flex-wrap:wrap">
            <div class="form-group mb-0 location-field" style="min-width:280px">
                <label for="town_search">Filter by town, suburb or postcode</label>
                <input type="text" id="town_search" name="location" value="<?= e_attr($selectedTownLabel ?? '') ?>" placeholder="Start typing…" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                <input type="hidden" id="town_id" name="town" value="<?= $townId ? (int) $townId : '' ?>">
                <input type="hidden" name="lat" value="<?= isset($lat) && $lat !== null ? e_attr((string) $lat) : '' ?>">
                <input type="hidden" name="lng" value="<?= isset($lng) && $lng !== null ? e_attr((string) $lng) : '' ?>">
                <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline', 'selectTarget' => '#town_id']); ?>
                <p class="location-status muted" role="status" aria-live="polite" hidden></p>
            </div>
            <?php $this->include('partials.search-distance-filter', [
                'selected' => $distanceSelection ?? ($selectedTown !== null ? 'town' : 'any'),
                'townName' => $selectedTown['name'] ?? null,
                'disabled' => empty($hasOrigin),
            ]); ?>
            <button type="submit" class="btn btn-ghost">Apply</button>
            <?php if ($selectedTown !== null): ?>
                <a class="btn btn-ghost" href="<?= e(url('services/' . $category['slug'])) ?>">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($selectedTown !== null && ($matches !== [] || $possible !== [])): ?>
            <p class="muted" style="font-size:.9rem;margin:.25rem 0 0">Sorted by approximate distance from <?= $this->e((string) $selectedTown['name']) ?><?= !empty($maxDistance) ? ' (within ' . (int) $maxDistance . ' km)' : '' ?>. Distances are to each provider's base town. <span class="badge badge-confirmed">&#128666; Mobile service</span> providers travel to you.</p>
        <?php endif; ?>
        <?php $serviceOrigin=is_numeric($lat??null)&&is_numeric($lng??null)?['lat'=>(float)$lat,'lng'=>(float)$lng]:null; $this->include('partials/results-map',['mapItems'=>$mappedProviders,'mapOrigin'=>$serviceOrigin,'mapTitle'=>count($mappedProviders).' located '.$category['name'].' results']); ?>

        <?php if ($matches !== []): ?>
            <h3 style="margin-top:1rem">Offering this service<?= $this->e($inArea) ?></h3>
            <div class="provider-card-grid">
                <?php foreach ($matches as $p): $mapIndex=array_search('service-provider-'.(int)$p['id'],array_column($mappedProviders,'id'),true); ?>
                    <?php $this->include('partials.provider-result-card',['p'=>$p,'isPossible'=>false,'resultCardId'=>'service-provider-'.(int)$p['id'],'mapResultNumber'=>$mapIndex===false?0:$mapIndex+1]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($possible !== []): ?>
            <h3 style="margin-top:1.5rem">May also offer this service<?= $this->e($inArea) ?></h3>
            <p class="muted">These businesses work in a related trade and may be able to help. Confirm they cover this specific job before booking.</p>
            <div class="provider-card-grid">
                <?php foreach ($possible as $p): $mapIndex=array_search('service-provider-'.(int)$p['id'],array_column($mappedProviders,'id'),true); ?>
                    <?php $this->include('partials.provider-result-card',['p'=>$p,'isPossible'=>true,'resultCardId'=>'service-provider-'.(int)$p['id'],'mapResultNumber'=>$mapIndex===false?0:$mapIndex+1]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($matches === [] && $possible === []): ?>
            <div class="card">
                <p class="mb-0">No providers are listed for this service<?= $this->e($inArea) ?> yet. <a href="<?= e(url('request-assistance?category=' . $category['slug'])) ?>">Register a request</a> and we'll notify relevant providers in your area.</p>
            </div>
        <?php endif; ?>

        <?php if ($children !== []): ?>
            <h2 style="margin-top:2rem">Related services</h2>
            <div class="btn-row">
                <?php foreach ($children as $child): ?>
                    <a class="btn btn-ghost" data-location-link href="<?= e(url('services/' . $child['slug'])) ?>"><?= $this->e((string) $child['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($launchTowns !== []): ?>
            <h2 style="margin-top:2rem">Available in these areas</h2>
            <div class="btn-row">
                <?php foreach ($launchTowns as $town): ?>
                    <a class="btn btn-ghost" href="<?= e(url('towns/' . $town['slug'])) ?>"><?= $this->e((string) $town['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
