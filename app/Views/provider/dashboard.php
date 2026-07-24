<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $provider */
/** @var array<string,int> $counts */
/** @var array<string,bool> $checklist */
/** @var array<string,mixed>|null $foundingPromo */
/** @var array{slug:string,name:string,charging_enabled:bool,summary:string}|null $membershipState */
$this->extend('layouts.public');
$statusBadge = ['active' => 'badge-verified', 'pending' => 'badge-confirmed', 'suspended' => 'badge-neutral', 'rejected' => 'badge-neutral', 'draft' => 'badge-neutral'];
$done = $checklist ? count(array_filter($checklist)) : 0;
$totalChecks = $checklist ? count($checklist) : 0;
?>
<?php $this->section('content'); ?>
<section class="section provider-dashboard">
    <div class="container">
        <header class="provider-dashboard-head">
            <div><span class="eyebrow">Business command centre</span><h1>Provider dashboard</h1><p>Manage demand, reputation and coverage from one place.</p></div>
            <?php if ($provider !== null && $provider['status'] === 'active'): ?><a class="btn btn-secondary" href="<?= e(url('providers/' . $provider['slug'])) ?>" target="_blank" rel="noopener">View public profile <span aria-hidden="true">↗</span></a><?php endif; ?>
        </header>
        <?php $this->include('partials.provider-nav', ['active' => 'dashboard']); ?>

        <?php if ($provider === null): ?>
            <div class="card">
                <h2>No provider profile linked</h2>
                <p class="muted">Your account isn't linked to a provider profile yet. If you were invited to join, please use your invitation link, or contact the VanAssist team.</p>
                <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Contact us</a>
            </div>
        <?php else: ?>
            <div class="card provider-overview">
                <div class="provider-overview-head">
                    <div><span class="eyebrow">Your business</span><h2><?= $this->e((string) $provider['business_name']) ?></h2></div>
                    <span class="badge <?= $statusBadge[$provider['status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $provider['status'])) ?></span>
                </div>
                <?php if ($provider['status'] === 'pending'): ?>
                    <p class="muted">Your profile is awaiting review. Complete the checklist below to help us approve it faster.</p>
                <?php elseif ($provider['status'] === 'active'): ?>
                    <p class="muted">Your profile is live. <a href="<?= e(url('providers/' . $provider['slug'])) ?>" target="_blank">View public profile</a>.</p>
                <?php elseif ($provider['status'] === 'suspended'): ?>
                    <p class="muted">Your profile is currently suspended. Please contact the VanAssist team.</p>
                <?php endif; ?>
                <p class="provider-badges">
                    <?= $provider['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                    <?= $provider['insurance_verified'] ? '<span class="badge badge-verified">Insured</span> ' : '' ?>
                </p>
            </div>

            <?php if ($membershipState !== null): ?>
                <div class="card provider-membership">
                    <span class="badge badge-confirmed">Membership</span>
                    <h2 style="margin-bottom:.35rem"><?= $this->e($membershipState['name']) ?></h2>
                    <p class="muted" style="margin:0"><?= $this->e($membershipState['summary']) ?></p>
                    <?php if (!$membershipState['charging_enabled']): ?>
                        <p style="margin-bottom:0"><strong>Billing is not active.</strong> Paid membership selection and checkout remain unavailable.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($foundingPromo !== null): ?>
                <div class="card provider-promotion">
                    <h2 style="margin-top:0">Free local ad graphics</h2>
                    <?php if ($foundingPromo['status'] === 'eligible' && empty($foundingPromo['verified'])): ?>
                        <p class="muted" style="margin:0">Launch offer: free desktop + mobile ad graphics (worth $99) for travellers near you. <strong>Verify your profile</strong> to unlock the request form.</p>
                    <?php elseif (!empty($foundingPromo['can_request'])): ?>
                        <p class="muted" style="margin:0">Your verified founding-provider offer is ready — request your free local ad graphic.</p>
                        <a class="btn btn-primary" href="<?= e(url('provider/promotion')) ?>" style="margin-top:.75rem">Request free graphic</a>
                    <?php elseif (in_array($foundingPromo['status'], ['requested', 'in_progress'], true)): ?>
                        <p class="muted" style="margin:0">We are designing your free ad graphic. We will email you when it is ready.</p>
                    <?php elseif ($foundingPromo['status'] === 'delivered'): ?>
                        <p class="muted" style="margin:0">Your ad graphic is live and your listing is featured during launch.</p>
                        <a class="btn btn-secondary" href="<?= e(url('provider/promotion')) ?>" style="margin-top:.75rem">View graphic</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalChecks > 0 && $done < $totalChecks): ?>
                <div class="card provider-checklist">
                    <div class="provider-checklist-head"><h2>Profile readiness</h2><strong><?= $done ?>/<?= $totalChecks ?></strong></div>
                    <div class="provider-progress" aria-label="Profile <?= $totalChecks > 0 ? (int) round(($done / $totalChecks) * 100) : 0 ?>% complete"><span style="width:<?= $totalChecks > 0 ? (int) round(($done / $totalChecks) * 100) : 0 ?>%"></span></div>
                    <ul class="list-plain">
                        <?php foreach ($checklist as $label => $ok): ?>
                            <li><?= $ok ? '✓' : '○' ?> <?= $this->e($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="provider-kpi-grid">
                <a class="provider-kpi" href="<?= e(url('provider/requests')) ?>">
                    <span>Incoming demand</span><strong><?= (int) ($counts['open_requests'] ?? 0) ?></strong><small>Open matched requests</small>
                </a>
                <a class="provider-kpi" href="<?= e(url('provider/runs')) ?>">
                    <span>Service runs</span><strong><?= (int) ($counts['active_runs'] ?? 0) ?></strong><small>Active or forming</small>
                </a>
                <a class="provider-kpi provider-kpi--accent" href="<?= e(url('provider/analytics')) ?>">
                    <span>Market intelligence</span><strong aria-hidden="true">↗</strong><small>Explore demand by area</small>
                </a>
            </div>

            <div class="provider-section-head"><div><span class="eyebrow">Manage your presence</span><h2>Business essentials</h2></div></div>
            <div class="provider-action-grid">
                <a class="provider-action-card" href="<?= e(url('provider/profile')) ?>">
                    <span class="provider-action-index">01</span><h3>Business profile</h3><p>Edit business details and public contact options.</p><strong>Manage profile <span aria-hidden="true">→</span></strong>
                </a>
                <a class="provider-action-card" href="<?= e(url('provider/services')) ?>">
                    <span class="provider-action-index">02</span><h3>Services <span><?= $counts['services'] ?></span></h3><p>Define the work customers can find you for.</p><strong>Manage services <span aria-hidden="true">→</span></strong>
                </a>
                <a class="provider-action-card" href="<?= e(url('provider/areas')) ?>">
                    <span class="provider-action-index">03</span><h3>Service areas <span><?= $counts['areas'] ?></span></h3><p>Shape your geographic reach and mobile coverage.</p><strong>Manage coverage <span aria-hidden="true">→</span></strong>
                </a>
                <a class="provider-action-card" href="<?= e(url('provider/documents')) ?>">
                    <span class="provider-action-index">04</span><h3>Documents <span><?= $counts['documents'] ?></span></h3><p>Build trust with current insurance and evidence.</p><strong>Manage documents <span aria-hidden="true">→</span></strong>
                </a>
                <a class="provider-action-card" href="<?= e(url('provider/licences')) ?>">
                    <span class="provider-action-index">05</span><h3>Licences</h3><p>Record credentials and stay ahead of expiry dates.</p><strong>Manage licences <span aria-hidden="true">→</span></strong>
                </a>
                <a class="provider-action-card" href="<?= e(url('provider/availability')) ?>">
                    <span class="provider-action-index">06</span><h3>Availability</h3><p>Keep the platform aligned with when you can work.</p><strong>Manage availability <span aria-hidden="true">→</span></strong>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
