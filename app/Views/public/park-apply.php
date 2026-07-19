<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $towns */
/** @var array $errors */
$this->extend('layouts.public');
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:760px">
        <h1>Become a caravan park partner</h1>
        <p class="muted">Help your guests find trusted caravan and RV service providers. Display your own QR code, register guest requests, and see service runs forming nearby. It's free for participating parks during launch.</p>

        <form method="post" action="<?= e(url('caravan-parks/apply')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="honeypot" aria-hidden="true"><label>Leave this blank<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="card stack">
                <h2 style="margin-top:0">About your park</h2>
                <div class="form-group">
                    <label for="park_name">Park name <span class="required">*</span></label>
                    <input type="text" id="park_name" name="park_name" required value="<?= e_attr((string) old('park_name')) ?>">
                    <?= $err('park_name') ?>
                </div>
                <div class="form-group">
                    <label for="town_id">Nearest town</label>
                    <select id="town_id" name="town_id">
                        <option value="">Select a town…</option>
                        <?php foreach ($towns as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (int) old('town_id') === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label for="address">Address</label><input type="text" id="address" name="address" value="<?= e_attr((string) old('address')) ?>"></div>
                    <div class="form-group"><label for="number_of_sites">Number of sites</label><input type="number" min="0" id="number_of_sites" name="number_of_sites" value="<?= e_attr((string) old('number_of_sites')) ?>"></div>
                </div>
                <div class="form-group"><label for="phone">Park phone</label><input type="text" id="phone" name="phone" value="<?= e_attr((string) old('phone')) ?>"></div>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Your login</h2>
                <div class="form-group">
                    <label for="contact_name">Your name <span class="required">*</span></label>
                    <input type="text" id="contact_name" name="contact_name" required value="<?= e_attr((string) old('contact_name')) ?>">
                    <?= $err('contact_name') ?>
                </div>
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required value="<?= e_attr((string) old('email')) ?>">
                    <?= $err('email') ?>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
                        <?= $err('password') ?>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm password <span class="required">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        <?= $err('password_confirmation') ?>
                    </div>
                </div>
            </div>

            <div class="card stack">
                <label><input type="checkbox" name="consent_terms" value="1"> I agree to the <a href="<?= e(url('terms-of-use')) ?>" target="_blank" rel="noopener">terms of use</a> and <a href="<?= e(url('privacy-policy')) ?>" target="_blank" rel="noopener">privacy policy</a>. <span class="required">*</span></label>
                <?= $err('consent_terms') ?>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Apply to partner</button>
                <a class="btn btn-ghost" href="<?= e(url('for-caravan-parks')) ?>">Learn more</a>
            </div>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
