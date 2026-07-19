<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $run */
/** @var array<int,array<string,mixed>> $providers */
/** @var array<int,array<string,mixed>> $regions */
$this->extend('layouts.admin');
$v = static fn (string $key, $default = '') => $run[$key] ?? $default;
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $run ? 'Edit run' : 'New run' ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/runs')) ?>">Back to runs</a>
    </div>

    <form method="post" action="<?= e(url('admin/runs/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php if ($run): ?><input type="hidden" name="id" value="<?= (int) $run['id'] ?>"><?php endif; ?>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="provider_id">Provider *</label>
                <select id="provider_id" name="provider_id" required>
                    <option value="">Select a provider</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) $v('provider_id') === (int) $p['id'] ? 'selected' : '' ?>><?= $this->e((string) $p['business_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="region_id">Region</label>
                <select id="region_id" name="region_id">
                    <option value="">None</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $v('region_id') === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" required value="<?= e((string) $v('title')) ?>" placeholder="e.g. Far North Queensland spring run">
        </div>

        <div class="grid grid-3">
            <div class="form-group"><label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" value="<?= e((string) $v('start_date')) ?>"></div>
            <div class="form-group"><label for="end_date">End date</label><input type="date" id="end_date" name="end_date" value="<?= e((string) $v('end_date')) ?>"></div>
            <div class="form-group"><label for="booking_deadline">Register-by date</label><input type="date" id="booking_deadline" name="booking_deadline" value="<?= e((string) $v('booking_deadline')) ?>"></div>
        </div>

        <div class="grid grid-2">
            <div class="form-group"><label for="appointments_total">Total places (capacity)</label><input type="number" min="0" id="appointments_total" name="appointments_total" value="<?= e((string) $v('appointments_total')) ?>"></div>
            <div class="form-group"><label for="min_bookings">Minimum to go ahead</label><input type="number" min="0" id="min_bookings" name="min_bookings" value="<?= e((string) $v('min_bookings', '0')) ?>"></div>
        </div>

        <div class="form-group">
            <label for="travel_fee_description">Travel / call-out note</label>
            <input type="text" id="travel_fee_description" name="travel_fee_description" value="<?= e((string) $v('travel_fee_description')) ?>" placeholder="e.g. Travel included within 30km of each stop">
        </div>

        <div class="form-group">
            <label for="notes">Description</label>
            <textarea id="notes" name="notes" rows="5"><?= e((string) $v('notes')) ?></textarea>
        </div>

        <div class="btn-row">
            <label><input type="checkbox" name="mobile_only" value="1" <?= $v('mobile_only') ? 'checked' : '' ?>> Mobile / on-site only</label>
            <label><input type="checkbox" name="is_public" value="1" <?= $run === null || $v('is_public') ? 'checked' : '' ?>> Listed publicly</label>
            <label><input type="checkbox" name="is_featured" value="1" <?= $v('is_featured') ? 'checked' : '' ?>> Featured</label>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= $run ? 'Save changes' : 'Create run' ?></button>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
