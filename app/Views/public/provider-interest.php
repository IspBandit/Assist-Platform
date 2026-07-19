<?php
/** @var \App\Core\View $this */
/** @var array<string,string> $errors */
$err = static fn (string $k): string => isset($errors[$k]) ? '<span class="field-error" style="display:block;color:#c0392b;font-size:.85rem;margin-top:.25rem">' . htmlspecialchars($errors[$k], ENT_QUOTES) . '</span>' : '';
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:760px">
        <a class="muted" href="<?= e(url('for-providers')) ?>">&laquo; Back to provider info</a>
        <h1 style="margin-top:.5rem">Register your interest</h1>
        <p class="lead">Tell us about your business and we'll send your secure onboarding link as provider sign-ups open. Free founding-provider access during launch — no fees, and you only ever accept the work you want.</p>

        <form method="post" action="<?= e(url('for-providers/register')) ?>" class="card stack" style="margin-top:1.5rem">
            <?= csrf_field() ?>
            <div style="position:absolute;left:-9999px" aria-hidden="true">
                <label>Company URL <input type="text" name="company_url" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="business_name">Business name <span style="color:#c0392b">*</span></label>
                    <input type="text" id="business_name" name="business_name" value="<?= e_attr((string) old('business_name')) ?>" required>
                    <?= $err('business_name') ?>
                </div>
                <div class="form-group">
                    <label for="contact_name">Your name</label>
                    <input type="text" id="contact_name" name="contact_name" value="<?= e_attr((string) old('contact_name')) ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e_attr((string) old('email')) ?>" placeholder="you@business.com.au">
                    <?= $err('email') ?>
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" value="<?= e_attr((string) old('phone')) ?>" placeholder="04xx xxx xxx">
                </div>
            </div>
            <p class="muted" style="margin:-.5rem 0 0;font-size:.85rem">Give us at least one of email or phone so we can reach you.</p>

            <div class="grid grid-2">
                <div class="form-group" style="position:relative">
                    <label for="town">Town you're based in</label>
                    <input type="text" id="town" name="town" value="<?= e_attr((string) old('town')) ?>" placeholder="Start typing a town or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>">
                    <input type="hidden" id="region_id" name="region_id" value="<?= e_attr((string) old('region_id')) ?>">
                    <div class="town-suggest" id="town-suggest" role="listbox" hidden></div>
                </div>
                <div class="form-group">
                    <label for="region">Region</label>
                    <input type="text" id="region" name="region" value="<?= e_attr((string) old('region')) ?>" placeholder="Filled in from your town" readonly>
                </div>
            </div>

            <fieldset class="form-group" style="border:1px solid #e3e0d8;border-radius:10px;padding:1rem">
                <legend style="font-weight:600;padding:0 .4rem">Do you offer a mobile service? <span style="color:#c0392b">*</span></legend>
                <p class="muted" style="margin:.25rem 0 .6rem;font-size:.9rem">A mobile service means you travel to the customer's caravan or RV (on-site).</p>
                <?php $om = (string) old('offers_mobile'); ?>
                <label style="display:inline-flex;align-items:center;gap:.4rem;margin-right:1.25rem">
                    <input type="radio" name="offers_mobile" value="yes" <?= $om === 'yes' ? 'checked' : '' ?>> Yes, I travel to customers
                </label>
                <label style="display:inline-flex;align-items:center;gap:.4rem">
                    <input type="radio" name="offers_mobile" value="no" <?= $om === 'no' ? 'checked' : '' ?>> No, I don't
                </label>
                <?= $err('offers_mobile') ?>

                <div style="margin-top:.9rem">
                    <span style="font-weight:600">Do you also have a workshop customers can visit?</span>
                    <?php $hw = (string) old('has_workshop'); ?>
                    <div style="margin-top:.4rem">
                        <label style="display:inline-flex;align-items:center;gap:.4rem;margin-right:1.25rem">
                            <input type="radio" name="has_workshop" value="yes" <?= $hw === 'yes' ? 'checked' : '' ?>> Yes
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:.4rem">
                            <input type="radio" name="has_workshop" value="no" <?= $hw === 'no' ? 'checked' : '' ?>> No
                        </label>
                    </div>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="services">Services you offer</label>
                <input type="text" id="services" name="services" value="<?= e_attr((string) old('services')) ?>" placeholder="e.g. caravan servicing, brakes &amp; bearings, 12-volt, gas">
            </div>

            <div class="form-group">
                <label for="message">Anything else? (optional)</label>
                <textarea id="message" name="message" rows="4"><?= e((string) old('message')) ?></textarea>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary btn-lg">Register interest</button>
                <a class="btn btn-outline btn-lg" href="<?= e(url('how-it-works')) ?>">How it works</a>
            </div>
            <p class="muted" style="font-size:.85rem">By submitting, you consent to VanAssist contacting you about provider onboarding. We don't share your details.</p>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
