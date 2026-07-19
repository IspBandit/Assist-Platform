<?php
/** @var \App\Core\View $this */
/** @var bool $ok */
/** @var string $reference */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container text-center" style="max-width:640px">
        <?php if ($ok): ?>
            <h1>Request verified</h1>
            <div class="alert alert-success">Thanks — your request <strong><?= $this->e($reference) ?></strong> is verified and now with our team for review.</div>
        <?php else: ?>
            <h1>Verification link invalid</h1>
            <div class="alert alert-info">This verification link is invalid or has already been used. If your request was already verified, no further action is needed.</div>
        <?php endif; ?>
        <div class="btn-row" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to home</a>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
