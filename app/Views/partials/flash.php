<?php
/** @var \App\Core\View $this */
use App\Core\Session;
$messages = Session::flashMessages();
?>
<?php if ($messages !== []): ?>
    <div class="container" style="padding-top:1rem">
        <?php foreach ($messages as $type => $message): ?>
            <div class="alert alert-<?= $this->e($type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info')) ?>"
                 role="status" data-auto-dismiss>
                <?= $this->e($message) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
