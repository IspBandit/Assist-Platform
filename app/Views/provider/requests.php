<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $matches */
$this->extend('layouts.public');
$labels = \App\Controllers\Admin\MatchingController::MATCH_LABELS;
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Incoming requests</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'requests']); ?>

        <div class="card">
            <?php if ($matches === []): ?>
                <p class="muted">You have no matched requests right now. When our team matches you to a customer request, it'll appear here.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Reference</th><th>Summary</th><th>Town</th><th>Service</th><th>Urgency</th><th>Your status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($matches as $m): ?>
                            <tr>
                                <td><strong><?= $this->e((string) $m['reference']) ?></strong></td>
                                <td><?= $this->e((string) $m['title']) ?></td>
                                <td><?= $this->e((string) ($m['town_name'] ?? '—')) ?></td>
                                <td><?= $this->e((string) ($m['category_name'] ?? '—')) ?></td>
                                <td><?= $this->e(ucfirst((string) $m['urgency'])) ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e($labels[$m['match_status']] ?? (string) $m['match_status']) ?></span></td>
                                <td><a class="btn btn-primary" href="<?= e(url('provider/requests/' . (int) $m['match_id'])) ?>">View</a></td>
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
