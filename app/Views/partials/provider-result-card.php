<?php
/** @var array<string,mixed> $p */
$isPossible = !empty($isPossible);
$model = (string) ($p['service_model'] ?? '');
$isMobile = in_array($model, ['mobile', 'both'], true);
$name = (string) ($p['business_name'] ?? 'Business');
$profilePath = current_brand()->id() === 'localtorque' ? 'business/' : 'providers/';
$location = trim((string) ($p['town_name'] ?? ''));
if ($location !== '' && !empty($p['state_abbr'])) { $location .= ', ' . $p['state_abbr']; }
$description = trim((string) ($p['description'] ?? ''));
$isWorkshop = in_array($model, ['workshop', 'both'], true);
$mapDestination = $isWorkshop && is_navigable_street_address($p['street_address'] ?? '')
    ? map_destination(null, null, [$p['street_address'] ?? '', $p['town_name'] ?? '', $p['state_abbr'] ?? ''])
    : '';
$resultCardId = trim((string) ($resultCardId ?? ''));
$compact = !isset($compact) || $compact !== false;
$searchId = isset($searchId) ? (int) $searchId : 0;
$gapId = isset($gapId) ? (int) $gapId : 0;
$mapResultNumber = isset($mapResultNumber) ? max(0, (int) $mapResultNumber) : 0;
$contactParts = [];
if ($searchId > 0) {
    $contactParts[] = 's=' . $searchId;
}
if ($gapId > 0) {
    $contactParts[] = 'g=' . $gapId;
}
$contactQuery = $contactParts !== [] ? '?' . implode('&', $contactParts) : '';
$profileUrl = url($profilePath . $p['slug']) . $contactQuery;
$hasListedPhone = trim((string) ($p['public_phone'] ?? '')) !== '';
$canCall = $hasListedPhone && !empty($p['show_public_phone']);
?>
<article<?= $resultCardId !== '' ? ' id="' . e_attr($resultCardId) . '"' : '' ?> class="provider-card<?= !empty($p['is_featured']) ? ' provider-card--featured' : '' ?><?= $isPossible ? ' provider-card--possible' : '' ?><?= $compact ? ' provider-card--compact' : '' ?>"<?= $resultCardId !== '' ? ' tabindex="-1"' : '' ?>>
    <?php if (!empty($p['is_featured'])): ?><span class="provider-featured-label">Featured</span><?php endif; ?>
    <a class="provider-card-main" href="<?= e($profileUrl) ?>">
        <span class="provider-card-content">
            <span class="provider-card-title-row">
                <?php if ($mapResultNumber > 0): ?><span class="provider-map-reference" data-number="<?= $mapResultNumber ?>" aria-label="Map pin <?= $mapResultNumber ?>"></span><?php endif; ?>
                <span class="provider-card-title"><?= e($name) ?></span>
            </span>
            <?php if ($location !== ''): ?><span class="provider-location"><?= e($location) ?><?php if (isset($p['distance_km']) && $p['distance_km'] !== null): ?> · <?= max(1, (int) $p['distance_km']) ?> km straight-line<?php endif; ?></span><?php endif; ?>
        </span>
        <span class="provider-card-arrow" aria-hidden="true">→</span>
    </a>
    <div class="provider-card-badges">
        <?php if ($compact && $isPossible): ?><span class="badge badge-neutral">Related service — confirm fit</span><?php endif; ?>
        <?php if (!empty($p['is_verified'])): ?><span class="badge badge-verified">Verified business</span><?php endif; ?>
        <?php if (str_contains((string) ($p['source_note'] ?? ''), 'qld-fuel-reporting')): ?><span class="badge badge-neutral">Queensland Government source</span><?php endif; ?>
        <?php if (!empty($p['is_unclaimed'])): ?><span class="badge badge-neutral">Details not yet claimed</span><?php endif; ?>
        <?php if ($isPossible && !$compact): ?><span class="badge badge-neutral">Related service</span><?php endif; ?>
        <?php if ($isMobile): ?><span class="badge badge-confirmed"><?= $model === 'both' ? 'Mobile and workshop' : 'Mobile service' ?></span><?php elseif ($model !== ''): ?><span class="badge badge-neutral">Workshop</span><?php endif; ?>
    </div>
    <?php if (!$compact && $description !== ''): ?><p class="provider-card-description"><?= e(mb_substr($description, 0, 150)) ?><?= mb_strlen($description) > 150 ? '…' : '' ?></p><?php endif; ?>
    <div class="btn-row provider-card-actions"><a class="provider-card-link" href="<?= e($profileUrl) ?>"><?= $compact ? 'Details' : 'View services and contact details' ?></a><?php if ($canCall): ?><a class="provider-card-link" href="<?= e(url('go/phone/' . $p['slug']) . $contactQuery) ?>">Call</a><?php endif; ?><?php if ($mapDestination !== ''): ?><a class="provider-card-link" href="<?= e(url('go/directions/' . $p['slug']) . $contactQuery) ?>" data-map-directions data-map-destination="<?= e_attr($mapDestination) ?>" target="_blank" rel="noopener noreferrer">Directions</a><?php endif; ?></div>
</article>
