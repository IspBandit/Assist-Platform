<?php
/** @var \App\Core\View $this */
/** @var string|null $active */
$active = $active ?? '';
$items = [
    'pages'  => ['Pages', 'admin/content'],
    'blocks' => ['Homepage blocks', 'admin/content/blocks'],
    'faqs'   => ['FAQs', 'admin/content/faqs'],
];
?>
<div class="btn-row" style="margin-bottom:1.5rem;border-bottom:1px solid #e3e0d8;padding-bottom:1rem">
    <?php foreach ($items as $key => [$label, $href]): ?>
        <a class="btn <?= $active === $key ? 'btn-secondary' : 'btn-ghost' ?>" href="<?= e(url($href)) ?>"><?= $this->e($label) ?></a>
    <?php endforeach; ?>
</div>
