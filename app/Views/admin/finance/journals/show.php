<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $entry */
/** @var array<int,array<string,mixed>> $lines */
/** @var array<string,mixed>|null $reversalOf */
$this->extend('layouts.admin');
$fmt = static fn ($v): string => $v > 0 ? '$' . number_format((float) $v, 2) : '';
$isPosted = (string) $entry['status'] === 'posted';
?>
<?php $this->section('content'); ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
            <h1 style="margin:0"><?= $this->e((string) $entry['entry_number']) ?></h1>
            <p class="muted" style="margin:.25rem 0 0">
                <?= $this->e((string) $entry['transaction_date']) ?> ·
                status <strong><?= $this->e((string) $entry['status']) ?></strong>
                <?php if ($entry['source_type']): ?> · source <?= $this->e((string) $entry['source_type']) ?> <?= $this->e((string) ($entry['source_number'] ?? '')) ?><?php endif; ?>
            </p>
        </div>
        <a class="btn btn-ghost" href="<?= e(url('admin/finance/journals')) ?>">Back to journals</a>
    </div>
    <?php if ($entry['description']): ?><p style="margin:.5rem 0 0"><?= $this->e((string) $entry['description']) ?></p><?php endif; ?>
    <?php if ($reversalOf !== null): ?>
        <div class="alert alert-info" style="margin-top:.75rem">This entry reverses
            <a href="<?= e(url('admin/finance/journals/show?id=' . (int) $reversalOf['id'])) ?>"><?= $this->e((string) $reversalOf['entry_number']) ?></a>.</div>
    <?php endif; ?>

    <div class="table-wrap" style="margin-top:1rem">
        <table class="data">
            <thead><tr><th>Account</th><th>Description</th><th>Tax</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><code><?= $this->e((string) $l['account_code']) ?></code></td>
                    <td><?= $this->e((string) ($l['description'] ?? '')) ?></td>
                    <td class="muted"><?= $this->e((string) ($l['tax_code'] ?? '')) ?></td>
                    <td style="text-align:right"><?= $this->e($fmt($l['debit'])) ?></td>
                    <td style="text-align:right"><?= $this->e($fmt($l['credit'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600">
                    <td colspan="3">Totals</td>
                    <td style="text-align:right">$<?= number_format((float) $entry['total_debit'], 2) ?></td>
                    <td style="text-align:right">$<?= number_format((float) $entry['total_credit'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($isPosted && can('owner_finance.manage_journals')): ?>
        <div class="card" style="margin-top:1rem;background:#fbf7ef">
            <h3 style="margin:0 0 .5rem">Reverse this entry</h3>
            <p class="muted" style="margin:0 0 .75rem">Posted entries cannot be edited or deleted. Reversing creates an equal-and-opposite entry dated today and marks this one reversed.</p>
            <form method="post" action="<?= e(url('admin/finance/journals/reverse')) ?>" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                <label><span>Reason (required)</span><input type="text" name="reason" maxlength="500" required></label>
                <div class="btn-row"><button type="submit" class="btn btn-secondary">Reverse entry</button></div>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
