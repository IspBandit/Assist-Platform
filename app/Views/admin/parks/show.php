<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $documents */
/** @var array<int,array<string,mixed>> $serviceDays */
/** @var array<int,array<string,mixed>> $managers */
/** @var int $requestCount */
/** @var array<string,string> $statuses */
$this->extend('layouts.admin');
$id = (int) $park['id'];
$sdrStatuses = ['open' => 'Open', 'reviewing' => 'Reviewing', 'arranged' => 'Arranged', 'declined' => 'Declined', 'completed' => 'Completed'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h1 style="margin:0"><?= $this->e((string) $park['name']) ?></h1>
            <p class="muted" style="margin:.25rem 0 0">
                <span class="badge badge-neutral"><?= $this->e(ucfirst((string) $park['status'])) ?></span>
                <?php if ($park['town_name']): ?> · <?= $this->e((string) $park['town_name']) ?><?php endif; ?>
                · <?= $requestCount ?> guest request<?= $requestCount === 1 ? '' : 's' ?>
            </p>
        </div>
        <div class="btn-row">
            <?php if ($park['public_page_enabled'] && $park['status'] === 'active'): ?>
                <a class="btn btn-ghost" href="<?= e(url('caravan-parks/' . $park['slug'])) ?>" target="_blank" rel="noopener">View public</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= e(url('admin/parks/form?id=' . $id)) ?>">Edit</a>
        </div>
    </div>
</div>

<div class="grid grid-2" style="align-items:flex-start">
    <div class="card stack">
        <h2 style="margin-top:0">Status</h2>
        <form method="post" action="<?= e(url('admin/parks/status')) ?>" class="btn-row" style="align-items:flex-end">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group mb-0">
                <label for="status">Set status</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $park['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>

    <div class="card stack">
        <h2 style="margin-top:0">Contact &amp; managers</h2>
        <?php if ($park['email']): ?><p style="margin:0"><strong>Email:</strong> <?= $this->e((string) $park['email']) ?></p><?php endif; ?>
        <?php if ($park['phone']): ?><p style="margin:0"><strong>Phone:</strong> <?= $this->e((string) $park['phone']) ?></p><?php endif; ?>
        <?php if ($managers !== []): ?>
            <ul class="list-plain">
                <?php foreach ($managers as $m): ?>
                    <li><?= $this->e((string) $m['name']) ?> — <?= $this->e((string) $m['email']) ?> <span class="muted">(<?= $this->e((string) $m['role']) ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card stack">
    <h2 style="margin-top:0">Documents</h2>
    <?php if ($documents === []): ?><p class="muted">No documents uploaded.</p><?php else: ?>
        <ul class="list-plain">
            <?php foreach ($documents as $d): ?>
                <li><?= $this->e(ucfirst((string) $d['doc_type'])) ?> — <a href="<?= e(url('admin/parks/document/download?document_id=' . (int) $d['id'])) ?>"><?= $this->e((string) ($d['original_name'] ?? 'document')) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card stack">
    <h2 style="margin-top:0">Service-day requests</h2>
    <?php if ($serviceDays === []): ?><p class="muted">No service-day requests.</p><?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Dates</th><th>Service</th><th>Notes</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($serviceDays as $s): ?>
                    <tr>
                        <td><?= $this->e((string) ($s['preferred_dates'] ?? '—')) ?></td>
                        <td><?= $this->e((string) ($s['category_name'] ?? 'Any')) ?></td>
                        <td><?= $this->e((string) ($s['notes'] ?? '')) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('admin/parks/service-day')) ?>" class="btn-row" style="margin:0;align-items:center">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="sdr_id" value="<?= (int) $s['id'] ?>">
                                <select name="sdr_status">
                                    <?php foreach ($sdrStatuses as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $s['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-ghost">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
