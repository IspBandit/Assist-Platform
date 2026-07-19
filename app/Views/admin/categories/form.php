<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $category */
/** @var array<int,array<string,mixed>> $parents */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $category[$k] ?? $d;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1><?= $this->e($title) ?></h1>
    <form method="post" action="<?= e(url('admin/categories/save')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($category['id'] ?? 0) ?>">

        <div class="grid grid-2">
            <div class="form-group">
                <label for="name">Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="<?= e_attr((string) $v('name')) ?>" required>
            </div>
            <div class="form-group">
                <label for="parent_id">Parent category</label>
                <select id="parent_id" name="parent_id">
                    <option value="">— top level —</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) $v('parent_id') === (int) $p['id'] ? 'selected' : '' ?>><?= $this->e((string) $p['name']) ?></option>
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
                <label for="icon">Icon key</label>
                <input type="text" id="icon" name="icon" value="<?= e_attr((string) $v('icon')) ?>" maxlength="80">
            </div>
            <div class="form-group">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= (int) $v('sort_order', 0) ?>">
            </div>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?= $v('is_active', 1) ? 'checked' : '' ?>> Active (visible on the public site)</label>
        </div>

        <div class="form-group">
            <label for="short_description">Short description</label>
            <input type="text" id="short_description" name="short_description" value="<?= e_attr((string) $v('short_description')) ?>" maxlength="320">
        </div>
        <div class="form-group">
            <label for="public_description">Public description</label>
            <textarea id="public_description" name="public_description" rows="4"><?= $this->e((string) $v('public_description')) ?></textarea>
        </div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="customer_guidance">Customer guidance</label>
                <textarea id="customer_guidance" name="customer_guidance" rows="3"><?= $this->e((string) $v('customer_guidance')) ?></textarea>
            </div>
            <div class="form-group">
                <label for="typical_issues">Typical issues</label>
                <textarea id="typical_issues" name="typical_issues" rows="3"><?= $this->e((string) $v('typical_issues')) ?></textarea>
            </div>
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
            <button type="submit" class="btn btn-primary">Save category</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/categories')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
