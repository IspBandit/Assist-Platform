<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Reset your password</h1>
    <p class="muted">Enter your email and we'll send you a reset link.</p>
    <form method="post" action="<?= e(url('forgot-password')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Send reset link</button>
    </form>
    <p style="margin-top:1rem"><a href="<?= e(url('login')) ?>">Back to sign in</a></p>
</div>
<?php $this->endSection(); ?>
