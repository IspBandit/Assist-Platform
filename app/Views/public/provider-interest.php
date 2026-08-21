<?php
/** @var \App\Core\View $this */
/** @var array<string,string> $errors */
/** @var array<string,mixed>|null $listingProvider */
/** @var string $step */
/** @var list<array<string,mixed>> $matches */
/** @var array<string,mixed> $search */
$err = static fn (string $k): string => isset($errors[$k]) ? '<span class="field-error" style="display:block;color:#c0392b;font-size:.85rem;margin-top:.25rem">' . htmlspecialchars($errors[$k], ENT_QUOTES) . '</span>' : '';
$listingProvider = $listingProvider ?? null;
$step = $step ?? 'form';
$matches = $matches ?? [];
$search = $search ?? [];
$businessValue = (string) old('business_name');
if ($businessValue === '' && $listingProvider !== null) {
    $businessValue = (string) $listingProvider['business_name'];
}
if ($businessValue === '' && !empty($search['business_name'])) {
    $businessValue = (string) $search['business_name'];
}
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:760px">
        <a class="muted" href="<?= e(url('for-providers')) ?>">&laquo; Back to provider info</a>

        <?php if ($step === 'search'): ?>
            <h1 style="margin-top:.5rem">Find your business listing</h1>
            <p class="lead">Search before creating a new listing. If your business is already in the directory, claim or correct that profile instead.</p>
            <form method="post" action="<?= e(url('for-providers/register/search')) ?>" class="card stack" style="margin-top:1.5rem">
                <?= csrf_field() ?>
                <?php $this->include('partials.turnstile'); ?>
                <div class="form-group">
                    <label for="business_name">Business name <span style="color:#c0392b">*</span></label>
                    <input type="text" id="business_name" name="business_name" value="<?= e_attr($businessValue) ?>" required>
                    <?= $err('business_name') ?>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" style="position:relative">
                        <label for="town">Town you're based in</label>
                        <input type="text" id="town" name="town" value="<?= e_attr((string) ($search['town'] ?? old('town'))) ?>" placeholder="Start typing a town or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>">
                        <input type="hidden" id="region_id" name="region_id" value="<?= e_attr((string) ($search['base_town_id'] ?? old('region_id'))) ?>">
                        <div class="town-suggest" id="town-suggest" role="listbox" hidden></div>
                    </div>
                    <div class="form-group">
                        <label for="region">Region</label>
                        <input type="text" id="region" name="region" value="<?= e_attr((string) ($search['region'] ?? old('region'))) ?>" placeholder="Filled in from your town" readonly>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary btn-lg">Search directory</button>
                </div>
            </form>
        <?php elseif ($step === 'matches'): ?>
            <h1 style="margin-top:.5rem">Is this your business?</h1>
            <p class="lead">We found <?= count($matches) > 0 ? 'possible matches' : 'no close matches' ?> for <strong><?= e((string) ($search['business_name'] ?? '')) ?></strong><?php if (!empty($search['town'])): ?> near <?= e((string) $search['town']) ?><?php endif; ?>.</p>

            <?php if ($matches !== []): ?>
                <div class="stack" style="margin-top:1.25rem">
                    <?php foreach ($matches as $match): ?>
                        <article class="card" style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap">
                            <div>
                                <h2 style="margin:0;font-size:1.1rem"><?= e((string) $match['business_name']) ?></h2>
                                <?php if (!empty($match['town_name'])): ?>
                                    <p class="muted" style="margin:.35rem 0 0"><?= e((string) $match['town_name']) ?><?php if (!empty($match['state_abbr'])): ?>, <?= e((string) $match['state_abbr']) ?><?php endif; ?></p>
                                <?php endif; ?>
                                <?php if (!empty($match['is_unclaimed'])): ?><span class="badge badge-neutral">Unclaimed listing</span><?php endif; ?>
                            </div>
                            <div class="btn-row">
                                <a class="btn btn-primary" href="<?= e(url('for-providers/register?listing=' . rawurlencode((string) $match['slug']))) ?>">Claim or correct this listing</a>
                                <a class="btn btn-ghost" href="<?= e(url('providers/' . rawurlencode((string) $match['slug']))) ?>">View profile</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="margin-top:1.25rem"><p class="muted" style="margin:0">No similar listings were found. You can continue to register a new business if none of the suggestions above would apply.</p></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('for-providers/register/confirm-new')) ?>" class="card stack" style="margin-top:1.5rem">
                <?= csrf_field() ?>
                <?php $this->include('partials.turnstile'); ?>
                <label><input type="checkbox" name="confirm_none" value="1" required> None of these listings match my business — continue to register a new listing</label>
                <div class="btn-row">
                    <button type="submit" class="btn btn-secondary btn-lg">Continue with new listing</button>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('for-providers/register')) ?>">Search again</a>
                </div>
            </form>
        <?php else: ?>
        <h1 style="margin-top:.5rem"><?= $listingProvider !== null ? 'Request to claim or correct this listing' : 'Register your business' ?></h1>
        <p class="lead"><?= $listingProvider !== null ? 'Tell us who you are and how you are authorised to act for this business. We will review the request before giving anyone control of the listing.' : 'Tell us about your business so we can review the details and guide you through onboarding. Registration does not start billing or guarantee leads.' ?></p>

        <form method="post" action="<?= e(url('for-providers/register')) ?>" class="card stack" style="margin-top:1.5rem">
        <?= csrf_field() ?>
        <?php if ($listingProvider !== null): ?><input type="hidden" name="listing_slug" value="<?= e_attr((string) $listingProvider['slug']) ?>"><?php endif; ?>
        <?php $this->include('partials.turnstile'); ?>
            <div style="position:absolute;left:-9999px" aria-hidden="true">
                <label>Company URL <input type="text" name="company_url" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="business_name">Business name <span style="color:#c0392b">*</span></label>
                    <input type="text" id="business_name" name="business_name" value="<?= e_attr($businessValue) ?>" required>
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
                    <input type="text" id="town" name="town" value="<?= e_attr((string) ($search['town'] ?? old('town'))) ?>" placeholder="Start typing a town or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>">
                    <input type="hidden" id="region_id" name="region_id" value="<?= e_attr((string) ($search['base_town_id'] ?? old('region_id'))) ?>">
                    <div class="town-suggest" id="town-suggest" role="listbox" hidden></div>
                </div>
                <div class="form-group">
                    <label for="region">Region</label>
                    <input type="text" id="region" name="region" value="<?= e_attr((string) ($search['region'] ?? old('region'))) ?>" placeholder="Filled in from your town" readonly>
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
                <textarea id="message" name="message" rows="4" placeholder="<?= $listingProvider !== null ? 'Explain your role and any details that need correcting.' : 'Add any information that will help us review the business.' ?>"><?= e((string) old('message')) ?></textarea>
            </div>

            <div class="form-group consent-choice">
                <label><input type="checkbox" name="marketing_opt_in" value="1" <?= old('marketing_opt_in') ? 'checked' : '' ?>> I would also like occasional VanAssist provider news and offers by email.</label>
                <p class="muted small">Optional and unticked by default. Onboarding or claim-request contact is separate from promotional email, and you can unsubscribe at any time.</p>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary btn-lg">Register interest</button>
                <a class="btn btn-outline btn-lg" href="<?= e(url('how-it-works')) ?>">How it works</a>
            </div>
            <p class="muted" style="font-size:.85rem">By submitting, you consent to contact about this onboarding or listing request. We use the details to review authority, prevent misuse and respond to you. See our <a href="<?= e(url('privacy-policy')) ?>">privacy policy</a>. A submission does not prove ownership or grant listing access.</p>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
