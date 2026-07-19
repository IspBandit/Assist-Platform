<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $run */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $services */
/** @var array<int,array<string,mixed>> $bookings */
/** @var array<int,array<string,mixed>> $requests */
/** @var array<int,array<string,mixed>> $candidates */
/** @var array<int,array<string,mixed>> $history */
/** @var array<string,string> $statuses */
/** @var array<int,array<string,mixed>> $allTowns */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.admin');
$id = (int) $run['id'];
$bookingStatuses = ['joined' => 'Joined', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'no_show' => 'No show', 'cancelled' => 'Cancelled'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h1 style="margin:0"><?= $this->e((string) $run['title']) ?></h1>
            <p class="muted" style="margin:.25rem 0 0"><?= $this->e((string) ($run['business_name'] ?? '—')) ?> · <span class="badge badge-neutral"><?= $this->e(\App\Services\RunWorkflow::label((string) $run['status'])) ?></span></p>
        </div>
        <div class="btn-row">
            <?php if ($run['is_public']): ?><a class="btn btn-ghost" href="<?= e(url('service-runs/' . $run['slug'])) ?>" target="_blank" rel="noopener">View public</a><?php endif; ?>
            <a class="btn btn-secondary" href="<?= e(url('admin/runs/form?id=' . $id)) ?>">Edit</a>
        </div>
    </div>
</div>

<div class="grid grid-2" style="align-items:flex-start">
    <div class="card stack">
        <h2 style="margin-top:0">Status</h2>
        <p class="muted" style="margin:0">Capacity: <?= (int) $run['bookings_count'] ?><?= $run['appointments_total'] ? ' / ' . (int) $run['appointments_total'] : '' ?> · Minimum: <?= (int) $run['min_bookings'] ?></p>
        <form method="post" action="<?= e(url('admin/runs/status')) ?>" class="btn-row" style="align-items:flex-end">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group mb-0">
                <label for="status">Set status</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $run['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0"><label for="note">Note</label><input type="text" id="note" name="note"></div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>

    <div class="card stack">
        <h2 style="margin-top:0">Services</h2>
        <?php if ($services === []): ?><p class="muted">None yet.</p><?php else: ?>
            <div class="btn-row">
                <?php foreach ($services as $s): ?>
                    <form method="post" action="<?= e(url('admin/runs/service/remove')) ?>" style="margin:0">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="category_id" value="<?= (int) $s['category_id'] ?>">
                        <button type="submit" class="btn btn-ghost"><?= $this->e((string) $s['name']) ?> &times;</button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/runs/service/add')) ?>" class="btn-row" style="align-items:flex-end">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group mb-0">
                <label for="category_id">Add service</label>
                <select id="category_id" name="category_id">
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $this->e((string) $c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Add</button>
        </form>
    </div>
</div>

<div class="card stack">
    <h2 style="margin-top:0">Stops</h2>
    <?php if ($towns === []): ?><p class="muted">No stops added.</p><?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Town</th><th>Arrival</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($towns as $t): ?>
                    <tr>
                        <td><?= $this->e((string) $t['town_name']) ?></td>
                        <td><?= $this->e((string) ($t['arrival_date'] ?? '—')) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('admin/runs/town/remove')) ?>" style="margin:0">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="town_id" value="<?= (int) $t['town_id'] ?>">
                                <button type="submit" class="btn btn-ghost">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/runs/town/add')) ?>" class="btn-row" style="align-items:flex-end">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group mb-0">
            <label for="town_id">Add stop</label>
            <select id="town_id" name="town_id">
                <?php foreach ($allTowns as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= $this->e((string) $t['name']) ?><?= $t['region_name'] ? ' (' . $this->e((string) $t['region_name']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0"><label for="arrival_date">Arrival</label><input type="date" id="arrival_date" name="arrival_date"></div>
        <button type="submit" class="btn btn-secondary">Add</button>
    </form>
</div>

<div class="card stack">
    <h2 style="margin-top:0">Registrations <span class="muted" style="font-size:1rem">(<?= count($bookings) ?>)</span></h2>
    <?php if ($bookings === []): ?><p class="muted">No registrations yet.</p><?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Customer</th><th>Email</th><th>Stop</th><th>Request</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= $this->e((string) ($b['customer_name'] ?? '—')) ?></td>
                        <td><?= $this->e((string) ($b['customer_email'] ?? '—')) ?></td>
                        <td><?= $this->e((string) ($b['town_name'] ?? '—')) ?></td>
                        <td><?= $this->e((string) ($b['request_reference'] ?? '—')) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('admin/runs/booking')) ?>" class="btn-row" style="margin:0;align-items:center">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                <select name="booking_status">
                                    <?php foreach ($bookingStatuses as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $b['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-ghost">Save</button>
                            </form>
                        </td>
                        <td><?= $this->e((string) ($b['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card stack">
    <h2 style="margin-top:0">Linked requests</h2>
    <?php if ($requests === []): ?><p class="muted">No requests linked to this run.</p><?php else: ?>
        <ul class="list-plain">
            <?php foreach ($requests as $r): ?>
                <li class="btn-row" style="justify-content:space-between">
                    <span><a href="<?= e(url('admin/requests/show?id=' . (int) $r['id'])) ?>"><?= $this->e((string) $r['reference']) ?></a> — <?= $this->e((string) $r['title']) ?> <span class="muted">(<?= $this->e((string) ($r['town_name'] ?? '—')) ?>)</span></span>
                    <form method="post" action="<?= e(url('admin/runs/request/unlink')) ?>" style="margin:0">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="btn btn-ghost">Unlink</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($candidates !== []): ?>
        <h3 style="margin-bottom:.25rem">Matched requests you can link</h3>
        <ul class="list-plain">
            <?php foreach ($candidates as $c): ?>
                <li class="btn-row" style="justify-content:space-between">
                    <span><a href="<?= e(url('admin/requests/show?id=' . (int) $c['id'])) ?>"><?= $this->e((string) $c['reference']) ?></a> — <?= $this->e((string) $c['title']) ?> <span class="badge badge-neutral"><?= $this->e((string) $c['match_status']) ?></span></span>
                    <form method="post" action="<?= e(url('admin/runs/request/link')) ?>" style="margin:0">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="request_id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="btn btn-secondary">Link</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card stack">
    <h2 style="margin-top:0">Status history</h2>
    <?php if ($history === []): ?><p class="muted">No history.</p><?php else: ?>
        <ul class="list-plain">
            <?php foreach ($history as $h): ?>
                <li>
                    <strong><?= $this->e(\App\Services\RunWorkflow::label((string) $h['to_status'])) ?></strong>
                    <span class="muted">· <?= $this->e((string) ($h['created_at'] ?? '')) ?><?php if ($h['by_name']): ?> · <?= $this->e((string) $h['by_name']) ?><?php endif; ?></span>
                    <?php if ($h['note']): ?><br><span class="muted"><?= $this->e((string) $h['note']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
