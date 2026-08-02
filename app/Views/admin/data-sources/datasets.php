<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $datasets */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="page-header">
    <div>
        <p class="eyebrow">Data Sources</p>
        <h1>Government datasets</h1>
        <p class="muted">Catalogue of CKAN / ArcGIS / CSV / GeoJSON sources for traveller facilities. Review-first ingest — never writes into <code>caravan_parks</code>.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/data-sources')) ?>">Connectors</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/data-sources/facilities/review')) ?>">Facility review</a>
        <a class="btn btn-primary" href="<?= e(url('admin/data-sources/datasets/edit')) ?>">Add dataset</a>
    </div>
</div>

<section class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Publisher</th>
                <th>Title</th>
                <th>Method</th>
                <th>Trust</th>
                <th>Enabled</th>
                <th>Last import</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($datasets as $dataset): ?>
            <tr>
                <td><?= $this->e((string) $dataset['publisher']) ?></td>
                <td>
                    <strong><?= $this->e((string) $dataset['title']) ?></strong>
                    <div class="muted"><code><?= $this->e((string) $dataset['dataset_key']) ?></code> · <?= $this->e((string) $dataset['connector_key']) ?></div>
                    <?php if (!empty($dataset['last_error'])): ?>
                        <div class="muted" style="color:#a94442"><?= $this->e((string) $dataset['last_error']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $this->e((string) $dataset['fetch_method']) ?></td>
                <td><?= $this->e((string) $dataset['trust_policy']) ?></td>
                <td>
                    <form method="post" action="<?= e(url('admin/data-sources/datasets/save')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $dataset['id'] ?>">
                        <label>
                            <input type="checkbox" name="is_enabled" value="1" <?= !empty($dataset['is_enabled']) ? 'checked' : '' ?> onchange="this.form.submit()">
                            On
                        </label>
                    </form>
                </td>
                <td class="muted"><?= $this->e((string) ($dataset['last_imported_at'] ?? '—')) ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/data-sources/datasets/edit?id=' . (int) $dataset['id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('admin/data-sources/datasets/fetch')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $dataset['id'] ?>">
                        <?php if (str_starts_with((string) $dataset['dataset_key'], 'demo_')): ?>
                            <input type="hidden" name="use_fixture" value="1">
                            <button class="btn btn-secondary btn-sm" type="submit">Import fixture</button>
                        <?php elseif (!empty($dataset['is_enabled'])): ?>
                            <button class="btn btn-primary btn-sm" type="submit">Fetch</button>
                        <?php else: ?>
                            <span class="muted">Enable to fetch</span>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($datasets === []): ?>
            <tr><td colspan="7" class="muted">No catalogue rows. Apply migration 093.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php $this->endSection(); ?>
