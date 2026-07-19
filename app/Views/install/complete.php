<?php
/** @var \App\Core\View $this */
$this->extend('layouts.minimal');
?>
<?php $this->section('content'); ?>
<div class="card text-center">
    <h1>Installation complete</h1>
    <p class="muted">Step 3 of 3</p>
    <p>VanAssist is installed and the installer is now locked. For security you should:</p>
    <ul style="text-align:left">
        <li>Confirm your <code>.env</code> file is not web accessible.</li>
        <li>Set up the cron jobs from <code>INSTALL-CPANEL.md</code>.</li>
        <li>Configure SMTP email and send a test.</li>
        <li>Review the launch mode in admin settings.</li>
    </ul>
    <div class="btn-row" style="justify-content:center;margin-top:1.5rem">
        <a class="btn btn-primary btn-lg" href="<?= e(url('login')) ?>">Sign in to admin</a>
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>">View site</a>
    </div>
</div>
<?php $this->endSection(); ?>
