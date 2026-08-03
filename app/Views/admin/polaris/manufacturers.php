<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1>Polaris manufacturers</h1>
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
        <thead><tr><th>Name</th><th>Claim</th><th>Verification</th><th>Publication</th><th>Lifecycle</th><th>Demo</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $this->e($row['trading_name']) ?></td>
                <td><?= $this->e($row['claim_status']) ?></td>
                <td><?= $this->e($row['verification_status']) ?></td>
                <td><?= $this->e($row['publication_status']) ?></td>
                <td><?= $this->e($row['lifecycle_status']) ?></td>
                <td><?= !empty($row['is_demo']) ? 'Yes' : 'No' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
