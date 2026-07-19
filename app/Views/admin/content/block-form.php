<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $block */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $block[$k] ?? $d;
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $block ? 'Edit block' : 'New block' ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/content/blocks')) ?>">Back to blocks</a>
    </div>

    <form method="post" action="<?= e(url('admin/content/blocks/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php if ($block): ?><input type="hidden" name="id" value="<?= (int) $block['id'] ?>"><?php endif; ?>

        <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" value="<?= e_attr((string) $v('title')) ?>" required></div>
        <div class="form-group"><label for="subtitle">Subtitle</label><input type="text" id="subtitle" name="subtitle" value="<?= e_attr((string) $v('subtitle')) ?>"></div>
        <div class="form-group"><label for="body">Body</label><textarea id="body" name="body" rows="4"><?= e((string) $v('body')) ?></textarea></div>
        <div class="grid grid-2">
            <div class="form-group"><label for="button_label">Button label</label><input type="text" id="button_label" name="button_label" value="<?= e_attr((string) $v('button_label')) ?>"></div>
            <div class="form-group"><label for="button_url">Button URL</label><input type="text" id="button_url" name="button_url" value="<?= e_attr((string) $v('button_url')) ?>"></div>
        </div>
        <div class="grid grid-2">
            <div class="form-group"><label for="sort_order">Sort order</label><input type="number" id="sort_order" name="sort_order" value="<?= e_attr((string) $v('sort_order', '0')) ?>"></div>
            <div class="form-group" style="align-self:end"><label><input type="checkbox" name="is_active" value="1" <?= ($block === null || $v('is_active')) ? 'checked' : '' ?>> Active</label></div>
        </div>

        <div class="btn-row"><button type="submit" class="btn btn-primary"><?= $block ? 'Save block' : 'Create block' ?></button></div>
    </form>
</div>
<?php $this->endSection(); ?>
