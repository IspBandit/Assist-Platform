<?php
/** @var \App\Core\View $this */
/** @var int $retryAfter */
/** @var bool $turnstileEnabled */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:32rem">
        <h1>Ask VanAssist is temporarily paused</h1>
        <?php if ($turnstileEnabled ?? false): ?>
        <p class="lead">Please complete the quick security check to continue.</p>
        <form method="post" action="<?= e(url('ask/unlock')) ?>" class="card" style="padding:1.25rem">
            <?= csrf_field() ?>
            <?php $this->include('partials.turnstile'); ?>
            <div class="search-submit-row" style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">Continue to Ask VanAssist</button>
                <a class="btn btn-secondary" href="<?= e(url('find')) ?>">Use category search</a>
            </div>
        </form>
        <?php else: ?>
        <p class="lead">This connection has made a lot of searches. Please try again later, or use the category search now.</p>
        <p><a class="btn btn-primary" href="<?= e(url('find')) ?>">Use category search</a></p>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
