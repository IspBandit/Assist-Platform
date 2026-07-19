<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $prospect */
/** @var array<int,array<string,mixed>> $notes */
/** @var array<int,array<string,mixed>> $invitations */
$this->extend('layouts.admin');
$id = (int) $prospect['id'];
$label = static fn (string $s): string => ucwords(str_replace('_', ' ', $s));
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/prospects')) ?>">&laquo; Back to prospects</a>
    <div class="btn-row" style="justify-content:space-between;align-items:center;margin-top:.25rem">
        <h1 style="margin:0"><?= $this->e((string) $prospect['business_name']) ?></h1>
        <a class="btn btn-secondary" href="<?= e(url('admin/prospects/edit?id=' . $id)) ?>">Edit</a>
    </div>
    <p>
        <span class="badge badge-neutral"><?= $this->e($label((string) $prospect['outreach_status'])) ?></span>
    </p>
    <p class="muted">
        <?= $this->e((string) ($prospect['town_name'] ?? '—')) ?>
        <?php if ($prospect['email']): ?> · <?= $this->e((string) $prospect['email']) ?><?php endif; ?>
        <?php if ($prospect['phone']): ?> · <?= $this->e((string) $prospect['phone']) ?><?php endif; ?>
        <?php if ($prospect['website']): ?> · <a href="<?= e((string) $prospect['website']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
    </p>
    <?php if ($prospect['services_observed']): ?><p><strong>Services:</strong> <?= $this->e((string) $prospect['services_observed']) ?></p><?php endif; ?>
    <?php if ($prospect['notes']): ?><p><?= nl2br($this->e((string) $prospect['notes'])) ?></p><?php endif; ?>
</div>

<div class="card">
    <h2>Send invitation</h2>
    <p class="muted">Generates a secure link inviting this prospect to create their provider profile. The link expires in 14 days.</p>
    <form method="post" action="<?= e(url('admin/prospects/invite')) ?>" class="btn-row">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="email" name="email" value="<?= e((string) ($prospect['email'] ?? '')) ?>" placeholder="Email address" required style="min-width:260px">
        <button type="submit" class="btn btn-primary">Send invitation</button>
    </form>
    <?php if ($invitations !== []): ?>
        <table class="data" style="margin-top:1rem">
            <thead><tr><th>Email</th><th>Sent</th><th>Expires</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($invitations as $inv): ?>
                <tr>
                    <td><?= $this->e((string) $inv['email']) ?></td>
                    <td><?= $this->e((string) $inv['created_at']) ?></td>
                    <td><?= $this->e((string) $inv['expires_at']) ?></td>
                    <td><?= $inv['accepted_at'] ? '<span class="badge badge-verified">Accepted</span>' : '<span class="badge badge-confirmed">Pending</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Contact log</h2>
    <form method="post" action="<?= e(url('admin/prospects/note')) ?>" style="margin-bottom:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="grid grid-3" style="align-items:flex-end">
            <div class="form-group mb-0">
                <label for="note_type">Type</label>
                <select id="note_type" name="note_type">
                    <option value="note">Note</option>
                    <option value="call">Call</option>
                    <option value="email">Email</option>
                    <option value="meeting">Meeting</option>
                </select>
            </div>
            <div class="form-group mb-0" style="grid-column:span 2">
                <label for="body">Note</label>
                <input type="text" id="body" name="body" placeholder="What happened?" required>
            </div>
        </div>
        <div class="btn-row" style="margin-top:.75rem"><button type="submit" class="btn btn-secondary">Log contact</button></div>
    </form>
    <ul class="list-plain">
        <?php foreach ($notes as $n): ?>
            <li style="border-top:1px solid #e3e0d8;padding:.5rem 0">
                <div class="muted" style="font-size:.85rem"><span class="badge badge-neutral"><?= $this->e(ucfirst((string) $n['note_type'])) ?></span> <?= $this->e((string) ($n['admin_name'] ?? 'System')) ?> · <?= $this->e((string) $n['created_at']) ?></div>
                <div><?= nl2br($this->e((string) $n['body'])) ?></div>
            </li>
        <?php endforeach; ?>
        <?php if ($notes === []): ?><li class="muted">No contact logged yet.</li><?php endif; ?>
    </ul>
</div>
<?php $this->endSection(); ?>
