<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $states */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Locations</h1>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-secondary" href="<?= e(url('admin/locations/regions')) ?>">Regions</a>
            <a class="btn btn-secondary" href="<?= e(url('admin/locations/towns')) ?>">Towns</a>
            <a class="btn btn-primary" href="<?= e(url('admin/locations/states/new')) ?>">New state</a>
        </div>
    </div>
    <p class="muted">The location hierarchy drives public town, region and service pages. No state is hardcoded — activate new states to expand nationally.</p>
    <?php if (can('locations.manage')): ?>
        <form method="post" action="<?= e(url('admin/locations/sync')) ?>" style="margin:0" onsubmit="return confirm('Add any new regions and towns shipped with the latest release? Existing locations are left untouched.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Sync from seed</button>
            <span class="help" style="display:inline-block;margin-left:.5rem">Adds new Queensland (and future) towns from the latest release. Safe to run repeatedly — never overwrites or deletes.</span>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>States &amp; territories</h2>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th>State</th><th>Abbr</th><th>Regions</th><th>Towns</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($states as $s): ?>
                <tr>
                    <td><strong><?= $this->e((string) $s['name']) ?></strong><br><span class="muted" style="font-size:.85rem"><?= $this->e((string) ($s['country_name'] ?? '')) ?></span></td>
                    <td><?= $this->e((string) ($s['abbreviation'] ?? '')) ?></td>
                    <td><a href="<?= e(url('admin/locations/regions?state=' . (int) $s['id'])) ?>"><?= (int) $s['region_count'] ?></a></td>
                    <td><a href="<?= e(url('admin/locations/towns?state=' . (int) $s['id'])) ?>"><?= (int) $s['town_count'] ?></a></td>
                    <td><?= $s['is_active'] ? '<span class="badge badge-verified">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/locations/states/edit?id=' . (int) $s['id'])) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($states === []): ?>
                <tr><td colspan="6" class="muted">No states yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
