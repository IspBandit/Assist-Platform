<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Sign in</h1>
    <form method="post" action="<?= e(url('login')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="honeypot" aria-hidden="true">
            <label>Leave this blank<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e_attr((string) old('email')) ?>" required autofocus autocomplete="email">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
    </form>
    <p style="margin-top:1rem"><a href="<?= e(url('forgot-password')) ?>">Forgot your password?</a></p>
    <p>New to <?= $this->e(current_brand()->name()) ?>? <a href="<?= e(url('register')) ?>">Create an account</a></p>
</div>
<?php $this->endSection(); ?>
