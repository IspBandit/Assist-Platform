<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $rows */
/** @var string $status */
/** @var \App\Platform\Brand\Brand $brand */
/** @var bool $canManage */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="page-header">
    <div>
        <p class="eyebrow">DATA-013 / AI-4</p>
        <h1>Knowledge gaps</h1>
        <p class="muted">Grouped weak and zero-result Ask searches for <?= $this->e($brand->name()) ?>. CSV or SearchGap-shaped JSON feeds RIC. After CORE-011 merge, Admin API <code>GET /search-gaps</code> unions these with <code>provider_searches</code> (dual-source). Does not invent listings.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/ai-search')) ?>">AI settings</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/ai-search/gaps/export?status=' . rawurlencode($status))) ?>">Export CSV for RIC</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/ai-search/gaps/export?format=json&status=' . rawurlencode($status))) ?>">Export SearchGap JSON</a>
    </div>
</div>

<div class="btn-row" style="margin-bottom:1rem">
    <?php foreach (['open' => 'Open', 'researching' => 'Researching', 'resolved' => 'Resolved', 'wont_fix' => "Won't fix", 'all' => 'All'] as $key => $label): ?>
        <a class="btn <?= $status === $key ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/ai-search/gaps?status=' . $key)) ?>"><?= $this->e($label) ?></a>
    <?php endforeach; ?>
</div>

<section class="card">
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Priority</th>
                <th>Query</th>
                <th>Intent</th>
                <th>Quality</th>
                <th>Searches</th>
                <th>Zero / weak</th>
                <th>Safety</th>
                <th>Status</th>
                <?php if ($canManage): ?><th>Update</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= (int) ($row['priority_score'] ?? 0) ?></strong></td>
                    <td>
                        <code><?= $this->e((string) ($row['normalised_query'] ?? '')) ?></code>
                        <div class="muted"><?= $this->e((string) ($row['original_query_sample'] ?? '')) ?></div>
                    </td>
                    <td><?= $this->e((string) ($row['intent_type'] ?? '')) ?></td>
                    <td><?= $this->e((string) ($row['result_quality'] ?? '')) ?></td>
                    <td><?= (int) ($row['search_count'] ?? 0) ?></td>
                    <td><?= (int) ($row['zero_result_count'] ?? 0) ?> / <?= (int) ($row['weak_result_count'] ?? 0) ?></td>
                    <td><?= !empty($row['safety_relevant']) ? 'Yes' : 'No' ?></td>
                    <td><?= $this->e((string) ($row['resolution_status'] ?? '')) ?></td>
                    <?php if ($canManage): ?>
                        <td>
                            <form method="post" action="<?= e(url('admin/ai-search/gaps')) ?>" class="form-stack">
                                <?= csrf_field() ?>
                                <input type="hidden" name="gap_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                <select name="resolution_status">
                                    <?php foreach (['open', 'researching', 'resolved', 'wont_fix'] as $st): ?>
                                        <option value="<?= e($st) ?>" <?= ($row['resolution_status'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="assigned_research_job" placeholder="RIC job / ticket" value="<?= e_attr((string) ($row['assigned_research_job'] ?? '')) ?>">
                                <input type="text" name="resolution_notes" placeholder="Notes" value="<?= e_attr((string) ($row['resolution_notes'] ?? '')) ?>">
                                <button class="btn btn-secondary" type="submit">Save</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= $canManage ? 9 : 8 ?>" class="muted">No knowledge gaps for this filter yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $this->endSection(); ?>
