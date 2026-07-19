<?php
/** @var \App\Core\View $this */
/** @var string $token */
/** @var string $email */
/** @var string $businessName */
/** @var string $contactName */
/** @var array<string,string> $formErrors */
$this->extend('layouts.minimal');
$err = static fn (string $k) => isset($formErrors[$k]) ? '<p class="field-error">' . e($formErrors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1><?= !empty($isClaim) ? 'Claim your provider profile' : 'Create your provider profile' ?></h1>
    <p class="muted"><?php if (!empty($isClaim)): ?>
        You are claiming <strong><?= $this->e($businessName) ?></strong><?php if (!empty($townName)): ?> in <?= $this->e($townName) ?><?php endif; ?>. Existing services and coverage from the directory will stay in place.
    <?php else: ?>
        You've been invited to join VanAssist. Set up your login below — our team will review your profile before it goes live.
    <?php endif; ?></p>

    <?php if ($formErrors !== []): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom:1rem">
            <strong>Please check the following:</strong>
            <ul style="margin:.5rem 0 0 1.1rem">
                <?php foreach ($formErrors as $message): ?>
                    <li><?= e((string) $message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('provider/join/' . $token)) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" value="<?= e_attr($email) ?>" disabled>
            <p class="muted" style="font-size:.85rem">Your invitation is tied to this address.</p>
            <?= $err('email') ?>
        </div>
        <div class="form-group">
            <label for="business_name">Business name <span class="required">*</span></label>
            <input type="text" id="business_name" name="business_name" value="<?= e_attr($businessName) ?>" required>
            <?= $err('business_name') ?>
        </div>
        <div class="form-group">
            <label for="contact_name">Your name <span class="required">*</span></label>
            <input type="text" id="contact_name" name="contact_name" value="<?= e_attr($contactName) ?>" required>
            <?= $err('contact_name') ?>
        </div>
        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
            <p class="muted" style="font-size:.85rem">At least 10 characters.</p>
            <?= $err('password') ?>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password <span class="required">*</span></label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            <?= $err('password_confirmation') ?>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="consent_terms" value="1"> I agree to the <a href="<?= e(url('provider-terms')) ?>" target="_blank">provider terms</a> <span class="required">*</span></label>
            <?= $err('consent_terms') ?>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Create profile</button>
    </form>
</div>
<?php $this->endSection(); ?>
