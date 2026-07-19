<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card text-center">
    <h1>Invitation unavailable</h1>
    <p class="muted">This invitation link is invalid, has expired, or has already been used.</p>
    <p>If you believe this is an error, please contact the VanAssist team and we'll send a fresh invitation.</p>
    <div class="btn-row" style="justify-content:center">
        <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Contact us</a>
        <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Back to home</a>
    </div>
</div>
<?php $this->endSection(); ?>
