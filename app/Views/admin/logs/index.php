<?php
/** @var \App\Core\View $this */
/** @var string[] $channels */
/** @var string $channel */
/** @var int $lines */
/** @var string $content */
/** @var int $size */
/** @var int|null $modified */
/** @var bool $exists */
/** @var array{path:string,exists:bool,writable:bool,last_error:?string,db_table:bool,db_count:int} $diag */
/** @var int $fromDb */
$this->extend('layouts.admin');

$human = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem">
        <h1 style="margin:0">System logs</h1>
        <div class="btn-row" style="margin:0;gap:.4rem">
            <a class="btn btn-ghost" href="<?= e(url('admin/logs?channel=' . urlencode($channel) . '&lines=' . $lines)) ?>">Refresh</a>
            <form method="post" action="<?= e(url('admin/logs/repair')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Check / repair logging</button>
            </form>
            <form method="post" action="<?= e(url('admin/logs/test')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="channel" value="<?= e_attr($channel) ?>">
                <button type="submit" class="btn btn-primary">Write test entry</button>
            </form>
        </div>
    </div>
    <p class="muted" style="margin:.5rem 0 0">
        Step-by-step records of what the system tried to do and why it failed.
        The <strong>email</strong> channel logs the full SMTP conversation for every send (passwords redacted).
    </p>

    <?php if (!$diag['writable']): ?>
        <p style="margin:1rem 0 0;padding:.75rem;border-left:4px solid #c9a227;background:#fff9e8;font-size:.9rem">
            <strong>File logging is not writable on this server.</strong>
            <?php if ($diag['db_table']): ?>
                Entries are being saved to the <strong>database</strong> instead (<?= (int) $diag['db_count'] ?> total).
            <?php else: ?>
                Run <a href="<?= e(url('admin/maintenance')) ?>">Maintenance → Apply database updates</a> to enable the database log fallback, then click <strong>Check / repair logging</strong>.
            <?php endif; ?>
            <?php if ($diag['last_error']): ?>
                <br><span class="muted">Last error: <?= $this->e($diag['last_error']) ?></span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <p class="muted" style="margin:.75rem 0 0;font-size:.85rem">
        Log path: <code><?= $this->e($diag['path']) ?></code>
        · directory <?= $diag['exists'] ? 'exists' : 'missing' ?>
        · <?= $diag['writable'] ? 'writable' : 'not writable' ?>
        <?php if ($diag['db_table']): ?>· database fallback active (<?= (int) $diag['db_count'] ?> entries)<?php endif; ?>
    </p>

    <div class="btn-row" style="gap:.4rem;flex-wrap:wrap;margin-top:1rem">
        <?php foreach ($channels as $c): ?>
            <a class="btn <?= $c === $channel ? 'btn-primary' : 'btn-ghost' ?>"
               href="<?= e(url('admin/logs?channel=' . urlencode($c) . '&lines=' . $lines)) ?>">
                <?= $this->e($c) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="<?= e(url('admin/logs')) ?>" class="btn-row" style="align-items:end;margin-top:1rem;gap:.6rem">
        <input type="hidden" name="channel" value="<?= e_attr($channel) ?>">
        <div class="form-group" style="margin:0">
            <label for="lines">Show last</label>
            <select id="lines" name="lines" onchange="this.form.submit()">
                <?php foreach ([100, 300, 500, 1000, 2000] as $n): ?>
                    <option value="<?= $n ?>" <?= $lines === $n ? 'selected' : '' ?>><?= $n ?> lines</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Apply</button>
    </form>

    <p class="muted" style="margin:1rem 0 .25rem;font-size:.9rem">
        <?php if ($exists): ?>
            <code><?= $this->e($channel) ?>.log</code>
            <?php if ($size > 0): ?> — <?= e($human($size)) ?><?php endif; ?>
            <?php if ($modified !== null): ?> · file last written <?= e(date('Y-m-d H:i:s', $modified)) ?><?php endif; ?>
            <?php if ($fromDb > 0): ?> · <?= (int) $fromDb ?> line(s) from database fallback<?php endif; ?>
            · showing last <?= (int) $lines ?> lines
        <?php else: ?>
            No entries yet for <code><?= $this->e($channel) ?></code>. Click <strong>Write test entry</strong> to verify logging works.
        <?php endif; ?>
    </p>

    <?php if (trim($content) !== ''): ?>
        <pre style="background:#0f1720;color:#d6e2e0;padding:1rem;border-radius:8px;overflow:auto;max-height:65vh;white-space:pre-wrap;word-break:break-word;font-size:.82rem;line-height:1.5"><?= $this->e($content) ?></pre>
    <?php elseif ($exists): ?>
        <p class="muted">The log exists but is empty for this channel.</p>
    <?php endif; ?>

    <?php if ($exists && trim($content) !== ''): ?>
        <form method="post" action="<?= e(url('admin/logs/clear')) ?>"
              onsubmit="return confirm('Clear the <?= e_attr($channel) ?> log? This cannot be undone.');"
              style="margin-top:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="channel" value="<?= e_attr($channel) ?>">
            <button type="submit" class="btn btn-danger">Clear this log</button>
        </form>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
