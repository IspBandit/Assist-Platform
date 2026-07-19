<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $files */
/** @var string $dir */
$this->extend('layouts.admin');
$fmtSize = static function (int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Backups</h1>
        <form method="post" action="<?= e(url('admin/backups/generate')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Generate backup now</button>
        </form>
    </div>
    <p class="muted">Compressed database dumps in <code><?= $this->e($dir) ?></code> (outside the web root). The <code>database_backup</code> cron also runs on a schedule with automatic retention. Keep an off-server copy too.</p>

    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><code><?= $this->e((string) $f['name']) ?></code></td>
                    <td><?= $this->e($fmtSize((int) $f['size'])) ?></td>
                    <td><?= $this->e((string) $f['modified']) ?></td>
                    <td class="btn-row" style="margin:0">
                        <a class="btn btn-ghost" href="<?= e(url('admin/backups/download?file=' . urlencode((string) $f['name']))) ?>">Download</a>
                        <form method="post" action="<?= e(url('admin/backups/delete')) ?>" style="margin:0">
                            <?= csrf_field() ?><input type="hidden" name="file" value="<?= e_attr((string) $f['name']) ?>">
                            <button type="submit" class="btn btn-ghost">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($files === []): ?><tr><td colspan="4" class="muted">No backups yet. Generate one above.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
