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
<section class="section">
    <div class="container">
        <h1>Provider dashboard</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'dashboard']); ?>

        <?php if ($provider === null): ?>
            <div class="card">
                <h2>No provider profile linked</h2>
                <p class="muted">Your account isn't linked to a provider profile yet. If you were invited to join, please use your invitation link, or contact the VanAssist team.</p>
                <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Contact us</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="btn-row" style="justify-content:space-between;align-items:center">
                    <h2 style="margin:0"><?= $this->e((string) $provider['business_name']) ?></h2>
                    <span class="badge <?= $statusBadge[$provider['status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $provider['status'])) ?></span>
                </div>
                <?php if ($provider['status'] === 'pending'): ?>
                    <p class="muted">Your profile is awaiting review. Complete the checklist below to help us approve it faster.</p>
                <?php elseif ($provider['status'] === 'active'): ?>
                    <p class="muted">Your profile is live. <a href="<?= e(url('providers/' . $provider['slug'])) ?>" target="_blank">View public profile</a>.</p>
                <?php elseif ($provider['status'] === 'suspended'): ?>
                    <p class="muted">Your profile is currently suspended. Please contact the VanAssist team.</p>
                <?php endif; ?>
                <p>
                    <?= $provider['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                    <?= $provider['insurance_verified'] ? '<span class="badge badge-verified">Insured</span> ' : '' ?>
                </p>
            </div>

            <?php if ($membershipState !== null): ?>
                <div class="card" style="margin-top:1rem">
                    <span class="badge badge-confirmed">Membership</span>
                    <h2 style="margin-bottom:.35rem"><?= $this->e($membershipState['name']) ?></h2>
                    <p class="muted" style="margin:0"><?= $this->e($membershipState['summary']) ?></p>
                    <?php if (!$membershipState['charging_enabled']): ?>
                        <p style="margin-bottom:0"><strong>Billing is not active.</strong> Paid membership selection and checkout remain unavailable.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($foundingPromo !== null): ?>
                <div class="card" style="margin-top:1rem;border-left:4px solid #0f6e6e">
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
                <div class="card">
                    <h2>Profile checklist <span class="muted" style="font-size:1rem">(<?= $done ?>/<?= $totalChecks ?>)</span></h2>
                    <ul class="list-plain">
                        <?php foreach ($checklist as $label => $ok): ?>
                            <li><?= $ok ? '✓' : '○' ?> <?= $this->e($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grid grid-3" style="margin-bottom:1rem">
                <a class="card stack" href="<?= e(url('provider/requests')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Incoming requests</h3>
                    <p style="margin:0;font-size:1.5rem"><strong><?= (int) ($counts['open_requests'] ?? 0) ?></strong> open</p>
                    <p class="muted" style="margin:0">Matched customer requests awaiting your response.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/runs')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Service runs</h3>
                    <p style="margin:0;font-size:1.5rem"><strong><?= (int) ($counts['active_runs'] ?? 0) ?></strong> active</p>
                    <p class="muted" style="margin:0">Forming or confirmed runs you are planning.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/analytics')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Demand in your areas</h3>
                    <p class="muted" style="margin:0">See where travellers are searching and registering requests.</p>
                </a>
            </div>

            <div class="grid grid-3">
                <a class="card stack" href="<?= e(url('provider/profile')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Business profile</h3>
                    <p class="muted" style="margin:0">Edit your business details and public contact options.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/services')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Services <span class="muted">(<?= $counts['services'] ?>)</span></h3>
                    <p class="muted" style="margin:0">Choose the services you offer.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/areas')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Service areas <span class="muted">(<?= $counts['areas'] ?>)</span></h3>
                    <p class="muted" style="margin:0">Tell us where you travel and work.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/documents')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Documents <span class="muted">(<?= $counts['documents'] ?>)</span></h3>
                    <p class="muted" style="margin:0">Upload insurance and licences for verification.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/licences')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Licences</h3>
                    <p class="muted" style="margin:0">Record your credentials and expiry dates.</p>
                </a>
                <a class="card stack" href="<?= e(url('provider/availability')) ?>" style="text-decoration:none;color:inherit">
                    <h3 style="margin:0">Availability</h3>
                    <p class="muted" style="margin:0">Set the dates you can take work.</p>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
