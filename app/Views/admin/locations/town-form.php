<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $town */
/** @var array<int,array<string,mixed>> $states */
/** @var array<int,array<string,mixed>> $regions */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $town[$k] ?? $d;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1><?= $this->e($title) ?></h1>
    <form method="post" action="<?= e(url('admin/locations/towns/save')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($town['id'] ?? 0) ?>">

        <div class="grid grid-2">
            <div class="form-group">
                <label for="name">Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="<?= e_attr((string) $v('name')) ?>" required>
            </div>
            <div class="form-group">
                <label for="primary_postcode">Primary postcode</label>
                <input type="text" id="primary_postcode" name="primary_postcode" value="<?= e_attr((string) $v('primary_postcode')) ?>" maxlength="10">
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="state_id">State <span class="required">*</span></label>
                <select id="state_id" name="state_id" required>
                    <option value="">Select…</option>
                    <?php foreach ($states as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $v('state_id') === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e((string) $s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="region_id">Region</label>
                <select id="region_id" name="region_id">
                    <option value="">— none —</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $v('region_id') === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-3">
            <div class="form-group">
                <label for="slug">Slug <span class="muted">(auto)</span></label>
                <input type="text" id="slug" name="slug" value="<?= e_attr((string) $v('slug')) ?>">
            </div>
            <div class="form-group">
                <label for="latitude">Latitude</label>
                <input type="text" id="latitude" name="latitude" value="<?= e_attr((string) $v('latitude')) ?>">
            </div>
            <div class="form-group">
                <label for="longitude">Longitude</label>
                <input type="text" id="longitude" name="longitude" value="<?= e_attr((string) $v('longitude')) ?>">
            </div>
        </div>

        <fieldset>
            <legend>Flags</legend>
            <div class="btn-row">
                <label class="mb-0"><input type="checkbox" name="is_active" value="1" <?= $v('is_active', 1) ? 'checked' : '' ?>> Active</label>
                <label class="mb-0"><input type="checkbox" name="is_launch_town" value="1" <?= $v('is_launch_town') ? 'checked' : '' ?>> Launch town</label>
                <label class="mb-0"><input type="checkbox" name="is_featured" value="1" <?= $v('is_featured') ? 'checked' : '' ?>> Featured</label>
                <label class="mb-0"><input type="checkbox" name="noindex" value="1" <?= $v('noindex', 1) ? 'checked' : '' ?>> noindex (search engines)</label>
            </div>
        </fieldset>

        <div class="form-group">
            <label for="public_content">Public content</label>
            <textarea id="public_content" name="public_content" rows="4"><?= $this->e((string) $v('public_content')) ?></textarea>
        </div>
        <div class="form-group">
            <label for="seo_title">SEO title</label>
            <input type="text" id="seo_title" name="seo_title" value="<?= e_attr((string) $v('seo_title')) ?>" maxlength="190">
        </div>
        <div class="form-group">
            <label for="seo_description">SEO description</label>
            <textarea id="seo_description" name="seo_description" maxlength="320"><?= $this->e((string) $v('seo_description')) ?></textarea>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save town</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/locations/towns')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
