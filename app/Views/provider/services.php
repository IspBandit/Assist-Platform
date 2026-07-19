<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $services */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Services</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'services']); ?>

        <div class="card">
            <h2>Your services</h2>
            <div class="btn-row" style="flex-wrap:wrap">
                <?php foreach ($services as $s): ?>
                    <form method="post" action="<?= e(url('provider/services/remove')) ?>" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="btn btn-ghost"><?= $this->e((string) $s['name']) ?> &times;</button>
                    </form>
                <?php endforeach; ?>
                <?php if ($services === []): ?><span class="muted">You haven't added any services yet.</span><?php endif; ?>
            </div>

            <form method="post" action="<?= e(url('provider/services/add')) ?>" class="btn-row" style="margin-top:1.5rem">
                <?= csrf_field() ?>
                <select name="category_id" required>
                    <option value="">Add a service…</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= $this->e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Add service</button>
            </form>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
