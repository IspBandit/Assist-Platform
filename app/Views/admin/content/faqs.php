<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $faqs */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Content</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/content/faqs/new')) ?>">New FAQ</a>
    </div>
    <?php $this->include('partials.admin-content-nav', ['active' => 'faqs']); ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Category</th><th>Question</th><th>Order</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($faqs as $f): ?>
                <tr>
                    <td><?= $this->e((string) $f['category']) ?></td>
                    <td><?= $this->e((string) $f['question']) ?></td>
                    <td><?= (int) $f['sort_order'] ?></td>
                    <td><?= $f['is_active'] ? 'Yes' : 'No' ?></td>
                    <td class="btn-row" style="margin:0">
                        <a class="btn btn-ghost" href="<?= e(url('admin/content/faqs/edit?id=' . (int) $f['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('admin/content/faqs/delete')) ?>" style="margin:0">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <button type="submit" class="btn btn-ghost">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($faqs === []): ?><tr><td colspan="5" class="muted">No FAQs.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
