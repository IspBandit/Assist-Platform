<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $documents */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1>Park documents</h1>
        <?php $this->include('partials.park-nav', ['active' => 'documents']); ?>

        <div class="card stack">
            <h2 style="margin-top:0">Upload a document</h2>
            <p class="muted" style="margin:0">Insurance, agreements or other documents for our records. PDF, JPG, PNG or WebP.</p>
            <form method="post" action="<?= e(url('park/documents/upload')) ?>" enctype="multipart/form-data" class="btn-row" style="align-items:flex-end">
                <?= csrf_field() ?>
                <div class="form-group mb-0">
                    <label for="doc_type">Type</label>
                    <select id="doc_type" name="doc_type">
                        <option value="agreement">Agreement</option>
                        <option value="insurance">Insurance</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="document">File</label>
                    <input type="file" id="document" name="document" accept="application/pdf,image/jpeg,image/png,image/webp" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>

        <div class="card stack">
            <h2 style="margin-top:0">Uploaded documents</h2>
            <?php if ($documents === []): ?>
                <p class="muted">No documents uploaded.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Type</th><th>Name</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td><?= $this->e(ucfirst((string) $d['doc_type'])) ?></td>
                                <td><?= $this->e((string) ($d['original_name'] ?? 'document')) ?></td>
                                <td class="btn-row" style="margin:0">
                                    <a class="btn btn-ghost" href="<?= e(url('park/documents/download?document_id=' . (int) $d['id'])) ?>">Download</a>
                                    <form method="post" action="<?= e(url('park/documents/delete')) ?>" style="margin:0">
                                        <?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                                        <button type="submit" class="btn btn-ghost">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
