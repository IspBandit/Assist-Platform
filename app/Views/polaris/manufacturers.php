<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <h1>Manufacturers</h1>
    <p>Claim-first onboarding will encourage matching an existing profile before creating a new one.</p>
    <div class="polaris-model-grid">
        <?php foreach ($manufacturers as $mfr): ?>
            <article class="polaris-model-card<?= !empty($mfr['is_demo']) ? ' is-demo' : '' ?>">
                <div class="polaris-model-card-body">
                    <?php if (!empty($mfr['is_demo'])): ?><span class="badge badge-neutral">Demonstration fixture</span><?php endif; ?>
                    <h2><a href="<?= e(url('manufacturers/' . $mfr['slug'])) ?>"><?= $this->e($mfr['trading_name']) ?></a></h2>
                    <p class="muted"><?= (int) ($mfr['model_count'] ?? 0) ?> published models · <?= $this->e(ucfirst((string) $mfr['claim_status'])) ?> · <?= $this->e(ucfirst((string) $mfr['verification_status'])) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php $this->endSection(); ?>
