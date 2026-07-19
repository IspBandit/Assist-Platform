<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $requests */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.public');
$statusLabels = ['open' => 'Open', 'reviewing' => 'Reviewing', 'arranged' => 'Arranged', 'declined' => 'Declined', 'completed' => 'Completed'];
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1>Request a service day</h1>
        <?php $this->include('partials.park-nav', ['active' => 'serviceday']); ?>

        <p class="muted">Ask the VanAssist team to arrange a provider to visit your park on a set day, so several guests can be serviced at once.</p>

        <div class="card stack">
            <form method="post" action="<?= e(url('park/service-day')) ?>" class="stack">
                <?= csrf_field() ?>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="preferred_dates">Preferred dates</label>
                        <input type="text" id="preferred_dates" name="preferred_dates" placeholder="e.g. mid-July, or 12–15 August">
                    </div>
                    <div class="form-group">
                        <label for="category_id">Service type</label>
                        <select id="category_id" name="category_id">
                            <option value="">Any / not sure</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= $this->e((string) $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Roughly how many guests are interested, any access notes, etc."></textarea>
                </div>
                <div class="btn-row"><button type="submit" class="btn btn-primary">Send request</button></div>
            </form>
        </div>

        <div class="card stack">
            <h2 style="margin-top:0">Your service-day requests</h2>
            <?php if ($requests === []): ?>
                <p class="muted">No requests yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Dates</th><th>Service</th><th>Status</th><th>Sent</th></tr></thead>
                        <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><?= $this->e((string) ($r['preferred_dates'] ?? '—')) ?></td>
                                <td><?= $this->e((string) ($r['category_name'] ?? 'Any')) ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e($statusLabels[$r['status']] ?? (string) $r['status']) ?></span></td>
                                <td><?= $this->e((string) ($r['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
