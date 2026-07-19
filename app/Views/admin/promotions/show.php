<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $promo */
/** @var array{desktop:?string,mobile:?string} $imageUrls */
/** @var string|null $logoUrl */
/** @var string $providerUrl */
/** @var array{desktop:string,mobile:string} $promoSpecs */
$this->extend('layouts.admin');
$id = (int) $promo['id'];
$status = (string) $promo['status'];
$town = (string) ($promo['town_name'] ?? '');
if ($town !== '' && !empty($promo['state_abbr'])) {
    $town .= ', ' . $promo['state_abbr'];
}
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/promotions')) ?>">&laquo; Back to ad graphics</a>
    <h1 style="margin-top:.5rem"><?= $this->e((string) $promo['business_name']) ?></h1>
    <p>
        <span class="badge badge-confirmed"><?= $this->e(ucfirst(str_replace('_', ' ', $status))) ?></span>
        <?php if (!empty($promo['is_verified'])): ?><span class="badge badge-verified">Verified</span><?php endif; ?>
        <?php if (!empty($promo['is_featured'])): ?><span class="badge badge-confirmed">Featured</span><?php endif; ?>
    </p>
    <p class="muted">
        <?= $town !== '' ? $this->e($town) : '—' ?>
        · <a href="<?= e($providerUrl) ?>">Provider profile</a>
        <?php if (!empty($promo['provider_email'])): ?> · <?= $this->e((string) $promo['provider_email']) ?><?php endif; ?>
    </p>
</div>

<div class="grid grid-2" style="margin-top:1rem">
    <div class="card">
        <h2 style="margin-top:0">Provider brief</h2>
        <?php if ($status === 'eligible'): ?>
            <p class="muted">Eligible for a free founding graphic. Waiting for the provider to verify and submit their brief.</p>
        <?php elseif (!empty($promo['headline'])): ?>
            <p><strong>Headline</strong><br><?= $this->e((string) $promo['headline']) ?></p>
            <p><strong>Tagline</strong><br><?= $this->e((string) ($promo['tagline'] ?? '')) ?></p>
            <?php if (!empty($promo['brief_notes'])): ?>
                <p><strong>Notes</strong><br><span style="white-space:pre-wrap"><?= $this->e((string) $promo['brief_notes']) ?></span></p>
            <?php endif; ?>
            <?php if ($logoUrl): ?>
                <p><strong>Logo supplied</strong><br><img src="<?= e_attr($logoUrl) ?>" alt="Provider logo" style="max-width:240px;border:1px solid #e3e0d8;border-radius:8px"></p>
            <?php endif; ?>
            <p class="muted" style="margin:0">Requested <?= !empty($promo['requested_at']) ? $this->e((string) $promo['requested_at']) : '' ?></p>
        <?php else: ?>
            <p class="muted">No brief submitted yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Timeline</h2>
        <ul class="list-plain muted">
            <li>Eligible: <?= !empty($promo['eligible_at']) ? $this->e((string) $promo['eligible_at']) : '—' ?></li>
            <li>Requested: <?= !empty($promo['requested_at']) ? $this->e((string) $promo['requested_at']) : '—' ?></li>
            <li>Delivered: <?= !empty($promo['delivered_at']) ? $this->e((string) $promo['delivered_at']) : '—' ?>
                <?php if (!empty($promo['delivered_by_name'])): ?> (by <?= $this->e((string) $promo['delivered_by_name']) ?>)<?php endif; ?>
            </li>
        </ul>
        <?php if (!empty($imageUrls['desktop']) || !empty($imageUrls['mobile'])): ?>
            <p><strong>Live preview</strong> <span class="muted">(switches by screen width)</span></p>
            <?php $this->include('partials.provider-promotion-ad', [
                'promo' => $promo,
                'alt'   => (string) ($promo['headline'] ?? $promo['business_name']),
            ]); ?>
            <div class="grid grid-2" style="margin-top:1rem">
                <?php if (!empty($imageUrls['desktop'])): ?>
                    <div>
                        <p class="muted" style="margin:0 0 .35rem"><?= $this->e($promoSpecs['desktop']) ?></p>
                        <a href="<?= e_attr($imageUrls['desktop']) ?>" target="_blank" rel="noopener">Download desktop</a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($imageUrls['mobile'])): ?>
                    <div>
                        <p class="muted" style="margin:0 0 .35rem"><?= $this->e($promoSpecs['mobile']) ?></p>
                        <a href="<?= e_attr($imageUrls['mobile']) ?>" target="_blank" rel="noopener">Download mobile</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($status === 'requested'): ?>
<div class="card" style="margin-top:1rem">
    <form method="post" action="<?= e(url('admin/promotions/in-progress')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-secondary">Mark in progress</button>
    </form>
</div>
<?php endif; ?>

<?php if (in_array($status, ['requested', 'in_progress'], true)): ?>
<div class="card" style="margin-top:1rem;border:2px solid #0f6e6e">
    <h2 style="margin-top:0">Deliver finished graphics</h2>
    <p class="muted">Upload both versions. Travellers on phones see the mobile creative; larger screens see the desktop banner. Specs: <strong><?= $this->e($promoSpecs['desktop']) ?></strong> and <strong><?= $this->e($promoSpecs['mobile']) ?></strong>.</p>
    <form method="post" action="<?= e(url('admin/promotions/deliver')) ?>" enctype="multipart/form-data" class="stack" style="margin-top:.75rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="grid grid-2">
            <div class="form-group mb-0">
                <label for="graphic_desktop">Desktop graphic</label>
                <input type="file" id="graphic_desktop" name="graphic_desktop" accept="image/jpeg,image/png,image/webp" required>
            </div>
            <div class="form-group mb-0">
                <label for="graphic_mobile">Mobile graphic</label>
                <input type="file" id="graphic_mobile" name="graphic_mobile" accept="image/jpeg,image/png,image/webp" required>
            </div>
        </div>
        <div class="btn-row" style="align-items:center">
            <label class="mb-0"><input type="checkbox" name="feature_provider" value="1" checked> Feature provider on delivery</label>
            <button type="submit" class="btn btn-primary">Deliver graphics</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
