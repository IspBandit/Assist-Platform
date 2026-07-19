<?php
/** @var \App\Core\View $this */
/** @var array $requirements */
/** @var bool $allPassed */
$this->extend('layouts.minimal');
$cardClass = 'install-card';
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Install VanAssist</h1>
    <p class="muted">Step 1 of 3 — Server requirements</p>
    <p>Welcome. This wizard will check your server, create the database tables, seed the initial Queensland locations and service categories, and create your super administrator account.</p>

    <ul class="req-list">
        <?php foreach ($requirements as $req): ?>
            <li>
                <span><?= $this->e($req['label']) ?> <small class="muted">(<?= $this->e($req['detail']) ?>)</small></span>
                <span class="<?= $req['passed'] ? 'req-pass' : 'req-fail' ?>"><?= $req['passed'] ? 'Pass' : 'Check' ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="btn-row" style="margin-top:1.5rem">
        <?php if ($allPassed): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('install/setup')) ?>">Continue to configuration</a>
        <?php else: ?>
            <div class="alert alert-error" role="status">Please resolve the items marked "Check" (folder permissions or missing PHP extensions), then reload this page.</div>
            <a class="btn btn-secondary" href="<?= e(url('install')) ?>">Re-check requirements</a>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
