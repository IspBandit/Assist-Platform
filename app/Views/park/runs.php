<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $runs */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Service runs nearby</h1>
        <?php $this->include('partials.park-nav', ['active' => 'runs']); ?>

        <div class="card">
            <?php if ($runs === []): ?>
                <p class="muted">No service runs are forming near <?= $this->e((string) $park['name']) ?> right now. Encourage guests to <a href="<?= e(url('request-assistance?park=' . $park['slug'])) ?>">register a request</a> — demand helps runs form.</p>
            <?php else: ?>
                <div class="grid grid-2">
                    <?php foreach ($runs as $run): ?>
                        <?php
                        $total = (int) $run['appointments_total'];
                        $count = (int) $run['bookings_count'];
                        $pct = $total > 0 ? min(100, (int) round($count / $total * 100)) : 0;
                        ?>
                        <a class="card stack" href="<?= e(url('service-runs/' . $run['slug'])) ?>" style="text-decoration:none;color:inherit">
                            <div class="btn-row" style="justify-content:space-between;margin:0">
                                <strong><?= $this->e((string) $run['title']) ?></strong>
                                <span class="badge badge-forming"><?= $this->e(\App\Services\RunWorkflow::label((string) $run['status'])) ?></span>
                            </div>
                            <span class="muted"><?= $this->e((string) $run['business_name']) ?><?php if ($run['start_date']): ?> · from <?= $this->e((string) $run['start_date']) ?><?php endif; ?></span>
                            <?php if ($total > 0): ?>
                                <div style="background:#eceae3;border-radius:999px;height:8px;overflow:hidden">
                                    <div style="background:var(--green,#0f6e6e);height:8px;width:<?= $pct ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
