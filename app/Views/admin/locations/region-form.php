<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $region */
/** @var array<int,array<string,mixed>> $states */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $region[$k] ?? $d;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1><?= $this->e($title) ?></h1>
    <form method="post" action="<?= e(url('admin/locations/regions/save')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($region['id'] ?? 0) ?>">

        <div class="grid grid-2">
            <div class="form-group">
                <label for="name">Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="<?= e_attr((string) $v('name')) ?>" required>
            </div>
            <div class="form-group">
                <label for="state_id">State <span class="required">*</span></label>
                <select id="state_id" name="state_id" required>
                    <option value="">Select…</option>
                    <?php foreach ($states as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $v('state_id') === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e((string) $s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="slug">Slug <span class="muted">(auto from name if blank)</span></label>
            <input type="text" id="slug" name="slug" value="<?= e_attr((string) $v('slug')) ?>">
        </div>

        <div class="btn-row" style="margin-bottom:1.1rem">
            <label class="mb-0"><input type="checkbox" name="is_active" value="1" <?= $v('is_active', 1) ? 'checked' : '' ?>> Active</label>
            <label class="mb-0"><input type="checkbox" name="is_featured" value="1" <?= $v('is_featured') ? 'checked' : '' ?>> Featured</label>
        </div>

        <div class="form-group">
            <label for="public_content">Public content <span class="muted">(shown on the region page)</span></label>
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
            <button type="submit" class="btn btn-primary">Save region</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/locations/regions')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
