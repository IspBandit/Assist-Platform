<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $provider */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
$this->extend('layouts.admin');
$p = $provider ?? [];
$v = static fn (string $k, $default = '') => e((string) ($p[$k] ?? $default));
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/providers')) ?>">&laquo; Back to providers</a>
    <h1 style="margin:.25rem 0 1rem"><?= $provider ? 'Edit provider' : 'New provider' ?></h1>

    <form method="post" action="<?= e(url('admin/providers/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($provider): ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><?php endif; ?>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="business_name">Business name *</label>
                <input type="text" id="business_name" name="business_name" value="<?= $v('business_name') ?>" required>
            </div>
            <div class="form-group">
                <label for="contact_name">Contact name</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= $v('contact_name') ?>">
            </div>
            <div class="form-group">
                <label for="abn">ABN</label>
                <input type="text" id="abn" name="abn" value="<?= $v('abn') ?>">
            </div>
            <div class="form-group">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" value="<?= $v('website') ?>">
            </div>
            <div class="form-group">
                <label for="email">Private email</label>
                <input type="email" id="email" name="email" value="<?= $v('email') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Private phone</label>
                <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>">
            </div>
            <div class="form-group">
                <label for="public_email">Public email</label>
                <input type="email" id="public_email" name="public_email" value="<?= $v('public_email') ?>">
            </div>
            <div class="form-group">
                <label for="public_phone">Public phone</label>
                <input type="text" id="public_phone" name="public_phone" value="<?= $v('public_phone') ?>">
            </div>
            <div class="form-group">
                <label for="base_town_id">Base town</label>
                <select id="base_town_id" name="base_town_id">
                    <option value="">—</option>
                    <?php foreach ($towns as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($p['base_town_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="region_id">Region</label>
                <select id="region_id" name="region_id">
                    <option value="">—</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) ($p['region_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="service_model">Service model</label>
                <select id="service_model" name="service_model">
                    <?php foreach (['mobile' => 'Mobile', 'workshop' => 'Workshop', 'both' => 'Both'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($p['service_model'] ?? 'mobile') === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="max_travel_km">Max travel (km)</label>
                <input type="number" id="max_travel_km" name="max_travel_km" value="<?= $v('max_travel_km') ?>" min="0">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= $v('description') ?></textarea>
        </div>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="seo_title">SEO title</label>
                <input type="text" id="seo_title" name="seo_title" value="<?= $v('seo_title') ?>">
            </div>
            <div class="form-group">
                <label for="seo_description">SEO description</label>
                <input type="text" id="seo_description" name="seo_description" value="<?= $v('seo_description') ?>">
            </div>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="show_public_phone" value="1" <?= !empty($p['show_public_phone']) ? 'checked' : '' ?>> Show public phone on profile</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="show_public_email" value="1" <?= !empty($p['show_public_email']) ? 'checked' : '' ?>> Show public email on profile</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="marketing_opt_in" value="1" <?= !empty($p['marketing_opt_in']) ? 'checked' : '' ?>> A valid promotional-email consent basis is documented</label>
            <p class="muted">Select only when documented consent is on file. Clearing this immediately removes the provider from campaign audiences.</p>
            <label for="marketing_consent_source">Consent basis</label>
            <select id="marketing_consent_source" name="marketing_consent_source">
                <option value="">Choose documented basis</option>
                <?php foreach (['express_written'=>'Express — written','express_phone'=>'Express — phone/in person','express_web'=>'Express — web form','inferred_role_relevant'=>'Inferred — published role-relevant address'] as $value=>$label): ?>
                    <option value="<?= e($value) ?>" <?= ($p['marketing_consent_source'] ?? '') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="marketing_consent_evidence">Consent evidence</label>
            <input type="text" id="marketing_consent_evidence" name="marketing_consent_evidence" maxlength="500" value="<?= $v('marketing_consent_evidence') ?>" placeholder="When, how, by whom, and where the evidence is retained">
        </div>
        <?php if (!$provider): ?>
        <div class="form-group">
            <label><input type="checkbox" name="is_founding_provider" value="1"> Mark as founding provider</label>
        </div>
        <?php endif; ?>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save provider</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/providers')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
