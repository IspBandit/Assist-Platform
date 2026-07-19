<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $blocks */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Content</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/content/blocks/new')) ?>">New block</a>
    </div>
    <?php $this->include('partials.admin-content-nav', ['active' => 'blocks']); ?>
    <p class="muted">These blocks appear on the homepage in sort order.</p>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Order</th><th>Title</th><th>Subtitle</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blocks as $b): ?>
                <tr>
                    <td><?= (int) $b['sort_order'] ?></td>
                    <td><strong><?= $this->e((string) $b['title']) ?></strong></td>
                    <td><?= $this->e((string) ($b['subtitle'] ?? '')) ?></td>
                    <td><?= $b['is_active'] ? 'Yes' : 'No' ?></td>
                    <td class="btn-row" style="margin:0">
                        <a class="btn btn-ghost" href="<?= e(url('admin/content/blocks/edit?id=' . (int) $b['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('admin/content/blocks/delete')) ?>" style="margin:0">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <button type="submit" class="btn btn-ghost">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($blocks === []): ?><tr><td colspan="5" class="muted">No blocks.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
