<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $providers */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array<string,mixed> $filters */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int,array<string,mixed>> $states */
/** @var array<string,int>|null $claimInviteStats */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$inviteOffset = (int) ($_GET['invite_offset'] ?? 0);
$statuses = ['' => 'All statuses', 'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'draft' => 'Draft'];
$sources = ['' => 'All listings', 'claimed' => 'Claimed only', 'unclaimed' => 'Unclaimed only'];
$badge = ['active' => 'badge-verified', 'pending' => 'badge-confirmed', 'suspended' => 'badge-neutral', 'rejected' => 'badge-neutral', 'draft' => 'badge-neutral'];
$f = static fn (string $key, $default = '') => $filters[$key] ?? $default;
$qs = static function (array $extra) use ($filters): string {
    $base = [
        'status' => $filters['status'] ?? '', 'q' => $filters['search'] ?? '', 'town' => $filters['town'] ?? '',
        'category' => $filters['category'] ?? '', 'state' => $filters['state'] ?? '', 'source' => $filters['source'] ?? '',
        'verified' => !empty($filters['verified']) ? 1 : '', 'featured' => !empty($filters['featured']) ? 1 : '',
    ];
    $params = array_filter($base + $extra, static fn ($v) => $v !== null && $v !== '' && $v !== 0);
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Providers <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-secondary" href="<?= e(url('admin/providers/duplicates')) ?>">Possible duplicates</a>
            <a class="btn btn-primary" href="<?= e(url('admin/providers/new')) ?>">New provider</a>
        </div>
    </div>
    <form method="get" action="<?= e(url('admin/providers')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
        <div class="form-group mb-0">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e((string) $f('search')) ?>" placeholder="Business, email, contact, phone">
        </div>
        <div class="form-group mb-0">
            <label for="town">Base town</label>
            <input type="text" id="town" name="town" value="<?= e((string) $f('town')) ?>" placeholder="e.g. Gladstone">
        </div>
        <div class="form-group mb-0">
            <label for="category">Service type</label>
            <select id="category" name="category">
                <option value="">All services</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $f('category') === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="state">State</label>
            <select id="state" name="state">
                <option value="">All states</option>
                <?php foreach ($states as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $f('state') === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e((string) $s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= (string) $f('status') === (string) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="source">Listing source</label>
            <select id="source" name="source">
                <?php foreach ($sources as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= (string) $f('source') === (string) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="mb-0" style="display:flex;gap:.4rem;align-items:center;font-weight:normal">
                <input type="checkbox" name="verified" value="1" <?= !empty($f('verified')) ? 'checked' : '' ?>> Verified only
            </label>
            <label class="mb-0" style="display:flex;gap:.4rem;align-items:center;font-weight:normal">
                <input type="checkbox" name="featured" value="1" <?= !empty($f('featured')) ? 'checked' : '' ?>> Featured only
            </label>
        </div>
        <div class="form-group mb-0 btn-row">
            <button class="btn btn-secondary" type="submit">Filter</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/providers')) ?>">Reset</a>
        </div>
    </form>
</div>

<?php if ($claimInviteStats !== null): ?>
<div class="card" style="border-left:4px solid #0f6e6e">
    <h2 style="margin-top:0">Bulk claim invites</h2>
    <p class="muted" style="margin:0 0 .75rem">Send the improved claim-invite email to unclaimed listings that have an email on file. Each message explains what VanAssist is, that listing is <strong>free during launch</strong>, and includes a personal claim link.</p>
    <ul style="margin:0 0 1rem;padding-left:1.2rem">
        <li><strong><?= number_format($claimInviteStats['eligible']) ?></strong> ready to email now</li>
        <li><strong><?= number_format($claimInviteStats['no_email']) ?></strong> skipped — no email on file</li>
        <li><strong><?= number_format($claimInviteStats['pending_invite']) ?></strong> skipped — active invite already sent</li>
    </ul>
    <?php if ($claimInviteStats['eligible'] > 0 || $inviteOffset > 0): ?>
        <form method="post" action="<?= e(url('admin/providers/bulk-claim-invites')) ?>" class="btn-row" style="margin:0;align-items:center">
            <input type="hidden" name="status" value="<?= e((string) $f('status')) ?>">
            <input type="hidden" name="q" value="<?= e((string) $f('search')) ?>">
            <input type="hidden" name="town" value="<?= e((string) $f('town')) ?>">
            <input type="hidden" name="category" value="<?= (int) $f('category') ?>">
            <input type="hidden" name="state" value="<?= (int) $f('state') ?>">
            <?php if (!empty($f('verified'))): ?><input type="hidden" name="verified" value="1"><?php endif; ?>
            <?php if (!empty($f('featured'))): ?><input type="hidden" name="featured" value="1"><?php endif; ?>
            <input type="hidden" name="offset" value="<?= $inviteOffset ?>">
            <button type="submit" class="btn btn-primary"><?= $inviteOffset > 0 ? 'Continue bulk invites' : 'Send claim invites (25 per batch)' ?></button>
            <span class="muted" style="font-size:.9rem">Emails are queued; run the mail queue or wait for cron to deliver.</span>
        </form>
    <?php else: ?>
        <p class="muted mb-0">No eligible unclaimed providers match the current filters. Try widening filters or add email addresses on individual listings first.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Business</th><th>Base town</th><th>Status</th><th>Flags</th><th>Plan</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($providers as $p): ?>
                <tr>
                    <td><strong><?= $this->e((string) $p['business_name']) ?></strong><?= $p['is_demo'] ? ' <span class="badge badge-neutral">demo</span>' : '' ?></td>
                    <td><?= $this->e((string) ($p['town_name'] ?? '—')) ?></td>
                    <td><span class="badge <?= $badge[$p['status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $p['status'])) ?></span></td>
                    <td>
                        <?= $p['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                        <?= $p['is_featured'] ? '<span class="badge badge-confirmed">Featured</span> ' : '' ?>
                        <?= $p['is_founding_provider'] ? '<span class="badge badge-confirmed">Founding</span> ' : '' ?>
                        <?= !empty($p['is_unclaimed']) ? '<span class="badge badge-neutral">Unclaimed</span>' : '' ?>
                    </td>
                    <td><span class="muted"><?= $this->e((string) ($p['subscription_state'] ?? '—')) ?></span></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/providers/show?id=' . (int) $p['id'])) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($providers === []): ?>
                <tr><td colspan="6" class="muted">No providers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/providers' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/providers' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
