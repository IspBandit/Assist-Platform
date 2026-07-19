<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $towns */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array<int,array<string,mixed>> $states */
/** @var array<int,array<string,mixed>> $regions */
/** @var int|null $stateId */
/** @var int|null $regionId */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$qs = static function (array $extra) use ($stateId, $regionId): string {
    $params = array_filter(['state' => $stateId, 'region' => $regionId] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Towns <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <a class="btn btn-primary" href="<?= e(url('admin/locations/towns/new')) ?>">New town</a>
    </div>
    <form method="get" action="<?= e(url('admin/locations/towns')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
        <div class="form-group mb-0">
            <label for="state">State</label>
            <select id="state" name="state">
                <option value="">All states</option>
                <?php foreach ($states as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $stateId === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e((string) $s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="region">Region</label>
            <select id="region" name="region">
                <option value="">All regions</option>
                <?php foreach ($regions as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= $regionId === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-secondary" type="submit">Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Town</th><th>Region</th><th>State</th><th>Postcode</th><th>Flags</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($towns as $t): ?>
                <tr>
                    <td><strong><?= $this->e((string) $t['name']) ?></strong></td>
                    <td><?= $this->e((string) ($t['region_name'] ?? '—')) ?></td>
                    <td><?= $this->e((string) ($t['state_abbr'] ?? $t['state_name'])) ?></td>
                    <td><?= $this->e((string) ($t['primary_postcode'] ?? '')) ?></td>
                    <td>
                        <?= $t['is_active'] ? '' : '<span class="badge badge-neutral">Hidden</span> ' ?>
                        <?= $t['is_launch_town'] ? '<span class="badge badge-verified">Launch</span> ' : '' ?>
                        <?= $t['is_featured'] ? '<span class="badge badge-confirmed">Featured</span> ' : '' ?>
                        <?= (int) $t['noindex'] === 1 ? '<span class="badge badge-neutral">noindex</span>' : '' ?>
                    </td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/locations/towns/edit?id=' . (int) $t['id'])) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($towns === []): ?>
                <tr><td colspan="6" class="muted">No towns found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/locations/towns' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/locations/towns' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
