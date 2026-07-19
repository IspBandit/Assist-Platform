<?php
/** @var \App\Core\View $this */
/** @var string $token */
/** @var string $email */
/** @var string $businessName */
/** @var string $townName */
/** @var array<int,array<string,mixed>> $services */
/** @var array<string,string> $formErrors */
$this->extend('layouts.minimal');
$err = static fn (string $k) => isset($formErrors[$k]) ? '<p class="field-error">' . e($formErrors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1>Claim your listing</h1>
    <p class="muted">You are claiming <strong><?= $this->e($businessName) ?></strong><?php if ($townName !== ''): ?> in <?= $this->e($townName) ?><?php endif; ?> on VanAssist. Your imported services and coverage will stay in place — review and complete your profile after signing in.</p>
    <?php if (!empty($launchOffer)): ?>
        <p class="muted" style="padding:.75rem 1rem;background:#eef9f7;border-left:4px solid #0f6e6e;margin:0 0 1rem"><strong>Launch offer:</strong> Verify your profile to receive <strong>free local ad graphics</strong> (desktop + mobile, worth $99) for travellers searching near <?= $townName !== '' ? $this->e($townName) : 'your area' ?>.</p>
    <?php endif; ?>
    <?php if ($services !== []): ?>
        <p class="muted">Services on file: <?= $this->e(implode(', ', array_column($services, 'name'))) ?></p>
    <?php endif; ?>

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

    <form method="post" action="<?= e(url('provider/claim/' . $token)) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" value="<?= e_attr($email) ?>" disabled>
            <?= $err('email') ?>
        </div>
        <div class="form-group">
            <label for="contact_name">Your name <span class="required">*</span></label>
            <input type="text" id="contact_name" name="contact_name" value="<?= e_attr((string) old('contact_name')) ?>" required>
            <?= $err('contact_name') ?>
        </div>
        <div class="form-group">
            <label for="password">Choose a password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
            <?= $err('password') ?>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password <span class="required">*</span></label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            <?= $err('password_confirmation') ?>
        </div>
        <label><input type="checkbox" name="consent_terms" value="1" required> I agree to the <a href="<?= e(url('provider-terms')) ?>" target="_blank">provider terms</a></label>
        <?= $err('consent_terms') ?>
        <button type="submit" class="btn btn-primary btn-lg">Claim listing &amp; sign in</button>
    </form>
</div>
<?php $this->endSection(); ?>
