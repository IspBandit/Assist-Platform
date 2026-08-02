<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1><?= $this->e($title) ?></h1>
    <p>Managing <strong><?= $this->e($manufacturer['trading_name']) ?></strong>.</p>
    <p class="muted">
        This <?= $this->e(str_replace('-', ' ', (string) $section)) ?> workspace remains a lightweight shell.
        Profile, media upload, dealer linking and team membership are available from the portal nav; deeper analytics remain planned.
    </p>
    <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('portal/manufacturer/models')) ?>">Your models</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer/claims')) ?>">Claims</a>
    </div>
</div></section>
<?php $this->endSection(); ?>
