<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $page */
/** @var array $errors */
$this->extend('layouts.admin');
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$v = static fn (string $k, $d = '') => old($k) ?? ($page[$k] ?? $d);
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $page ? 'Edit page' : 'New page' ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/content')) ?>">Back to pages</a>
    </div>

    <form method="post" action="<?= e(url('admin/content/pages/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php if ($page): ?><input type="hidden" name="id" value="<?= (int) $page['id'] ?>"><?php endif; ?>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?= e_attr((string) $v('title')) ?>" required>
                <?= $err('title') ?>
            </div>
            <div class="form-group">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="<?= e_attr((string) $v('slug')) ?>" placeholder="auto from title">
                <?= $err('slug') ?>
            </div>
        </div>

        <div class="form-group">
            <label for="body">Body (HTML)</label>
            <textarea id="body" name="body" rows="12"><?= e((string) $v('body')) ?></textarea>
        </div>

        <label><input type="checkbox" name="is_published" value="1" <?= ($page === null || $v('is_published')) ? 'checked' : '' ?>> Published</label>

        <h2 style="margin-bottom:0">SEO</h2>
        <div class="grid grid-2">
            <div class="form-group"><label for="seo_title">SEO title</label><input type="text" id="seo_title" name="seo_title" value="<?= e_attr((string) $v('seo_title')) ?>"></div>
            <div class="form-group"><label for="canonical_url">Canonical URL</label><input type="text" id="canonical_url" name="canonical_url" value="<?= e_attr((string) $v('canonical_url')) ?>"></div>
        </div>
        <div class="form-group"><label for="seo_description">SEO description</label><textarea id="seo_description" name="seo_description" rows="2"><?= e((string) $v('seo_description')) ?></textarea></div>
        <label><input type="checkbox" name="noindex" value="1" <?= $v('noindex') ? 'checked' : '' ?>> Hide from search engines (noindex)</label>

        <h2 style="margin-bottom:0">Social sharing</h2>
        <div class="grid grid-2">
            <div class="form-group"><label for="og_title">OG title</label><input type="text" id="og_title" name="og_title" value="<?= e_attr((string) $v('og_title')) ?>"></div>
            <div class="form-group"><label for="og_image">OG image URL</label><input type="text" id="og_image" name="og_image" value="<?= e_attr((string) $v('og_image')) ?>"></div>
        </div>
        <div class="form-group"><label for="og_description">OG description</label><textarea id="og_description" name="og_description" rows="2"><?= e((string) $v('og_description')) ?></textarea></div>

        <div class="form-group">
            <label for="schema_json">Structured data (JSON-LD)</label>
            <textarea id="schema_json" name="schema_json" rows="5" placeholder='{"@context":"https://schema.org", ...}'><?= e((string) $v('schema_json')) ?></textarea>
            <?= $err('schema_json') ?>
        </div>

        <div class="btn-row"><button type="submit" class="btn btn-primary"><?= $page ? 'Save page' : 'Create page' ?></button></div>
    </form>
</div>
<?php $this->endSection(); ?>
