<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $prospect */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
/** @var array<int,string> $statuses */
/** @var array<int,string> $sources */
$this->extend('layouts.admin');
$p = $prospect ?? [];
$v = static fn (string $k, $default = '') => e((string) ($p[$k] ?? $default));
$label = static fn (string $s): string => ucwords(str_replace('_', ' ', $s));
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/prospects')) ?>">&laquo; Back to prospects</a>
    <h1 style="margin:.25rem 0 1rem"><?= $prospect ? 'Edit prospect' : 'New prospect' ?></h1>

    <form method="post" action="<?= e(url('admin/prospects/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($prospect): ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><?php endif; ?>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="business_name">Business name *</label>
                <input type="text" id="business_name" name="business_name" value="<?= $v('business_name') ?>" required>
            </div>
            <div class="form-group">
                <label for="contact_name">Contact name</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= $v('contact_name') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= $v('email') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>">
            </div>
            <div class="form-group">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" value="<?= $v('website') ?>">
            </div>
            <div class="form-group">
                <label for="base_town_id">Base town</label>
                <select id="base_town_id" name="base_town_id">
                    <option value="">—</option>
                    <?php foreach ($towns as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($p['base_town_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="region_id">Region</label>
                <select id="region_id" name="region_id">
                    <option value="">—</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) ($p['region_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="source">Source</label>
                <select id="source" name="source">
                    <?php foreach ($sources as $s): ?>
                        <option value="<?= e($s) ?>" <?= ($p['source'] ?? '') === $s ? 'selected' : '' ?>><?= $this->e($label($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="outreach_status">Outreach status</label>
                <select id="outreach_status" name="outreach_status">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= ($p['outreach_status'] ?? 'not_contacted') === $s ? 'selected' : '' ?>><?= $this->e($label($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="next_follow_up_date">Next follow up</label>
                <input type="date" id="next_follow_up_date" name="next_follow_up_date" value="<?= $v('next_follow_up_date') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="services_observed">Services observed</label>
            <input type="text" id="services_observed" name="services_observed" value="<?= $v('services_observed') ?>">
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3"><?= $v('notes') ?></textarea>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save prospect</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/prospects')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
