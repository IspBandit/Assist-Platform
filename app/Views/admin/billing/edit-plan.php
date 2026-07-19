<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $plan */
/** @var array<string,mixed> $limits */
/** @var array<string,mixed> $features */
/** @var array<int,string> $limitKeys */
/** @var array<int,string> $featureKeys */
$this->extend('layouts.admin');
$label = static fn (string $key): string => ucwords(str_replace('_', ' ', $key));
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1><?= $this->e($title) ?></h1>
    <form method="post" action="<?= e(url('admin/billing/plans/update')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">

        <div class="form-grid">
            <label>Internal name
                <input type="text" name="internal_name" value="<?= e_attr((string) $plan['internal_name']) ?>" required>
            </label>
            <label>Public name
                <input type="text" name="public_name" value="<?= e_attr((string) $plan['public_name']) ?>" required>
            </label>
            <label>Monthly price (AUD)
                <input type="number" step="0.01" min="0" name="monthly_price" value="<?= e_attr(number_format(((int) $plan['monthly_price_cents']) / 100, 2, '.', '')) ?>">
            </label>
            <label>Annual price (AUD)
                <input type="number" step="0.01" min="0" name="annual_price" value="<?= e_attr(number_format(((int) $plan['annual_price_cents']) / 100, 2, '.', '')) ?>">
            </label>
            <label>Trial days
                <input type="number" min="0" name="trial_days" value="<?= (int) $plan['trial_days'] ?>">
            </label>
            <label>Display order
                <input type="number" name="display_order" value="<?= (int) $plan['display_order'] ?>">
            </label>
        </div>

        <label>Description
            <textarea name="description" rows="3"><?= $this->e((string) ($plan['description'] ?? '')) ?></textarea>
        </label>
        <label>Terms summary
            <textarea name="terms_summary" rows="2"><?= $this->e((string) ($plan['terms_summary'] ?? '')) ?></textarea>
        </label>

        <fieldset>
            <legend>Status</legend>
            <label class="inline"><input type="checkbox" name="is_active" value="1" <?= $plan['is_active'] ? 'checked' : '' ?>> Active</label>
            <label class="inline"><input type="checkbox" name="is_public" value="1" <?= $plan['is_public'] ? 'checked' : '' ?>> Public</label>
            <label class="inline"><input type="checkbox" name="signup_available" value="1" <?= $plan['signup_available'] ? 'checked' : '' ?>> Open for signup</label>
            <label class="inline"><input type="checkbox" name="is_recommended" value="1" <?= $plan['is_recommended'] ? 'checked' : '' ?>> Recommended</label>
            <label class="inline"><input type="checkbox" name="is_legacy" value="1" <?= $plan['is_legacy'] ? 'checked' : '' ?>> Legacy (closed)</label>
        </fieldset>

        <fieldset>
            <legend>Limits <span class="muted">(blank or "unlimited" = no limit)</span></legend>
            <div class="form-grid">
                <?php foreach ($limitKeys as $key): ?>
                    <?php $val = $limits[$key] ?? null; ?>
                    <label><?= $this->e($label($key)) ?>
                        <input type="text" name="limit_<?= e_attr($key) ?>" value="<?= e_attr($val === null ? '' : (string) $val) ?>" placeholder="unlimited">
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Feature entitlements</legend>
            <?php foreach ($featureKeys as $key): ?>
                <label class="inline">
                    <input type="checkbox" name="feature_<?= e_attr($key) ?>" value="1" <?= !empty($features[$key]) ? 'checked' : '' ?>>
                    <?= $this->e($label($key)) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Save plan</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/billing')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
