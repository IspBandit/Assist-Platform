<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p><a href="<?= e(url('portal/manufacturer/models')) ?>">← Models</a></p>
    <h1><?= $this->e($model['name']) ?></h1>
    <form method="post" action="<?= e(url('portal/manufacturer/models/save')) ?>" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $model['id'] ?>">
        <label>Description
            <textarea name="description" rows="6"><?= $this->e((string) ($model['description'] ?? '')) ?></textarea>
        </label>
        <label>Production status
            <select name="production_status">
                <?php foreach (['current','upcoming','superseded','discontinued'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $model['production_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Save for review</button>
    </form>
    <h2>Variants</h2>
    <ul>
        <?php foreach ($variants as $variant): ?>
            <li><?= $this->e($variant['name']) ?> — ATM <?= $variant['atm_kg'] !== null ? (int) $variant['atm_kg'] . ' kg' : 'unknown' ?></li>
        <?php endforeach; ?>
    </ul>
</div></section>
<?php $this->endSection(); ?>
