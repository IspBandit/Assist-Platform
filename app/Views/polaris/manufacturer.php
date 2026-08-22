<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <?php if (!empty($manufacturer['is_demo'])): ?><p class="badge badge-neutral">Demonstration fixture</p><?php endif; ?>
    <h1><?= $this->e($manufacturer['trading_name']) ?></h1>
    <p><?= $this->e((string) ($manufacturer['description'] ?? '')) ?></p>
    <p class="muted">Claim: <?= $this->e($manufacturer['claim_status']) ?> · Verification: <?= $this->e($manufacturer['verification_status']) ?></p>
    <h2>Models</h2>
    <ul>
        <?php foreach ($models as $model): ?>
            <li><a href="<?= e(url('rvs/' . $manufacturer['slug'] . '/' . $model['slug'])) ?>"><?= $this->e($model['name']) ?></a> — <?= $this->e(ucfirst((string) $model['production_status'])) ?></li>
        <?php endforeach; ?>
    </ul>
</div></section>
<?php $this->endSection(); ?>
