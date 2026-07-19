<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $provider */
/** @var array<int,array<string,mixed>> $services */
/** @var array<int,array<string,mixed>> $areas */
/** @var array<int,array<string,mixed>> $documents */
/** @var array<int,array<string,mixed>> $licences */
/** @var array<int,array<string,mixed>> $notes */
/** @var array<int,array<string,mixed>> $allCategories */
/** @var array<int,array<string,mixed>> $allTowns */
/** @var array<int,array<string,mixed>> $allRegions */
/** @var array<string,mixed>|null $foundingPromo */
/** @var array{desktop:?string,mobile:?string} $promoImageUrls */
$this->extend('layouts.admin');
$id = (int) $provider['id'];
$docBadge = ['verified' => 'badge-verified', 'rejected' => 'badge-neutral', 'pending' => 'badge-confirmed', 'expired' => 'badge-neutral'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/providers')) ?>">&laquo; Back to providers</a>
    <div class="btn-row" style="justify-content:space-between;align-items:center;margin-top:.25rem">
        <h1 style="margin:0"><?= $this->e((string) $provider['business_name']) ?></h1>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-secondary" href="<?= e(url('admin/providers/edit?id=' . $id)) ?>">Edit details</a>
            <?php if ($provider['status'] === 'active'): ?>
                <a class="btn btn-ghost" target="_blank" href="<?= e(url('providers/' . $provider['slug'])) ?>">View public profile</a>
            <?php endif; ?>
        </div>
    </div>
    <p>
        <span class="badge <?= $provider['status'] === 'active' ? 'badge-verified' : 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $provider['status'])) ?></span>
        <?= $provider['is_verified'] ? '<span class="badge badge-verified">Verified</span>' : '' ?>
        <?= $provider['insurance_verified'] ? '<span class="badge badge-verified">Insured</span>' : '' ?>
        <?= $provider['is_featured'] ? '<span class="badge badge-confirmed">Featured</span>' : '' ?>
        <?= $provider['is_founding_provider'] ? '<span class="badge badge-confirmed">Founding</span>' : '' ?>
        <?= !empty($provider['is_unclaimed']) ? '<span class="badge badge-neutral">Unclaimed</span>' : '' ?>
    </p>
    <?php if (!empty($provider['is_unclaimed'])): ?>
        <div class="card" style="margin-top:1rem;border-left:4px solid #c9a227">
            <p style="margin:0"><strong>Unclaimed listing</strong> — compiled from public research. Send a claim invite so the business can verify and manage this profile.</p>
            <form method="post" action="<?= e(url('admin/providers/send-claim-invite')) ?>" class="btn-row" style="margin-top:.75rem;align-items:flex-end">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="form-group mb-0">
                    <label for="claim_email">Email for claim link</label>
                    <input type="email" id="claim_email" name="email" value="<?= e_attr((string) ($provider['email'] ?? '')) ?>" placeholder="business@example.com" required style="min-width:240px">
                </div>
                <button type="submit" class="btn btn-primary">Send claim invite</button>
            </form>
        </div>
    <?php endif; ?>
    <p class="muted">
        <?= $this->e((string) ($provider['town_name'] ?? '—')) ?>
        <?php if ($provider['email']): ?> · <?= $this->e((string) $provider['email']) ?><?php endif; ?>
        <?php if ($provider['phone']): ?> · <?= $this->e((string) $provider['phone']) ?><?php endif; ?>
    </p>
</div>

<?php if (!empty($foundingPromo)): ?>
<div class="card" style="margin-top:1rem;border:2px solid #0f6e6e">
    <h2 style="margin-top:0">Founding ad graphic</h2>
    <p class="muted">Free launch offer for providers in a launch town who claim and verify.</p>
    <p>Status: <strong><?= $this->e(ucfirst(str_replace('_', ' ', (string) $foundingPromo['status']))) ?></strong></p>
    <?php if (!empty($foundingPromo['headline'])): ?>
        <p style="margin:.5rem 0"><strong><?= $this->e((string) $foundingPromo['headline']) ?></strong><br>
        <span class="muted"><?= $this->e((string) ($foundingPromo['tagline'] ?? '')) ?></span></p>
    <?php endif; ?>
    <?php if (!empty($promoImageUrls['desktop']) || !empty($promoImageUrls['mobile'])): ?>
        <div style="margin:.75rem 0">
            <?php $this->include('partials.provider-promotion-ad', [
                'promo' => $foundingPromo,
                'alt'   => (string) ($foundingPromo['headline'] ?? $provider['business_name']),
            ]); ?>
        </div>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(url('admin/promotions/show?id=' . (int) $foundingPromo['id'])) ?>">Manage in ad graphics queue</a>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h2>Status workflow</h2>
        <div class="btn-row">
            <?php
            $actions = [];
            if (in_array($provider['status'], ['pending', 'draft', 'rejected'], true)) {
                $actions[] = ['approve', 'Approve', 'btn-primary'];
                $actions[] = ['reject', 'Reject', 'btn-secondary'];
            }
            if ($provider['status'] === 'active') {
                $actions[] = ['suspend', 'Suspend', 'btn-secondary'];
            }
            if ($provider['status'] === 'suspended') {
                $actions[] = ['reactivate', 'Reactivate', 'btn-primary'];
            }
            ?>
            <?php foreach ($actions as [$action, $label, $class]): ?>
                <form method="post" action="<?= e(url('admin/providers/status')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <button type="submit" class="btn <?= $class ?>"><?= $label ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Flags</h2>
        <div class="btn-row">
            <?php foreach (['verified' => 'is_verified', 'insurance' => 'insurance_verified', 'featured' => 'is_featured'] as $flag => $col): ?>
                <form method="post" action="<?= e(url('admin/providers/flag')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="flag" value="<?= $flag ?>">
                    <button type="submit" class="btn <?= $provider[$col] ? 'btn-secondary' : 'btn-ghost' ?>">
                        <?= $provider[$col] ? 'Remove ' : 'Set ' ?><?= $flag ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <h2>Services</h2>
    <div class="btn-row" style="flex-wrap:wrap">
        <?php foreach ($services as $s): ?>
            <form method="post" action="<?= e(url('admin/providers/service/remove')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn btn-ghost"><?= $this->e((string) $s['name']) ?> &times;</button>
            </form>
        <?php endforeach; ?>
        <?php if ($services === []): ?><span class="muted">No services yet.</span><?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('admin/providers/service/add')) ?>" class="btn-row" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <select name="category_id" required>
            <option value="">Add a service…</option>
            <?php foreach ($allCategories as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= $this->e((string) $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Add</button>
    </form>
</div>

<div class="card">
    <h2>Service areas</h2>
    <ul class="list-plain">
        <?php foreach ($areas as $a): ?>
            <li class="btn-row" style="justify-content:space-between;align-items:center">
                <span>
                    <strong><?= $this->e(ucfirst((string) $a['area_type'])) ?></strong>:
                    <?= $this->e((string) ($a['town_name'] ?? $a['region_name'] ?? $a['state_name'] ?? $a['label'] ?? ($a['radius_km'] ? $a['radius_km'] . ' km' : '—'))) ?>
                </span>
                <form method="post" action="<?= e(url('admin/providers/area/remove')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="area_id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="btn btn-ghost">Remove</button>
                </form>
            </li>
        <?php endforeach; ?>
        <?php if ($areas === []): ?><li class="muted">No service areas defined.</li><?php endif; ?>
    </ul>
    <form method="post" action="<?= e(url('admin/providers/area/add')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group mb-0">
            <label for="area_type">Type</label>
            <select id="area_type" name="area_type">
                <option value="town">Town</option>
                <option value="region">Region</option>
                <option value="radius">Radius (km)</option>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="area_town">Town</label>
            <select id="area_town" name="town_id">
                <option value="">—</option>
                <?php foreach ($allTowns as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= $this->e((string) $t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="area_region">Region</label>
            <select id="area_region" name="region_id">
                <option value="">—</option>
                <?php foreach ($allRegions as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= $this->e((string) $r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="radius_km">Radius km</label>
            <input type="number" id="radius_km" name="radius_km" min="0">
        </div>
        <div class="form-group mb-0">
            <label for="area_label">Label</label>
            <input type="text" id="area_label" name="label">
        </div>
        <div class="form-group mb-0">
            <button type="submit" class="btn btn-secondary">Add area</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Verification documents</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Type</th><th>File</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $d): ?>
                <tr>
                    <td><?= $this->e((string) $d['doc_type']) ?></td>
                    <td><a href="<?= e(url('admin/providers/document/download?document_id=' . (int) $d['id'])) ?>" target="_blank"><?= $this->e((string) ($d['original_name'] ?? 'file')) ?></a></td>
                    <td><span class="badge <?= $docBadge[$d['verification_status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $d['verification_status'])) ?></span></td>
                    <td>
                        <form method="post" action="<?= e(url('admin/providers/document/verify')) ?>" class="btn-row" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                            <select name="verification_status">
                                <option value="verified">Verify</option>
                                <option value="rejected">Reject</option>
                                <option value="pending">Pending</option>
                            </select>
                            <input type="text" name="notes" placeholder="Notes">
                            <button type="submit" class="btn btn-ghost">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($documents === []): ?><tr><td colspan="4" class="muted">No documents uploaded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Licences</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Type</th><th>Number</th><th>Expiry</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($licences as $l): ?>
                <tr>
                    <td><?= $this->e((string) $l['licence_type']) ?></td>
                    <td><?= $this->e((string) ($l['licence_number'] ?? '')) ?></td>
                    <td><?= $this->e((string) ($l['expiry_date'] ?? '—')) ?></td>
                    <td><span class="badge <?= $docBadge[$l['verification_status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $l['verification_status'])) ?></span></td>
                    <td>
                        <form method="post" action="<?= e(url('admin/providers/licence/verify')) ?>" class="btn-row" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="licence_id" value="<?= (int) $l['id'] ?>">
                            <select name="verification_status">
                                <option value="verified">Verify</option>
                                <option value="rejected">Reject</option>
                                <option value="expired">Expired</option>
                                <option value="pending">Pending</option>
                            </select>
                            <button type="submit" class="btn btn-ghost">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($licences === []): ?><tr><td colspan="5" class="muted">No licences recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Internal notes</h2>
    <form method="post" action="<?= e(url('admin/providers/note')) ?>" style="margin-bottom:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <textarea name="body" rows="2" placeholder="Add an internal note (not visible to the provider)"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary">Add note</button>
    </form>
    <ul class="list-plain">
        <?php foreach ($notes as $n): ?>
            <li style="border-top:1px solid #e3e0d8;padding:.5rem 0">
                <div class="muted" style="font-size:.85rem"><?= $this->e((string) ($n['admin_name'] ?? 'System')) ?> · <?= $this->e((string) $n['created_at']) ?></div>
                <div><?= nl2br($this->e((string) $n['body'])) ?></div>
            </li>
        <?php endforeach; ?>
        <?php if ($notes === []): ?><li class="muted">No notes yet.</li><?php endif; ?>
    </ul>
</div>
<?php $this->endSection(); ?>
