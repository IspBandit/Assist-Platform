<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $rows */
/** @var string $section */
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
    <?php if ($rows === []): ?>
        <p class="muted">No rows to display for this section yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= $this->e(str_replace('_', ' ', (string) $col)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= $this->e(is_scalar($value) || $value === null ? (string) ($value ?? '—') : json_encode($value)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php $this->endSection(); ?>
