<?php
/** @var \App\Core\View $this */
/** @var string|null $active */
$active = $active ?? '';
$items = [
    'dashboard' => ['Dashboard', 'park'],
    'profile'   => ['Park profile', 'park/profile'],
    'request'   => ['Register guest request', 'park/register-request'],
    'runs'      => ['Nearby runs', 'park/runs'],
    'serviceday' => ['Request a service day', 'park/service-day'],
    'documents' => ['Documents', 'park/documents'],
    'materials' => ['QR code & materials', 'park/materials'],
];
?>
<nav aria-label="Caravan park" class="btn-row" style="margin-bottom:1.5rem;border-bottom:1px solid #e3e0d8;padding-bottom:1rem;flex-wrap:wrap">
    <?php foreach ($items as $key => [$label, $href]): ?>
        <a class="btn <?= $active === $key ? 'btn-secondary' : 'btn-ghost' ?>" href="<?= e(url($href)) ?>"><?= $this->e($label) ?></a>
    <?php endforeach; ?>
</nav>
