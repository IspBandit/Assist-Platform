<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <h1>Floorplans</h1>
    <p>Accessible descriptions first. Imagery may be added when licensed manufacturer media is available.</p>
    <?php if ($floorplans === []): ?>
        <p class="empty-state">No published floorplans yet.</p>
    <?php else: ?>
        <div class="polaris-model-grid">
            <?php foreach ($floorplans as $fp): ?>
                <article class="polaris-model-card">
                    <div class="polaris-model-card-body">
                        <p class="polaris-model-meta"><?= $this->e($fp['manufacturer_name']) ?></p>
                        <h2><a href="<?= e(url('rvs/' . $fp['manufacturer_slug'] . '/' . $fp['model_slug'])) ?>"><?= $this->e($fp['model_name']) ?> — <?= $this->e($fp['title']) ?></a></h2>
                        <p><?= $this->e((string) ($fp['accessible_description'] ?? '')) ?></p>
                        <p class="muted"><?= $this->e(trim(implode(' · ', array_filter([
                            $fp['bed_configuration'] ?? null,
                            $fp['bathroom_position'] ? 'Bathroom: ' . $fp['bathroom_position'] : null,
                            $fp['kitchen_position'] ? 'Kitchen: ' . $fp['kitchen_position'] : null,
                        ])))) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
