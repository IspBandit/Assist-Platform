<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $account */
/** @var array<string,string> $types */
/** @var array<int,array<string,mixed>> $taxCodes */
$this->extend('layouts.admin');
$isEdit = $account !== null;
$isSystem = $isEdit && (int) $account['is_system'] === 1;
?>
<?php $this->section('content'); ?>
<div class="card" style="max-width:640px">
    <h1 style="margin:0"><?= $isEdit ? 'Edit account' : 'New account' ?></h1>
    <form method="post" action="<?= e(url('admin/finance/accounts/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><?php endif; ?>

        <label>
            <span>Account code</span>
            <?php if ($isEdit): ?>
                <input type="text" value="<?= e_attr((string) $account['code']) ?>" disabled>
                <small class="muted">Codes are fixed once created to keep ledger history stable.</small>
            <?php else: ?>
                <input type="text" name="code" required maxlength="20" placeholder="e.g. 4870">
            <?php endif; ?>
        </label>

        <label>
            <span>Account name</span>
            <input type="text" name="name" required maxlength="190" value="<?= e_attr((string) ($account['name'] ?? '')) ?>">
        </label>

        <label>
            <span>Type</span>
            <select name="type" <?= $isSystem ? 'disabled' : '' ?>>
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e_attr($key) ?>" <?= ($account['type'] ?? '') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isSystem): ?><small class="muted">Control accounts cannot be retyped.</small><?php endif; ?>
        </label>

        <label>
            <span>Default tax code (optional)</span>
            <select name="default_tax_code">
                <option value="">— none —</option>
                <?php foreach ($taxCodes as $tc): ?>
                    <option value="<?= e_attr((string) $tc['code']) ?>" <?= ($account['default_tax_code'] ?? '') === $tc['code'] ? 'selected' : '' ?>>
                        <?= $this->e((string) $tc['code'] . ' — ' . (string) $tc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create account' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('admin/finance/accounts')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
