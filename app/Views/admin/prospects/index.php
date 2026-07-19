<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $prospects */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $status */
/** @var string $search */
/** @var array<int,string> $statuses */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$qs = static function (array $extra) use ($status, $search): string {
    $params = array_filter(['status' => $status, 'q' => $search] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
$label = static fn (string $s): string => ucwords(str_replace('_', ' ', $s));
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Provider prospects <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-secondary" href="<?= e(url('admin/prospects/export')) ?>">Export CSV</a>
            <a class="btn btn-primary" href="<?= e(url('admin/prospects/new')) ?>">New prospect</a>
        </div>
    </div>
    <p class="muted">Track outreach to potential providers, log contact notes and send registration invitations.</p>

    <form method="get" action="<?= e(url('admin/prospects')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
        <div class="form-group mb-0">
            <label for="status">Outreach status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= $this->e($label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Business, email, contact">
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-secondary" type="submit">Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Import from CSV</h2>
    <p class="muted">Columns: business_name, contact_name, email, phone, website, services_observed, source, outreach_status, notes.</p>
    <form method="post" action="<?= e(url('admin/prospects/import')) ?>" enctype="multipart/form-data" class="btn-row">
        <?= csrf_field() ?>
        <input type="file" name="csv" accept=".csv,text/csv" required>
        <button type="submit" class="btn btn-secondary">Import</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Business</th><th>Contact</th><th>Town</th><th>Status</th><th>Follow up</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($prospects as $p): ?>
                <tr>
                    <td><strong><?= $this->e((string) $p['business_name']) ?></strong><br><span class="muted" style="font-size:.85rem"><?= $this->e((string) ($p['email'] ?? '')) ?></span></td>
                    <td><?= $this->e((string) ($p['contact_name'] ?? '—')) ?></td>
                    <td><?= $this->e((string) ($p['town_name'] ?? '—')) ?></td>
                    <td><span class="badge badge-neutral"><?= $this->e($label((string) $p['outreach_status'])) ?></span></td>
                    <td><?= $this->e((string) ($p['next_follow_up_date'] ?? '—')) ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/prospects/show?id=' . (int) $p['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($prospects === []): ?>
                <tr><td colspan="6" class="muted">No prospects found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/prospects' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/prospects' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
