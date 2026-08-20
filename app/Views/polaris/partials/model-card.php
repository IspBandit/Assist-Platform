<?php
/** @var array<string,mixed> $model */
$isDemo = !empty($model['is_demo']);
?>
<article class="polaris-model-card<?= $isDemo ? ' is-demo' : '' ?>">
    <div class="polaris-model-card-media" aria-hidden="true"></div>
    <div class="polaris-model-card-body">
        <?php if ($isDemo): ?><span class="badge badge-neutral">Demonstration fixture</span><?php endif; ?>
        <p class="polaris-model-meta"><?= $this->e($model['manufacturer_name'] ?? '') ?> · <?= $this->e($model['category_label'] ?? '') ?></p>
        <h3><a href="<?= e($model['url'] ?? '#') ?>"><?= $this->e($model['name'] ?? '') ?></a></h3>
        <ul class="polaris-model-stats">
            <?php if (!empty($model['sleeps'])): ?><li>Sleeps <?= (int) $model['sleeps'] ?></li><?php endif; ?>
            <?php if (!empty($model['body_length_m'])): ?><li><?= $this->e(number_format((float) $model['body_length_m'], 2)) ?> m</li><?php endif; ?>
            <?php if (!empty($model['tare_kg'])): ?><li>Tare <?= (int) $model['tare_kg'] ?> kg</li><?php endif; ?>
            <?php if (!empty($model['atm_kg'])): ?><li>ATM <?= (int) $model['atm_kg'] ?> kg</li><?php endif; ?>
            <?php if (isset($model['payload_kg']) && $model['payload_kg'] !== null): ?><li>Payload <?= (int) $model['payload_kg'] ?> kg</li><?php endif; ?>
        </ul>
        <p class="polaris-price"><?= $this->e($model['price_label'] ?? 'Price unavailable') ?></p>
        <p class="muted small">Status: <?= $this->e(ucfirst((string) ($model['production_status'] ?? 'current'))) ?> · <?= $this->e(ucfirst((string) ($model['verification_status'] ?? 'unverified'))) ?></p>
        <div class="polaris-card-actions">
            <a class="btn btn-secondary btn-sm" href="<?= e($model['url'] ?? '#') ?>">View details</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('compare?ids=' . (int) ($model['id'] ?? 0))) ?>">Compare</a>
        </div>
        <?php if (!empty($model['price_freshness']['stale'])): ?>
            <p class="polaris-warn"><?= $this->e((string) $model['price_freshness']['warning']) ?></p>
        <?php endif; ?>
    </div>
</article>
