<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> / Services
        </nav>
        <h1>Caravan &amp; RV service categories</h1>
        <p class="muted">Browse the services available through VanAssist. Choose a category to learn more or register a request to help bring a provider to your area.</p>

        <?php if ($categories === []): ?>
            <p class="muted">Service categories are being added.</p>
        <?php else: ?>
            <div class="grid grid-3" style="margin-top:1.5rem">
                <?php foreach ($categories as $cat): ?>
                    <div class="card">
                        <h3 style="margin-top:0"><a href="<?= e(url('services/' . $cat['slug'])) ?>"><?= $this->e((string) $cat['name']) ?></a></h3>
                        <?php if (!empty($cat['short_description'])): ?>
                            <p class="muted mb-0"><?= $this->e((string) $cat['short_description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-sand text-center">
    <div class="container">
        <h2>Not sure which service you need?</h2>
        <p class="muted">Describe the problem and VanAssist will help match it to the right specialist.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
    </div>
</section>
<?php $this->endSection(); ?>
