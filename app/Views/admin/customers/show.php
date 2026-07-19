<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $customer */
/** @var array<string,string> $contacts */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $saved */
/** @var array<int,array<string,mixed>> $alerts */
/** @var array<int,array<string,mixed>> $requests */
$this->extend('layouts.admin');
$id = (int) $customer['id'];
$userId = (int) $customer['user_id'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h1 style="margin:0"><?= $this->e((string) $customer['name']) ?></h1>
            <p class="muted" style="margin:.25rem 0 0">
                <?= $this->e((string) $customer['email']) ?>
                <?= $customer['phone'] ? ' · ' . $this->e((string) $customer['phone']) : '' ?>
            </p>
        </div>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-ghost" href="<?= e(url('admin/customers')) ?>">Back to customers</a>
            <a class="btn btn-ghost" href="<?= e(url('admin/users/show?id=' . $userId)) ?>">User account</a>
        </div>
    </div>
</div>

<div class="grid grid-2" style="gap:1rem;align-items:flex-start">
    <div class="card">
        <h2 style="margin:0 0 .5rem">Customer details</h2>
        <form method="post" action="<?= e(url('admin/customers/save')) ?>" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <label>
                <span>Preferred contact</span>
                <select name="preferred_contact">
                    <?php foreach ($contacts as $value => $label): ?>
                        <option value="<?= e_attr((string) $value) ?>" <?= (string) $customer['preferred_contact'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Home town</span>
                <select name="home_town_id">
                    <option value="">— none —</option>
                    <?php foreach ($towns as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($customer['home_town_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Notes</span><textarea name="notes" rows="3" maxlength="2000"><?= $this->e((string) ($customer['notes'] ?? '')) ?></textarea></label>
            <div class="btn-row"><button type="submit" class="btn btn-primary">Save customer</button></div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin:0 0 .5rem">Saved locations</h2>
        <?php if ($saved === []): ?><p class="muted">None saved.</p><?php else: ?>
            <ul class="list-plain">
                <?php foreach ($saved as $s): ?>
                    <li><?= $this->e((string) $s['town_name']) ?><?= $s['label'] ? ' — ' . $this->e((string) $s['label']) : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2 style="margin:1rem 0 .5rem">Alerts</h2>
        <?php if ($alerts === []): ?><p class="muted">No alerts configured.</p><?php else: ?>
            <ul class="list-plain">
                <?php foreach ($alerts as $a): ?>
                    <li>
                        <?= $this->e((string) ($a['category_name'] ?? 'Any service')) ?>
                        in <?= $this->e((string) ($a['town_name'] ?? $a['region_name'] ?? 'any area')) ?>
                        <?= (int) $a['is_active'] === 1 ? '' : ' <span class="badge badge-neutral">inactive</span>' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 style="margin:0 0 .5rem">Service request history</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Title</th><th>Urgency</th><th>Status</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><code><?= $this->e((string) $r['reference']) ?></code></td>
                    <td><?= $this->e((string) $r['title']) ?></td>
                    <td class="muted"><?= $this->e(ucfirst((string) $r['urgency'])) ?></td>
                    <td><span class="badge badge-neutral"><?= $this->e(ucwords(str_replace('_', ' ', (string) $r['status']))) ?></span></td>
                    <td class="muted"><?= $this->e((string) $r['created_at']) ?></td>
                    <td><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/requests/show?id=' . (int) $r['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requests === []): ?><tr><td colspan="6" class="muted">No service requests yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
