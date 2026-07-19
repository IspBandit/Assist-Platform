<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $documents */
/** @var array<int,string> $docTypes */
$this->extend('layouts.public');
$badge = ['verified' => 'badge-verified', 'rejected' => 'badge-neutral', 'pending' => 'badge-confirmed'];
$mb = (int) config('uploads.max_document_mb', 10);
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Verification documents</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'documents']); ?>

        <div class="card">
            <h2>Upload a document</h2>
            <p class="muted">Accepted: PDF, JPG, PNG, WEBP. Maximum <?= $mb ?> MB. Documents are stored privately and reviewed by our team.</p>
            <form method="post" action="<?= e(url('provider/documents/upload')) ?>" enctype="multipart/form-data" class="grid grid-3" style="align-items:flex-end">
                <?= csrf_field() ?>
                <div class="form-group mb-0">
                    <label for="doc_type">Document type</label>
                    <select id="doc_type" name="doc_type">
                        <?php foreach ($docTypes as $t): ?>
                            <option value="<?= e($t) ?>"><?= $this->e(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="document">File</label>
                    <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-secondary">Upload</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Your documents</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Type</th><th>File</th><th>Uploaded</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td><?= $this->e(ucfirst((string) $d['doc_type'])) ?></td>
                            <td><a href="<?= e(url('provider/documents/download?id=' . (int) $d['id'])) ?>" target="_blank"><?= $this->e((string) ($d['original_name'] ?? 'file')) ?></a></td>
                            <td class="muted"><?= $this->e((string) $d['created_at']) ?></td>
                            <td><span class="badge <?= $badge[$d['verification_status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $d['verification_status'])) ?></span>
                                <?php if ($d['verification_status'] === 'rejected' && $d['verification_notes']): ?><br><span class="muted" style="font-size:.85rem"><?= $this->e((string) $d['verification_notes']) ?></span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['verification_status'] !== 'verified'): ?>
                                    <form method="post" action="<?= e(url('provider/documents/delete')) ?>" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                                        <button type="submit" class="btn btn-ghost">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($documents === []): ?><tr><td colspan="5" class="muted">No documents uploaded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
