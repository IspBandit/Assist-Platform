<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $runs */
$this->extend('layouts.public');
$logo = $park['logo_path'] ? url('uploads-public/park-logos/' . $park['logo_path']) : null;
$stayLabels = ['caravan_park'=>'Caravan park','campground'=>'Campground','free_camp'=>'Free camp','national_park'=>'National park camping','showground'=>'Showground','rest_area'=>'Permitted overnight rest area','council_camp'=>'Council camp','farm_stay'=>'Farm stay','station_stay'=>'Station stay','other'=>'Place to stay'];
$mapDestination = map_destination($park['latitude'] ?? null, $park['longitude'] ?? null, [$park['address'] ?? '', $park['town_name'] ?? '']);
$parkLocation = \App\Models\CaravanPark::publicLocation($park);
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> /
            <?php if ($park['town_slug']): ?><a href="<?= e(url('towns/' . $park['town_slug'])) ?>"><?= $this->e((string) $park['town_name']) ?></a> / <?php endif; ?>
            <?= $this->e((string) $park['name']) ?>
        </nav>

        <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
            <div>
                <h1 style="margin-bottom:.25rem"><?= $this->e((string) $park['name']) ?></h1>
                <div class="badge-row"><?php if (($park['verification_type'] ?? '') === 'authority'): ?><span class="badge badge-verified">Authority confirmed</span><?php elseif (($park['verification_type'] ?? '') === 'operator'): ?><span class="badge badge-verified">Operator verified</span><?php else: ?><span class="badge badge-neutral">Confirm before arrival</span><?php endif; ?><?php if (!empty($park['is_featured'])): ?><span class="badge badge-sponsored">Sponsored</span><?php endif; ?></div>
                <?php if ($parkLocation !== ''): ?><p class="muted"><?= $this->e($stayLabels[$park['stay_type'] ?? 'caravan_park'] ?? 'Place to stay') ?> in <?= $this->e($parkLocation) ?></p><?php endif; ?>
            </div>
            <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt="<?= e_attr((string) $park['name']) ?> logo" style="max-height:90px;border-radius:8px"><?php endif; ?>
        </div>

        <div class="grid grid-2" style="margin-top:1rem;align-items:flex-start">
            <div class="stack">
                <?php if ($park['description']): ?>
                    <div class="card stack"><?= nl2br($this->e((string) $park['description'])) ?></div>
                <?php endif; ?>

                <div class="card stack">
                    <h2 style="margin-top:0">Need help with your van while you're here?</h2>
                    <p class="muted">Tell us what's wrong and we'll coordinate trusted caravan and RV service providers in the area. There's no charge to submit a request.</p>
                    <a class="btn btn-primary" href="<?= e(url('request-assistance?park=' . $park['slug'])) ?>">Request assistance</a>
                </div>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Park details</h2>
                <p class="notice"><strong>Confirm before travelling.</strong> Overnight permission, access, fees, closures, vehicle limits and stay limits can change. Check the linked authority or operator source before relying on this listing.</p>
                <?php if ($park['address']): ?><p style="margin:0"><strong>Address:</strong> <?= $this->e((string) $park['address']) ?></p><?php endif; ?>
                <?php if ($park['phone']): ?><p style="margin:0"><strong>Phone:</strong> <?= $this->e((string) $park['phone']) ?></p><?php endif; ?>
                <?php if ($park['website']): ?><p style="margin:0"><strong>Website:</strong> <a href="<?= e((string) $park['website']) ?>" target="_blank" rel="noopener nofollow"><?= $this->e((string) $park['website']) ?></a></p><?php endif; ?>
                <?php if ($mapDestination !== ''): ?><a class="btn btn-primary" href="<?= e(map_directions_url($mapDestination)) ?>" data-map-directions data-map-destination="<?= e_attr($mapDestination) ?>" target="_blank" rel="noopener noreferrer">Directions from my location</a><?php endif; ?>
                <?php if ($park['number_of_sites']): ?><p style="margin:0"><strong>Sites:</strong> <?= (int) $park['number_of_sites'] ?></p><?php endif; ?>
                <?php if($facilities!==[]): ?><div class="stay-facility-list"><h3>Facilities</h3><?php foreach($facilities as $fact): ?><p><strong><?= $this->e((string)$fact['label']) ?>:</strong> <?= $this->e((string)$fact['display']) ?><?php if($fact['details']): ?><br><small><?= $this->e((string)$fact['details']) ?></small><?php endif; ?></p><?php endforeach; ?><details><summary>Facility information</summary><?php foreach($facilities as $fact): ?><p class="muted small"><strong><?= $this->e((string)$fact['label']) ?></strong> — <?= $this->e((string)$fact['source_name']) ?><?php if($fact['verified_at']??$fact['last_seen_at']??null): ?> · Last verified <?= $this->e(date('F Y',strtotime((string)($fact['verified_at']??$fact['last_seen_at'])))) ?><?php endif; ?><?php if($fact['source_url']): ?> · <a href="<?= e_attr((string)$fact['source_url']) ?>" target="_blank" rel="noopener noreferrer">source</a><?php endif; ?></p><?php endforeach; ?></details></div><?php else: ?><?php foreach (['powered_sites'=>'Powered sites','unpowered_sites'=>'Unpowered sites','toilets'=>'Toilets','showers'=>'Showers','potable_water'=>'Drinking water','dump_point'=>'Dump point','pets_allowed'=>'Pets considered'] as $field => $label): ?><?php if ((int) ($park[$field] ?? 0) === 1): ?><p style="margin:0">✓ <?= $this->e($label) ?></p><?php endif; ?><?php endforeach; ?><?php endif; ?>
                <?php if (!empty($park['max_stay'])): ?><p style="margin:0"><strong>Maximum stay:</strong> <?= $this->e((string) $park['max_stay']) ?></p><?php endif; ?>
                <?php if (!empty($park['source_url'])): ?><p class="muted small">Source: <a href="<?= e_attr((string) $park['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= ($park['verification_type'] ?? '') === 'authority' ? 'Council or road authority' : 'Directory source' ?></a></p><?php endif; ?>
                <?php if (empty($isManaged)): ?><hr><p class="muted">Own or manage this location?</p><a class="btn btn-secondary" href="<?= e(url('caravan-parks/' . $park['slug'] . '/claim')) ?>">Claim and update this listing</a><?php endif; ?>
                <?php if(\App\Core\Database::tableExists('facility_contributions')): ?><a class="btn btn-ghost" href="<?= e(url('caravan-parks/'.$park['slug'].'/suggest-facility')) ?>">Suggest a facility or correction</a><?php endif; ?>
            </div>
        </div>

        <?php if ($runs !== []): ?>
            <div class="card stack" style="margin-top:1rem">
                <h2 style="margin-top:0">Service runs forming nearby</h2>
                <div class="grid grid-2">
                    <?php foreach ($runs as $run): ?>
                        <a class="card" href="<?= e(url('service-runs/' . $run['slug'])) ?>" style="text-decoration:none;color:inherit">
                            <strong><?= $this->e((string) $run['title']) ?></strong>
                            <p class="muted" style="margin:.25rem 0 0"><?= $this->e((string) $run['business_name']) ?><?php if ($run['start_date']): ?> · from <?= $this->e((string) $run['start_date']) ?><?php endif; ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
