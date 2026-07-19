<?php
/** @var \App\Core\View $this */
/** @var string $reference */
/** @var bool $needsVerification */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container text-center" style="max-width:640px">
        <h1>Request received</h1>
        <p>Your reference is <strong style="font-size:1.25rem"><?= $this->e($reference) ?></strong></p>
        <?php if ($needsVerification): ?>
            <div class="alert alert-info">Please check your email and click the verification link to confirm your request. We can't start matching until it's verified.</div>
        <?php else: ?>
            <div class="alert alert-success">Your request is now with our team for review. We'll be in touch as we find suitable providers.</div>
        <?php endif; ?>
        <p class="muted">Keep your reference handy for any correspondence.</p>
        <div class="btn-row" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to home</a>
            <?php if (auth()->check()): ?>
                <a class="btn btn-ghost" href="<?= e(url('account/requests')) ?>">View my requests</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
