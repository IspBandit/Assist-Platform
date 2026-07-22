<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $nearbyTown */
/** @var array<int,array<string,mixed>> $nearbyProviders */
/** @var string $nearbyFindUrl */
/** @var string $nearbyEndpoint */
/** @var int $providerDirectoryCount */

$townLabel = '';
if ($nearbyTown !== null) {
    $townLabel = (string) $nearbyTown['name'];
    if (!empty($nearbyTown['state_abbr'])) {
        $townLabel .= ', ' . $nearbyTown['state_abbr'];
    }
}

$renderCard = static function (array $p): string {
    $slot = (string) ($p['slot'] ?? 'local');
    $isFeatured = $slot === 'featured' || !empty($p['is_featured']);
    $badges = '';
    if ($isFeatured) {
        $badges .= '<span class="badge badge-sponsored">Featured</span> ';
    }
    if (!empty($p['is_verified'])) {
        $badges .= '<span class="badge badge-verified">Verified</span> ';
    }
    if (!empty($p['is_unclaimed'])) {
        $badges .= '<span class="badge badge-neutral">Unclaimed</span> ';
    }
    $model = (string) ($p['service_model'] ?? '');
    if ($model !== '') {
        $badges .= '<span class="badge badge-neutral">' . e(ucfirst($model)) . '</span>';
    }
    $loc = '';
    if (!empty($p['town_name'])) {
        $loc = '<p class="muted nearby-card-loc">' . e((string) $p['town_name']);
        if (!empty($p['state_abbr'])) {
            $loc .= ', ' . e((string) $p['state_abbr']);
        }
        $loc .= '</p>';
    }

    $class = 'nearby-card card' . ($isFeatured ? ' nearby-card-featured' : '');

    return '<a class="' . e_attr($class) . '" href="' . e(url('providers/' . $p['slug'])) . '">'
        . '<h3 class="nearby-card-title">' . e((string) $p['business_name']) . '</h3>'
        . '<div class="nearby-card-badges">' . $badges . '</div>'
        . $loc
        . '</a>';
};
?>
<section
    id="nearby-providers"
    class="section section-sand nearby-section"
    data-nearby-providers
    data-endpoint="<?= e_attr($nearbyEndpoint) ?>"
    data-nearest-url="<?= e_attr(url('locations/nearest')) ?>"
    <?php if ($nearbyTown !== null): ?>data-initial-town-id="<?= (int) $nearbyTown['id'] ?>"<?php endif; ?>
    aria-labelledby="nearby-providers-heading"
>
    <div class="container">
        <div class="nearby-head">
            <div>
                <h2 id="nearby-providers-heading">Providers near you</h2>
                <p class="muted nearby-subtitle" data-nearby-subtitle>
                    <?php if ($nearbyTown !== null && $nearbyProviders !== []): ?>
                        Showing relevant listings serving <strong><?= $this->e($townLabel) ?></strong>.
                    <?php else: ?>
                        Search <?= number_format((int) ($providerDirectoryCount ?? 0)) ?> Australian service listings by town or current location.
                    <?php endif; ?>
                </p>
            </div>
            <div class="nearby-actions">
                <button type="button" class="btn btn-secondary" data-nearby-locate hidden>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    Use my location
                </button>
                <a class="btn btn-ghost" data-nearby-find href="<?= e($nearbyFindUrl) ?>">Search all services</a>
            </div>
        </div>

        <p class="nearby-status muted" data-nearby-status role="status" aria-live="polite" hidden></p>

        <div class="nearby-grid" data-nearby-grid>
            <?php if ($nearbyProviders !== []): ?>
                <?php foreach ($nearbyProviders as $p): ?>
                    <?= $renderCard($p) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="nearby-empty card" data-nearby-empty>
                    <p style="margin:0"><strong>Choose your location to see nearby help.</strong> Tap <em>Use my location</em>, <a href="<?= e(url('find')) ?>">search by town</a>, or <a href="<?= e(url('providers')) ?>">browse all <?= number_format((int) ($providerDirectoryCount ?? 0)) ?> service listings</a>.</p>
                </div>
            <?php endif; ?>
        </div>

        <p class="muted nearby-footnote" style="font-size:.85rem;margin:1rem 0 0">
            Featured and verified providers are shown first. Discovered listings are clearly marked unclaimed; confirm their details before booking.
        </p>
    </div>
</section>
