<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1>Polaris catalogue</h1>
    <p class="muted">Shared admin surface for the Polaris brand. Module: <code>rv_catalogue</code>.</p>
    <div class="stat-grid">
        <div class="stat-card"><strong><?= (int) $counts['manufacturers'] ?></strong><span>Manufacturers</span></div>
        <div class="stat-card"><strong><?= (int) $counts['models'] ?></strong><span>Models</span></div>
        <div class="stat-card"><strong><?= (int) $counts['published_models'] ?></strong><span>Published models</span></div>
    </div>
    <ul>
        <li><a href="<?= e(url('admin/polaris/manufacturers')) ?>">Manufacturers</a></li>
        <li><a href="<?= e(url('admin/polaris/models')) ?>">Models</a></li>
        <li><a href="<?= e(url('admin/polaris/recycle-bin')) ?>">Recycle bin</a></li>
    </ul>
</div>
<?php $this->endSection(); ?>
