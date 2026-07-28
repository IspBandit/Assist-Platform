<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $provider */
/** @var array<string,int> $counts */
/** @var array<string,bool> $checklist */
/** @var array<int,array<string,mixed>> $recentRequests */
/** @var array<string,mixed>|null $foundingPromo */
/** @var array{slug:string,name:string,charging_enabled:bool,summary:string}|null $membershipState */
$this->extend('layouts.public');
$statusBadge = ['active' => 'badge-verified', 'pending' => 'badge-confirmed', 'suspended' => 'badge-neutral', 'rejected' => 'badge-neutral', 'draft' => 'badge-neutral'];
$done = $checklist ? count(array_filter($checklist)) : 0;
$totalChecks = $checklist ? count($checklist) : 0;
$profilePercent = $totalChecks > 0 ? (int) round(($done / $totalChecks) * 100) : 0;
$incompleteItems = array_keys(array_filter($checklist, static fn (bool $complete): bool => !$complete));
$nextAction = null;
if (($counts['open_requests'] ?? 0) > 0) {
    $nextAction = ['Review incoming requests', 'Customers are waiting for a provider response.', 'provider/requests'];
} elseif ($incompleteItems !== []) {
    $nextAction = [$incompleteItems[0], 'Complete the next profile step to improve customer confidence.', match ($incompleteItems[0]) {
        'List at least one service' => 'provider/services',
        'Define a service area' => 'provider/areas',
        'Upload a verification document' => 'provider/documents',
        default => 'provider/profile',
    }];
} elseif (($counts['expiring_licences'] ?? 0) > 0) {
    $nextAction = ['Review expiring licences', 'At least one recorded licence expires within 60 days.', 'provider/licences'];
} else {
    $nextAction = ['Review market activity', 'See where customers are searching and how they interact with your profile.', 'provider/analytics'];
}
?>
<?php $this->section('content'); ?>
<section class="provider-workspace">
    <div class="container">
        <header class="provider-workspace-header">
            <div><p class="experience-kicker dark">Business workspace</p><h1>Good work starts with a clear next step.</h1><p>Review customer demand, keep your public presence credible and decide where to focus today.</p></div>
            <?php if ($provider !== null && $provider['status'] === 'active'): ?><a class="btn btn-secondary" href="<?= e(url('providers/' . $provider['slug'])) ?>" target="_blank" rel="noopener">Preview public profile <span aria-hidden="true">↗</span></a><?php endif; ?>
        </header>
        <?php $this->include('partials.provider-nav', ['active' => 'dashboard']); ?>

        <?php if ($provider === null): ?>
            <div class="workspace-empty"><p class="experience-kicker dark">Account setup</p><h2>No provider profile is linked yet.</h2><p>If you were invited, return to your secure invitation link. Otherwise contact the team so the business record can be linked safely.</p><a class="btn btn-primary" href="<?= e(url('contact')) ?>">Contact support</a></div>
        <?php else: ?>
            <section class="business-status-bar" aria-label="Business status">
                <div><span>Your business</span><strong><?= $this->e((string) $provider['business_name']) ?></strong></div>
                <div class="business-status-meta"><span class="badge <?= $statusBadge[$provider['status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $provider['status'])) ?></span><?php if (!empty($provider['is_verified'])): ?><span class="badge badge-verified">Verified</span><?php endif; ?><?php if (!empty($provider['insurance_verified'])): ?><span class="badge badge-verified">Insurance reviewed</span><?php endif; ?></div>
            </section>

            <div class="workspace-priority-grid">
                <section class="next-action-panel" aria-labelledby="next-action-heading">
                    <div class="next-action-label"><span>Priority</span><span>Today</span></div>
                    <h2 id="next-action-heading"><?= $this->e($nextAction[0]) ?></h2>
                    <p><?= $this->e($nextAction[1]) ?></p>
                    <a class="btn btn-light btn-lg" href="<?= e(url($nextAction[2])) ?>">Take the next step <span aria-hidden="true">→</span></a>
                </section>
                <section class="profile-health-panel" aria-labelledby="profile-health-heading">
                    <div class="profile-health-score"><span><?= $profilePercent ?>%</span><small>complete</small></div>
                    <div><p class="experience-kicker dark">Public profile readiness</p><h2 id="profile-health-heading"><?= $done === $totalChecks ? 'Core profile complete' : ($totalChecks - $done) . ' step' . (($totalChecks - $done) === 1 ? '' : 's') . ' remaining' ?></h2><p><?= $done === $totalChecks ? 'Review it regularly so customers see current information.' : 'Complete the essentials customers use to assess fit and credibility.' ?></p><a class="text-link" href="<?= e(url('provider/profile')) ?>">Review business profile <span aria-hidden="true">→</span></a></div>
                </section>
            </div>

            <section class="workspace-section demand-workspace" aria-labelledby="incoming-demand-heading">
                <header class="workspace-section-heading"><div><p class="experience-kicker dark">Incoming demand</p><h2 id="incoming-demand-heading">Requests that may need your attention.</h2></div><a class="text-link" href="<?= e(url('provider/requests')) ?>">View all requests <span aria-hidden="true">→</span></a></header>
                <?php if ($recentRequests === []): ?>
                    <div class="demand-empty"><strong>No open matched requests right now.</strong><p>Your availability, services and coverage settings help determine which future requests are relevant.</p><div><a href="<?= e(url('provider/availability')) ?>">Check availability</a><a href="<?= e(url('provider/areas')) ?>">Review coverage</a><a href="<?= e(url('provider/services')) ?>">Review services</a></div></div>
                <?php else: ?>
                    <div class="demand-list">
                        <?php foreach ($recentRequests as $item): ?>
                            <?php $location = trim((string) ($item['town_name'] ?? '') . (!empty($item['state_abbr']) ? ', ' . $item['state_abbr'] : '')); if ($location === '') { $location = (string) ($item['location_label'] ?? 'Location supplied in request'); } ?>
                            <a href="<?= e(url('provider/requests/' . (int) $item['match_id'])) ?>">
                                <span class="demand-urgency demand-urgency-<?= e_attr((string) $item['urgency']) ?>"><?= $this->e(ucfirst((string) $item['urgency'])) ?></span>
                                <div><strong><?= $this->e((string) $item['title']) ?></strong><span><?= $this->e((string) ($item['category_name'] ?? 'Service request')) ?> · <?= $this->e($location) ?></span></div>
                                <span class="demand-status"><?= $this->e(ucwords(str_replace('_', ' ', (string) $item['match_status']))) ?></span><i aria-hidden="true">→</i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="workspace-section" aria-labelledby="performance-heading">
                <header class="workspace-section-heading"><div><p class="experience-kicker dark">Last 30 days</p><h2 id="performance-heading">Marketplace activity, without inflated claims.</h2></div><a class="text-link" href="<?= e(url('provider/analytics')) ?>">Open analytics <span aria-hidden="true">→</span></a></header>
                <div class="performance-ledger">
                    <div><span>Profile views</span><strong><?= number_format((int) ($counts['profile_views_30d'] ?? 0)) ?></strong><small>Recorded profile visits</small></div>
                    <div><span>Contact actions</span><strong><?= number_format((int) ($counts['contact_actions_30d'] ?? 0)) ?></strong><small>Phone, email, web or directions actions</small></div>
                    <div><span>Open requests</span><strong><?= number_format((int) ($counts['open_requests'] ?? 0)) ?></strong><small>Matched requests still active</small></div>
                    <div><span>Service runs</span><strong><?= number_format((int) ($counts['active_runs'] ?? 0)) ?></strong><small>Forming or confirmed</small></div>
                </div>
            </section>

            <section class="workspace-section" aria-labelledby="business-controls-heading">
                <header class="workspace-section-heading"><div><p class="experience-kicker dark">Business controls</p><h2 id="business-controls-heading">Keep the public promise accurate.</h2></div></header>
                <div class="business-control-list">
                    <a href="<?= e(url('provider/services')) ?>"><span>Services</span><strong><?= (int) $counts['services'] ?></strong><small>What customers can find you for</small><i>→</i></a>
                    <a href="<?= e(url('provider/areas')) ?>"><span>Coverage</span><strong><?= (int) $counts['areas'] ?></strong><small>Where and how you operate</small><i>→</i></a>
                    <a href="<?= e(url('provider/documents')) ?>"><span>Documents</span><strong><?= (int) $counts['documents'] ?></strong><small><?= (int) ($counts['pending_documents'] ?? 0) ?> awaiting review</small><i>→</i></a>
                    <a href="<?= e(url('provider/licences')) ?>"><span>Licences</span><strong><?= (int) ($counts['expiring_licences'] ?? 0) ?></strong><small>Expiring within 60 days</small><i>→</i></a>
                    <a href="<?= e(url('provider/availability')) ?>"><span>Availability</span><strong aria-hidden="true">—</strong><small>Set dates and service capacity</small><i>→</i></a>
                    <a href="<?= e(url('provider/runs')) ?>"><span>Service runs</span><strong><?= (int) $counts['active_runs'] ?></strong><small>Plan regional work</small><i>→</i></a>
                </div>
            </section>

            <?php if ($membershipState !== null || $foundingPromo !== null): ?>
                <section class="workspace-notices" aria-label="Membership and promotion information">
                    <?php if ($membershipState !== null): ?><article><p class="experience-kicker dark">Membership</p><h2><?= $this->e($membershipState['name']) ?></h2><p><?= $this->e($membershipState['summary']) ?></p><?php if (!$membershipState['charging_enabled']): ?><strong>Billing is not active.</strong><?php endif; ?></article><?php endif; ?>
                    <?php if ($foundingPromo !== null): ?><article><p class="experience-kicker dark">Launch programme</p><h2>Provider promotion</h2><?php if (!empty($foundingPromo['can_request'])): ?><p>Your verified founding-provider offer is ready to request.</p><a class="text-link" href="<?= e(url('provider/promotion')) ?>">Review the offer <span aria-hidden="true">→</span></a><?php else: ?><p>Promotion status: <?= $this->e(ucwords(str_replace('_', ' ', (string) $foundingPromo['status']))) ?>.</p><?php endif; ?></article><?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
