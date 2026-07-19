<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $accounts */
/** @var array<string,string> $types */
$this->extend('layouts.admin');
// Group accounts by type for readability.
$grouped = [];
foreach ($accounts as $a) {
    $grouped[(string) $a['type']][] = $a;
}
?>
<?php $this->section('content'); ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <h1 style="margin:0">Chart of accounts</h1>
            <p class="muted" style="margin:.25rem 0 0">The accounts your general ledger posts to. Control accounts are protected and cannot be deleted.</p>
        </div>
        <?php if (can('owner_finance.manage_accounts')): ?>
            <a class="btn btn-primary" href="<?= e(url('admin/finance/accounts/new')) ?>">New account</a>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($types as $typeKey => $typeLabel): ?>
    <?php $rows = $grouped[$typeKey] ?? []; if ($rows === []) { continue; } ?>
    <div class="card" style="margin-top:1rem">
        <h2 style="margin:0 0 .5rem"><?= $this->e($typeLabel) ?></h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Code</th><th>Name</th><th>Tax default</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr<?= (int) $a['is_active'] === 0 ? ' style="opacity:.55"' : '' ?>>
                        <td><code><?= $this->e((string) $a['code']) ?></code></td>
                        <td>
                            <?= $this->e((string) $a['name']) ?>
                            <?php if ((int) $a['is_system'] === 1): ?><span class="badge badge-verified" style="margin-left:.4rem">control</span><?php endif; ?>
                        </td>
                        <td class="muted"><?= $this->e((string) ($a['default_tax_code'] ?? '')) ?></td>
                        <td><?= (int) $a['is_active'] === 1 ? 'Active' : 'Archived' ?></td>
                        <td style="text-align:right">
                            <?php if (can('owner_finance.manage_accounts')): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/finance/accounts/edit?id=' . (int) $a['id'])) ?>">Edit</a>
                                <?php if ((int) $a['is_system'] === 0): ?>
                                    <form method="post" action="<?= e(url('admin/finance/accounts/toggle')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"><?= (int) $a['is_active'] === 1 ? 'Archive' : 'Reactivate' ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php $this->endSection(); ?>
