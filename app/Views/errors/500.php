<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card text-center">
    <h1><?= $this->e((string) ($status ?? 500)) ?> — Something went wrong</h1>
    <p class="muted"><?= $this->e($message ?: 'A server error occurred. Our team has been notified. Please try again shortly.') ?></p>
    <div class="btn-row" style="justify-content:center">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
    </div>
</div>
<?php $this->endSection(); ?>
