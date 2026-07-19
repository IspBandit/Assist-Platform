<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $request */
/** @var array<int,array<string,mixed>> $images */
/** @var array<int,array<string,mixed>> $history */
/** @var array<int,array<string,mixed>> $notes */
/** @var array<string,string> $statuses */
$this->extend('layouts.admin');
$r = $request;
$id = (int) $r['id'];
$field = static function (string $label, $value) {
    if ($value === null || $value === '') {
        return;
    }
    echo '<li><strong>' . e($label) . ':</strong> ' . e((string) $value) . '</li>';
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/requests')) ?>">&laquo; Back to requests</a>
    <div class="btn-row" style="justify-content:space-between;align-items:center;margin-top:.25rem">
        <h1 style="margin:0"><?= $this->e((string) $r['title']) ?></h1>
        <div class="btn-row" style="margin:0;align-items:center">
            <?php if (can('requests.match')): ?>
                <a class="btn btn-secondary" href="<?= e(url('admin/matching/request?id=' . $id)) ?>">Match providers</a>
            <?php endif; ?>
            <span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span>
        </div>
    </div>
    <p class="muted">Reference <strong><?= $this->e((string) $r['reference']) ?></strong> · <?= $this->e((string) $r['created_at']) ?>
        <?= $r['is_spam'] ? ' · <span class="badge badge-neutral">spam</span>' : '' ?>
        <?= $r['safety_concern'] ? ' · <span class="badge badge-urgent">safety concern</span>' : '' ?>
    </p>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Actions</h2>
        <div class="btn-row">
            <?php foreach ([['approve', 'Approve (open)', 'btn-primary'], ['reject', 'Reject', 'btn-secondary']] as [$action, $label, $class]): ?>
                <form method="post" action="<?= e(url('admin/requests/status')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <button type="submit" class="btn <?= $class ?>"><?= $label ?></button>
                </form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('admin/requests/spam')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-ghost"><?= $r['is_spam'] ? 'Clear spam' : 'Mark spam' ?></button>
            </form>
        </div>
        <form method="post" action="<?= e(url('admin/requests/status')) ?>" class="btn-row" style="margin-top:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="set">
            <select name="status">
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $r['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="note" placeholder="Optional note">
            <button type="submit" class="btn btn-secondary">Set status</button>
        </form>
    </div>

    <div class="card">
        <h2>Customer</h2>
        <ul class="list-plain">
            <?php
            $field('Name', $r['contact_name']);
            $field('Email', $r['contact_email']);
            $field('Phone', $r['contact_phone']);
            $field('Preferred contact', ucfirst((string) $r['preferred_contact']));
            echo '<li><strong>Consent to share:</strong> ' . ($r['consent_share'] ? 'Yes' : 'No') . '</li>';
            ?>
        </ul>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Location &amp; vehicle</h2>
        <ul class="list-plain">
            <?php
            $field('Town', $r['town_name']);
            $field('Region', $r['region_name']);
            $field('Postcode', $r['postcode']);
            $field('Location', $r['location_label']);
            $field('Vehicle type', $r['vehicle_type']);
            $field('Make', $r['vehicle_make']);
            $field('Model', $r['vehicle_model']);
            $field('Year', $r['vehicle_year']);
            $field('Length (m)', $r['vehicle_length_m']);
            ?>
        </ul>
    </div>

    <div class="card">
        <h2>Service &amp; fault</h2>
        <ul class="list-plain">
            <?php
            $field('Category', $r['category_name']);
            $field('Urgency', ucfirst((string) $r['urgency']));
            $field('Issue started', $r['issue_started']);
            $field('Error code', $r['error_code']);
            $field('Appliance', trim((string) $r['appliance_brand'] . ' ' . (string) $r['appliance_model']));
            $field('Travel deadline', $r['travel_deadline']);
            ?>
        </ul>
        <?php if ($r['description']): ?><p><?= nl2br($this->e((string) $r['description'])) ?></p><?php endif; ?>
    </div>
</div>

<?php if ($images !== []): ?>
    <div class="card">
        <h2>Photos</h2>
        <div class="btn-row">
            <?php foreach ($images as $img): ?>
                <a href="<?= e(url('admin/requests/image?image_id=' . (int) $img['id'])) ?>" target="_blank">
                    <img src="<?= e(url('admin/requests/image?image_id=' . (int) $img['id'] . '&thumb=1')) ?>" alt="Request photo" style="width:140px;height:105px;object-fit:cover;border-radius:8px">
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h2>Status history</h2>
        <ul class="list-plain">
            <?php foreach ($history as $h): ?>
                <li style="border-top:1px solid #e3e0d8;padding:.5rem 0">
                    <strong><?= $this->e(\App\Services\RequestWorkflow::label((string) $h['to_status'])) ?></strong>
                    <span class="muted" style="font-size:.85rem">· <?= $this->e((string) ($h['changed_by_name'] ?? 'System')) ?> · <?= $this->e((string) $h['created_at']) ?></span>
                    <?php if ($h['note']): ?><div class="muted"><?= $this->e((string) $h['note']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if ($history === []): ?><li class="muted">No history.</li><?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h2>Internal notes</h2>
        <form method="post" action="<?= e(url('admin/requests/note')) ?>" style="margin-bottom:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group"><textarea name="body" rows="2" placeholder="Internal note (not visible to the customer)"></textarea></div>
            <button type="submit" class="btn btn-secondary">Add note</button>
        </form>
        <ul class="list-plain">
            <?php foreach ($notes as $n): ?>
                <li style="border-top:1px solid #e3e0d8;padding:.5rem 0">
                    <div class="muted" style="font-size:.85rem"><?= $this->e((string) ($n['author_name'] ?? 'System')) ?> · <?= $this->e((string) $n['created_at']) ?></div>
                    <div><?= nl2br($this->e((string) $n['body'])) ?></div>
                </li>
            <?php endforeach; ?>
            <?php if ($notes === []): ?><li class="muted">No notes yet.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?php $this->endSection(); ?>
