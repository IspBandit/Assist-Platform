<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $faq */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $faq[$k] ?? $d;
$categories = ['general' => 'General', 'customers' => 'For caravan owners', 'providers' => 'For providers', 'parks' => 'For caravan parks'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $faq ? 'Edit FAQ' : 'New FAQ' ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/content/faqs')) ?>">Back to FAQs</a>
    </div>

    <form method="post" action="<?= e(url('admin/content/faqs/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php if ($faq): ?><input type="hidden" name="id" value="<?= (int) $faq['id'] ?>"><?php endif; ?>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= (string) $v('category', 'general') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="sort_order">Sort order</label><input type="number" id="sort_order" name="sort_order" value="<?= e_attr((string) $v('sort_order', '0')) ?>"></div>
        </div>
        <div class="form-group"><label for="question">Question</label><input type="text" id="question" name="question" value="<?= e_attr((string) $v('question')) ?>" required></div>
        <div class="form-group"><label for="answer">Answer</label><textarea id="answer" name="answer" rows="5" required><?= e((string) $v('answer')) ?></textarea></div>
        <label><input type="checkbox" name="is_active" value="1" <?= ($faq === null || $v('is_active')) ? 'checked' : '' ?>> Active</label>

        <div class="btn-row"><button type="submit" class="btn btn-primary"><?= $faq ? 'Save FAQ' : 'Create FAQ' ?></button></div>
    </form>
</div>
<?php $this->endSection(); ?>
