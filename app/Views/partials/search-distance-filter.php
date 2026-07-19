<?php
/** @var string $name */
/** @var string|int|null $selected */
/** @var bool $disabled */
/** @var string|null $townName */
$name = $name ?? 'max_distance';
$disabled = !empty($disabled);
$townName = $townName ?? null;
$townLabel = $townName !== null && $townName !== '' ? 'This area (' . $townName . ')' : 'This area';
$isTown = $selected === \App\Helpers\Geo::SCOPE_TOWN || $selected === 'town';
$isAny = $selected === \App\Helpers\Geo::SCOPE_ANY || $selected === 'any';
$selectedKm = is_numeric($selected) ? (int) $selected : null;
?>
<div class="form-group mb-0">
    <label for="<?= e_attr($name) ?>">Within distance</label>
    <select id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" <?= $disabled ? 'disabled' : '' ?>>
        <option value="town" <?= $isTown ? 'selected' : '' ?>><?= e($townLabel) ?></option>
        <option value="any" <?= $isAny ? 'selected' : '' ?>>Any distance</option>
        <?php foreach (\App\Helpers\Geo::DISTANCE_OPTIONS as $km): ?>
            <option value="<?= $km ?>" <?= $selectedKm === $km ? 'selected' : '' ?>><?= $km ?> km</option>
        <?php endforeach; ?>
    </select>
    <?php if ($disabled): ?>
        <p class="muted" style="font-size:.8rem;margin:.25rem 0 0">Enter a town, suburb or postcode to filter by distance.</p>
    <?php else: ?>
        <p class="muted" style="font-size:.8rem;margin:.25rem 0 0">Default shows providers in and serving this suburb or town. Widen with km options.</p>
    <?php endif; ?>
</div>
