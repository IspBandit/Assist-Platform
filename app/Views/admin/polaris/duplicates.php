<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $rows */
/** @var string $note */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="page-header">
    <div>
        <p class="eyebrow">Polaris</p>
        <h1><?= $this->e($title) ?></h1>
        <p class="muted"><?= $this->e($note) ?></p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/polaris')) ?>">Catalogue home</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/polaris/review-queue')) ?>">Review queue</a>
    </div>
</div>
<section class="card">
    <h2>Merge manufacturers</h2>
    <form method="post" action="<?= e(url('admin/polaris/manufacturers/merge')) ?>" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <label>Survivor manufacturer ID <input type="number" name="survivor_id" min="1" required></label>
        <label>Absorbed manufacturer ID <input type="number" name="absorbed_id" min="1" required></label>
        <label>Notes <input type="text" name="notes" maxlength="500"></label>
        <button class="btn btn-primary" type="submit">Merge into survivor</button>
    </form>
</section>
<section class="card">
    <?php if ($rows === []): ?>
        <p class="muted">No manufacturers to display.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Claim</th>
                        <th>Lifecycle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= (int) ($row['id'] ?? 0) ?></td>
                            <td><?= $this->e((string) ($row['trading_name'] ?? '')) ?></td>
                            <td><?= $this->e((string) ($row['slug'] ?? '')) ?></td>
                            <td><?= $this->e((string) ($row['claim_status'] ?? '')) ?></td>
                            <td><?= $this->e((string) ($row['lifecycle_status'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php $this->endSection(); ?>
