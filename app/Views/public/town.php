<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $town */
/** @var array<int,array<string,mixed>> $neighbours */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int,array<string,mixed>> $providers */
$this->extend('layouts.public');
$mappedProviders=[];
foreach ($providers as $p) { $pLat=$p['latitude']??$p['town_lat']??null; $pLng=$p['longitude']??$p['town_lng']??null; if(!is_numeric($pLat)||!is_numeric($pLng))continue; $id='town-provider-'.(int)$p['id']; $mappedProviders[]=['id'=>$id,'listId'=>$id,'number'=>count($mappedProviders)+1,'name'=>(string)$p['business_name'],'location'=>trim((string)($p['town_name']??'').(!empty($p['state_abbr'])?', '.$p['state_abbr']:'')),'lat'=>(float)$pLat,'lng'=>(float)$pLng,'profile'=>url('providers/'.$p['slug']),'directions'=>'','destination'=>'','featured'=>!empty($p['is_featured']),'possible'=>false]; }
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> /
            <a href="<?= e(url('regions')) ?>">Regions</a> /
            <?php if (!empty($town['region_slug'])): ?>
                <a href="<?= e(url('regions/' . $town['region_slug'])) ?>"><?= $this->e((string) $town['region_name']) ?></a> /
            <?php endif; ?>
            <?= $this->e((string) $town['name']) ?>
        </nav>

        <h1>Caravan &amp; RV services in <?= $this->e((string) $town['name']) ?></h1>
        <p class="muted">
            <?= $this->e((string) $town['name']) ?><?= !empty($town['primary_postcode']) ? ' ' . $this->e((string) $town['primary_postcode']) : '' ?>,
            <?= $this->e((string) $town['state_abbr']) ?>
        </p>

        <?php if (!empty($town['public_content'])): ?>
            <div class="card stack"><?= nl2br($this->e((string) $town['public_content'])) ?></div>
        <?php else: ?>
            <div class="card">
                <p class="mb-0">VanAssist connects caravan and RV travellers in <?= $this->e((string) $town['name']) ?> with mobile and workshop specialists. If no provider is currently listed for your area, register a request and we'll notify relevant providers when help becomes available.</p>
            </div>
        <?php endif; ?>

        <?php
        $groups = [0 => [], 1 => [], 2 => []];
        foreach ($providers as $p) {
            $groups[(int) ($p['relevance'] ?? 0)][] = $p;
        }
        $townLabel = (string) $town['name'];
        $regionLabel = (string) ($town['region_name'] ?? '');
        $providerCard = function (array $p) use ($mappedProviders): void {
            $mapIndex=array_search('town-provider-'.(int)$p['id'],array_column($mappedProviders,'id'),true);
            $this->include('partials.provider-result-card', ['p'=>$p,'isPossible'=>false,'resultCardId'=>'town-provider-'.(int)$p['id'],'mapResultNumber'=>$mapIndex===false?0:$mapIndex+1]);
        };
        ?>
        <?php $townOrigin=is_numeric($town['latitude']??null)&&is_numeric($town['longitude']??null)?['lat'=>(float)$town['latitude'],'lng'=>(float)$town['longitude']]:null; $this->include('partials/results-map',['mapItems'=>$mappedProviders,'mapOrigin'=>$townOrigin,'mapTitle'=>count($mappedProviders).' located services near '.$townLabel]); ?>

        <?php if ($groups[0] !== []): ?>
            <h2 style="margin-top:2rem">Service businesses in <?= $this->e($townLabel) ?></h2>
            <p class="muted">Businesses based in or directly serving <?= $this->e($townLabel) ?>. Unclaimed listings were compiled from public sources — confirm details before booking.</p>
            <div class="provider-card-grid">
                <?php foreach ($groups[0] as $p) {
                    $providerCard($p);
                } ?>
            </div>
        <?php endif; ?>

        <?php if ($groups[1] !== []): ?>
            <h2 style="margin-top:2rem">Mobile operators serving the <?= $this->e($regionLabel !== '' ? $regionLabel : $townLabel) ?> area</h2>
            <p class="muted">Mobile businesses based elsewhere in the region that travel to jobs — confirm they cover <?= $this->e($townLabel) ?> before booking.</p>
            <div class="provider-card-grid">
                <?php foreach ($groups[1] as $p) {
                    $providerCard($p);
                } ?>
            </div>
        <?php endif; ?>

        <?php if ($groups[2] !== []): ?>
            <h2 style="margin-top:2rem">Workshops elsewhere in <?= $this->e($regionLabel !== '' ? $regionLabel : 'the region') ?></h2>
            <p class="muted">Nearby workshop options if you can travel to them.</p>
            <div class="provider-card-grid">
                <?php foreach ($groups[2] as $p) {
                    $providerCard($p);
                } ?>
            </div>
        <?php endif; ?>

        <?php if ($groups[0] === [] && $groups[1] === [] && $groups[2] === []): ?>
            <div class="card" style="margin-top:2rem;border-left:4px solid #c9a227">
                <p style="margin:0"><strong>No providers listed for <?= $this->e($townLabel) ?> yet.</strong> Register your request and we'll notify relevant providers when help becomes available.</p>
                <p class="muted" style="margin:.5rem 0 0"><a href="<?= e(url('request-assistance?town=' . urlencode((string) $town['slug']))) ?>">Request assistance in <?= $this->e($townLabel) ?></a> · <a href="<?= e(url('find?location=' . urlencode($townLabel))) ?>">Search nearby</a></p>
            </div>
        <?php endif; ?>

        <?php if ($categories !== []): ?>
            <h2 style="margin-top:2rem">Services</h2>
            <div class="btn-row">
                <?php foreach ($categories as $cat): ?>
                    <a class="btn btn-ghost" href="<?= e(url('services/' . $cat['slug'])) ?>"><?= $this->e((string) $cat['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($neighbours !== []): ?>
            <h2 style="margin-top:2rem">Nearby towns</h2>
            <div class="btn-row">
                <?php foreach ($neighbours as $n): ?>
                    <a class="btn btn-ghost" href="<?= e(url('towns/' . $n['slug'])) ?>">
                        <?= $this->e((string) $n['name']) ?><?= $n['distance_km'] !== null ? ' (' . (int) $n['distance_km'] . ' km)' : '' ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-sand text-center">
    <div class="container">
        <h2>Need help in <?= $this->e((string) $town['name']) ?>?</h2>
        <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance?town=' . urlencode((string) $town['slug']))) ?>">Request assistance</a>
    </div>
</section>
<?php $this->endSection(); ?>
