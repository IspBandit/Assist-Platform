<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $regions */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> / Regions
        </nav>
        <h1>Regions we cover</h1>
        <p class="muted">VanAssist is growing across regional Australia. Explore a region to see its towns and register a request.</p>

        <?php if ($regions === []): ?>
            <p class="muted">Regions are being added.</p>
        <?php else: ?>
            <div class="grid grid-3" style="margin-top:1.5rem">
                <?php foreach ($regions as $r): ?>
                    <div class="card">
                        <?php if ($r['is_featured']): ?><span class="badge badge-verified">Featured</span><?php endif; ?>
                        <h3 style="margin-top:.5rem"><a href="<?= e(url('regions/' . $r['slug'])) ?>"><?= $this->e((string) $r['name']) ?></a></h3>
                        <p class="muted mb-0"><?= $this->e((string) $r['state_name']) ?> &middot; <?= (int) $r['town_count'] ?> town<?= (int) $r['town_count'] === 1 ? '' : 's' ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
