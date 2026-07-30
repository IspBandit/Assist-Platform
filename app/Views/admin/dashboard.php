<?php
/** @var \App\Core\View $this */
/** @var array $stats */
/** @var array $recentActivity */
/** @var array $tasks */
/** @var array<int,string> $dashboardWarnings */
$this->extend('layouts.admin');
$labels = [
    'new_requests' => 'New requests', 'open_requests' => 'Open requests',
    'pending_providers' => 'Pending providers', 'pending_documents' => 'Pending documents',
    'active_providers' => 'Active providers', 'active_runs' => 'Active runs',
    'forming_runs' => 'Runs forming', 'confirmed_runs' => 'Confirmed runs',
    'customers' => 'Customers', 'parks' => 'Caravan parks',
    'prospects' => 'Provider prospects', 'failed_emails' => 'Failed emails',
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
    'brand_accounts' => can('users.manage') ? '/admin/users' : null,
    'social_assets' => can('content.manage') ? '/admin/social-media' : null,
    'trailer_listings' => can('providers.manage') ? '/admin/trailer-listings' : null,
    'regulatory_documents' => can('regulatory.manage') ? '/admin/trust-growth' : null,
];
$attentionKeys = ['new_requests', 'open_requests', 'pending_providers', 'pending_documents', 'failed_emails'];
$attentionStats = array_intersect_key($stats, array_flip($attentionKeys));
$inventoryStats = array_diff_key($stats, array_flip($attentionKeys));
?>
<?php $this->section('content'); ?>

<div class="alert alert-info">
    <strong><?= $this->e($dashboardBrand->name()) ?> workspace</strong> · Launch mode: <strong><?= $this->e(ucfirst($launchMode)) ?></strong>.
    <?php if ($maintenance): ?> <strong style="color:var(--red)">Maintenance mode is ON.</strong><?php endif; ?>
</div>

<?php if (can('notifications.send')): ?>
<section class="card" aria-labelledby="dashboard-campaign-heading">
    <div class="admin-section-heading">
        <div>
            <p class="eyebrow">Provider growth</p>
            <h2 id="dashboard-campaign-heading">Email campaigns</h2>
            <p class="muted">Review recipients, send the preview to yourself, then start the controlled provider batch.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('admin/notifications')) ?>">Open email campaigns</a>
    </div>
</section>
<?php endif; ?>

<?php if ($dashboardWarnings !== []): ?>
<div class="alert alert-error" role="alert">
    <strong>Some dashboard data is unavailable.</strong>
    The affected sections are: <?= $this->e(implode(', ', $dashboardWarnings)) ?>.
    The problem has been written to System logs; these values are not being shown as zero.
</div>
<?php endif; ?>

<section aria-labelledby="dashboard-attention-heading">
<div class="admin-section-heading"><div><p class="eyebrow">Act first</p><h2 id="dashboard-attention-heading">Needs attention</h2><p class="muted">Live queues that can block customers, providers or outreach.</p></div></div>
<div class="stat-grid stat-grid--attention">
    <?php foreach ($attentionStats as $key => $value): ?>
        <?php $destination = $destinations[$key] ?? null; ?>
        <?php if ($destination !== null && $value !== null): ?><a class="stat stat-link" href="<?= e(url(ltrim($destination, '/'))) ?>"><?php else: ?><div class="stat<?= $value === null ? ' stat-unavailable' : '' ?>"><?php endif; ?>
            <div class="num"><?= $value === null ? '—' : (int) $value ?></div>
            <div class="label"><?= $this->e($labels[$key] ?? $key) ?></div>
        <?php if ($destination !== null && $value !== null): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
</div>
</section>

<?php if (is_array($websiteSummary)): ?>
<section class="card dashboard-insights-card">
    <div class="admin-section-heading">
        <div>
            <p class="eyebrow">Last 30 days</p>
            <h2>Website activity</h2>
            <p class="muted">Anonymous visitor behaviour and provider-interest signals for this workspace.</p>
        </div>
        <a class="btn btn-ghost" href="<?= e(url('admin/demand')) ?>">Open website insights</a>
    </div>
    <div class="dashboard-insight-row">
        <?php foreach ([
            ['Visitors', $websiteSummary['visitors'] ?? 0],
            ['Page views', $websiteSummary['page_views'] ?? 0],
            ['Searches', $websiteSummary['searches'] ?? 0],
            ['Profiles opened', $websiteSummary['profile_views'] ?? 0],
            ['Contact actions', $websiteSummary['contact_actions'] ?? 0],
        ] as [$label, $value]): ?>
            <div><strong><?= number_format((int) $value) ?></strong><span><?= $this->e($label) ?></span></div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($inventoryStats !== []): ?>
<details class="card dashboard-secondary" data-mobile-collapse>
    <summary><h2>Directory and workspace totals</h2><span>Reference information</span></summary>
    <div class="stat-grid">
        <?php foreach ($inventoryStats as $key => $value): ?>
            <?php $destination = $destinations[$key] ?? null; ?>
            <?php if ($destination !== null && $value !== null): ?><a class="stat stat-link" href="<?= e(url(ltrim($destination, '/'))) ?>"><?php else: ?><div class="stat<?= $value === null ? ' stat-unavailable' : '' ?>"><?php endif; ?>
                <div class="num"><?= $value === null ? '—' : (int) $value ?></div><div class="label"><?= $this->e($labels[$key] ?? $key) ?></div>
            <?php if ($destination !== null && $value !== null): ?></a><?php else: ?></div><?php endif; ?>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php if ($canViewAudit || $canViewHealth): ?>
<div class="grid grid-2" style="margin-top:1.5rem">
    <?php if ($canViewAudit): ?>
    <details class="card dashboard-secondary">
        <summary><h2>Recent administrative activity</h2><span>System detail</span></summary>
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
    </details>
    <?php endif; ?>

    <?php if ($canViewHealth): ?>
    <details class="card dashboard-secondary">
        <summary><h2>Scheduled tasks</h2><span>System detail</span></summary>
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
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
