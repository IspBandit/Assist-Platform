<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Your account</p>
    <h1>Saved searches</h1>
    <p class="muted">Filters you saved from Browse. Email and price alerts are not delivered yet — reopen a search anytime from here or Saved.</p>

    <?php if ($searches === []): ?>
        <p>No saved searches yet.</p>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('rvs')) ?>">Browse RVs</a>
            <a class="btn btn-ghost" href="<?= e(url('saved')) ?>">Saved shortlist</a>
        </div>
    <?php else: ?>
        <ul class="polaris-account-list">
            <?php foreach ($searches as $search): ?>
                <?php $path = (string) ($search['browse_path'] ?? '/rvs'); ?>
                <li>
                    <a href="<?= e(url(ltrim($path, '/'))) ?>"><?= $this->e((string) $search['name']) ?></a>
                    <span class="muted">Saved browse filters · notifications off</span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('rvs')) ?>">Browse RVs</a>
            <a class="btn btn-ghost" href="<?= e(url('saved')) ?>">Saved shortlist</a>
        </div>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
