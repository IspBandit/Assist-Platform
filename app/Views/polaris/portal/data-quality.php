<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1>Data quality</h1>
    <p>Managing <strong><?= $this->e($manufacturer['trading_name']) ?></strong>.</p>
    <p class="muted">Completeness checklist for your catalogue rows. This is guidance for edits — not a Platform Quality Gate or WCAG verdict.</p>

    <dl class="polaris-spec-glance">
        <div><dt>Models</dt><dd><?= (int) $report['model_count'] ?></dd></div>
        <div><dt>Variants</dt><dd><?= (int) $report['variant_count'] ?></dd></div>
        <div><dt>Complete variants</dt><dd><?= (int) $report['complete_variants'] ?> (<?= (int) $report['coverage_percent'] ?>%)</dd></div>
    </dl>

    <?php if ($report['models'] === []): ?>
        <p class="empty-state" role="status">No models linked to this manufacturer yet.</p>
    <?php else: ?>
        <ul class="polaris-account-list">
            <?php foreach ($report['models'] as $model): ?>
                <li>
                    <a href="<?= e(url('portal/manufacturer/models/' . (int) $model['id'])) ?>"><?= $this->e($model['name']) ?></a>
                    <span class="muted">
                        <?= $this->e($model['publication_status']) ?>
                        · <?= $this->e($model['verification_status']) ?>
                    </span>
                    <?php if ($model['gaps'] !== []): ?>
                        <span class="polaris-warn">Model: <?= $this->e(implode('; ', $model['gaps'])) ?></span>
                    <?php endif; ?>
                    <?php if ($model['variants'] !== []): ?>
                        <ul class="polaris-dq-variants">
                            <?php foreach ($model['variants'] as $variant): ?>
                                <li>
                                    <?= $this->e($variant['name']) ?>
                                    <?php if (!empty($variant['complete'])): ?>
                                        <span class="muted">— complete for ATM, length, berths, price guidance</span>
                                    <?php else: ?>
                                        <span class="polaris-warn">— <?= $this->e(implode('; ', $variant['gaps'])) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('portal/manufacturer/models')) ?>">Edit models</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/analytics')) ?>">Analytics</a>
    </div>
</div></section>
<?php $this->endSection(); ?>
