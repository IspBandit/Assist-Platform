<?php
/** @var array<string,mixed> $p */
/** @var bool $isPossible */
$isPossible = !empty($isPossible);
$model = (string) ($p['service_model'] ?? '');
$isMobile = in_array($model, ['mobile', 'both'], true);

$badges = '';
if (!empty($p['is_verified'])) {
    $badges .= '<span class="badge badge-verified">Verified</span> ';
}
if (!empty($p['is_founding_provider'])) {
    $badges .= '<span class="badge badge-confirmed">Founding</span> ';
}
if (!empty($p['is_unclaimed'])) {
    $badges .= '<span class="badge badge-neutral">Unclaimed</span> ';
}
if ($isPossible) {
    $badges .= '<span class="badge badge-neutral">May offer this service</span> ';
}
if ($isMobile) {
    $label = $model === 'both' ? 'Mobile &amp; workshop' : 'Mobile service';
    $badges .= '<span class="badge badge-confirmed" title="Comes to you">&#128666; ' . $label . '</span>';
} elseif ($model !== '') {
    $badges .= '<span class="badge badge-neutral">Workshop</span>';
}

$loc = '';
if (!empty($p['town_name'])) {
    $dist = '';
    if (isset($p['distance_km']) && $p['distance_km'] !== null) {
        $km = (float) $p['distance_km'];
        $dist = $km < 1
            ? ' &middot; <strong>in this area</strong>'
            : ' &middot; <strong>~' . (int) $km . ' km away</strong>';
    }
    $loc = '<p class="muted" style="margin:0">' . e((string) $p['town_name'])
        . (!empty($p['state_abbr']) ? ', ' . e((string) $p['state_abbr']) : '') . $dist . '</p>';
}
?>
<?php $profilePath = current_brand()->id() === 'localtorque' ? 'business/' : 'providers/'; ?>
<a class="card stack" href="<?= e(url($profilePath . $p['slug'])) ?>" style="text-decoration:none;color:inherit">
    <h3 style="margin:0"><?= e((string) $p['business_name']) ?></h3>
    <div><?= $badges ?></div>
    <?= $loc ?>
</a>
