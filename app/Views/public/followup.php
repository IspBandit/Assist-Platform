<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $followup */
/** @var array<string,mixed>|null $request */
/** @var array<int,array<string,mixed>> $providers */
/** @var string $token */
/** @var bool $done */
$done = $done ?? false;
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:640px">
        <?php if ($done): ?>
            <div class="card"><h1 style="margin-top:0">Thank you</h1><p class="mb-0">Your feedback helps us connect more travellers with the right providers.</p></div>
        <?php elseif ($followup === null || $request === null): ?>
            <div class="card"><h1 style="margin-top:0">This link has expired</h1><p class="mb-0">If you'd still like to share how your request went, please <a href="<?= e(url('account')) ?>">sign in to your account</a>.</p></div>
        <?php else: ?>
            <h1 style="margin-bottom:.25rem">How did it go?</h1>
            <p class="muted">Request <strong><?= $this->e((string) $request['reference']) ?></strong> — <?= $this->e((string) $request['title']) ?></p>
            <form method="post" action="<?= e(url('followup/' . $token)) ?>" class="card stack">
                <?= csrf_field() ?>
                <fieldset class="stack" style="border:0;padding:0;margin:0">
                    <label><input type="radio" name="used" value="yes_vanassist" checked> I used a provider found through VanAssist</label>
                    <label><input type="radio" name="used" value="yes_elsewhere"> I used someone found elsewhere</label>
                    <label><input type="radio" name="used" value="not_yet"> Not arranged yet</label>
                    <label><input type="radio" name="used" value="could_not_find"> Couldn't find anyone suitable</label>
                </fieldset>

                <?php if ($providers !== []): ?>
                    <label>Which provider?
                        <select name="provider_id">
                            <option value="">— Select —</option>
                            <?php foreach ($providers as $p): ?><option value="<?= (int) $p['provider_id'] ?>"><?= $this->e((string) $p['business_name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label><input type="checkbox" name="completed" value="1"> The work was completed</label>
                    <label>Satisfaction
                        <select name="satisfaction_rating">
                            <option value="">— Not rated —</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> / 5</option><?php endfor; ?>
                        </select>
                    </label>
                    <label><input type="checkbox" name="would_use_again" value="1"> I would use them again</label>
                <?php endif; ?>

                <label>Anything else? (optional) <textarea name="comment" rows="2" maxlength="500"></textarea></label>
                <div class="btn-row"><button type="submit" class="btn btn-primary">Submit</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
