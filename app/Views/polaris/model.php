<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <?php if (!empty($model['is_demo'])): ?>
            <p class="badge badge-neutral">Demonstration fixture — not production catalogue data</p>
        <?php endif; ?>
        <p class="polaris-model-meta">
            <a href="<?= e(url('manufacturers/' . $model['manufacturer_slug'])) ?>"><?= $this->e($model['manufacturer_name']) ?></a>
            · <?= $this->e($categoryLabel) ?>
            <?php if (!empty($selectedYear)): ?>
                · <?= (int) $selectedYear['model_year'] ?>
                <?php if ((string) ($selectedYear['production_status'] ?? '') !== 'current'): ?>
                    <span class="muted">(<?= $this->e(str_replace('_', ' ', (string) $selectedYear['production_status'])) ?>)</span>
                <?php endif; ?>
            <?php else: ?>
                · <?= $this->e(ucfirst((string) $model['production_status'])) ?>
            <?php endif; ?>
            · <?= $this->e(ucfirst((string) $model['verification_status'])) ?>
        </p>
        <h1><?= $this->e($model['name']) ?></h1>
        <p><?= $this->e((string) ($model['description'] ?? '')) ?></p>
        <?php if (!empty($modelYears) && count($modelYears) > 1): ?>
            <nav class="polaris-year-selector" aria-labelledby="polaris-year-selector-label">
                <span class="polaris-year-selector__label" id="polaris-year-selector-label">Model year</span>
                <ul>
                    <?php foreach ($modelYears as $yearRow): ?>
                        <?php
                        $y = (int) $yearRow['model_year'];
                        $isActive = $selectedYear !== null && (int) $selectedYear['model_year'] === $y;
                        $href = url(ltrim((string) $modelPath, '/') . '?year=' . $y);
                        $status = (string) ($yearRow['production_status'] ?? '');
                        ?>
                        <li>
                            <?php if ($isActive): ?>
                                <span class="polaris-year-selector__current" aria-current="true"><?= $y ?><?php if ($status !== 'current'): ?> <span class="muted"><?= $this->e($status) ?></span><?php endif; ?></span>
                            <?php else: ?>
                                <a href="<?= e($href) ?>"><?= $y ?><?php if ($status !== 'current'): ?> <span class="muted"><?= $this->e($status) ?></span><?php endif; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <?php if (!empty($yearRequestedInvalid)): ?>
            <p class="polaris-warn" role="status">That model year is not published for this RV. Showing the current published year instead.</p>
        <?php endif; ?>
        <div class="polaris-card-actions">
            <a class="btn btn-primary" href="<?= e(url('find')) ?>">Start matching</a>
            <a class="btn btn-secondary" href="<?= e(url('compare?ids=' . (int) $model['id'])) ?>">Compare</a>
            <a class="btn btn-ghost" href="<?= e(url('tow-match?model_id=' . (int) $model['id'])) ?>">Tow Match</a>
            <?php if (current_user() !== null): ?>
                <?php if (!empty($isSaved)): ?>
                    <form method="post" action="<?= e(url('saved/models/remove')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="model_id" value="<?= (int) $model['id'] ?>">
                        <input type="hidden" name="return" value="<?= e('/rvs/' . $model['manufacturer_slug'] . '/' . $model['slug']) ?>">
                        <button class="btn btn-ghost" type="submit">Remove save</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('saved/models')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="model_id" value="<?= (int) $model['id'] ?>">
                        <input type="hidden" name="return" value="<?= e('/rvs/' . $model['manufacturer_slug'] . '/' . $model['slug']) ?>">
                        <button class="btn btn-ghost" type="submit">Save</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($model['manufacturer_website'])): ?>
                <a class="btn btn-ghost" href="<?= e($model['manufacturer_website']) ?>" rel="noopener noreferrer">Manufacturer website</a>
            <?php endif; ?>
        </div>

        <?php if ($primary !== null): ?>
            <h2>At a glance</h2>
            <dl class="polaris-spec-glance">
                <?php if (!empty($primary['sleeps'])): ?><div><dt>Sleeps</dt><dd><?= (int) $primary['sleeps'] ?></dd></div><?php endif; ?>
                <?php if (!empty($primary['body_length_m'])): ?><div><dt>Body length</dt><dd><?= $this->e(number_format((float) $primary['body_length_m'], 2)) ?> m</dd></div><?php endif; ?>
                <?php if (!empty($primary['tare_kg'])): ?><div><dt>Tare</dt><dd><?= (int) $primary['tare_kg'] ?> kg <span class="muted">— unladen mass as specified</span></dd></div><?php endif; ?>
                <?php if (!empty($primary['atm_kg'])): ?><div><dt>ATM</dt><dd><?= (int) $primary['atm_kg'] ?> kg <span class="muted">— maximum permitted trailer mass</span></dd></div><?php endif; ?>
                <?php if ($primary['payload_kg'] !== null): ?><div><dt>Payload</dt><dd><?= (int) $primary['payload_kg'] ?> kg <span class="muted">— ATM minus tare</span></dd></div><?php endif; ?>
                <?php if (!empty($primary['fresh_water_l'])): ?><div><dt>Fresh water</dt><dd><?= (int) $primary['fresh_water_l'] ?> L</dd></div><?php endif; ?>
                <?php if (!empty($primary['solar_w'])): ?><div><dt>Solar</dt><dd><?= (int) $primary['solar_w'] ?> W</dd></div><?php endif; ?>
                <?php if (!empty($primary['battery_ah'])): ?><div><dt>Battery</dt><dd><?= (int) $primary['battery_ah'] ?> Ah</dd></div><?php endif; ?>
                <div>
                    <dt>Price</dt>
                    <dd>
                        <?= $this->e($primary['price_label']) ?>
                        <?php if (!empty($primary['price_freshness']['warning'])): ?>
                            <span class="polaris-warn"><?= $this->e($primary['price_freshness']['warning']) ?></span>
                        <?php elseif (!empty($primary['price_freshness']['label'])): ?>
                            <span class="muted"><?= $this->e($primary['price_freshness']['label']) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        <?php elseif (!empty($modelYears)): ?>
            <p class="muted" role="status">No published variants for this model year yet.</p>
        <?php endif; ?>

        <h2>Source transparency</h2>
        <?php if ($provenance === []): ?>
            <p class="muted">No source records linked to this model yet. Brand-level sources may still exist in admin.</p>
        <?php else: ?>
            <ul class="polaris-trust-list">
                <?php foreach ($provenance as $chip): ?>
                    <li>
                        <span class="polaris-provenance-chip" data-authority="<?= e($chip['authority']) ?>">
                            <?= $this->e($chip['label']) ?>
                        </span>
                        <?php if (!empty($chip['title'])): ?>
                            — <?= $this->e($chip['title']) ?>
                        <?php endif; ?>
                        <?php if (!empty($chip['retrieved'])): ?> · <?= $this->e($chip['retrieved']) ?><?php endif; ?>
                        <?php if (!empty($chip['is_primary'])): ?> <span class="muted">(primary)</span><?php endif; ?>
                        <?php if (!empty($chip['url'])): ?>
                            · <a href="<?= e($chip['url']) ?>" rel="noopener noreferrer">Open source</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($specProvenance)): ?>
            <h3>Specification provenance</h3>
            <div class="table-wrap">
                <table class="table polaris-spec-table">
                    <caption class="sr-only">Specification values with source authority for this model year</caption>
                    <thead>
                        <tr><th scope="col">Specification</th><th scope="col">Value</th><th scope="col">Source</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($specProvenance as $row): ?>
                            <tr>
                                <th scope="row"><?= $this->e($row['field']) ?></th>
                                <td><?= $this->e($row['value']) ?><?= $row['unit'] !== '' ? ' ' . $this->e($row['unit']) : '' ?></td>
                                <td>
                                    <span class="polaris-provenance-chip"><?= $this->e($row['source_label']) ?></span>
                                    <span class="muted"><?= $this->e($row['authority']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p>Last reviewed: <?= !empty($model['last_reviewed_at']) ? $this->e((string) $model['last_reviewed_at']) : 'Not yet reviewed' ?>.</p>

        <h2>Tow compatibility</h2>
        <p>Use Tow Match for TowSmart-powered checks. Headline tow capacity alone is never treated as a legal guarantee.</p>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('tow-match?model_id=' . (int) $model['id'])) ?>">Open Tow Match</a>

        <?php if (!empty($floorplans)): ?>
            <h2>Floorplans</h2>
            <?php foreach ($floorplans as $fp): ?>
                <article class="polaris-floorplan">
                    <h3><?= $this->e($fp['title']) ?></h3>
                    <p><?= $this->e((string) ($fp['accessible_description'] ?? '')) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($variants !== []): ?>
            <h2>Variants</h2>
            <ul>
                <?php foreach ($variants as $variant): ?>
                    <li>
                        <strong><?= $this->e($variant['name']) ?></strong>
                        — <?= $this->e($variant['price_label']) ?>
                        <?php if (empty($variant['tare_kg']) || empty($variant['atm_kg'])): ?>
                            <span class="muted">(weights incomplete)</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($dealers)): ?>
            <h2>Contact a dealer</h2>
            <p class="muted">Dealer response times vary. Polaris does not send enquiries on your behalf — you contact the dealer directly.</p>
            <ul class="polaris-account-list">
                <?php foreach ($dealers as $dealer): ?>
                    <li>
                        <strong><?= $this->e($dealer['trading_name']) ?></strong>
                        <?php if ($dealer['locality'] !== '' || $dealer['state_abbr'] !== ''): ?>
                            <span class="muted">
                                — <?= $this->e(trim($dealer['locality'] . ($dealer['state_abbr'] !== '' ? ', ' . $dealer['state_abbr'] : ''))) ?>
                            </span>
                        <?php endif; ?>
                        <div class="btn-row" style="margin-top:.35rem">
                            <?php if (!empty($dealer['mailto_url'])): ?>
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= e(url('dealers/' . (int) $dealer['id'] . '/enquire?channel=email&model_id=' . (int) $model['id'])) ?>">Email dealer</a>
                            <?php endif; ?>
                            <?php if (!empty($dealer['website_handoff'])): ?>
                                <a class="btn btn-ghost btn-sm"
                                   href="<?= e(url('dealers/' . (int) $dealer['id'] . '/enquire?channel=website&model_id=' . (int) $model['id'])) ?>"
                                   rel="noopener noreferrer">Dealer website</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2>Related VanAssist services</h2>
        <p class="muted"><?= $this->e($relatedServices['disclaimer'] ?? '') ?></p>
        <?php if (empty($relatedServices['providers'])): ?>
            <p class="muted">No related VanAssist providers available in this environment.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($relatedServices['providers'] as $provider): ?>
                    <li>
                        <a href="<?= e($provider['vanassist_url']) ?>" rel="noopener noreferrer"><?= $this->e($provider['business_name']) ?></a>
                        <?php if (!empty($provider['town_name'])): ?>
                            <span class="muted">— <?= $this->e($provider['town_name']) ?><?= !empty($provider['state_abbr']) ? ', ' . $this->e($provider['state_abbr']) : '' ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a href="<?= e(url('portal/manufacturer')) ?>">Are you this manufacturer?</a></p>
    </div>
</section>
<?php $this->endSection(); ?>
