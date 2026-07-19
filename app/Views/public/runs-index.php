<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $runs */
/** @var array<int,array<string,mixed>> $regions */
/** @var array<int,array<string,mixed>> $categories */
/** @var int|null $regionId */
/** @var int|null $categoryId */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Service runs forming</h1>
        <p class="muted">When enough travellers in an area register interest, a provider commits to a "run" — visiting the area on set dates. Find one near you and register your interest to help it go ahead.</p>

        <form method="get" action="<?= e(url('service-runs')) ?>" class="grid grid-3" style="margin:1.5rem 0;align-items:flex-end">
            <div class="form-group mb-0">
                <label for="region">Region</label>
                <select id="region" name="region">
                    <option value="">All regions</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $regionId === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="category">Service</label>
                <select id="category" name="category">
                    <option value="">All services</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0"><button type="submit" class="btn btn-primary">Filter</button></div>
        </form>

        <?php if ($runs === []): ?>
            <div class="card text-center">
                <p class="muted">No runs are forming in that area yet. <a href="<?= e(url('request-assistance')) ?>">Register a request</a> — when there's enough demand, a run can form.</p>
            </div>
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
                            <h3 style="margin:0"><?= $this->e((string) $run['title']) ?></h3>
                            <span class="badge badge-forming"><?= $this->e(\App\Services\RunWorkflow::label((string) $run['status'])) ?></span>
                        </div>
                        <p class="muted" style="margin:0">
                            <?= $this->e((string) $run['business_name']) ?><?php if ($run['region_name']): ?> · <?= $this->e((string) $run['region_name']) ?><?php endif; ?>
                        </p>
                        <?php if ($run['start_date']): ?><p style="margin:0"><strong>From:</strong> <?= $this->e((string) $run['start_date']) ?></p><?php endif; ?>
                        <?php if ($total > 0): ?>
                            <div style="background:#eceae3;border-radius:999px;height:8px;overflow:hidden">
                                <div style="background:var(--green,#0f6e6e);height:8px;width:<?= $pct ?>%"></div>
                            </div>
                            <span class="muted" style="font-size:.85rem"><?= $count ?> of <?= $total ?> places registered</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
