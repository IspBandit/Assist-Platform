<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $regions */
/** @var array<int,array<string,mixed>> $states */
/** @var int|null $stateId */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Regions</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/locations/regions/new')) ?>">New region</a>
    </div>
    <form method="get" action="<?= e(url('admin/locations/regions')) ?>" class="btn-row" style="margin-top:1rem;align-items:flex-end">
        <div class="form-group mb-0">
            <label for="state">Filter by state</label>
            <select id="state" name="state">
                <option value="">All states</option>
                <?php foreach ($states as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $stateId === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e((string) $s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Region</th><th>State</th><th>Towns</th><th>Featured</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($regions as $r): ?>
                <tr>
                    <td><strong><?= $this->e((string) $r['name']) ?></strong></td>
                    <td><?= $this->e((string) $r['state_name']) ?></td>
                    <td><a href="<?= e(url('admin/locations/towns?region=' . (int) $r['id'])) ?>"><?= (int) $r['town_count'] ?></a></td>
                    <td><?= $r['is_featured'] ? 'Yes' : '—' ?></td>
                    <td><?= $r['is_active'] ? '<span class="badge badge-verified">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/locations/regions/edit?id=' . (int) $r['id'])) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($regions === []): ?>
                <tr><td colspan="6" class="muted">No regions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
