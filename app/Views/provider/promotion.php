<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $provider */
/** @var array<string,mixed> $promo */
/** @var string $townLabel */
/** @var array{desktop:?string,mobile:?string} $imageUrls */
/** @var bool $canRequest */
/** @var array<string,string> $formErrors */
/** @var array{desktop:string,mobile:string} $promoSpecs */
$this->extend('layouts.public');
$status = (string) $promo['status'];
$err = static fn (string $k) => isset($formErrors[$k]) ? '<p class="field-error">' . e($formErrors[$k]) . '</p>' : '';
$hasImages = !empty($imageUrls['desktop']) || !empty($imageUrls['mobile']);
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Promote your business</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'promotion']); ?>

        <div class="card stack">
            <p class="muted" style="margin:0">Founding provider launch offer for <strong><?= $this->e($townLabel) ?></strong> — free local ad graphics (worth $99): a <strong>desktop banner</strong> and a <strong>mobile version</strong>, shown to travellers on the right screen size.</p>
        </div>

        <?php if ($status === 'eligible' && !$canRequest): ?>
            <div class="card" style="margin-top:1rem;border-left:4px solid #c9a227">
                <p style="margin:0"><strong>Almost there.</strong> Upload your verification documents and we will verify your profile. Once verified, you can submit your ad brief here.</p>
                <p class="muted" style="margin:.5rem 0 0"><a href="<?= e(url('provider/documents')) ?>">Upload documents</a></p>
            </div>
        <?php endif; ?>

        <?php if ($canRequest): ?>
            <div class="card" style="margin-top:1rem">
                <h2 style="margin-top:0">Request your free graphics</h2>
                <p class="muted">Tell us what to highlight. We will produce <?= $this->e($promoSpecs['desktop']) ?> and <?= $this->e(strtolower($promoSpecs['mobile'])) ?> creatives for placements near <?= $this->e($townLabel) ?>.</p>
                <form method="post" action="<?= e(url('provider/promotion')) ?>" enctype="multipart/form-data" class="stack">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="headline">Headline <span class="required">*</span></label>
                        <input type="text" id="headline" name="headline" maxlength="120" value="<?= e_attr((string) old('headline')) ?>" placeholder="e.g. Mobile caravan electrical — Gladstone" required>
                        <?= $err('headline') ?>
                    </div>
                    <div class="form-group">
                        <label for="tagline">Tagline <span class="required">*</span></label>
                        <input type="text" id="tagline" name="tagline" maxlength="200" value="<?= e_attr((string) old('tagline')) ?>" placeholder="e.g. Dual batteries, solar &amp; 12V repairs. We come to you." required>
                        <?= $err('tagline') ?>
                    </div>
                    <div class="form-group">
                        <label for="brief_notes">Anything else we should include?</label>
                        <textarea id="brief_notes" name="brief_notes" rows="3" placeholder="Phone number, years in business, specialist services, brand colours…"><?= e((string) old('brief_notes')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="logo">Logo (optional)</label>
                        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                        <p class="muted" style="font-size:.85rem;margin:.25rem 0 0">JPEG, PNG or WebP, max <?= (int) config('uploads.max_image_mb', 8) ?> MB.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit request</button>
                </form>
            </div>
        <?php elseif (in_array($status, ['requested', 'in_progress'], true)): ?>
            <div class="card" style="margin-top:1rem;border-left:4px solid #0f6e6e">
                <h2 style="margin-top:0">We are working on your graphics</h2>
                <p class="muted" style="margin:0">Submitted <?= !empty($promo['requested_at']) ? $this->e((string) $promo['requested_at']) : '' ?>.</p>
                <?php if (!empty($promo['headline'])): ?>
                    <p style="margin:.75rem 0 0"><strong><?= $this->e((string) $promo['headline']) ?></strong><br><?= $this->e((string) ($promo['tagline'] ?? '')) ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'delivered' && $hasImages): ?>
            <div class="card" style="margin-top:1rem">
                <h2 style="margin-top:0">Your ad graphics</h2>
                <p class="muted">Delivered <?= !empty($promo['delivered_at']) ? $this->e((string) $promo['delivered_at']) : '' ?>. Your listing is featured for travellers in your service area. Resize your browser or check on your phone to preview both versions.</p>
                <?php $this->include('partials.provider-promotion-ad', [
                    'promo' => $promo,
                    'alt'   => (string) ($promo['headline'] ?? $provider['business_name']),
                ]); ?>
                <div class="btn-row" style="margin-top:.75rem">
                    <?php if (!empty($imageUrls['desktop'])): ?>
                        <a class="btn btn-secondary" href="<?= e_attr($imageUrls['desktop']) ?>" download>Download desktop</a>
                    <?php endif; ?>
                    <?php if (!empty($imageUrls['mobile'])): ?>
                        <a class="btn btn-secondary" href="<?= e_attr($imageUrls['mobile']) ?>" download>Download mobile</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
