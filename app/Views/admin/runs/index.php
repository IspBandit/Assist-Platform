<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $runs */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $status */
/** @var string $search */
/** @var array<string,string> $statuses */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$qs = static function (array $extra) use ($status, $search): string {
    $params = array_filter(['status' => $status, 'q' => $search] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Service runs <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <a class="btn btn-primary" href="<?= e(url('admin/runs/form')) ?>">New run</a>
    </div>
    <form method="get" action="<?= e(url('admin/runs')) ?>" class="grid grid-3" style="align-items:flex-end;margin-top:1rem">
        <div class="form-group mb-0">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Title or provider">
        </div>
        <div class="form-group mb-0"><button class="btn btn-secondary" type="submit">Filter</button></div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Run</th><th>Provider</th><th>Start</th><th>Places</th><th>Public</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td><strong><?= $this->e((string) $r['title']) ?></strong></td>
                    <td><?= $this->e((string) ($r['business_name'] ?? '—')) ?></td>
                    <td><?= $this->e((string) ($r['start_date'] ?? '—')) ?></td>
                    <td><?= (int) $r['bookings_count'] ?><?= $r['appointments_total'] ? ' / ' . (int) $r['appointments_total'] : '' ?></td>
                    <td><?= $r['is_public'] ? 'Yes' : 'No' ?></td>
                    <td><span class="badge badge-neutral"><?= $this->e(\App\Services\RunWorkflow::label((string) $r['status'])) ?></span></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/runs/show?id=' . (int) $r['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($runs === []): ?><tr><td colspan="7" class="muted">No runs found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?><a class="btn btn-ghost" href="<?= e(url('admin/runs' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a><?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost" href="<?= e(url('admin/runs' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
