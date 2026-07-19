<?php
/** @var \App\Core\View $this */
/** @var array<string,array<int,array<string,mixed>>> $grouped */
/** @var string|null $introBody */
$this->extend('layouts.public');
$labels = [
    'general'   => 'General',
    'customers' => 'For caravan owners',
    'providers' => 'For providers',
    'parks'     => 'For caravan parks',
];
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1>Frequently asked questions</h1>
        <?php if ($introBody): ?><div class="muted" style="margin-bottom:1.5rem"><?= $introBody /* trusted admin HTML */ ?></div><?php endif; ?>

        <?php if ($grouped === []): ?>
            <div class="card"><p class="muted">FAQs are being prepared. Please check back soon.</p></div>
        <?php else: ?>
            <?php foreach ($grouped as $category => $items): ?>
                <div class="card stack">
                    <h2 style="margin-top:0"><?= $this->e($labels[$category] ?? ucfirst((string) $category)) ?></h2>
                    <?php foreach ($items as $faq): ?>
                        <details>
                            <summary style="cursor:pointer;font-weight:600"><?= $this->e((string) $faq['question']) ?></summary>
                            <div class="muted" style="margin-top:.5rem"><?= nl2br($this->e((string) $faq['answer'])) ?></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
