<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1>Team permissions</h1>
    <p>Invite collaborators by existing platform user ID. Email invites remain planned.</p>
    <form method="post" action="<?= e(url('portal/manufacturer/team')) ?>" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <label>User ID <input type="number" name="user_id" min="1" required></label>
        <label>Role
            <select name="role_label">
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
                <option value="owner">Owner</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Add team member</button>
    </form>
    <h2>Current team</h2>
    <?php if ($team === []): ?>
        <p class="muted">No additional team members yet. Claimed owner access remains on your account.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Email</th><th>Name</th><th>Role</th></tr></thead>
            <tbody>
            <?php foreach ($team as $member): ?>
                <tr>
                    <td><?= $this->e((string) ($member['email'] ?? '')) ?></td>
                    <td><?= $this->e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?></td>
                    <td><?= $this->e($member['role_label']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a></p>
</div></section>
<?php $this->endSection(); ?>
