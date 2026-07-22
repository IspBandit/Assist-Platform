<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $areas */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
/** @var array<int,array<string,mixed>> $states */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Service areas</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'areas']); ?>

        <div class="card">
            <h2>Where you work</h2>
            <p class="muted">Add each way you cover customers. Examples: a specific town, your whole region, statewide roadside assistance, or a radius from your base (e.g. 100 km from Bundaberg).</p>
            <ul class="list-plain">
                <?php foreach ($areas as $a): ?>
                    <li class="btn-row" style="justify-content:space-between;align-items:center">
                        <span><strong><?= $this->e(ucfirst((string) $a['area_type'])) ?>:</strong>
                            <?= $this->e((string) ($a['town_name'] ?? $a['region_name'] ?? $a['state_name'] ?? $a['label'] ?? ($a['radius_km'] ? $a['radius_km'] . ' km from base town' : '—'))) ?>
                        </span>
                        <form method="post" action="<?= e(url('provider/areas/remove')) ?>" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="area_id" value="<?= (int) $a['id'] ?>">
                            <button type="submit" class="btn btn-ghost">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if ($areas === []): ?><li class="muted">No service areas defined yet.</li><?php endif; ?>
            </ul>

            <form method="post" action="<?= e(url('provider/areas/add')) ?>" class="stack" style="margin-top:1.5rem" id="area-form">
                <?= csrf_field() ?>
                <div class="form-group mb-0">
                    <label for="area_type">Coverage type</label>
                    <select id="area_type" name="area_type">
                        <option value="town">Specific town</option>
                        <option value="region">Whole region</option>
                        <option value="state">Statewide</option>
                        <option value="radius">Radius from base town (km)</option>
                    </select>
                </div>
                <div class="form-group mb-0" data-area-field="town">
                    <label for="town_id">Town</label>
                    <select id="town_id" name="town_id">
                        <option value="">—</option>
                        <?php foreach ($towns as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= $this->e((string) $t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" data-area-field="region" hidden>
                    <label for="region_id">Region</label>
                    <select id="region_id" name="region_id">
                        <option value="">—</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= (int) $r['id'] ?>"><?= $this->e((string) $r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" data-area-field="state" hidden>
                    <label for="state_id">State / territory</label>
                    <select id="state_id" name="state_id">
                        <option value="">—</option>
                        <?php foreach ($states as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= $this->e((string) $s['name']) ?> (<?= $this->e((string) $s['abbreviation']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" data-area-field="radius" hidden>
                    <label for="radius_km">Radius (km from your base town)</label>
                    <input type="number" id="radius_km" name="radius_km" min="1" placeholder="e.g. 100">
                    <p class="muted" style="font-size:.85rem">Uses your base town from your profile as the centre point.</p>
                </div>
                <div class="form-group mb-0">
                    <label for="label">Label (optional)</label>
                    <input type="text" id="label" name="label" placeholder="e.g. Within 100 km of Bundaberg">
                </div>
                <button type="submit" class="btn btn-secondary">Add area</button>
            </form>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
