<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $licences */
$this->extend('layouts.public');
$badge = ['verified' => 'badge-verified', 'rejected' => 'badge-neutral', 'pending' => 'badge-confirmed', 'expired' => 'badge-neutral'];
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Licences &amp; credentials</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'licences']); ?>

        <div class="card">
            <h2>Add a licence</h2>
            <form method="post" action="<?= e(url('provider/licences/save')) ?>" class="grid grid-2">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="licence_type">Licence / credential type *</label>
                    <input type="text" id="licence_type" name="licence_type" placeholder="e.g. Gas fitting licence" required>
                </div>
                <div class="form-group">
                    <label for="licence_number">Number</label>
                    <input type="text" id="licence_number" name="licence_number">
                </div>
                <div class="form-group">
                    <label for="issuing_authority">Issuing authority</label>
                    <input type="text" id="issuing_authority" name="issuing_authority">
                </div>
                <div class="form-group">
                    <label for="issue_date">Issue date</label>
                    <input type="date" id="issue_date" name="issue_date">
                </div>
                <div class="form-group">
                    <label for="expiry_date">Expiry date</label>
                    <input type="date" id="expiry_date" name="expiry_date">
                </div>
                <div class="form-group" style="align-self:flex-end">
                    <label><input type="checkbox" name="display_publicly" value="1"> Show on my public profile once verified</label>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary">Add licence</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Your licences</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Type</th><th>Number</th><th>Authority</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($licences as $l): ?>
                        <tr>
                            <td><?= $this->e((string) $l['licence_type']) ?></td>
                            <td><?= $this->e((string) ($l['licence_number'] ?? '—')) ?></td>
                            <td><?= $this->e((string) ($l['issuing_authority'] ?? '—')) ?></td>
                            <td><?= $this->e((string) ($l['expiry_date'] ?? '—')) ?></td>
                            <td><span class="badge <?= $badge[$l['verification_status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $l['verification_status'])) ?></span></td>
                            <td>
                                <form method="post" action="<?= e(url('provider/licences/delete')) ?>" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="licence_id" value="<?= (int) $l['id'] ?>">
                                    <button type="submit" class="btn btn-ghost">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($licences === []): ?><tr><td colspan="6" class="muted">No licences recorded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
