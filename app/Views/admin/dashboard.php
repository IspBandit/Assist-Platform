<?php
/** @var \App\Core\View $this */
/** @var array $stats */
/** @var array $recentActivity */
/** @var array $tasks */
$this->extend('layouts.admin');
$labels = [
    'new_requests' => 'New requests', 'open_requests' => 'Open requests',
    'pending_providers' => 'Pending providers', 'pending_documents' => 'Pending documents',
    'active_providers' => 'Active providers', 'active_runs' => 'Active runs',
    'forming_runs' => 'Runs forming', 'confirmed_runs' => 'Confirmed runs',
    'customers' => 'Customers', 'parks' => 'Caravan parks',
    'prospects' => 'Provider prospects', 'failed_emails' => 'Failed emails',
    'ad_graphics_queue' => 'Ad graphics to fulfil',
];
?>
<?php $this->section('content'); ?>

<div class="alert alert-info">
    Launch mode: <strong><?= $this->e(ucfirst($launchMode)) ?></strong>.
    <?php if ($maintenance): ?> <strong style="color:var(--red)">Maintenance mode is ON.</strong><?php endif; ?>
    <?php if (!empty($adGraphicsQueue)): ?>
        · <a href="<?= e(url('admin/promotions')) ?>"><strong><?= (int) $adGraphicsQueue ?></strong> ad graphic<?= (int) $adGraphicsQueue === 1 ? '' : 's' ?> awaiting fulfilment</a>
    <?php endif; ?>
</div>

<div class="stat-grid">
    <?php foreach ($stats as $key => $value): ?>
        <div class="stat">
            <div class="num"><?= (int) $value ?></div>
            <div class="label"><?= $this->e($labels[$key] ?? $key) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2" style="margin-top:1.5rem">
    <div class="card">
        <h2>Recent activity</h2>
        <?php if ($recentActivity === []): ?>
            <p class="muted">No activity recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Action</th><th>Object</th><th>User</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentActivity as $a): ?>
                        <tr>
                            <td><?= $this->e($a['action']) ?></td>
                            <td><?= $this->e(trim(($a['object_type'] ?? '') . ' ' . ($a['object_id'] ?? ''))) ?></td>
                            <td><?= $this->e($a['user_name'] ?? 'system') ?></td>
                            <td><?= $this->e((string) $a['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Scheduled tasks</h2>
        <?php if ($tasks === []): ?>
            <p class="muted">No scheduled tasks registered.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Task</th><th>Status</th><th>Last run</th></tr></thead>
                    <tbody>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td><?= $this->e($t['task_key']) ?></td>
                            <td><span class="badge badge-<?= $t['last_status'] === 'success' ? 'confirmed' : ($t['last_status'] === 'failed' ? 'urgent' : 'neutral') ?>"><?= $this->e($t['last_status']) ?></span></td>
                            <td><?= $this->e((string) ($t['last_run_at'] ?? 'never')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
