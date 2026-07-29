<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="page-header">
  <div>
    <p class="eyebrow">Platform Control Centre</p>
    <h1>Queensland coverage</h1>
    <p class="muted">Read-only locality × category matrix from offline seed analysis (LOC-002 / DATA-001). Nearby candidates are not confirmed coverage. Production data is not modified here. Marketing consent is never inferred from a public email.</p>
  </div>
  <div class="btn-row">
    <a class="btn btn-secondary" href="<?= e(url('admin/data-sources/review')) ?>">Import review queue</a>
    <a class="btn btn-ghost" href="<?= e(url('admin/data-sources')) ?>">Data sources</a>
  </div>
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
  <h2>Filter coverage gaps</h2>
  <form method="get" action="<?= e(url('admin/qld-coverage')) ?>" class="form-stack">
    <div class="form-grid">
      <label>Region batch
        <select name="batch">
          <option value="">All batches</option>
          <?php foreach ($batches as $batch): ?>
            <option value="<?= $this->e((string)($batch['batch_id'] ?? '')) ?>" <?= (($filters['batch'] ?? '') === ($batch['batch_id'] ?? '')) ? 'selected' : '' ?>><?= $this->e((string)($batch['batch_name'] ?? $batch['batch_id'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Town / suburb
        <input type="search" name="town" value="<?= $this->e((string)($filters['town'] ?? '')) ?>" placeholder="e.g. Toowoomba">
      </label>
      <label>Category
        <input type="search" name="category" value="<?= $this->e((string)($filters['category'] ?? '')) ?>" placeholder="e.g. caravan">
      </label>
      <label>Coverage status
        <select name="status">
          <?php
            $statuses = ['', 'no_coverage', 'nearby_only', 'unclaimed_only', 'needs_review', 'partially_verified', 'verified'];
            foreach ($statuses as $st):
          ?>
            <option value="<?= $this->e($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= $st === '' ? 'Any' : $this->e($st) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>List
        <select name="source">
          <option value="zero" <?= (($filters['source'] ?? '') === 'zero') ? 'selected' : '' ?>>Zero coverage sample</option>
          <option value="weak" <?= (($filters['source'] ?? '') === 'weak') ? 'selected' : '' ?>>Weak coverage sample</option>
        </select>
      </label>
    </div>
    <div class="btn-row">
      <button class="btn btn-primary" type="submit">Apply filters</button>
      <a class="btn btn-ghost" href="<?= e(url('admin/qld-coverage')) ?>">Clear</a>
    </div>
  </form>
</section>

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
          <th>Last audited</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($batches as $batch): ?>
        <tr>
          <td><a href="<?= e(url('admin/qld-coverage') . '?batch=' . rawurlencode((string)($batch['batch_id'] ?? ''))) ?>"><?= $this->e((string)($batch['batch_name'] ?? $batch['batch_id'] ?? '')) ?></a></td>
          <td><?= number_format((int)($batch['towns'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['verified_cells'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['zero_coverage_cells'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['weak_coverage_cells'] ?? 0)) ?></td>
          <td><?= number_format((int)($batch['providers_referenced'] ?? 0)) ?></td>
          <td><?= $this->e((string)($batch['last_audited'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2><?= (($filters['source'] ?? 'zero') === 'weak') ? 'Weak-coverage sample' : 'Zero-coverage sample' ?></h2>
  <p class="muted">Committed samples only (500 rows). Full gap lists live under <code>storage/imports/qld-coverage/</code>. Approve/merge/reject of live candidates remains in the import review queue — uncertain records are never auto-published.</p>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Region</th>
          <th>Town</th>
          <th>Postcode</th>
          <th>Category</th>
          <th>Verified local</th>
          <th>Verified mobile</th>
          <th>Unclaimed</th>
          <th>Nearby</th>
          <th>Status</th>
          <th>Last audited</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($coverageSample === []): ?>
        <tr><td colspan="11" class="muted">No rows match the current filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($coverageSample as $row): ?>
        <tr>
          <td><?= $this->e((string)($row['Region'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Town/suburb'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Postcode'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Service category'] ?? '')) ?></td>
          <td><?= number_format((int)($row['Verified local providers'] ?? 0)) ?></td>
          <td><?= number_format((int)($row['Verified mobile providers'] ?? 0)) ?></td>
          <td><?= number_format((int)($row['Unclaimed sourced providers'] ?? 0)) ?></td>
          <td><?= number_format((int)($row['Nearby candidates'] ?? 0)) ?></td>
          <td><?= $this->e((string)($row['Coverage status'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Last audited'] ?? '')) ?></td>
          <td><?= $this->e((string)($row['Recommended action'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Review candidates (source &amp; category evidence)</h2>
  <p class="muted">Offline review queue sample. Field evidence is pack-derived until website or licence re-verification is recorded. Public emails are not marketing consent.</p>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Business</th>
          <th>Town</th>
          <th>Categories</th>
          <th>Sources</th>
          <th>Confidence</th>
          <th>Last checked</th>
          <th>Review reasons</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($reviewSample === []): ?>
        <tr><td colspan="7" class="muted">No review candidates match the current filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($reviewSample as $p): ?>
        <tr>
          <td>
            <strong><?= $this->e((string)($p['business_name'] ?? '')) ?></strong>
            <?php if (!empty($p['website'])): ?><br><a href="<?= $this->e((string)$p['website']) ?>" rel="noopener noreferrer" target="_blank">Website</a><?php endif; ?>
            <?php if (!empty($p['phone'])): ?><br><span class="muted"><?= $this->e((string)$p['phone']) ?></span><?php endif; ?>
          </td>
          <td><?= $this->e(trim(((string)($p['town'] ?? '')) . ' ' . ((string)($p['postcode'] ?? '')))) ?></td>
          <td>
            <?php foreach (($p['field_evidence']['categories'] ?? []) as $ev): ?>
              <div><code><?= $this->e((string)($ev['category'] ?? '')) ?></code> — <?= $this->e((string)($ev['evidence'] ?? '')) ?></div>
            <?php endforeach; ?>
          </td>
          <td>
            <?php foreach (($p['source_records'] ?? []) as $src): ?>
              <div><?= $this->e((string)($src['source_name'] ?? '')) ?> (<?= $this->e((string)($src['source_licence'] ?? '')) ?>)</div>
            <?php endforeach; ?>
            <?php if (!empty($p['field_evidence']['email'])): ?>
              <div class="muted">Email: sourced; marketing consent=<?= !empty($p['field_evidence']['email']['marketing_consent']) ? 'yes' : 'no' ?></div>
            <?php endif; ?>
          </td>
          <td><?= number_format((int)($p['confidence'] ?? 0)) ?></td>
          <td><?= $this->e((string)($p['last_checked_at'] ?? '')) ?></td>
          <td><?= $this->e(implode(', ', array_map('strval', $p['review_reasons'] ?? []))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Possible duplicates</h2>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Key</th><th>Candidate IDs</th></tr></thead>
      <tbody>
      <?php foreach ($duplicates as $dup): ?>
        <tr>
          <td><?= $this->e((string)($dup['key'] ?? '')) ?></td>
          <td><code><?= $this->e(implode(', ', array_map('strval', $dup['ids'] ?? []))) ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Regulated categories missing licence evidence</h2>
  <p class="muted">Held regardless of confidence score until a Queensland licence or approved-provider register match is recorded.</p>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Business</th><th>Categories</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($regulated as $row): ?>
        <tr>
          <td><?= $this->e((string)($row['name'] ?? '')) ?></td>
          <td><?= $this->e(implode(', ', array_map('strval', $row['categories'] ?? []))) ?></td>
          <td><?= $this->e((string)($row['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Import dry-run</h2>
  <p class="muted">Publishable-minus-regulated candidates shaped for the review queue. Default CLI mode writes files only — no production writes.</p>
  <p><code>php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay</code></p>
  <p class="muted">Reports land in <code>storage/imports/qld-coverage/dry-run-*.json</code>. Use <code>--apply</code> only on local/test to insert pending review rows.</p>
</section>

<section class="card">
  <h2>Sources and licences</h2>
  <ul>
  <?php foreach (($summary['sources'] ?? []) as $sourceRow): ?>
    <li><?= $this->e((string)($sourceRow['source_name'] ?? '')) ?> — <?= $this->e((string)($sourceRow['source_licence'] ?? '')) ?> (<?= number_format((int)($sourceRow['count'] ?? 0)) ?>)</li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php $this->endSection(); ?>
