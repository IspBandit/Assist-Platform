<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container narrow">
        <span class="product-kicker dark">Find My RV</span>
        <h1>Guided matching</h1>
        <?php if ($prompt !== ''): ?>
            <p class="polaris-prompt-echo">You said: <em><?= $this->e($prompt) ?></em></p>
            <?php if (!empty($nlHints)): ?>
                <ul class="polaris-nl-hints">
                    <?php foreach ($nlHints as $hint): ?>
                        <li><?= $this->e($hint) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="muted">
                    Deterministic keyword hints<?= $nlConfidence !== null ? ' (confidence ' . $this->e(number_format((float) $nlConfidence, 2)) . ')' : '' ?>.
                    Not AI ranking — adjust the structured answers below before scoring.
                    <?php if (!empty($towHint)): ?>
                        <a href="<?= e(url('tow-match?vehicle_q=' . rawurlencode((string) $towHint))) ?>">Open Tow Match for <?= $this->e((string) $towHint) ?></a>.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="muted">No strong keyword matches yet. Structured answers below drive deterministic scoring.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>One clear step at a time. Scores explain constraints, preferences and missing data — AI does not rank models.</p>
        <?php endif; ?>

        <ol class="polaris-stage-list">
            <?php foreach ($stages as $number => $info): ?>
                <li class="<?= $number === $stage ? 'is-current' : ($number < $stage ? 'is-done' : '') ?>">
                    <span>
                        <strong>Stage <?= (int) $number ?>: <?= $this->e($info['title']) ?></strong>
                        <span><?= $this->e($info['summary']) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php
        $carry = [
            'q' => $prompt,
            'adults' => $profile->adults,
            'children' => $profile->children,
            'max_budget_aud' => $profile->maxBudgetAudCents !== null ? (int) ($profile->maxBudgetAudCents / 100) : '',
            'max_atm_kg' => $profile->maxAtmKg ?? '',
            'max_length_m' => $profile->maxLengthM ?? '',
            'min_sleeps' => $profile->minSleeps ?? '',
            'off_grid_nights' => $profile->offGridNights,
            'require_bathroom' => $profile->requireBathroom ? '1' : '',
            'priority_towability' => $profile->priorities['towability'] ?? 'strong',
            'priority_price' => $profile->priorities['price'] ?? 'strong',
            'priority_off_grid' => $profile->priorities['off_grid'] ?? 'nice',
            'priority_comfort' => $profile->priorities['comfort'] ?? 'nice',
            'priority_payload' => $profile->priorities['payload'] ?? 'strong',
            'vehicle_q' => $vehicleQ ?? '',
            'travel_surface' => $travelSurface ?? '',
            'layout_pref' => $layoutPref ?? '',
        ];
        $carryCategories = $profile->categories;
        ?>

        <?php if ($stage < 10): ?>
            <form class="polaris-stage-panel" method="get" action="<?= e(url('find')) ?>">
                <input type="hidden" name="stage" value="<?= (int) min(10, $stage + 1) ?>">
                <?php foreach ($carry as $key => $value): ?>
                    <?php if ($value === '' || $value === null) { continue; } ?>
                    <?php if (in_array($key, match ($stage) {
                        1 => ['adults', 'children', 'min_sleeps'],
                        2 => ['vehicle_q'],
                        3 => ['travel_surface'],
                        4 => ['off_grid_nights'],
                        5 => ['require_bathroom'],
                        6 => ['layout_pref'],
                        7 => ['max_atm_kg', 'max_length_m'],
                        8 => ['max_budget_aud'],
                        9 => ['priority_towability', 'priority_price', 'priority_off_grid', 'priority_comfort', 'priority_payload'],
                        default => [],
                    }, true)) { continue; } ?>
                    <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
                <?php endforeach; ?>
                <?php foreach ($carryCategories as $cat): ?>
                    <input type="hidden" name="categories[]" value="<?= e((string) $cat) ?>">
                <?php endforeach; ?>

                <h2><?= $this->e($stages[$stage]['title'] ?? 'Preferences') ?></h2>
                <p><?= $this->e($stages[$stage]['summary'] ?? '') ?></p>

                <?php if ($stage === 1): ?>
                    <div class="polaris-pref-grid">
                        <label>Adults <input type="number" name="adults" min="1" max="8" value="<?= (int) $profile->adults ?>" required></label>
                        <label>Children <input type="number" name="children" min="0" max="8" value="<?= (int) $profile->children ?>"></label>
                        <label>Minimum permanent beds <input type="number" name="min_sleeps" min="1" max="10" value="<?= e((string) ($profile->minSleeps ?? '')) ?>" placeholder="Optional"></label>
                    </div>
                <?php elseif ($stage === 2): ?>
                    <div class="polaris-pref-grid">
                        <label>Tow vehicle search
                            <input type="search" name="vehicle_q" value="<?= $this->e((string) ($vehicleQ ?? '')) ?>" placeholder="e.g. Prado 250, LandCruiser 300">
                        </label>
                    </div>
                    <p class="muted">
                        TowSmart remains the tow authority.
                        <?php if (!empty($vehicleQ)): ?>
                            <a href="<?= e(url('tow-match?vehicle_q=' . rawurlencode((string) $vehicleQ))) ?>">Open Tow Match for <?= $this->e((string) $vehicleQ) ?></a>
                        <?php else: ?>
                            <a href="<?= e(url('tow-match')) ?>">Open Tow Match</a> when you are ready — you can skip and continue.
                        <?php endif; ?>
                    </p>
                <?php elseif ($stage === 3): ?>
                    <fieldset class="polaris-priority-set">
                        <legend>Typical travel surfaces</legend>
                        <?php foreach ([
                            'sealed' => 'Mostly sealed roads and caravan parks',
                            'mixed' => 'Mix of sealed and well-maintained gravel',
                            'remote' => 'Remote tracks / corrugated outback routes',
                        ] as $value => $label): ?>
                            <label class="polaris-check">
                                <input type="radio" name="travel_surface" value="<?= e($value) ?>" <?= ($travelSurface ?? '') === $value ? 'checked' : '' ?>>
                                <?= $this->e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="muted">Surface preference is recorded for guidance. Model capability claims still require manufacturer provenance.</p>
                <?php elseif ($stage === 4): ?>
                    <div class="polaris-pref-grid">
                        <label>Typical off-grid nights
                            <input type="number" name="off_grid_nights" min="0" max="30" value="<?= (int) $profile->offGridNights ?>">
                        </label>
                    </div>
                <?php elseif ($stage === 5): ?>
                    <label class="polaris-check"><input type="checkbox" name="require_bathroom" value="1" <?= $profile->requireBathroom ? 'checked' : '' ?>> Bathroom is essential</label>
                <?php elseif ($stage === 6): ?>
                    <fieldset class="polaris-priority-set">
                        <legend>Layout preference</legend>
                        <?php foreach ([
                            'island_bed' => 'Island bed preferred',
                            'bunks' => 'Bunks / family layout',
                            'rear_ensuite' => 'Rear ensuite',
                            'flexible' => 'Flexible — refine from floorplans later',
                        ] as $value => $label): ?>
                            <label class="polaris-check">
                                <input type="radio" name="layout_pref" value="<?= e($value) ?>" <?= ($layoutPref ?? '') === $value ? 'checked' : '' ?>>
                                <?= $this->e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php elseif ($stage === 7): ?>
                    <div class="polaris-pref-grid">
                        <label>Max ATM (kg) <input type="number" name="max_atm_kg" min="0" step="50" value="<?= e((string) ($profile->maxAtmKg ?? '')) ?>" placeholder="Optional"></label>
                        <label>Max length (m) <input type="number" name="max_length_m" min="0" step="0.1" value="<?= e((string) ($profile->maxLengthM ?? '')) ?>" placeholder="Optional"></label>
                    </div>
                <?php elseif ($stage === 8): ?>
                    <div class="polaris-pref-grid">
                        <label>Max budget (AUD) <input type="number" name="max_budget_aud" min="0" step="1000" value="<?= $profile->maxBudgetAudCents !== null ? (int) ($profile->maxBudgetAudCents / 100) : '' ?>" placeholder="Optional"></label>
                    </div>
                <?php elseif ($stage === 9): ?>
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
                <?php endif; ?>

                <div class="btn-row" style="margin-top:1rem">
                    <button class="btn btn-primary" type="submit"><?= $stage === 9 ? 'See explained matches' : 'Continue' ?></button>
                    <?php if ($stage > 1): ?>
                        <a class="btn btn-ghost" href="<?= e(url('find?stage=' . ($stage - 1) . ($prompt !== '' ? '&q=' . rawurlencode($prompt) : ''))) ?>">Back</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="polaris-stage-panel">
                <h2>Results</h2>
                <p>Score version <?= $this->e(\App\Services\Polaris\PreferenceProfile::SCORE_VERSION) ?>. Missing data is penalised, not treated as a favourable match.</p>
                <?php if (!empty($travelSurface) || !empty($layoutPref) || !empty($vehicleQ)): ?>
                    <p class="muted">
                        Context:
                        <?php if (!empty($vehicleQ)): ?>tow vehicle hint “<?= $this->e((string) $vehicleQ) ?>”; <?php endif; ?>
                        <?php if (!empty($travelSurface)): ?>travel “<?= $this->e((string) $travelSurface) ?>”; <?php endif; ?>
                        <?php if (!empty($layoutPref)): ?>layout “<?= $this->e((string) $layoutPref) ?>”.<?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if ($matches === []): ?>
                    <p class="empty-state" role="status">No published models available to score.</p>
                <?php else: ?>
                    <ol class="polaris-match-list">
                        <?php foreach (array_slice($matches, 0, 12) as $match): ?>
                            <li class="polaris-match-card band-<?= e($match['match']['band']) ?>">
                                <div class="polaris-match-score" aria-label="Match score <?= $this->e((string) $match['match']['overall']) ?> percent">
                                    <strong><?= $this->e(number_format((float) $match['match']['overall'], 0)) ?>%</strong>
                                    <span><?= $this->e(str_replace('_', ' ', $match['match']['band'])) ?></span>
                                </div>
                                <div>
                                    <h3><a href="<?= e($match['url']) ?>"><?= $this->e($match['manufacturer_name'] . ' ' . $match['name']) ?></a></h3>
                                    <p class="muted"><?= $this->e($match['category_label']) ?> · <?= $this->e($match['price_label']) ?></p>
                                    <?php if ($match['match']['reasons'] !== []): ?>
                                        <p><strong>Why it scored:</strong> <?= $this->e(implode(' ', array_slice($match['match']['reasons'], 0, 2))) ?></p>
                                    <?php endif; ?>
                                    <?php if ($match['match']['failed'] !== []): ?>
                                        <p class="polaris-warn"><strong>Failed constraints:</strong> <?= $this->e(implode(' ', $match['match']['failed'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($match['match']['missing'] !== []): ?>
                                        <p class="muted"><strong>Missing data:</strong> <?= $this->e(implode(' ', array_slice($match['match']['missing'], 0, 2))) ?></p>
                                    <?php endif; ?>
                                    <?php if ($match['match']['compromises'] !== []): ?>
                                        <p class="muted"><strong>Compromises:</strong> <?= $this->e(implode(' ', array_slice($match['match']['compromises'], 0, 2))) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <a class="btn btn-ghost" href="<?= e(url('find?stage=1' . ($prompt !== '' ? '&q=' . rawurlencode($prompt) : ''))) ?>">Refine answers</a>
                <a class="btn btn-secondary" href="<?= e(url('rvs')) ?>">Browse catalogue</a>
                <?php if (current_user() !== null): ?>
                    <a class="btn btn-ghost" href="<?= e(url('account/preferences')) ?>">Saved preferences</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
