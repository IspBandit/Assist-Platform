<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Claim link unavailable</h1>
    <p class="muted">This claim link is invalid, expired or the listing has already been claimed. Contact VanAssist if you need a new link.</p>
    <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Contact VanAssist</a>
</div>
<?php $this->endSection(); ?>
