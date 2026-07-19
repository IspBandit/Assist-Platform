<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card text-center">
    <h1>Access denied</h1>
    <p class="muted"><?= $this->e($message ?: 'You do not have permission to view this page.') ?></p>
    <div class="btn-row" style="justify-content:center">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
        <a class="btn btn-secondary" href="<?= e(url('login')) ?>">Sign in</a>
    </div>
</div>
<?php $this->endSection(); ?>
