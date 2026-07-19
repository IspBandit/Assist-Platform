<?php
/** @var \App\Core\View $this */
/** @var string $message */
/** @var string|null $nextUrl */
/** @var bool $done */
/** @var string|null $error */
$this->extend('layouts.admin');
$nextUrl = $nextUrl ?? null;
$done = !empty($done);
$error = $error ?? null;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin-top:0"><?= $done ? ($error ? 'Stopped' : 'Done') : 'Working…' ?></h1>
    <?php if ($error): ?>
        <p class="alert alert-error" role="alert"><?= $this->e($error) ?></p>
    <?php endif; ?>
    <p style="font-size:1.05rem"><?= $this->e($message) ?></p>
    <?php if (!$done && $nextUrl): ?>
        <p class="muted">This page refreshes automatically. Leave the tab open.</p>
        <p style="margin-top:1rem"><a class="btn btn-primary" href="<?= e($nextUrl) ?>">Continue now</a>
            <a class="btn btn-secondary" href="<?= e(url('admin/maintenance?stop_auto=1')) ?>">Stop</a></p>
        <script>
        (function () {
            var url = <?= json_encode($nextUrl) ?>;
            setTimeout(function () { window.location.replace(url); }, 900);
        })();
        </script>
    <?php else: ?>
        <p style="margin-top:1rem"><a class="btn btn-primary" href="<?= e(url('admin/maintenance')) ?>">Back to Maintenance</a></p>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
