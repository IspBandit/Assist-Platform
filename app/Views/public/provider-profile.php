<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
$isUnclaimed = !empty($provider['is_unclaimed']);
$showPhone = (!empty($provider['show_public_phone']) || $isUnclaimed);
$phone = $showPhone ? trim((string) (($provider['public_phone'] ?? '') ?: ($provider['phone'] ?? ''))) : '';
$showPhone = $phone !== '';
$showEmail = (!empty($provider['show_public_email']) || $isUnclaimed);
$email = $showEmail ? trim((string) (($provider['public_email'] ?? '') ?: ($provider['email'] ?? ''))) : '';
$showEmail = $email !== '';
$website = trim((string) ($provider['website'] ?? ''));
$address = trim((string) ($provider['street_address'] ?? ''));
$townName = (string) ($provider['town_name'] ?? '');
$model = (string) ($provider['service_model'] ?? '');
$isWorkshop = in_array($model, ['workshop', 'both'], true);
$isMobile = in_array($model, ['mobile', 'both'], true);
$name = (string) $provider['business_name'];
$initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
$slug = (string) $provider['slug'];
$canNavigate = $isWorkshop && $address !== '' && stripos($address, 'mobile') === false;
$locationLabel = $townName;
if ($locationLabel !== '' && !empty($provider['state_abbr'])) { $locationLabel .= ', ' . $provider['state_abbr']; }
?>
<?php $this->section('content'); ?>
<section class="profile-hero">
    <div class="container">
        <nav aria-label="Breadcrumb" class="breadcrumbs"><a href="<?= e(url('/')) ?>">Home</a><span aria-hidden="true">/</span><a href="<?= e(url('providers')) ?>">Directory</a><span aria-hidden="true">/</span><span><?= $this->e($name) ?></span></nav>
        <div class="profile-heading">
            <span class="profile-avatar" aria-hidden="true"><?= e($initial) ?></span>
            <div class="profile-heading-copy">
                <div class="profile-badges">
                    <?php if (!empty($provider['is_verified'])): ?><span class="badge badge-verified">Verified business</span><?php endif; ?>
                    <?php if (!empty($provider['insurance_verified'])): ?><span class="badge badge-verified">Insurance verified</span><?php endif; ?>
                    <?php if (!empty($provider['is_featured'])): ?><span class="badge badge-sponsored">Featured</span><?php endif; ?>
                    <?php if ($isUnclaimed): ?><span class="badge badge-neutral">Details not yet claimed</span><?php endif; ?>
                </div>
                <h1><?= $this->e($name) ?></h1>
                <p class="profile-subtitle"><?php if ($locationLabel !== ''): ?><?= $this->e($locationLabel) ?> · <?php endif; ?><?= $isMobile && $isWorkshop ? 'Mobile and workshop service' : ($isMobile ? 'Mobile service' : 'Workshop service') ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section profile-section">
    <div class="container profile-layout">
        <div class="profile-main">
            <?php if ($isUnclaimed): ?>
                <aside class="trust-notice">
                    <div><strong>This business has not claimed this profile yet.</strong><p>Details come from public sources and may change. Confirm services, qualifications, pricing and availability directly with the business.</p></div>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('contact')) ?>">Claim this business</a>
                </aside>
            <?php endif; ?>

            <?php if ($promotionAd !== null && !empty($provider['is_featured'])): ?>
                <div class="provider-promo-wrap card"><p class="sponsored-label">Sponsored provider promotion</p><?php $this->include('partials.provider-promotion-ad', ['promo' => $promotionAd, 'alt' => (string) ($promotionAd['headline'] ?? $name)]); ?></div>
            <?php endif; ?>

            <section class="profile-panel">
                <h2>About this business</h2>
                <?php if (!empty($provider['description'])): ?><div class="profile-description"><?= nl2br($this->e((string) $provider['description'])) ?></div><?php else: ?><p class="muted">A detailed business introduction has not been supplied yet. Use the contact details to confirm whether this provider is suitable for your needs.</p><?php endif; ?>
            </section>

            <section class="profile-panel">
                <h2>Services</h2>
                <?php if ($services === []): ?>
                    <div class="inline-empty"><strong>Services have not been confirmed.</strong><span>Contact the business before relying on this listing.</span></div>
                <?php else: ?>
                    <div class="service-chips"><?php foreach ($services as $s): ?><a href="<?= e(url('services/' . $s['slug'])) ?>"><?= $this->e((string) $s['name']) ?></a><?php endforeach; ?></div>
                <?php endif; ?>
            </section>

            <?php if ($areas !== []): ?>
                <section class="profile-panel"><h2>Areas serviced</h2><ul class="detail-list"><?php foreach ($areas as $a): ?><li><?= $this->e((string) ($a['town_name'] ?? $a['region_name'] ?? $a['state_name'] ?? $a['label'] ?? ($a['radius_km'] ? $a['radius_km'] . ' km radius' : ucfirst((string) $a['area_type'])))) ?></li><?php endforeach; ?></ul></section>
            <?php endif; ?>

            <?php if ($licences !== []): ?>
                <section class="profile-panel"><h2>Verified credentials</h2><ul class="credential-list"><?php foreach ($licences as $l): ?><li><span aria-hidden="true">✓</span><div><strong><?= $this->e((string) $l['licence_type']) ?></strong><?php if ($l['issuing_authority']): ?><small><?= $this->e((string) $l['issuing_authority']) ?></small><?php endif; ?></div></li><?php endforeach; ?></ul></section>
            <?php endif; ?>

            <?php if ($runs !== []): ?>
                <section class="profile-panel"><h2>Upcoming service runs</h2><div class="grid grid-2"><?php foreach ($runs as $r): ?><a class="mini-result" href="<?= e(url('service-runs/' . $r['slug'])) ?>"><strong><?= $this->e((string) $r['title']) ?></strong><span><?= $this->e(ucfirst((string) $r['status'])) ?><?php if ($r['start_date']): ?> · from <?= $this->e((string) $r['start_date']) ?><?php endif; ?></span></a><?php endforeach; ?></div></section>
            <?php endif; ?>
        </div>

        <aside class="profile-contact" aria-label="Business contact details">
            <div class="profile-contact-card">
                <h2>Contact <?= $this->e($name) ?></h2>
                <?php if ($address !== ''): ?><div class="contact-detail"><span>Location</span><strong><?= $this->e($address) ?></strong></div><?php elseif ($locationLabel !== ''): ?><div class="contact-detail"><span>Based near</span><strong><?= $this->e($locationLabel) ?></strong></div><?php endif; ?>
                <div class="profile-actions">
                    <?php if ($showPhone): ?><a class="btn btn-primary btn-block" href="<?= e(url('go/phone/' . $slug)) ?>">Call <?= $this->e($phone) ?></a><?php endif; ?>
                    <?php if ($showEmail): ?><a class="btn btn-secondary btn-block" href="<?= e(url('go/email/' . $slug)) ?>">Email business</a><?php endif; ?>
                    <?php if ($website !== ''): ?><a class="btn btn-ghost btn-block" href="<?= e(url('go/website/' . $slug)) ?>" target="_blank" rel="noopener nofollow">Visit business website</a><?php endif; ?>
                    <?php if ($canNavigate): ?><a class="btn btn-ghost btn-block" href="<?= e(url('go/directions/' . $slug)) ?>" target="_blank" rel="noopener">Get directions</a><?php endif; ?>
                </div>
                <?php if (!$showPhone && !$showEmail && $website === ''): ?><div class="inline-empty"><strong>Contact details unavailable</strong><span>This business has not supplied public contact information.</span></div><?php endif; ?>
                <p class="contact-disclaimer"><?= $this->e($brand->name()) ?> does not guarantee availability, pricing or suitability. Confirm important details with the provider.</p>
                <?php if (!empty($requestsEnabled)): ?><a class="btn btn-primary btn-block" href="<?= e(url('request-assistance')) ?>">Request help through <?= $this->e($brand->name()) ?></a><?php endif; ?>
                <?php if (current_user() !== null): ?><form method="post" action="<?= e(url('account/providers/save')) ?>"><?= csrf_field() ?><input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>"><button type="submit" class="btn btn-ghost btn-block btn-sm">Save this business</button></form><?php endif; ?>
            </div>
        </aside>
    </div>
</section>
<?php $this->endSection(); ?>
