<?php
/** @var \App\Core\View $this */
/** @var bool $schemaReady */
/** @var array<int,array<string,mixed>> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array<string,mixed> $filters */
/** @var array<string,int> $counts */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$statuses = [
    'actionable'  => 'To do (requested + in progress)',
    ''            => 'All statuses',
    'requested'   => 'Requested',
    'in_progress' => 'In progress',
    'eligible'    => 'Eligible (awaiting provider)',
    'delivered'   => 'Delivered',
    'cancelled'   => 'Cancelled',
];
$statusBadge = [
    'eligible'    => 'badge-neutral',
    'requested'   => 'badge-urgent',
    'in_progress' => 'badge-confirmed',
    'delivered'   => 'badge-verified',
    'cancelled'   => 'badge-neutral',
];
$queue = ($counts['requested'] ?? 0) + ($counts['in_progress'] ?? 0);
$qs = static function (array $extra) use ($filters): string {
    $params = array_filter([
        'status' => $filters['status'] ?? '',
        'q'      => $filters['q'] ?? '',
    ] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Ad graphics <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <?php if ($queue > 0): ?>
            <span class="badge badge-urgent"><?= (int) $queue ?> to fulfil</span>
        <?php endif; ?>
    </div>
    <p class="muted">Track founding-provider free ad graphic requests from launch towns. Providers claim, verify, then submit a brief; you design and upload desktop + mobile versions here.</p>

    <?php if (!$schemaReady): ?>
        <p style="margin:1rem 0;padding:.75rem;border-left:4px solid #e67e22;background:#fef9f0">
            <strong>Database update required.</strong> Apply migration 026 via <a href="<?= e(url('admin/maintenance')) ?>">Maintenance</a> before using this queue.
        </p>
    <?php else: ?>
        <div class="stat-grid" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(120px,1fr))">
            <div class="stat"><div class="num"><?= (int) ($counts['requested'] ?? 0) ?></div><div class="label">Requested</div></div>
            <div class="stat"><div class="num"><?= (int) ($counts['in_progress'] ?? 0) ?></div><div class="label">In progress</div></div>
            <div class="stat"><div class="num"><?= (int) ($counts['eligible'] ?? 0) ?></div><div class="label">Awaiting brief</div></div>
            <div class="stat"><div class="num"><?= (int) ($counts['delivered'] ?? 0) ?></div><div class="label">Delivered</div></div>
        </div>

        <form method="get" action="<?= e(url('admin/promotions')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
            <div class="form-group mb-0">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Business, email, headline">
            </div>
            <div class="form-group mb-0">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($schemaReady): ?>
<div class="card" style="margin-top:1rem">
    <?php if ($rows === []): ?>
        <p class="muted mb-0">No ad graphic requests match this filter.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Town</th>
                        <th>Status</th>
                        <th>Brief</th>
                        <th>Requested</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $town = (string) ($row['town_name'] ?? '');
                    if ($town !== '' && !empty($row['state_abbr'])) {
                        $town .= ', ' . $row['state_abbr'];
                    }
                    $badge = $statusBadge[(string) $row['status']] ?? 'badge-neutral';
                    ?>
                    <tr>
                        <td>
                            <strong><?= $this->e((string) $row['business_name']) ?></strong>
                            <?php if (!empty($row['is_verified'])): ?> <span class="badge badge-verified">Verified</span><?php endif; ?>
                            <?php if (!empty($row['is_featured'])): ?> <span class="badge badge-confirmed">Featured</span><?php endif; ?>
                        </td>
                        <td><?= $town !== '' ? $this->e($town) : '—' ?></td>
                        <td><span class="badge <?= $badge ?>"><?= $this->e(ucfirst(str_replace('_', ' ', (string) $row['status']))) ?></span></td>
                        <td>
                            <?php if (!empty($row['headline'])): ?>
                                <?= $this->e((string) $row['headline']) ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($row['requested_at']) ? $this->e((string) $row['requested_at']) : '—' ?></td>
                        <td><a href="<?= e(url('admin/promotions/show?id=' . (int) $row['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="btn-row" style="margin-top:1rem">
                <?php if ($page > 1): ?>
                    <a class="btn btn-ghost" href="<?= e(url('admin/promotions' . $qs(['page' => $page - 1]))) ?>">Previous</a>
                <?php endif; ?>
                <span class="muted">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-ghost" href="<?= e(url('admin/promotions' . $qs(['page' => $page + 1]))) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
