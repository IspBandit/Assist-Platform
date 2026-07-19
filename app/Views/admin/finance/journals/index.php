<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $entries */
/** @var string $status */
$this->extend('layouts.admin');
$fmt = static fn ($v): string => '$' . number_format((float) $v, 2);
$filters = ['' => 'All', 'posted' => 'Posted', 'draft' => 'Draft', 'reversed' => 'Reversed'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h1 style="margin:0">Journals</h1>
        <?php if (can('owner_finance.manage_journals')): ?>
            <a class="btn btn-primary" href="<?= e(url('admin/finance/journals/new')) ?>">New manual journal</a>
        <?php endif; ?>
    </div>
    <div class="btn-row" style="margin-top:.75rem">
        <?php foreach ($filters as $key => $label): ?>
            <a class="btn <?= $status === $key ? 'btn-secondary' : 'btn-ghost' ?> btn-sm" href="<?= e(url('admin/finance/journals' . ($key !== '' ? '?status=' . $key : ''))) ?>"><?= $this->e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap" style="margin-top:.75rem">
        <table class="data">
            <thead><tr><th>Entry</th><th>Date</th><th>Description</th><th>Source</th><th>Status</th><th style="text-align:right">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $e): ?>
                <tr>
                    <td><a href="<?= e(url('admin/finance/journals/show?id=' . (int) $e['id'])) ?>"><?= $this->e((string) $e['entry_number']) ?></a></td>
                    <td><?= $this->e((string) $e['transaction_date']) ?></td>
                    <td><?= $this->e((string) ($e['description'] ?? '')) ?></td>
                    <td class="muted"><?= $this->e((string) ($e['source_type'] ?? '')) ?><?= $e['source_number'] ? ' ' . $this->e((string) $e['source_number']) : '' ?></td>
                    <td><?= $this->e((string) $e['status']) ?></td>
                    <td style="text-align:right"><?= $this->e($fmt($e['total_debit'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($entries === []): ?><tr><td colspan="6" class="muted">No journals found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
