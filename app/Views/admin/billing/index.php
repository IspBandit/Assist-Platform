<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $plans */
/** @var array<int,array<string,mixed>> $flags */
/** @var bool $billingEnabled */
$this->extend('layouts.admin');
$money = static fn (int $cents): string => '$' . number_format($cents / 100, 2);
?>
<?php $this->section('content'); ?>

<?php if (!$billingEnabled): ?>
    <div class="card" style="border-left:4px solid #0f8b8d">
        <span class="badge badge-neutral">Billing disabled</span>
        <h1 style="margin-top:.5rem">Plans &amp; billing</h1>
        <p>VanAssist is running in <strong>free launch mode</strong> (<code>ENABLE_BILLING=false</code>). Nothing is charged, no checkout or provider billing portal is shown, and no provider feature is blocked by payment status.</p>
        <p class="muted">You can still configure plans, limits and entitlements privately below. They take effect only once billing is enabled.</p>
    </div>
<?php else: ?>
    <div class="card" style="border-left:4px solid #c8861a">
        <span class="badge badge-warning">Billing enabled</span>
        <h1 style="margin-top:.5rem">Plans &amp; billing</h1>
        <p>Active gateway: <strong><?= $this->e((string) $gateway) ?></strong>. Paid features and the provider billing portal are live.</p>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Provider plans</h2>
    <p class="muted">Names, prices, limits and entitlements are fully editable. Business logic never hardcodes these — providers are evaluated through the entitlement service.</p>
    <table class="table">
        <thead>
            <tr><th>Plan</th><th>Slug</th><th>Monthly</th><th>Annual</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
            <tr>
                <td>
                    <strong><?= $this->e((string) $plan['public_name']) ?></strong>
                    <?php if ($plan['is_recommended']): ?><span class="badge badge-success">Recommended</span><?php endif; ?>
                    <?php if ($plan['is_legacy']): ?><span class="badge badge-neutral">Legacy</span><?php endif; ?>
                    <br><span class="muted" style="font-size:.85rem"><?= $this->e((string) $plan['internal_name']) ?></span>
                </td>
                <td><code><?= $this->e((string) $plan['slug']) ?></code></td>
                <td><?= $money((int) $plan['monthly_price_cents']) ?></td>
                <td><?= $money((int) $plan['annual_price_cents']) ?></td>
                <td><?= $plan['is_active'] ? 'Active' : 'Inactive' ?><?= $plan['is_public'] ? ', public' : ', private' ?></td>
                <td><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/billing/plans/edit?id=' . (int) $plan['id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Billing feature flags</h2>
    <p class="muted">Each capability is hidden until explicitly enabled (managed via environment / super-admin settings). A feature is never exposed merely because its tables exist.</p>
    <table class="table">
        <thead><tr><th>Flag</th><th>State</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($flags as $flag): ?>
            <tr>
                <td><code><?= $this->e((string) $flag['flag_key']) ?></code></td>
                <td><?= $flag['is_enabled'] ? '<span class="badge badge-success">On</span>' : '<span class="badge badge-neutral">Off</span>' ?></td>
                <td class="muted"><?= $this->e((string) ($flag['description'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" style="border-left:4px solid #c8861a">
    <h2>Tax &amp; invoice templates</h2>
    <p>GST and tax-invoice settings status: <strong><?= $this->e($taxReview) ?></strong>.</p>
    <p class="muted">Generated invoices are <strong>marked for accountant review</strong> and must not be treated as compliant with tax obligations until professionally reviewed before launch.</p>
</div>

<?php $this->endSection(); ?>
