<?php
/** @var array<string,mixed> $p */
$isPossible = !empty($isPossible);
$model = (string) ($p['service_model'] ?? '');
$isMobile = in_array($model, ['mobile', 'both'], true);
$name = (string) ($p['business_name'] ?? 'Business');
$initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
$profilePath = current_brand()->id() === 'localtorque' ? 'business/' : 'providers/';
$location = trim((string) ($p['town_name'] ?? ''));
if ($location !== '' && !empty($p['state_abbr'])) { $location .= ', ' . $p['state_abbr']; }
$description = trim((string) ($p['description'] ?? ''));
?>
<article class="provider-card<?= !empty($p['is_featured']) ? ' provider-card--featured' : '' ?>">
    <?php if (!empty($p['is_featured'])): ?><span class="provider-featured-label">Featured</span><?php endif; ?>
    <a class="provider-card-main" href="<?= e(url($profilePath . $p['slug'])) ?>">
        <span class="provider-avatar" aria-hidden="true"><?= e($initial) ?></span>
        <span class="provider-card-content">
            <span class="provider-card-title"><?= e($name) ?></span>
            <?php if ($location !== ''): ?><span class="provider-location"><?= e($location) ?><?php if (isset($p['distance_km']) && $p['distance_km'] !== null): ?> · approximately <?= max(1, (int) $p['distance_km']) ?> km away<?php endif; ?></span><?php endif; ?>
        </span>
        <span class="provider-card-arrow" aria-hidden="true">→</span>
    </a>
    <div class="provider-card-badges">
        <?php if (!empty($p['is_verified'])): ?><span class="badge badge-verified">Verified business</span><?php endif; ?>
        <?php if (!empty($p['is_unclaimed'])): ?><span class="badge badge-neutral">Details not yet claimed</span><?php endif; ?>
        <?php if ($isPossible): ?><span class="badge badge-neutral">Related service</span><?php endif; ?>
        <?php if ($isMobile): ?><span class="badge badge-confirmed"><?= $model === 'both' ? 'Mobile and workshop' : 'Mobile service' ?></span><?php elseif ($model !== ''): ?><span class="badge badge-neutral">Workshop</span><?php endif; ?>
    </div>
    <?php if ($description !== ''): ?><p class="provider-card-description"><?= e(mb_substr($description, 0, 150)) ?><?= mb_strlen($description) > 150 ? '…' : '' ?></p><?php endif; ?>
    <a class="provider-card-link" href="<?= e(url($profilePath . $p['slug'])) ?>">View services and contact details</a>
</article>
