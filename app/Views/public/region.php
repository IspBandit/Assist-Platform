<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $region */
/** @var array<int,array<string,mixed>> $towns */
/** @var int $townTotal */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int,array<string,mixed>> $providers */
$this->extend('layouts.public');
$townTotal = $townTotal ?? count($towns);
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> /
            <a href="<?= e(url('regions')) ?>">Regions</a> /
            <?= $this->e((string) $region['name']) ?>
        </nav>

        <h1><?= $this->e((string) $region['name']) ?></h1>
        <p class="muted"><?= $this->e((string) $region['state_name']) ?></p>

        <?php if (!empty($region['public_content'])): ?>
            <div class="card stack"><?= nl2br($this->e((string) $region['public_content'])) ?></div>
        <?php endif; ?>

        <h2 style="margin-top:2rem">Towns in <?= $this->e((string) $region['name']) ?></h2>
        <?php if ($towns === []): ?>
            <p class="muted">Towns are being added to this region.</p>
        <?php else: ?>
            <?php if ($townTotal > count($towns)): ?>
                <p class="muted">Showing <?= number_format(count($towns)) ?> of <?= number_format($townTotal) ?> towns. Use the search box at the top of the page to jump straight to any town or postcode.</p>
            <?php endif; ?>
            <div class="btn-row">
                <?php foreach ($towns as $t): ?>
                    <a class="btn btn-ghost" href="<?= e(url('towns/' . $t['slug'])) ?>">
                        <?= $this->e((string) $t['name']) ?><?= $t['is_launch_town'] ? ' ✓' : '' ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($providers !== []): ?>
            <h2 style="margin-top:2rem">Service businesses in <?= $this->e((string) $region['name']) ?></h2>
            <p class="muted">Unclaimed listings were compiled from public sources — confirm details before booking.</p>
            <div class="grid grid-3">
                <?php foreach ($providers as $p): ?>
                    <a class="card stack" href="<?= e(url('providers/' . $p['slug'])) ?>" style="text-decoration:none;color:inherit">
                        <h3 style="margin:0"><?= $this->e((string) $p['business_name']) ?></h3>
                        <div>
                            <?= $p['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                            <?= $p['is_founding_provider'] ? '<span class="badge badge-confirmed">Founding</span> ' : '' ?>
                            <?= !empty($p['is_unclaimed']) ? '<span class="badge badge-neutral">Unclaimed</span> ' : '' ?>
                            <span class="badge badge-neutral"><?= $this->e(ucfirst((string) $p['service_model'])) ?></span>
                        </div>
                        <?php if (!empty($p['town_name'])): ?><p class="muted" style="margin:0"><?= $this->e((string) $p['town_name']) ?></p><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($categories !== []): ?>
            <h2 style="margin-top:2rem">Services available</h2>
            <div class="btn-row">
                <?php foreach ($categories as $cat): ?>
                    <a class="btn btn-ghost" href="<?= e(url('services/' . $cat['slug'])) ?>"><?= $this->e((string) $cat['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-sand text-center">
    <div class="container">
        <h2>Travelling through <?= $this->e((string) $region['name']) ?>?</h2>
        <p class="muted">Register your request and VanAssist will notify relevant providers when assistance becomes available.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
    </div>
</section>
<?php $this->endSection(); ?>
