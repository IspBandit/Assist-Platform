<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $windows */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Availability</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'availability']); ?>

        <div class="card">
            <h2>Add availability</h2>
            <p class="muted">Record the dates you can take work (or block out dates you're unavailable).</p>
            <form method="post" action="<?= e(url('provider/availability/add')) ?>" class="grid grid-3" style="align-items:flex-end">
                <?= csrf_field() ?>
                <div class="form-group mb-0">
                    <label for="start_date">Start date *</label>
                    <input type="date" id="start_date" name="start_date" required>
                </div>
                <div class="form-group mb-0">
                    <label for="end_date">End date</label>
                    <input type="date" id="end_date" name="end_date">
                </div>
                <div class="form-group mb-0">
                    <label for="is_available">Status</label>
                    <select id="is_available" name="is_available">
                        <option value="1">Available</option>
                        <option value="0">Unavailable</option>
                    </select>
                </div>
                <div class="form-group mb-0" style="grid-column:span 2">
                    <label for="notes">Notes</label>
                    <input type="text" id="notes" name="notes">
                </div>
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-secondary">Add</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Your availability</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>From</th><th>To</th><th>Status</th><th>Notes</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($windows as $w): ?>
                        <tr>
                            <td><?= $this->e((string) $w['start_date']) ?></td>
                            <td><?= $this->e((string) ($w['end_date'] ?? 'ongoing')) ?></td>
                            <td><?= $w['is_available'] ? '<span class="badge badge-verified">Available</span>' : '<span class="badge badge-neutral">Unavailable</span>' ?></td>
                            <td><?= $this->e((string) ($w['notes'] ?? '')) ?></td>
                            <td>
                                <form method="post" action="<?= e(url('provider/availability/remove')) ?>" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="window_id" value="<?= (int) $w['id'] ?>">
                                    <button type="submit" class="btn btn-ghost">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($windows === []): ?><tr><td colspan="5" class="muted">No availability set.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
