<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $documents */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:flex-start;gap:1rem">
        <div>
            <h1 style="margin:0">Legal documents</h1>
            <p class="muted" style="margin:.5rem 0 0">Download professionally formatted PDFs for Terms, Privacy, Provider Terms, DPA, OpCo and IP (COM-005). Public site pages use the same effective date (<strong>18 September 2026</strong>). Formal solicitor review may refine wording later.</p>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:1.25rem">
        <table class="data">
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Audience</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td>
                        <strong><?= $this->e((string) $doc['title']) ?></strong>
                        <div class="muted" style="margin-top:.25rem"><?= $this->e((string) $doc['summary']) ?></div>
                    </td>
                    <td><?= $this->e((string) $doc['category']) ?></td>
                    <td><?= $this->e((string) $doc['audience']) ?></td>
                    <td><?= $this->e((string) $doc['effective_label']) ?></td>
                    <td class="btn-row" style="margin:0;justify-content:flex-end">
                        <a class="btn btn-primary" href="<?= e(url('admin/legal-documents/download?id=' . urlencode((string) $doc['id']))) ?>">Download PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($documents === []): ?>
                <tr><td colspan="5" class="muted">No legal source documents found under <code>docs/legal/drafts</code>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:1.25rem;background:transparent;box-shadow:none;border:1px solid var(--border, #ddd)">
        <h2 style="margin-top:0;font-size:1rem">How to use this pack</h2>
        <ul class="muted">
            <li><strong>Terms, Privacy, Provider Terms and Disclaimer</strong> are published on the public CMS pages (re-seed via Admin → Maintenance → Populate Pages &amp; Blocks after deploy).</li>
            <li><strong>DPA, OpCo and IP</strong> belong in contracts or the acquisition data room — not the public footer.</li>
            <li>Operator identity: Glen Condren (sole trader), ABN 76 553 821 887. Update immediately if you incorporate.</li>
            <li>Downloads are audited as <code>legal_document.download</code>.</li>
        </ul>
    </div>
</div>
<?php $this->endSection(); ?>
