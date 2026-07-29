<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="page-header">
  <div>
    <p class="eyebrow">Platform Control Centre</p>
    <h1>Queensland coverage</h1>
    <p class="muted">Read-only locality × category matrix from offline seed analysis. Nearby candidates are not confirmed coverage. Production data is not modified here.</p>
  </div>
  <a class="btn btn-secondary" href="<?= e(url('admin/data-sources/review')) ?>">Import review queue</a>
</div>

<?php if ($summary === null): ?>
<div class="notice notice-warning">No coverage summary found. Run <code>node tools/qld-coverage-matrix.js</code> then refresh.</div>
<?php else: ?>
<div class="stats-grid">
  <article class="stat-card"><span>Towns processed</span><strong><?= number_format((int)$summary['towns_suburbs_processed']) ?></strong></article>
  <article class="stat-card"><span>Categories</span><strong><?= number_format((int)$summary['service_categories_processed']) ?></strong></article>
  <article class="stat-card"><span>Publishable</span><strong><?= number_format((int)$summary['publishable_records']) ?></strong></article>
  <article class="stat-card"><span>Held for review</span><strong><?= number_format((int)$summary['held_for_review']) ?></strong></article>
  <article class="stat-card"><span>Verified cells</span><strong><?= number_format((int)$summary['verified_coverage_cells']) ?></strong></article>
  <article class="stat-card"><span>Zero coverage cells</span><strong><?= number_format((int)$summary['zero_coverage_cells']) ?></strong></article>
</div>

<section class="card">
  <h2>Regional batches</h2>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Batch</th>
          <th>Towns</th>
          <th>Verified cells</th>
          <th>Zero cells</th>
          <th>Weak cells</th>
          <th>Providers</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($batches as $batch): ?>
        <tr>
          <td><?= $this->e((string)($batch['batch_name'] ?? $batch['batch_id'] ?? '')) ?></td>
          <td><?= number_format((int)($batch['towns'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['verified_cells'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['zero_coverage_cells'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['weak_coverage_cells'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['providers_referenced'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Zero-coverage sample</h2>
  <p class="muted">First 50 town/category combinations with no local, mobile or nearby evidence. Full list: <code>database/seeds/qld-coverage/zero-coverage.jsonl</code>.</p>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Region</th>
          <th>Town</th>
          <th>Postcode</th>
          <th>Category</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($zeroSample as $row): ?>
        <tr>
          <td><?= $this->e((string)($row['Region'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Town/suburb'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Postcode'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Service category'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Recommended action'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Sources and licences</h2>
  <ul>
  <?php foreach (($summary['sources'] ?? []) as $source): ?>
    <li><?= $this->e((string)($source['source_name'] ?? '')) ?> — <?= $this->e((string)($source['source_licence'] ?? '')) ?> (<?= number_format((int)($source['count'] ?? 0)) ?>)</li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php $this->endSection(); ?>
