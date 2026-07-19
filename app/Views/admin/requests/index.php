<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $requests */
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
    <h1 style="margin:0 0 1rem">Service requests <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
    <form method="get" action="<?= e(url('admin/requests')) ?>" class="grid grid-3" style="align-items:flex-end">
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
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Reference, title, email">
        </div>
        <div class="form-group mb-0"><button class="btn btn-secondary" type="submit">Filter</button></div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Summary</th><th>Town</th><th>Category</th><th>Urgency</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><strong><?= $this->e((string) $r['reference']) ?></strong><?= $r['is_spam'] ? ' <span class="badge badge-neutral">spam</span>' : '' ?></td>
                    <td><?= $this->e((string) $r['title']) ?><?= $r['safety_concern'] ? ' <span class="badge badge-urgent">safety</span>' : '' ?></td>
                    <td><?= $this->e((string) ($r['town_name'] ?? '—')) ?></td>
                    <td><?= $this->e((string) ($r['category_name'] ?? '—')) ?></td>
                    <td><?= $this->e(ucfirst((string) $r['urgency'])) ?></td>
                    <td><span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/requests/show?id=' . (int) $r['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requests === []): ?><tr><td colspan="7" class="muted">No requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?><a class="btn btn-ghost" href="<?= e(url('admin/requests' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a><?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost" href="<?= e(url('admin/requests' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
