<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $match */
/** @var array<string,mixed> $request */
/** @var array<int,array<string,mixed>> $images */
/** @var bool $contactReleased */
$this->extend('layouts.public');
$r = $request;
$matchId = (int) $match['id'];
$labels = \App\Controllers\Admin\MatchingController::MATCH_LABELS;
$field = static function (string $label, $value) {
    if ($value === null || $value === '') {
        return;
    }
    echo '<li><strong>' . e($label) . ':</strong> ' . e((string) $value) . '</li>';
};
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <a class="muted" href="<?= e(url('provider/requests')) ?>">&laquo; Back to incoming requests</a>
        <div class="btn-row" style="justify-content:space-between;align-items:center;margin-top:.25rem">
            <h1 style="margin:0"><?= $this->e((string) $r['title']) ?></h1>
            <span class="badge badge-neutral"><?= $this->e($labels[$match['status']] ?? (string) $match['status']) ?></span>
        </div>
        <p class="muted">Reference <strong><?= $this->e((string) $r['reference']) ?></strong> · <?= $this->e(ucfirst((string) $r['urgency'])) ?></p>

        <div class="card stack">
            <h2 style="margin-top:0">Job details</h2>
            <?php if ($r['description']): ?><p><?= nl2br($this->e((string) $r['description'])) ?></p><?php endif; ?>
            <ul class="list-plain">
                <?php
                $field('Town', $r['town_name']);
                $field('Region', $r['region_name']);
                $field('Location', $r['location_label']);
                $field('Service', $r['category_name']);
                $field('Vehicle', trim((string) $r['vehicle_make'] . ' ' . (string) $r['vehicle_model']));
                $field('Vehicle type', $r['vehicle_type']);
                $field('Error code', $r['error_code']);
                $field('Travel deadline', $r['travel_deadline']);
                ?>
            </ul>
        </div>

        <?php if ($images !== []): ?>
            <div class="card">
                <h2 style="margin-top:0">Photos</h2>
                <div class="btn-row">
                    <?php foreach ($images as $img): ?>
                        <a href="<?= e(url('provider/requests/image?id=' . (int) $img['id'])) ?>" target="_blank">
                            <img src="<?= e(url('provider/requests/image?id=' . (int) $img['id'] . '&thumb=1')) ?>" alt="Request photo" style="width:130px;height:98px;object-fit:cover;border-radius:8px">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0">Customer contact</h2>
            <?php if ($contactReleased): ?>
                <ul class="list-plain">
                    <?php
                    $field('Name', $r['contact_name']);
                    $field('Email', $r['contact_email']);
                    $field('Phone', $r['contact_phone']);
                    $field('Preferred contact', ucfirst((string) $r['preferred_contact']));
                    ?>
                </ul>
            <?php else: ?>
                <p class="muted">Customer contact details are released once you've expressed interest and our team confirms the match.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Your response</h2>
            <form method="post" action="<?= e(url('provider/requests/' . $matchId . '/respond')) ?>" class="stack">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="provider_note">Note (optional, shared with our team)</label>
                    <textarea id="provider_note" name="provider_note" rows="2"><?= e((string) ($match['provider_note'] ?? '')) ?></textarea>
                </div>
                <div class="btn-row">
                    <button type="submit" name="action" value="interested" class="btn btn-primary">I'm interested</button>
                    <button type="submit" name="action" value="more_info" class="btn btn-secondary">Ask for more info</button>
                    <button type="submit" name="action" value="decline" class="btn btn-ghost">Decline</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Update job status</h2>
            <p class="muted" style="margin-top:0">This records the outcome for our analytics. Your update never overrides what the customer reports.</p>
            <form method="post" action="<?= e(url('provider/requests/' . $matchId . '/outcome')) ?>" class="stack">
                <?= csrf_field() ?>
                <label>Status
                    <select name="outcome_status">
                        <?php foreach ([
                            'contacted' => 'Contacted customer', 'responded' => 'Responded',
                            'quoted' => 'Quote issued', 'selected' => 'Customer accepted',
                            'booked' => 'Job booked', 'in_progress' => 'Job in progress',
                            'completed' => 'Job completed', 'cancelled' => 'Job cancelled',
                            'unable_to_assist' => 'Unable to assist', 'outside_area' => 'Outside service area',
                            'no_response' => 'No customer response',
                        ] as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Type of work (optional) <input type="text" name="work_type" maxlength="190"></label>
                <label>Reason (if cancelled / unable / outside area) <input type="text" name="reason" maxlength="255"></label>
                <div class="btn-row"><button type="submit" class="btn btn-secondary">Save job status</button></div>
            </form>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
