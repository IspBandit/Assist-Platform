<?php
/** @var \App\Core\View $this */
/** @var array $errors */
$this->extend('layouts.minimal');
$cardClass = 'install-card';
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Configure VanAssist</h1>
    <p class="muted">Step 2 of 3 — Database, site, email and administrator</p>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error"><?= $this->e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('install')) ?>" class="stack">
        <?= csrf_field() ?>

        <fieldset>
            <legend>Database</legend>
            <div class="form-group">
                <label for="db_host">Database host</label>
                <input type="text" id="db_host" name="db_host" value="<?= e_attr((string) old('db_host', 'localhost')) ?>">
                <?= $err('db_host') ?>
            </div>
            <div class="form-group">
                <label for="db_port">Port</label>
                <input type="number" id="db_port" name="db_port" value="<?= e_attr((string) old('db_port', '3306')) ?>">
            </div>
            <div class="form-group">
                <label for="db_name">Database name <span class="required">*</span></label>
                <input type="text" id="db_name" name="db_name" value="<?= e_attr((string) old('db_name')) ?>" required>
                <?= $err('db_name') ?>
            </div>
            <div class="form-group">
                <label for="db_user">Database user <span class="required">*</span></label>
                <input type="text" id="db_user" name="db_user" value="<?= e_attr((string) old('db_user')) ?>" required>
                <?= $err('db_user') ?>
            </div>
            <div class="form-group">
                <label for="db_password">Database password</label>
                <input type="password" id="db_password" name="db_password" autocomplete="new-password">
            </div>
        </fieldset>

        <fieldset>
            <legend>Site</legend>
            <div class="form-group">
                <label for="site_name">Site name</label>
                <input type="text" id="site_name" name="site_name" value="<?= e_attr((string) old('site_name', 'VanAssist')) ?>">
            </div>
            <div class="form-group">
                <label for="app_url">Site URL <span class="required">*</span></label>
                <input type="url" id="app_url" name="app_url" placeholder="https://vanassist.example.com" value="<?= e_attr((string) old('app_url')) ?>" required>
                <?= $err('app_url') ?>
            </div>
            <div class="form-group">
                <label for="launch_mode">Launch mode</label>
                <select id="launch_mode" name="launch_mode">
                    <option value="private">Private (admins only)</option>
                    <option value="provider-onboarding">Provider onboarding</option>
                    <option value="local-pilot">Local pilot</option>
                    <option value="public">Public</option>
                </select>
                <p class="help">You can change this any time in admin settings.</p>
            </div>
        </fieldset>

        <fieldset>
            <legend>Email (SMTP) — optional now, required for live email</legend>
            <div class="form-group"><label for="mail_host">SMTP host</label><input type="text" id="mail_host" name="mail_host" value="<?= e_attr((string) old('mail_host')) ?>"></div>
            <div class="form-group"><label for="mail_port">SMTP port</label><input type="number" id="mail_port" name="mail_port" value="<?= e_attr((string) old('mail_port', '587')) ?>"></div>
            <div class="form-group"><label for="mail_username">SMTP username</label><input type="text" id="mail_username" name="mail_username" value="<?= e_attr((string) old('mail_username')) ?>"></div>
            <div class="form-group"><label for="mail_password">SMTP password</label><input type="password" id="mail_password" name="mail_password" autocomplete="new-password"></div>
            <div class="form-group"><label for="mail_from_address">From address</label><input type="email" id="mail_from_address" name="mail_from_address" value="<?= e_attr((string) old('mail_from_address')) ?>"></div>
            <div class="form-group"><label for="mail_from_name">From name</label><input type="text" id="mail_from_name" name="mail_from_name" value="<?= e_attr((string) old('mail_from_name', 'VanAssist')) ?>"></div>
        </fieldset>

        <fieldset>
            <legend>Super administrator</legend>
            <div class="form-group">
                <label for="admin_name">Your name <span class="required">*</span></label>
                <input type="text" id="admin_name" name="admin_name" value="<?= e_attr((string) old('admin_name')) ?>" required>
                <?= $err('admin_name') ?>
            </div>
            <div class="form-group">
                <label for="admin_email">Email <span class="required">*</span></label>
                <input type="email" id="admin_email" name="admin_email" value="<?= e_attr((string) old('admin_email')) ?>" required>
                <?= $err('admin_email') ?>
            </div>
            <div class="form-group">
                <label for="admin_password">Password <span class="required">*</span></label>
                <input type="password" id="admin_password" name="admin_password" autocomplete="new-password" required>
                <p class="help">At least 10 characters.</p>
                <?= $err('admin_password') ?>
            </div>
            <div class="form-group">
                <label for="admin_password_confirm">Confirm password <span class="required">*</span></label>
                <input type="password" id="admin_password_confirm" name="admin_password_confirm" autocomplete="new-password" required>
                <?= $err('admin_password_confirm') ?>
            </div>
        </fieldset>

        <div class="form-group">
            <label><input type="checkbox" name="seed_demo" value="1"> Insert clearly-labelled demo data (recommended for first look)</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">Install VanAssist</button>
    </form>
</div>
<?php $this->endSection(); ?>
