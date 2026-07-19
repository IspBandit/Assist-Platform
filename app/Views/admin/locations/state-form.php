<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $state */
/** @var array<int,array<string,mixed>> $countries */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $state[$k] ?? $d;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1><?= $this->e($title) ?></h1>
    <form method="post" action="<?= e(url('admin/locations/states/save')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($state['id'] ?? 0) ?>">

        <div class="grid grid-2">
            <div class="form-group">
                <label for="name">Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="<?= e_attr((string) $v('name')) ?>" required>
            </div>
            <div class="form-group">
                <label for="abbreviation">Abbreviation</label>
                <input type="text" id="abbreviation" name="abbreviation" value="<?= e_attr((string) $v('abbreviation')) ?>" maxlength="10">
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="country_id">Country</label>
                <select id="country_id" name="country_id">
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $v('country_id') === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="slug">Slug <span class="muted">(auto from name if blank)</span></label>
                <input type="text" id="slug" name="slug" value="<?= e_attr((string) $v('slug')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?= $v('is_active', 1) ? 'checked' : '' ?>> Active (visible / selectable)</label>
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
            <button type="submit" class="btn btn-primary">Save state</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/locations')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
