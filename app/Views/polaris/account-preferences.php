<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <h1>Travel preferences</h1>
    <p class="muted">Saved for guided matching on Polaris. Tow vehicle selection remains on Tow Match / TowSmart.</p>
    <form method="post" action="<?= e(url('account/preferences')) ?>" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <div class="polaris-pref-grid">
            <label>Adults <input type="number" name="adults" min="1" max="8" value="<?= (int) $profile->adults ?>"></label>
            <label>Children <input type="number" name="children" min="0" max="8" value="<?= (int) $profile->children ?>"></label>
            <label>Max budget (AUD) <input type="number" name="max_budget_aud" min="0" step="1000" value="<?= $profile->maxBudgetAudCents !== null ? (int) ($profile->maxBudgetAudCents / 100) : '' ?>"></label>
            <label>Max ATM (kg) <input type="number" name="max_atm_kg" min="0" step="50" value="<?= e((string) ($profile->maxAtmKg ?? '')) ?>"></label>
            <label>Max length (m) <input type="number" name="max_length_m" min="0" step="0.1" value="<?= e((string) ($profile->maxLengthM ?? '')) ?>"></label>
            <label>Off-grid nights <input type="number" name="off_grid_nights" min="0" max="30" value="<?= (int) $profile->offGridNights ?>"></label>
            <label class="polaris-check"><input type="checkbox" name="require_bathroom" value="1" <?= $profile->requireBathroom ? 'checked' : '' ?>> Bathroom essential</label>
        </div>
        <fieldset class="polaris-priority-set">
            <legend>Priorities</legend>
            <?php foreach (['towability' => 'Towability', 'price' => 'Price', 'off_grid' => 'Off-grid', 'comfort' => 'Comfort', 'payload' => 'Payload'] as $key => $label): ?>
                <label><?= $this->e($label) ?>
                    <select name="priority_<?= e($key) ?>">
                        <?php foreach (['essential' => 'Essential', 'strong' => 'Strong', 'nice' => 'Nice to have', 'ignore' => 'Ignore'] as $opt => $optLabel): ?>
                            <option value="<?= e($opt) ?>" <?= ($profile->priorities[$key] ?? 'nice') === $opt ? 'selected' : '' ?>><?= $this->e($optLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit">Save preferences</button>
        <a class="btn btn-ghost" href="<?= e(url('find?stage=10')) ?>">Run matching with saved preferences</a>
    </form>
</div></section>
<?php $this->endSection(); ?>
