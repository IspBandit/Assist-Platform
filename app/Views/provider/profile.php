<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $provider */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
$this->extend('layouts.public');
$p = $provider;
$v = static fn (string $k) => e((string) ($p[$k] ?? ''));
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Business profile</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'profile']); ?>

        <form method="post" action="<?= e(url('provider/profile')) ?>" class="card">
            <?= csrf_field() ?>
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
                    <label for="phone">Private phone</label>
                    <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>">
                </div>
                <div class="form-group">
                    <label for="public_phone">Public phone</label>
                    <input type="text" id="public_phone" name="public_phone" value="<?= $v('public_phone') ?>">
                </div>
                <div class="form-group">
                    <label for="public_email">Public email</label>
                    <input type="email" id="public_email" name="public_email" value="<?= $v('public_email') ?>">
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
                    <label for="max_travel_km">Max travel (km)</label>
                    <input type="number" id="max_travel_km" name="max_travel_km" value="<?= $v('max_travel_km') ?>" min="0">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5"><?= $v('description') ?></textarea>
            </div>

            <div class="form-group">
                <label><input type="checkbox" name="show_public_phone" value="1" <?= !empty($p['show_public_phone']) ? 'checked' : '' ?>> Show my public phone on my profile</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="show_public_email" value="1" <?= !empty($p['show_public_email']) ? 'checked' : '' ?>> Show my public email on my profile</label>
            </div>

            <button type="submit" class="btn btn-primary">Save profile</button>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
