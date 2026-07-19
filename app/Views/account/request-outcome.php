<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $request */
/** @var array<int,array<string,mixed>> $providers */
/** @var array<int,array<string,mixed>> $existing */
$this->extend('layouts.public');
$r = $request;
$bands = [
    'under_100' => 'Under $100', '100_249' => '$100–$249', '250_499' => '$250–$499',
    '500_999' => '$500–$999', '1000_2499' => '$1,000–$2,499', '2500_4999' => '$2,500–$4,999',
    '5000_plus' => '$5,000 or more', 'prefer_not_say' => 'Prefer not to say',
];
$reasons = [
    'none_nearby' => 'No provider nearby', 'none_soon_enough' => 'No provider available soon enough',
    'no_mobile' => 'No mobile provider', 'no_workshop' => 'No workshop option',
    'outside_area' => 'Provider does not cover my area', 'wrong_category' => 'Provider does not offer the service',
    'could_not_assist' => 'Provider could not assist', 'price' => 'Price was unsuitable',
    'no_contact' => 'Could not contact provider', 'no_response' => 'Provider did not respond',
    'licensing' => 'Licensing or verification concerns', 'other' => 'Other',
];
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:760px">
        <a class="muted" href="<?= e(url('account/requests/' . $r['reference'])) ?>">&laquo; Back to request</a>
        <h1 style="margin:.25rem 0">Did you use a provider?</h1>
        <p class="muted">Request <strong><?= $this->e((string) $r['reference']) ?></strong> — <?= $this->e((string) $r['title']) ?></p>

        <form method="post" action="<?= e(url('account/requests/' . $r['reference'] . '/outcome')) ?>" class="card stack">
            <?= csrf_field() ?>

            <fieldset class="stack" style="border:0;padding:0;margin:0">
                <legend><strong>What happened with this request?</strong></legend>
                <label><input type="radio" name="used" value="yes_vanassist" checked> Yes — I used a provider found through VanAssist</label>
                <label><input type="radio" name="used" value="yes_elsewhere"> Yes — but I used a provider found elsewhere</label>
                <label><input type="radio" name="used" value="not_yet"> No — I have not arranged the work yet</label>
                <label><input type="radio" name="used" value="no_longer"> No — I no longer need the work</label>
                <label><input type="radio" name="used" value="could_not_find"> I could not find anyone suitable</label>
            </fieldset>

            <hr>
            <h2 style="margin:0;font-size:1.05rem">If you used a VanAssist provider</h2>
            <?php if ($providers === []): ?>
                <p class="muted">No providers were matched to this request yet — choose one of the other options above.</p>
            <?php else: ?>
                <label>Which provider?
                    <select name="provider_id">
                        <option value="">— Select provider —</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= (int) $p['provider_id'] ?>"><?= $this->e((string) $p['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="btn-row">
                    <label><input type="checkbox" name="booked" value="1"> Work was booked</label>
                    <label><input type="checkbox" name="completed" value="1"> Work was completed</label>
                </div>
                <label>Type of work <input type="text" name="work_type" maxlength="190" placeholder="e.g. fridge repair"></label>
                <label>Approximate value (optional)
                    <select name="value_band">
                        <option value="">— Prefer not to say —</option>
                        <?php foreach ($bands as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Satisfaction
                    <select name="satisfaction_rating">
                        <option value="">— Not rated —</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> / 5</option><?php endfor; ?>
                    </select>
                </label>
                <div class="btn-row">
                    <label><input type="checkbox" name="issue_resolved" value="1"> The issue was resolved</label>
                    <label><input type="checkbox" name="would_use_again" value="1"> I would use them again</label>
                </div>

                <hr>
                <h3 style="margin:0;font-size:1rem">Leave a review (optional)</h3>
                <label>Rating
                    <select name="review_rating">
                        <option value="">— No review —</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> / 5</option><?php endfor; ?>
                    </select>
                </label>
                <label>Title <input type="text" name="review_title" maxlength="150"></label>
                <label>Review <textarea name="review_body" rows="3" maxlength="4000"></textarea></label>
                <p class="muted" style="font-size:.85rem">Reviews are checked before they appear publicly.</p>
            <?php endif; ?>

            <hr>
            <h2 style="margin:0;font-size:1.05rem">If you could not find someone suitable</h2>
            <label>Reason
                <select name="reason">
                    <?php foreach ($reasons as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Anything else? <textarea name="comment" rows="2" maxlength="500"></textarea></label>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Save outcome</button>
            </div>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
