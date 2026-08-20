<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1>Polaris imports</h1>
    <p>Imports create <strong>draft</strong> rows only. Publish via the review queue. Never auto-publish safety-critical specs from low-confidence extraction.</p>

    <section class="card">
        <h2>Cost transparency</h2>
        <ul>
            <li>Deterministic CSV/JSON/XLSX/brochure text: <?= $this->e($costDeterministic['label']) ?></li>
            <li>Paid AI brochure path: <?= $this->e($costAi['label']) ?></li>
        </ul>
        <p class="muted">Flags: brochure extract <?= !empty($brochureEnabled) ? 'ON' : 'OFF' ?> · AI import <?= !empty($aiImportEnabled) ? 'ON' : 'OFF' ?>.</p>
    </section>

    <form method="post" action="<?= e(url('admin/polaris/imports/upload')) ?>" enctype="multipart/form-data" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <label>Format
            <select name="format" id="polaris-import-format">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="xlsx">XLSX (first sheet)</option>
                <option value="brochure" <?= empty($brochureEnabled) ? 'disabled' : '' ?>>Brochure text / PDF text layer</option>
            </select>
        </label>
        <label>Catalogue file
            <input type="file" name="catalogue" accept=".csv,.json,.xlsx,.txt,.pdf,text/csv,application/json,application/pdf">
        </label>
        <label>Brochure text (optional paste)
            <textarea name="brochure_text" rows="6" placeholder="Paste brochure wording when using brochure format" <?= empty($brochureEnabled) ? 'disabled' : '' ?>></textarea>
        </label>
        <label>Manufacturer hint (brochure)
            <input type="text" name="manufacturer_hint" maxlength="190" <?= empty($brochureEnabled) ? 'disabled' : '' ?>>
        </label>
        <p class="muted">Columns / keys: manufacturer, model, variant, category, sleeps, tare_kg, atm_kg, body_length_m, fresh_water_l, solar_w, bathroom_type, price_aud, price_status, description. Enable <code>polaris_brochure_extract</code> for brochure drafts. Paid AI remains behind <code>polaris_ai_import</code> (off).</p>
        <button class="btn btn-primary" type="submit">Upload draft import</button>
    </form>
    <h2>Recent jobs</h2>
    <table class="table">
        <thead><tr><th>ID</th><th>File</th><th>Status</th><th>Rows</th><th>Errors</th><th>Confidence</th></tr></thead>
        <tbody>
        <?php foreach ($jobs as $job): ?>
            <tr>
                <td><?= (int) $job['id'] ?></td>
                <td><?= $this->e((string) ($job['original_filename'] ?? '—')) ?></td>
                <td><?= $this->e($job['status']) ?></td>
                <td><?= (int) $job['row_count'] ?></td>
                <td><?= (int) $job['error_count'] ?></td>
                <td><?= $job['confidence_avg'] !== null ? $this->e((string) $job['confidence_avg']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
