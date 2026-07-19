<?php
/** @var \App\Core\View $this */
/** @var array<string,float|int> $metrics */
/** @var array{rows:array,total_debit:float,total_credit:float,balanced:bool} $trial */
/** @var array<int,array<string,mixed>> $recent */
/** @var bool $gstRegistered */
$this->extend('layouts.admin');
$fmt = static fn ($v): string => '$' . number_format((float) $v, 2);
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0">Finance</h1>
    <p class="muted">VanAssist platform-owner bookkeeping. Figures below are built from <strong>posted</strong> general-ledger entries only, so they always reconcile to the ledger.</p>
    <?php if (!$gstRegistered): ?>
        <div class="alert alert-info" style="margin-top:1rem">
            GST is <strong>not enabled</strong> (sole trader, not registered). Invoices are labelled “Invoice”, not “Tax Invoice”, and no GST is added. Enable GST with an effective date in Finance settings once registered — confirm with your accountant first.
        </div>
    <?php endif; ?>
</div>

<div class="grid-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-top:1rem">
    <?php
    $cards = [
        ['Bank balance (ledger)', $metrics['bank_balance']],
        ['Accounts receivable', $metrics['accounts_receivable']],
        ['Accounts payable', $metrics['accounts_payable']],
        ['Provider funds held', $metrics['provider_funds_held']],
        ['Income (this month)', $metrics['income_mtd']],
        ['Expenses (this month)', $metrics['expense_mtd']],
        ['Net (this month)', $metrics['net_mtd']],
        ['Net (financial year)', $metrics['net_fy']],
    ];
    foreach ($cards as [$label, $value]): ?>
        <div class="card" style="margin:0">
            <p class="muted" style="margin:0;font-size:.8rem"><?= $this->e($label) ?></p>
            <p style="margin:.25rem 0 0;font-size:1.4rem;font-weight:600"><?= $this->e($fmt($value)) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Trial balance</h2>
        <span class="badge <?= $trial['balanced'] ? 'badge-confirmed' : 'badge-urgent' ?>">
            <?= $trial['balanced'] ? 'Balanced' : 'OUT OF BALANCE' ?>
        </span>
    </div>
    <div class="table-wrap" style="margin-top:.75rem">
        <table class="data">
            <thead><tr><th>Code</th><th>Account</th><th>Type</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th></tr></thead>
            <tbody>
            <?php foreach ($trial['rows'] as $r): ?>
                <tr>
                    <td><code><?= $this->e((string) $r['code']) ?></code></td>
                    <td><?= $this->e((string) $r['name']) ?></td>
                    <td class="muted"><?= $this->e((string) $r['type']) ?></td>
                    <td style="text-align:right"><?= $r['debit'] > 0 ? $this->e($fmt($r['debit'])) : '' ?></td>
                    <td style="text-align:right"><?= $r['credit'] > 0 ? $this->e($fmt($r['credit'])) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($trial['rows'] === []): ?>
                <tr><td colspan="5" class="muted">No posted journal activity yet. Post a manual journal to see it here.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($trial['rows'] !== []): ?>
            <tfoot>
                <tr style="font-weight:600">
                    <td colspan="3">Totals</td>
                    <td style="text-align:right"><?= $this->e($fmt($trial['total_debit'])) ?></td>
                    <td style="text-align:right"><?= $this->e($fmt($trial['total_credit'])) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Recent journals</h2>
        <a class="btn btn-primary" href="<?= e(url('admin/finance/journals/new')) ?>">New manual journal</a>
    </div>
    <div class="table-wrap" style="margin-top:.75rem">
        <table class="data">
            <thead><tr><th>Entry</th><th>Date</th><th>Description</th><th>Source</th><th>Status</th><th style="text-align:right">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $e): ?>
                <tr>
                    <td><a href="<?= e(url('admin/finance/journals/show?id=' . (int) $e['id'])) ?>"><?= $this->e((string) $e['entry_number']) ?></a></td>
                    <td><?= $this->e((string) $e['transaction_date']) ?></td>
                    <td><?= $this->e((string) ($e['description'] ?? '')) ?></td>
                    <td class="muted"><?= $this->e((string) ($e['source_type'] ?? '')) ?></td>
                    <td><?= $this->e((string) $e['status']) ?></td>
                    <td style="text-align:right"><?= $this->e($fmt($e['total_debit'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recent === []): ?><tr><td colspan="6" class="muted">No journals yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
