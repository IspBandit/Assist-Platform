<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container narrow">
        <span class="product-kicker dark">Tow Match</span>
        <h1>Compatibility guidance</h1>
        <p>Powered by TowSmart calculations. Based on the figures and assumptions supplied, results describe whether a combination <em>appears</em> to remain within checked limits — not a legal or safety certification.</p>

        <form method="get" action="<?= e(url('tow-match')) ?>" class="polaris-stage-panel">
            <label>Search TowSmart vehicles
                <input type="search" name="vehicle_q" value="<?= e($query) ?>" placeholder="e.g. Prado, LandCruiser, Ranger">
            </label>
            <?php if ($vehicles !== []): ?>
                <label>Select vehicle
                    <select name="vehicle_id">
                        <option value="">Choose…</option>
                        <?php foreach ($vehicles as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= ($vehicle['id'] ?? null) == $item['id'] ? 'selected' : '' ?>>
                                <?= $this->e(($item['brand'] ?? '') . ' ' . ($item['name'] ?? '') . ' ' . ($item['years'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Polaris RV model
                <select name="model_id">
                    <option value="">Choose…</option>
                    <?php foreach ($models as $model): ?>
                        <option value="<?= (int) $model['id'] ?>" <?= ($selectedModel['id'] ?? null) == $model['id'] ? 'selected' : '' ?>>
                            <?= $this->e($model['manufacturer_name'] . ' ' . $model['name']) ?>
                            <?php if (empty($model['atm_kg'])): ?> (ATM unknown)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Check compatibility</button>
        </form>

        <?php if ($result !== null): ?>
            <article class="polaris-tow-result status-<?= e($result['status']) ?>">
                <h2><?= $this->e($result['headline']) ?></h2>
                <p><?= $this->e($result['summary']) ?></p>
                <?php if (!empty($result['warnings'])): ?>
                    <ul><?php foreach ($result['warnings'] as $warning): ?><li><?= $this->e($warning) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($result['missing'])): ?>
                    <p><strong>Missing data:</strong></p>
                    <ul><?php foreach ($result['missing'] as $item): ?><li><?= $this->e($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($result['calculation']['calculated'])): ?>
                    <dl class="polaris-spec-glance">
                        <div><dt>Combination mass</dt><dd><?= $this->e(number_format((float) $result['calculation']['calculated']['combination_mass'], 0)) ?> kg</dd></div>
                        <div><dt>Vehicle payload remaining</dt><dd><?= $this->e(number_format((float) $result['calculation']['calculated']['vehicle_payload_remaining'], 0)) ?> kg</dd></div>
                    </dl>
                <?php endif; ?>
                <p class="muted"><?= $this->e((string) ($result['calculation']['disclaimer'] ?? 'Informational estimate only.')) ?></p>
                <p><a href="https://towsmart.com.au/calculator" rel="noopener noreferrer">Open full TowSmart calculator</a> on the TowSmart brand.</p>
            </article>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
