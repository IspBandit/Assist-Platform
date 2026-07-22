<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $provider */
/** @var array<int,array<string,mixed>> $services */
/** @var array<int,array<string,mixed>> $areas */
/** @var array<int,array<string,mixed>> $licences */
/** @var array<int,array<string,mixed>> $runs */
/** @var array<string,mixed>|null $promotionAd */
$this->extend('layouts.public');

$isUnclaimed = !empty($provider['is_unclaimed']);

// Unclaimed listings expose the contact details the business already publishes;
// managed providers honour their own show_public_* preferences.
$showPhone = !empty($provider['show_public_phone']) || $isUnclaimed;
$phone = $showPhone ? trim((string) ($provider['public_phone'] ?? '') ?: (string) ($provider['phone'] ?? '')) : '';
$showPhone = $phone !== '';
$telHref = 'tel:' . preg_replace('/[^0-9+]/', '', $phone);

$showEmail = !empty($provider['show_public_email']) || $isUnclaimed;
$email = $showEmail ? trim((string) ($provider['public_email'] ?? '') ?: (string) ($provider['email'] ?? '')) : '';
$showEmail = $email !== '';

$website = trim((string) ($provider['website'] ?? ''));
$address = trim((string) ($provider['street_address'] ?? ''));
$townName = (string) ($provider['town_name'] ?? '');
$model = (string) ($provider['service_model'] ?? '');
$isWorkshop = in_array($model, ['workshop', 'both'], true);

