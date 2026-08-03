<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1>Manufacturer profile</h1>
    <p>Managing <strong><?= $this->e($manufacturer['trading_name']) ?></strong>. Changes return to pending verification.</p>
    <form method="post" action="<?= e(url('portal/manufacturer/profile')) ?>" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <label>Description
            <textarea name="description" rows="5"><?= $this->e((string) ($manufacturer['description'] ?? '')) ?></textarea>
        </label>
        <label>Website URL
            <input type="url" name="website_url" value="<?= $this->e((string) ($manufacturer['website_url'] ?? '')) ?>">
        </label>
        <label>Manufacturing location
            <input type="text" name="manufacturing_location" value="<?= $this->e((string) ($manufacturer['manufacturing_location'] ?? '')) ?>">
        </label>
        <label>Warranty summary
            <textarea name="warranty_summary" rows="3"><?= $this->e((string) ($manufacturer['warranty_summary'] ?? '')) ?></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Save profile</button>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a>
    </form>
</div></section>
<?php $this->endSection(); ?>
