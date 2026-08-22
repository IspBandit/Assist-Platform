<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Your account</p>
    <h1><?= $this->e($title) ?></h1>
    <p class="muted"><?= $this->e($note) ?></p>
    <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('account/preferences')) ?>">Preferences</a>
        <a class="btn btn-ghost" href="<?= e(url('saved')) ?>">Saved models</a>
        <a class="btn btn-ghost" href="<?= e(url('compare')) ?>">Compare</a>
        <a class="btn btn-ghost" href="<?= e(url('tow-match')) ?>">Tow Match</a>
    </div>
</div></section>
<?php $this->endSection(); ?>
