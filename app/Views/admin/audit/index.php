<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $action */
/** @var string $search */
/** @var array<int,array<string,mixed>> $actions */
$this->extend('layouts.admin');
$pages = (int) ceil($total / $perPage);
$qs = static function (array $extra) use ($action, $search): string {
    return http_build_query(array_merge(['action' => $action, 'q' => $search], $extra));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Audit log</h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/audit/export?' . $qs([]))) ?>">Export CSV</a>
    </div>

    <form method="get" action="<?= e(url('admin/audit')) ?>" class="btn-row" style="align-items:end;margin-top:1rem">
        <div class="form-group" style="margin:0">
            <label for="action">Action</label>
            <select id="action" name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e_attr((string) $a['action']) ?>" <?= $action === (string) $a['action'] ? 'selected' : '' ?>><?= $this->e((string) $a['action']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label for="q">Search (object / value)</label>
            <input type="text" id="q" name="q" value="<?= e_attr($search) ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <p class="muted" style="margin-top:1rem"><?= number_format($total) ?> record(s).</p>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Object</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= $this->e((string) $r['created_at']) ?></td>
                    <td><?= $this->e((string) ($r['user_name'] ?? 'system')) ?></td>
                    <td><code><?= $this->e((string) $r['action']) ?></code></td>
                    <td><?= $this->e(trim((string) ($r['object_type'] ?? '') . ' ' . (string) ($r['object_id'] ?? ''))) ?></td>
                    <td><?= $this->e((string) ($r['ip_address'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="5" class="muted">No audit records match.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?><a class="btn btn-ghost" href="<?= e(url('admin/audit?' . $qs(['page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost" href="<?= e(url('admin/audit?' . $qs(['page' => $page + 1]))) ?>">Next</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
