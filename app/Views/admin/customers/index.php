<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $customers */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $q */
/** @var int $townId */
/** @var array<int,array<string,mixed>> $towns */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$qs = static function (array $extra) use ($q, $townId): string {
    $params = array_filter(['q' => $q, 'town' => $townId ?: ''] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Customers <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/customers/export' . $qs([]))) ?>">Export CSV</a>
    </div>
    <form method="get" action="<?= e(url('admin/customers')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
        <div class="form-group mb-0">
            <label for="town">Home town</label>
            <select id="town" name="town">
                <option value="">All towns</option>
                <?php foreach ($towns as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $townId === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone">
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-secondary" type="submit">Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Email</th><th>Home town</th><th>Preferred contact</th><th>Requests</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= $this->e((string) $c['name']) ?></strong></td>
                    <td><?= $this->e((string) $c['email']) ?></td>
                    <td><?= $this->e((string) ($c['town_name'] ?? '—')) ?: '—' ?></td>
                    <td class="muted"><?= $this->e(ucfirst((string) $c['preferred_contact'])) ?></td>
                    <td><?= (int) $c['request_count'] ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/customers/show?id=' . (int) $c['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($customers === []): ?>
                <tr><td colspan="6" class="muted">No customers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/customers' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/customers' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
