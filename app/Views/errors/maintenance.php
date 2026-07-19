<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card text-center">
    <h1><?= $this->e($heading ?? 'We\'ll be back soon') ?></h1>
    <p class="muted"><?= $this->e($message ?: 'VanAssist is briefly offline for maintenance. Please check back soon.') ?></p>
    <p style="margin-top:1.5rem"><a class="btn btn-secondary" href="<?= e(url('login')) ?>">Staff sign in</a></p>
</div>
<?php $this->endSection(); ?>
