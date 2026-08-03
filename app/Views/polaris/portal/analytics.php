<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1>Profile analytics</h1>
    <p>Managing <strong><?= $this->e($manufacturer['trading_name']) ?></strong>.</p>
    <p class="muted">First-party counts for your published catalogue models only. Free-text prompts are never stored. Find impressions and dealer enquiry clicks remain planned.</p>

    <form method="get" action="<?= e(url('portal/manufacturer/analytics')) ?>" class="btn-row" style="margin:1rem 0">
        <label>Window
            <select name="days" onchange="this.form.submit()">
                <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $value => $label): ?>
                    <option value="<?= (int) $value ?>" <?= (int) $days === (int) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button class="btn btn-ghost" type="submit">Apply</button></noscript>
    </form>

    <dl class="polaris-spec-glance">
        <div><dt>Detail views</dt><dd><?= (int) $summary['views'] ?></dd></div>
        <div><dt>Saves</dt><dd><?= (int) $summary['saves'] ?></dd></div>
        <div><dt>Window</dt><dd><?= (int) $summary['days'] ?> days</dd></div>
    </dl>

    <h2>By model</h2>
    <?php if ($summary['by_model'] === []): ?>
        <p class="empty-state" role="status">No models linked to this manufacturer yet.</p>
    <?php elseif ((int) $summary['views'] === 0 && (int) $summary['saves'] === 0): ?>
        <p class="empty-state" role="status">No detail views or saves recorded in this window yet.</p>
        <ul class="polaris-account-list">
            <?php foreach ($summary['by_model'] as $row): ?>
                <li>
                    <span><?= $this->e($row['name']) ?></span>
                    <span class="muted">0 views · 0 saves</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <ul class="polaris-account-list">
            <?php foreach ($summary['by_model'] as $row): ?>
                <li>
                    <span><?= $this->e($row['name']) ?></span>
                    <span class="muted"><?= (int) $row['views'] ?> views · <?= (int) $row['saves'] ?> saves</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('portal/manufacturer/models')) ?>">Your models</a>
        <a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a>
    </div>
</div></section>
<?php $this->endSection(); ?>
