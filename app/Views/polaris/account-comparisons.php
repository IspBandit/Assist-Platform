<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Your account</p>
    <h1>Saved comparisons</h1>
    <p class="muted">Shareable links you created from Compare while signed in. Guest shares are not listed here.</p>

    <?php if ($comparisons === []): ?>
        <p class="empty-state" role="status">No saved comparisons yet. Open Compare, select models, then create a shareable link while signed in.</p>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('compare')) ?>">Compare models</a>
            <a class="btn btn-ghost" href="<?= e(url('account/preferences')) ?>">Preferences</a>
            <a class="btn btn-ghost" href="<?= e(url('saved')) ?>">Saved models</a>
        </div>
    <?php else: ?>
        <ul class="polaris-account-list">
            <?php foreach ($comparisons as $row): ?>
                <?php
                $token = (string) ($row['token'] ?? '');
                $href = url('compare/' . $token);
                $created = (string) ($row['created_at'] ?? '');
                $createdLabel = $created !== '' ? date('j M Y, g:ia', strtotime($created)) : '';
                ?>
                <li>
                    <a href="<?= e($href) ?>"><?= $this->e((string) $row['title']) ?></a>
                    <span class="muted">
                        <?= (int) ($row['model_count'] ?? 0) ?> model<?= ((int) ($row['model_count'] ?? 0)) === 1 ? '' : 's' ?>
                        <?php if ($createdLabel !== ''): ?> · <?= $this->e($createdLabel) ?><?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('compare')) ?>">New comparison</a>
            <a class="btn btn-ghost" href="<?= e(url('account/preferences')) ?>">Preferences</a>
            <a class="btn btn-ghost" href="<?= e(url('saved')) ?>">Saved models</a>
        </div>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
