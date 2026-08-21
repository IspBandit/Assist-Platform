<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1>Polaris models</h1>
    <form method="get" class="filter-inline">
        <label>Lifecycle
            <select name="lifecycle">
                <option value="">All</option>
                <?php foreach (['active', 'archived', 'recycle_bin'] as $state): ?>
                    <option value="<?= e($state) ?>" <?= $lifecycle === $state ? 'selected' : '' ?>><?= e($state) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
    </form>
    <table class="table">
        <thead><tr><th>Manufacturer</th><th>Model</th><th>Category</th><th>Production</th><th>Lifecycle</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $this->e($row['manufacturer_name']) ?></td>
                <td><?= $this->e($row['name']) ?><?= !empty($row['is_demo']) ? ' (demo)' : '' ?></td>
                <td><?= $this->e($row['category']) ?></td>
                <td><?= $this->e($row['production_status']) ?></td>
                <td><?= $this->e($row['lifecycle_status']) ?></td>
                <td>
                    <?php if ($row['lifecycle_status'] === 'active'): ?>
                        <form method="post" action="<?= e(url('admin/polaris/models/lifecycle')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="lifecycle" value="archived">
                            <input type="hidden" name="reason" value="Admin archive">
                            <button class="btn btn-ghost btn-sm" type="submit">Archive</button>
                        </form>
                    <?php elseif ($row['lifecycle_status'] === 'archived'): ?>
                        <form method="post" action="<?= e(url('admin/polaris/models/lifecycle')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="lifecycle" value="active">
                            <button class="btn btn-ghost btn-sm" type="submit">Restore</button>
                        </form>
                        <form method="post" action="<?= e(url('admin/polaris/models/lifecycle')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="lifecycle" value="recycle_bin">
                            <input type="hidden" name="reason" value="Moved to recycle bin">
                            <button class="btn btn-ghost btn-sm" type="submit">Recycle bin</button>
                        </form>
                    <?php elseif ($row['lifecycle_status'] === 'recycle_bin'): ?>
                        <form method="post" action="<?= e(url('admin/polaris/models/lifecycle')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="lifecycle" value="active">
                            <button class="btn btn-ghost btn-sm" type="submit">Restore</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
