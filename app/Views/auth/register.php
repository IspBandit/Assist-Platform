<?php
/** @var \App\Core\View $this */
/** @var array $errors */
$this->extend('layouts.minimal');
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Create your account</h1>
    <form method="post" action="<?= e(url('register')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="honeypot" aria-hidden="true">
            <label>Leave this blank<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="form-group">
            <label for="name">Full name <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="<?= e_attr((string) old('name')) ?>" required>
            <?= $err('name') ?>
        </div>
        <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" id="email" name="email" value="<?= e_attr((string) old('email')) ?>" required autocomplete="email">
            <?= $err('email') ?>
        </div>
        <div class="form-group">
            <label for="phone">Mobile number</label>
            <input type="tel" id="phone" name="phone" value="<?= e_attr((string) old('phone')) ?>" autocomplete="tel">
        </div>
        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
            <p class="help">At least 10 characters.</p>
            <?= $err('password') ?>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password <span class="required">*</span></label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            <?= $err('password_confirmation') ?>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="consent_terms" value="1"> I agree to the <a href="<?= e(url('terms-of-use')) ?>" target="_blank">terms of use</a> <span class="required">*</span></label>
            <?= $err('consent_terms') ?>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="consent_privacy" value="1"> I have read the <a href="<?= e(url('privacy-policy')) ?>" target="_blank">privacy policy</a> <span class="required">*</span></label>
            <?= $err('consent_privacy') ?>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="marketing_opt_in" value="1"> Send me occasional updates (optional)</label>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
    </form>
    <p style="margin-top:1rem">Already have an account? <a href="<?= e(url('login')) ?>">Sign in</a></p>
</div>
<?php $this->endSection(); ?>
