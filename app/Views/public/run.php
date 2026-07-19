<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $run */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $services */
/** @var bool $alreadyJoined */
/** @var bool $isAuthed */
/** @var array<int,array<string,mixed>> $myRequests */
$this->extend('layouts.public');
$total = (int) $run['appointments_total'];
$count = (int) $run['bookings_count'];
$pct = $total > 0 ? min(100, (int) round($count / $total * 100)) : 0;
$open = in_array($run['status'], \App\Models\ServiceRun::PUBLIC_STATUSES, true);
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> /
            <a href="<?= e(url('service-runs')) ?>">Service runs</a> /
            <?= $this->e((string) $run['title']) ?>
        </nav>

        <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
            <div>
                <h1 style="margin-bottom:.25rem"><?= $this->e((string) $run['title']) ?></h1>
                <p>
                    <span class="badge badge-forming"><?= $this->e(\App\Services\RunWorkflow::label((string) $run['status'])) ?></span>
                    <?php if ($run['region_name']): ?><span class="badge badge-neutral"><?= $this->e((string) $run['region_name']) ?></span><?php endif; ?>
                </p>
                <p class="muted">Run by <a href="<?= e(url('providers/' . $run['provider_slug'])) ?>"><?= $this->e((string) $run['business_name']) ?></a></p>
            </div>
        </div>

        <div class="grid grid-2" style="margin-top:1rem;align-items:flex-start">
            <div class="stack">
                <?php if ($run['notes']): ?>
                    <div class="card stack"><?= nl2br($this->e((string) $run['notes'])) ?></div>
                <?php endif; ?>

                <div class="card stack">
                    <h2 style="margin-top:0">Details</h2>
                    <?php if ($run['start_date']): ?><p style="margin:0"><strong>Start date:</strong> <?= $this->e((string) $run['start_date']) ?></p><?php endif; ?>
                    <?php if ($run['end_date']): ?><p style="margin:0"><strong>End date:</strong> <?= $this->e((string) $run['end_date']) ?></p><?php endif; ?>
                    <?php if ($run['booking_deadline']): ?><p style="margin:0"><strong>Register by:</strong> <?= $this->e((string) $run['booking_deadline']) ?></p><?php endif; ?>
                    <?php if ($run['travel_fee_description']): ?><p style="margin:0"><strong>Travel:</strong> <?= $this->e((string) $run['travel_fee_description']) ?></p><?php endif; ?>
                </div>

                <?php if ($services !== []): ?>
                    <div class="card stack">
                        <h2 style="margin-top:0">Services on this run</h2>
                        <div class="btn-row">
                            <?php foreach ($services as $s): ?>
                                <a class="btn btn-ghost" href="<?= e(url('services/' . $s['slug'])) ?>"><?= $this->e((string) $s['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($towns !== []): ?>
                    <div class="card stack">
                        <h2 style="margin-top:0">Stops</h2>
                        <ul class="list-plain">
                            <?php foreach ($towns as $t): ?>
                                <li><strong><?= $this->e((string) $t['town_name']) ?></strong><?php if ($t['arrival_date']): ?> — <?= $this->e((string) $t['arrival_date']) ?><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Register your interest</h2>
                <?php if ($total > 0): ?>
                    <div style="background:#eceae3;border-radius:999px;height:10px;overflow:hidden">
                        <div style="background:var(--green,#0f6e6e);height:10px;width:<?= $pct ?>%"></div>
                    </div>
                    <p class="muted" style="margin:0"><?= $count ?> of <?= $total ?> places registered<?php if ($run['min_bookings']): ?> (needs <?= (int) $run['min_bookings'] ?> to go ahead)<?php endif; ?></p>
                <?php endif; ?>

                <?php if (!$open): ?>
                    <p class="muted">This run is <?= $this->e(strtolower(\App\Services\RunWorkflow::label((string) $run['status']))) ?> and not currently accepting registrations.</p>
                <?php elseif ($alreadyJoined): ?>
                    <p>You've registered your interest in this run. We'll be in touch as it's confirmed.</p>
                <?php else: ?>
                    <p class="muted">Let the provider know you'd join this run if it goes ahead. There's no charge to register.</p>
                    <form method="post" action="<?= e(url('service-runs/' . $run['slug'] . '/join')) ?>" class="stack">
                        <?= csrf_field() ?>
                        <?php if ($towns !== []): ?>
                            <div class="form-group mb-0">
                                <label for="town_id">Which stop suits you?</label>
                                <select id="town_id" name="town_id">
                                    <option value="">No preference</option>
                                    <?php foreach ($towns as $t): ?>
                                        <option value="<?= (int) $t['town_id'] ?>"><?= $this->e((string) $t['town_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if ($isAuthed && $myRequests !== []): ?>
                            <div class="form-group mb-0">
                                <label for="request_id">Link an existing request (optional)</label>
                                <select id="request_id" name="request_id">
                                    <option value="">None</option>
                                    <?php foreach ($myRequests as $r): ?>
                                        <option value="<?= (int) $r['id'] ?>"><?= $this->e((string) $r['reference']) ?> — <?= $this->e((string) $r['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group mb-0">
                            <label for="notes">Anything to add? (optional)</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="e.g. travelling with a 22ft van, flexible on dates"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Register my interest</button>
                        <?php if (!$isAuthed): ?><p class="muted" style="font-size:.85rem;margin:0">You'll be asked to sign in or create a free account.</p><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
