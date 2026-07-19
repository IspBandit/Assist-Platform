<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $run */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $services */
/** @var array<int,array<string,mixed>> $bookings */
/** @var array<string,string> $statuses */
/** @var array<int,array<string,mixed>> $allTowns */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.public');
$id = (int) $run['id'];
$total = (int) $run['appointments_total'];
$count = (int) $run['bookings_count'];
$pct = $total > 0 ? min(100, (int) round($count / max(1, $total) * 100)) : 0;
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
            <h1 style="margin:0"><?= $this->e((string) $run['title']) ?></h1>
            <div class="btn-row">
                <?php if ($run['is_public']): ?><a class="btn btn-ghost" href="<?= e(url('service-runs/' . $run['slug'])) ?>" target="_blank" rel="noopener">View public</a><?php endif; ?>
                <a class="btn btn-secondary" href="<?= e(url('provider/runs/form?id=' . $id)) ?>">Edit</a>
            </div>
        </div>
        <?php $this->include('partials.provider-nav', ['active' => 'runs']); ?>

        <div class="card stack">
            <h2 style="margin-top:0">Status &amp; capacity</h2>
            <?php if ($total > 0): ?>
                <div style="background:#eceae3;border-radius:999px;height:10px;overflow:hidden">
                    <div style="background:var(--green,#0f6e6e);height:10px;width:<?= $pct ?>%"></div>
                </div>
            <?php endif; ?>
            <p class="muted" style="margin:0"><?= $count ?><?= $total ? ' / ' . $total : '' ?> places registered · Minimum: <?= (int) $run['min_bookings'] ?> · <span class="badge badge-neutral"><?= $this->e(\App\Services\RunWorkflow::label((string) $run['status'])) ?></span></p>
            <form method="post" action="<?= e(url('provider/runs/status')) ?>" class="btn-row" style="align-items:flex-end">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                <div class="form-group mb-0">
                    <label for="status">Set status</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $run['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>

        <div class="grid grid-2" style="align-items:flex-start">
            <div class="card stack">
                <h2 style="margin-top:0">Services</h2>
                <?php if ($services === []): ?><p class="muted">None yet.</p><?php else: ?>
                    <div class="btn-row">
                        <?php foreach ($services as $s): ?>
                            <form method="post" action="<?= e(url('provider/runs/service/remove')) ?>" style="margin:0">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="category_id" value="<?= (int) $s['category_id'] ?>">
                                <button type="submit" class="btn btn-ghost"><?= $this->e((string) $s['name']) ?> &times;</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('provider/runs/service/add')) ?>" class="btn-row" style="align-items:flex-end">
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

            <div class="card stack">
                <h2 style="margin-top:0">Stops</h2>
                <?php if ($towns === []): ?><p class="muted">No stops added.</p><?php else: ?>
                    <ul class="list-plain">
                        <?php foreach ($towns as $t): ?>
                            <li class="btn-row" style="justify-content:space-between">
                                <span><strong><?= $this->e((string) $t['town_name']) ?></strong><?php if ($t['arrival_date']): ?> — <?= $this->e((string) $t['arrival_date']) ?><?php endif; ?></span>
                                <form method="post" action="<?= e(url('provider/runs/town/remove')) ?>" style="margin:0">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="town_id" value="<?= (int) $t['town_id'] ?>">
                                    <button type="submit" class="btn btn-ghost">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="<?= e(url('provider/runs/town/add')) ?>" class="btn-row" style="align-items:flex-end">
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
        </div>

        <div class="card stack">
            <h2 style="margin-top:0">Registrations <span class="muted" style="font-size:1rem">(<?= count($bookings) ?>)</span></h2>
            <?php if ($bookings === []): ?><p class="muted">No registrations yet.</p><?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Customer</th><th>Stop</th><th>Status</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><?= $this->e((string) ($b['customer_name'] ?? 'Traveller')) ?></td>
                                <td><?= $this->e((string) ($b['town_name'] ?? '—')) ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e(ucfirst((string) $b['status'])) ?></span></td>
                                <td><?= $this->e((string) ($b['notes'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
