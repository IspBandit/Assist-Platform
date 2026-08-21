<?php
/** @var \App\Core\View $this */
/** @var string|null $active */
$active = $active ?? '';
$items = [
    'dashboard'    => ['Dashboard', 'provider'],
    'requests'     => ['Incoming requests', 'provider/requests'],
    'analytics'    => ['Analytics', 'provider/analytics'],
    'growth'       => ['Credentials & campaigns', 'provider/growth'],
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
if (\App\Billing\BillingManager::enabled()) {
    $items['billing'] = ['Billing', 'provider/billing'];
}
$groups = [
    'Overview' => ['dashboard', 'analytics'],
    'Your listing' => ['profile', 'services', 'areas', 'availability', 'trailer-listings'],
    'Trust' => ['documents', 'licences'],
    'Work' => ['requests', 'runs'],
    'Growth' => ['growth', 'billing'],
];
$activeLabel = isset($items[$active]) ? $items[$active][0] : 'Provider menu';
?>
<details class="provider-nav-mobile">
    <summary><span>Provider menu</span><strong><?= $this->e($activeLabel) ?></strong></summary>
    <nav aria-label="Provider mobile navigation">
        <?php foreach ($groups as $groupLabel => $keys): ?>
            <span class="provider-nav-group-label"><?= $this->e($groupLabel) ?></span>
            <?php foreach ($keys as $key): if (!isset($items[$key])) { continue; } [$label, $href] = $items[$key]; ?>
                <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</details>
<nav aria-label="Provider" class="provider-nav provider-nav-desktop">
    <?php foreach ($groups as $groupLabel => $keys): ?>
        <span class="provider-nav-group">
            <span class="provider-nav-group-label"><?= $this->e($groupLabel) ?></span>
            <span class="provider-nav-group-links">
                <?php foreach ($keys as $key): if (!isset($items[$key])) { continue; } [$label, $href] = $items[$key]; ?>
                    <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a>
                <?php endforeach; ?>
            </span>
        </span>
    <?php endforeach; ?>
</nav>
