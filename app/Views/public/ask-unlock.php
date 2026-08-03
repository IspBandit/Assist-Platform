<?php
/** @var \App\Core\View $this */
/** @var int $retryAfter */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:32rem">
        <h1>Too many Ask VanAssist searches</h1>
        <p class="lead">Please complete the security check to continue. You can try again after about <?= (int) ($retryAfter ?? 3600) ?> seconds if the check is unavailable.</p>
        <form method="post" action="<?= e(url('ask/unlock')) ?>" class="card" style="padding:1.25rem">
            <?= csrf_field() ?>
            <?php $this->include('partials.turnstile'); ?>
            <div class="search-submit-row" style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">Continue to Ask VanAssist</button>
                <a class="btn btn-secondary" href="<?= e(url('find')) ?>">Use category search</a>
            </div>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
