<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <h1>Saved</h1>
    <h2>Shortlist</h2>
    <?php if ($models === []): ?>
        <p class="empty-state" role="status">No saved models yet. Save from a model page while signed in.</p>
    <?php else: ?>
        <ul class="polaris-account-list">
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
    <p class="muted">Reopen browse filters you saved. Email alerts are not sent yet.</p>
    <?php if ($searches === []): ?>
        <p class="empty-state" role="status">No saved searches yet. Apply filters on Browse, then save the search while signed in.</p>
        <p><a class="btn btn-primary" href="<?= e(url('rvs')) ?>">Browse RVs</a></p>
    <?php else: ?>
        <ul class="polaris-account-list">
            <?php foreach ($searches as $search): ?>
                <?php
                $path = (string) ($search['browse_path'] ?? '/rvs');
                $created = (string) ($search['created_at'] ?? '');
                $createdLabel = $created !== '' ? date('j M Y', strtotime($created)) : '';
                ?>
                <li>
                    <a href="<?= e(url(ltrim($path, '/'))) ?>"><?= $this->e((string) $search['name']) ?></a>
                    <span class="muted">
                        Browse filters
                        <?php if ($createdLabel !== ''): ?> · <?= $this->e($createdLabel) ?><?php endif; ?>
                    </span>
                    <form method="post" action="<?= e(url('saved/searches/remove')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="search_id" value="<?= (int) $search['id'] ?>">
                        <input type="hidden" name="return" value="/saved">
                        <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('account/comparisons')) ?>">Comparisons</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Manufacturer portal</a>
    </div>
</div></section>
<?php $this->endSection(); ?>
