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
    'brand_accounts' => 'Active brand accounts', 'social_assets' => 'Campaign assets',
    'saved_combinations' => 'Saved towing combinations', 'trailer_listings' => 'Trailer listings',
    'regulatory_documents' => 'Official rule documents', 'motorsport_venues' => 'Motorsport venues',
];
$destinations = [
    'new_requests' => can('requests.manage') ? '/admin/requests' : null,
    'open_requests' => can('requests.manage') ? '/admin/requests' : null,
    'pending_providers' => can('providers.manage') ? '/admin/providers' : null,
    'pending_documents' => can('providers.manage') ? '/admin/providers' : null,
    'active_providers' => can('providers.manage') ? '/admin/providers' : null,
    'active_runs' => can('runs.manage') ? '/admin/runs' : null,
    'customers' => can('customers.manage') ? '/admin/customers' : null,
    'parks' => can('parks.manage') ? '/admin/parks' : null,
    'prospects' => can('prospects.manage') ? '/admin/prospects' : null,
    'failed_emails' => can('email.manage') ? '/admin/email-templates' : null,
    'ad_graphics_queue' => can('providers.manage') ? '/admin/promotions' : null,
    'brand_accounts' => can('users.manage') ? '/admin/users' : null,
    'social_assets' => can('content.manage') ? '/admin/social-media' : null,
    'trailer_listings' => can('providers.manage') ? '/admin/trailer-listings' : null,
    'regulatory_documents' => can('regulatory.manage') ? '/admin/trust-growth' : null,
];
?>
<?php $this->section('content'); ?>

<div class="alert alert-info">
    <strong><?= $this->e($dashboardBrand->name()) ?> workspace</strong> · Launch mode: <strong><?= $this->e(ucfirst($launchMode)) ?></strong>.
    <?php if ($maintenance): ?> <strong style="color:var(--red)">Maintenance mode is ON.</strong><?php endif; ?>
    <?php if (!empty($adGraphicsQueue)): ?>
        · <a href="<?= e(url('admin/promotions')) ?>"><strong><?= (int) $adGraphicsQueue ?></strong> ad graphic<?= (int) $adGraphicsQueue === 1 ? '' : 's' ?> awaiting fulfilment</a>
    <?php endif; ?>
</div>

<div class="stat-grid">
    <?php foreach ($stats as $key => $value): ?>
        <?php $destination = $destinations[$key] ?? null; ?>
        <?php if ($destination !== null): ?><a class="stat stat-link" href="<?= e(url(ltrim($destination, '/'))) ?>"><?php else: ?><div class="stat"><?php endif; ?>
            <div class="num"><?= (int) $value ?></div>
            <div class="label"><?= $this->e($labels[$key] ?? $key) ?></div>
        <?php if ($destination !== null): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
</div>

<?php if ($canViewAudit || $canViewHealth): ?>
<div class="grid grid-2" style="margin-top:1.5rem">
    <?php if ($canViewAudit): ?>
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
    <?php endif; ?>

    <?php if ($canViewHealth): ?>
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
    <?php endif; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
