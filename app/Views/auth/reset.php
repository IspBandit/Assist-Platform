<?php
/** @var \App\Core\View $this */
/** @var array $errors */
$this->extend('layouts.minimal');
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Choose a new password</h1>
    <?= $err('token') ?>
    <form method="post" action="<?= e(url('reset-password')) ?>" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e_attr($token) ?>">
        <input type="hidden" name="email" value="<?= e_attr($email) ?>">
        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
            <p class="help">At least 10 characters.</p>
            <?= $err('password') ?>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            <?= $err('password_confirmation') ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Reset password</button>
    </form>
</div>
<?php $this->endSection(); ?>
