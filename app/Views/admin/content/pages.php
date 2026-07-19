<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $pages */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Content</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/content/pages/new')) ?>">New page</a>
    </div>
    <?php $this->include('partials.admin-content-nav', ['active' => 'pages']); ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Title</th><th>Slug</th><th>Published</th><th>Indexable</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pages as $p): ?>
                <tr>
                    <td><strong><?= $this->e((string) $p['title']) ?></strong><?= (int) $p['is_system'] === 1 ? ' <span class="badge badge-neutral">system</span>' : '' ?></td>
                    <td>/<?= $this->e((string) $p['slug']) ?></td>
                    <td><?= $p['is_published'] ? 'Yes' : 'No' ?></td>
                    <td><?= $p['noindex'] ? 'No' : 'Yes' ?></td>
                    <td class="btn-row" style="margin:0">
                        <a class="btn btn-ghost" href="<?= e(url('admin/content/pages/edit?id=' . (int) $p['id'])) ?>">Edit</a>
                        <?php if ((int) $p['is_system'] !== 1): ?>
                            <form method="post" action="<?= e(url('admin/content/pages/delete')) ?>" style="margin:0">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-ghost">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pages === []): ?><tr><td colspan="5" class="muted">No pages.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
