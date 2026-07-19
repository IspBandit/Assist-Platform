<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $user */
/** @var array<string,string> $statuses */
/** @var array<int,array<string,mixed>> $allRoles */
/** @var array<int,int> $userRoleIds */
/** @var bool $isSuperAdmin */
$this->extend('layouts.admin');
$isEdit = $user !== null;
?>
<?php $this->section('content'); ?>
<div class="card" style="max-width:720px">
    <h1 style="margin:0"><?= $isEdit ? 'Edit user' : 'New user' ?></h1>
    <form method="post" action="<?= e(url('admin/users/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><?php endif; ?>

        <div class="grid grid-2" style="gap:1rem">
            <label><span>Name</span><input type="text" name="name" required maxlength="150" value="<?= e_attr((string) ($user['name'] ?? '')) ?>"></label>
            <label><span>Email</span><input type="email" name="email" required maxlength="190" value="<?= e_attr((string) ($user['email'] ?? '')) ?>"></label>
            <label><span>Phone</span><input type="text" name="phone" maxlength="40" value="<?= e_attr((string) ($user['phone'] ?? '')) ?>"></label>
            <label>
                <span>Status</span>
                <select name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e_attr((string) $value) ?>" <?= ($user['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label class="checkbox" style="display:flex;align-items:center;gap:.5rem">
            <input type="checkbox" name="marketing_opt_in" value="1" <?= ($user['marketing_opt_in'] ?? 0) ? 'checked' : '' ?>>
            <span>Marketing opt-in</span>
        </label>

        <fieldset style="border:1px solid var(--line);border-radius:8px;padding:1rem">
            <legend style="padding:0 .5rem">Roles</legend>
            <div class="grid grid-2" style="gap:.5rem">
                <?php foreach ($allRoles as $r): ?>
                    <?php
                        $isSuperRole = (string) $r['slug'] === 'super-administrator';
                        $disabled = $isSuperRole && !$isSuperAdmin;
                        $checked = in_array((int) $r['id'], $userRoleIds, true);
                    ?>
                    <label class="checkbox" style="display:flex;align-items:center;gap:.5rem<?= $disabled ? ';opacity:.5' : '' ?>">
                        <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" <?= $checked ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                        <span><?= $this->e((string) $r['name']) ?><?php if ($disabled): ?> <span class="muted">(super admin only)</span><?php endif; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <label><span>Internal notes</span><textarea name="internal_notes" rows="3" maxlength="2000"><?= $this->e((string) ($user['internal_notes'] ?? '')) ?></textarea></label>

        <?php if (!$isEdit): ?>
            <label class="checkbox" style="display:flex;align-items:center;gap:.5rem">
                <input type="checkbox" name="send_reset" value="1" checked>
                <span>Email a password-setup link so the user can choose their own password</span>
            </label>
        <?php endif; ?>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
            <a class="btn btn-ghost" href="<?= e(url($isEdit ? 'admin/users/show?id=' . (int) $user['id'] : 'admin/users')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
