<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card text-center">
    <h1>Page not found</h1>
    <p class="muted">We couldn't find the page you were looking for.</p>
    <p><?= $this->e($message ?? '') ?></p>
    <div class="btn-row" style="justify-content:center">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
        <a class="btn btn-secondary" href="<?= e(url('find')) ?>">Find a service</a>
    </div>
</div>
<?php $this->endSection(); ?>
