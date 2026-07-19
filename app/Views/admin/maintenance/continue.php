<?php
/** @var \App\Core\View $this */
/** @var string $action */
/** @var array<string,string> $fields */
/** @var string $message */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin-top:0">Working…</h1>
    <p id="continue-status"><?= $this->e($message) ?></p>
    <p class="muted">Leave this tab open. The next step starts automatically.</p>
    <form method="post" action="<?= e($action) ?>" id="maintenance-continue-form" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php foreach ($fields as $name => $value): ?>
            <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
        <?php endforeach; ?>
        <noscript>
            <button type="submit" class="btn btn-primary">Continue</button>
        </noscript>
        <button type="submit" class="btn btn-secondary" id="continue-manual" hidden>Continue now</button>
    </form>
</div>
<script>
(function () {
    var form = document.getElementById('maintenance-continue-form');
    var manual = document.getElementById('continue-manual');
    if (!form) return;
    if (manual) {
        setTimeout(function () { manual.hidden = false; }, 8000);
    }
    setTimeout(function () { form.submit(); }, 400);
})();
</script>
<?php $this->endSection(); ?>
