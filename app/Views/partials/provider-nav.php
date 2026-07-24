<?php
/** @var \App\Core\View $this */
/** @var string|null $active */
$active = $active ?? '';
$items = [
    'dashboard'    => ['Dashboard', 'provider'],
    'requests'     => ['Incoming requests', 'provider/requests'],
    'analytics'    => ['Analytics', 'provider/analytics'],
    'runs'         => ['Service runs', 'provider/runs'],
    'profile'      => ['Business profile', 'provider/profile'],
    'services'     => ['Services', 'provider/services'],
    'areas'        => ['Service areas', 'provider/areas'],
    'documents'    => ['Documents', 'provider/documents'],
    'licences'     => ['Licences', 'provider/licences'],
    'availability' => ['Availability', 'provider/availability'],
];
if (current_brand()->id() === 'trailerwise') {
    $items['trailer-listings'] = ['Trailer listings', 'provider/trailer-listings'];
}
if (function_exists('provider_founding_promo_active') && provider_founding_promo_active()) {
    $items['promotion'] = ['Promote', 'provider/promotion'];
}
if (\App\Billing\BillingManager::enabled()) {
    $items['billing'] = ['Billing', 'provider/billing'];
}
?>
<nav aria-label="Provider" class="provider-nav">
    <?php foreach ($items as $key => [$label, $href]): ?>
        <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a>
    <?php endforeach; ?>
</nav>
