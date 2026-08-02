<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <h1>Saved</h1>
    <h2>Shortlist</h2>
    <?php if ($models === []): ?>
        <p class="empty-state">No saved models yet. Save from a model page while signed in.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($models as $model): ?>
                <li>
                    <a href="<?= e(url('rvs/' . $model['manufacturer_slug'] . '/' . $model['slug'])) ?>"><?= $this->e($model['manufacturer_name'] . ' ' . $model['name']) ?></a>
                    <form method="post" action="<?= e(url('saved/models/remove')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="model_id" value="<?= (int) $model['id'] ?>">
                        <input type="hidden" name="return" value="/saved">
                        <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <h2>Saved searches</h2>
    <?php if ($searches === []): ?>
        <p class="muted">Saved search alerts are ready in the schema; UI capture expands next.</p>
    <?php else: ?>
        <ul><?php foreach ($searches as $search): ?><li><?= $this->e($search['name']) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <p><a class="btn btn-secondary" href="<?= e(url('portal/manufacturer')) ?>">Manufacturer portal</a></p>
</div></section>
<?php $this->endSection(); ?>