// Build a maps "Get directions" destination only when there is a fixed place to
// navigate to (workshop/both with a non-mobile address). Mobile-only businesses
// travel to the customer, so directions to them are not offered.
$canNavigate = $isWorkshop && $address !== '' && stripos($address, 'mobile') === false;
$navDest = '';
if ($canNavigate) {
    $navDest = $address;
    if ($townName !== '' && stripos($address, $townName) === false) {
        $navDest .= ', ' . $townName;
    }
    if (!empty($provider['state_abbr'])) {
        $navDest .= ' ' . (string) $provider['state_abbr'];
    }
    if (!empty($provider['town_postcode'])) {
        $navDest .= ' ' . (string) $provider['town_postcode'];
    }
}
$mapsUrl = $navDest !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($navDest) : '';
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> /
            <a href="<?= e(url('providers')) ?>">Providers</a> /
            <?= $this->e((string) $provider['business_name']) ?>
        </nav>

        <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
            <div>
                <h1 style="margin-bottom:.25rem"><?= $this->e((string) $provider['business_name']) ?></h1>
                <p>
                    <?= $provider['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                    <?= $provider['insurance_verified'] ? '<span class="badge badge-verified">Insured</span> ' : '' ?>
                    <?= $provider['is_founding_provider'] ? '<span class="badge badge-confirmed">Founding provider</span> ' : '' ?>
                    <?= !empty($provider['is_unclaimed']) ? '<span class="badge badge-neutral">Unclaimed listing</span> ' : '' ?>
                    <span class="badge badge-neutral"><?= $this->e(ucfirst((string) $provider['service_model'])) ?></span>
                </p>
                <?php if ($provider['town_name']): ?>
                    <p class="muted">Based in <a href="<?= e(url('towns/' . $provider['town_slug'])) ?>"><?= $this->e((string) $provider['town_name']) ?></a><?php if ($provider['region_name']): ?>, <?= $this->e((string) $provider['region_name']) ?><?php endif; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($provider['is_unclaimed'])): ?>
            <div class="card stack" style="margin-top:1rem;border-left:4px solid #c9a227">
                <p style="margin:0"><strong>This is an unclaimed listing.</strong> The details were compiled from publicly available sources and the business has not yet verified them with <?= $this->e($brand->name()) ?>.</p>
                <p class="muted" style="margin:.25rem 0 0">Is this your business? Ask <?= $this->e($brand->name()) ?> to <a href="<?= e(url('contact')) ?>">send you a claim link</a> to verify and manage this profile.</p>
            </div>
        <?php endif; ?>

        <?php if ($promotionAd !== null && !empty($provider['is_featured'])): ?>
            <div class="provider-promo-wrap card" style="margin-top:1rem;padding:0;overflow:hidden">
                <p class="muted" style="font-size:.8rem;margin:0;padding:.5rem 1rem;border-bottom:1px solid #e3e0d8">Sponsored local provider</p>
                <?php $this->include('partials.provider-promotion-ad', [
                    'promo' => $promotionAd,
                    'alt'   => (string) ($promotionAd['headline'] ?? $provider['business_name']),
                ]); ?>
            </div>
        <?php endif; ?>

        <?php if ($provider['description']): ?>
            <div class="card stack" style="margin-top:1rem"><?= nl2br($this->e((string) $provider['description'])) ?></div>
        <?php endif; ?>

        <div class="grid grid-2" style="margin-top:1rem">
            <div class="card stack">
                <h2 style="margin-top:0">Services</h2>
                <?php if ($services === []): ?>
                    <p class="muted">Service details coming soon.</p>
                <?php else: ?>
                    <div class="btn-row">
                        <?php foreach ($services as $s): ?>
                            <a class="btn btn-ghost" href="<?= e(url('services/' . $s['slug'])) ?>"><?= $this->e((string) $s['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($areas !== []): ?>
                    <h3>Service areas</h3>
                    <ul>
                        <?php foreach ($areas as $a): ?>
                            <li><?= $this->e((string) ($a['town_name'] ?? $a['region_name'] ?? $a['state_name'] ?? $a['label'] ?? ($a['radius_km'] ? $a['radius_km'] . ' km radius' : ucfirst((string) $a['area_type'])))) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($licences !== []): ?>
                    <h3>Verified credentials</h3>
                    <ul>
                        <?php foreach ($licences as $l): ?>
                            <li><?= $this->e((string) $l['licence_type']) ?><?php if ($l['issuing_authority']): ?> <span class="muted">— <?= $this->e((string) $l['issuing_authority']) ?></span><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Contact</h2>
                <?php if ($address !== ''): ?>
                    <p style="margin:0"><strong><?= $isWorkshop ? 'Address' : 'Service area' ?>:</strong> <?= $this->e($address) ?></p>
                <?php endif; ?>
                <?php $slug = (string) $provider['slug']; ?>
                <?php if ($showPhone || $showEmail || $website !== ''): ?>
                    <ul class="list-plain">
                        <?php if ($showPhone): ?><li><strong>Phone:</strong> <a href="<?= e(url('go/phone/' . $slug)) ?>"><?= $this->e($phone) ?></a></li><?php endif; ?>
                        <?php if ($showEmail): ?><li><strong>Email:</strong> <a href="<?= e(url('go/email/' . $slug)) ?>"><?= $this->e($email) ?></a></li><?php endif; ?>
                        <?php if ($website !== ''): ?><li><strong>Website:</strong> <a href="<?= e(url('go/website/' . $slug)) ?>" target="_blank" rel="noopener nofollow">Visit website</a></li><?php endif; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($showPhone || $mapsUrl !== '' || $showEmail): ?>
                    <div class="btn-row">
                        <?php if ($showPhone): ?><a class="btn btn-primary" href="<?= e(url('go/phone/' . $slug)) ?>">Call now</a><?php endif; ?>
                        <?php if ($mapsUrl !== ''): ?><a class="btn btn-ghost" href="<?= e(url('go/directions/' . $slug)) ?>" target="_blank" rel="noopener">Get directions</a><?php endif; ?>
                        <?php if ($showEmail): ?><a class="btn btn-ghost" href="<?= e(url('go/email/' . $slug)) ?>">Email</a><?php endif; ?>
                    </div>
                    <?php if ($mapsUrl !== ''): ?>
                        <p class="muted" style="font-size:.85rem;margin:.25rem 0 0">Opens your maps app and navigates from your current location.</p>
                    <?php elseif (!$isWorkshop): ?>
                        <p class="muted" style="font-size:.85rem;margin:.25rem 0 0">This is a mobile business that travels to you — give them a call to arrange a visit.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <p class="muted">VanAssist connects travellers with providers. Register a request and we'll coordinate assistance.</p>
                <a class="btn btn-primary" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
                <?php if (current_user() !== null): ?>
                    <form method="post" action="<?= e(url('account/providers/save')) ?>" style="margin-top:.5rem">
                        <?= csrf_field() ?>
                        <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm">Save provider</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($runs !== []): ?>
            <h2 style="margin-top:2rem">Upcoming service runs</h2>
            <div class="grid grid-3">
                <?php foreach ($runs as $r): ?>
                    <div class="card stack">
                        <h3 style="margin:0"><?= $this->e((string) $r['title']) ?></h3>
                        <p class="muted" style="margin:0"><?= $this->e(ucfirst((string) $r['status'])) ?><?php if ($r['start_date']): ?> · from <?= $this->e((string) $r['start_date']) ?><?php endif; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
