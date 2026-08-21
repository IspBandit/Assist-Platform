<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <h1>Manufacturer portal</h1>
    <p>Managing <strong><?= $this->e($manufacturer['trading_name']) ?></strong>. Changes are audit-logged and return to pending verification.</p>
    <div class="polaris-card-actions">
        <a class="btn btn-primary" href="<?= e(url('portal/manufacturer/models')) ?>">Your models</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/profile')) ?>">Profile</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/media')) ?>">Media</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/dealers')) ?>">Dealers</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/analytics')) ?>">Analytics</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/team')) ?>">Team</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/data-quality')) ?>">Data quality</a>
        <a class="btn btn-ghost" href="<?= e(url('manufacturers/' . $manufacturer['slug'])) ?>">Public page</a>
    </div>
    <h2>Published models</h2>
    <ul>
        <?php foreach ($models as $model): ?>
            <li><a href="<?= e(url('portal/manufacturer/models/' . $model['id'])) ?>"><?= $this->e($model['name']) ?></a> — <?= $this->e($model['production_status']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($models === []): ?><p class="muted">No models yet.</p><?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
