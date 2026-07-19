<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Service categories</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/categories/new')) ?>">New category</a>
    </div>
    <p class="muted">Categories can be nested under a parent and carry SEO content used on public service pages.</p>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Parent</th><th>Order</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td>
                        <?php if (!empty($c['parent_name'])): ?><span class="muted">↳ </span><?php endif; ?>
                        <strong><?= $this->e((string) $c['name']) ?></strong>
                        <br><span class="muted" style="font-size:.85rem"><code><?= $this->e((string) $c['slug']) ?></code></span>
                    </td>
                    <td><?= $this->e((string) ($c['parent_name'] ?? '—')) ?></td>
                    <td><?= (int) $c['sort_order'] ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge badge-verified">Active</span>' : '<span class="badge badge-neutral">Hidden</span>' ?></td>
                    <td class="btn-row" style="margin:0">
                        <a class="btn btn-ghost" href="<?= e(url('admin/categories/edit?id=' . (int) $c['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('admin/categories/toggle')) ?>" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-ghost"><?= $c['is_active'] ? 'Hide' : 'Show' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($categories === []): ?>
                <tr><td colspan="5" class="muted">No categories yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
