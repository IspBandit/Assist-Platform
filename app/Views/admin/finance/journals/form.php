<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $accounts */
/** @var array<int,array<string,mixed>> $taxCodes */
$this->extend('layouts.admin');
$rowCount = 8;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0">New manual journal</h1>
    <p class="muted">Enter at least two lines. Total debits must equal total credits — this is enforced when you post. Posted entries are immutable; correct them by reversal.</p>

    <form method="post" action="<?= e(url('admin/finance/journals/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
            <label>
                <span>Transaction date</span>
                <input type="date" name="transaction_date" value="<?= e_attr(date('Y-m-d')) ?>" required>
            </label>
            <label style="grid-column:1/-1">
                <span>Description</span>
                <input type="text" name="description" maxlength="500" placeholder="What is this journal for?">
            </label>
        </div>

        <div class="table-wrap" style="margin-top:.5rem">
            <table class="data">
                <thead><tr><th style="width:40%">Account</th><th>Description</th><th style="width:130px;text-align:right">Debit</th><th style="width:130px;text-align:right">Credit</th></tr></thead>
                <tbody>
                <?php for ($i = 0; $i < $rowCount; $i++): ?>
                    <tr>
                        <td>
                            <select name="account_code[]">
                                <option value="">—</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= e_attr((string) $a['code']) ?>"><?= $this->e((string) $a['code'] . ' — ' . (string) $a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="line_description[]" maxlength="500"></td>
                        <td><input type="number" step="0.01" min="0" name="debit[]" style="text-align:right"></td>
                        <td><input type="number" step="0.01" min="0" name="credit[]" style="text-align:right"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Post journal</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/finance/journals')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
