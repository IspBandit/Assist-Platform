<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $flags */
/** @var bool $billingEnv */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0">Feature flags</h1>
    <p class="muted">These control future platform features. Keep them off during the free launch unless you have tested the related flow.</p>
    <div class="alert alert-info" style="margin-top:1rem">
        Master billing switch (<code>ENABLE_BILLING</code>) is currently
        <strong><?= $billingEnv ? 'ON' : 'OFF' ?></strong>. That switch lives in <code>.env</code>, not here —
        the <code>billing</code> flag below is advisory until billing is enabled in the environment.
    </div>

    <form method="post" action="<?= e(url('admin/feature-flags')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Enabled</th><th>Flag</th><th>Description</th></tr></thead>
                <tbody>
                <?php foreach ($flags as $flag): ?>
                    <tr>
                        <td><input type="checkbox" name="flags[<?= e_attr((string) $flag['flag_key']) ?>]" value="1" <?= $flag['is_enabled'] ? 'checked' : '' ?>></td>
                        <td><code><?= $this->e((string) $flag['flag_key']) ?></code></td>
                        <td class="muted"><?= $this->e((string) ($flag['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($flags === []): ?><tr><td colspan="3" class="muted">No feature flags defined.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="btn-row"><button type="submit" class="btn btn-primary">Save flags</button></div>
    </form>
</div>
<?php $this->endSection(); ?>
