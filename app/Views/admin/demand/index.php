<?php
/** @var \App\Core\View $this */
/** @var \App\Platform\Brand\Brand $brand */
/** @var array<string,mixed> $insights */
$this->extend('layouts.admin');
$summary = $insights['summary'];
$qs = http_build_query(['range' => $range, 'from' => $from, 'to' => $to]);
$humanise = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
?>
<?php $this->section('content'); ?>

<div class="admin-page-intro">
    <div>
        <p class="eyebrow"><?= $this->e($brand->name()) ?> website</p>
        <h1>Website insights</h1>
        <p class="muted">See what visitors looked for, which providers attracted attention and where people tried to make contact. Anonymous visitors remain anonymous.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('admin/demand/export?type=overview&' . $qs)) ?>">Export summary</a>
</div>

<?php $this->include('partials.demand-range', ['action' => url('admin/demand'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>

<?php if (!$pageTrackingOn || !$demandTrackingOn): ?>
    <div class="alert alert-warning website-insights-status">
        <strong>Some insights are not being recorded.</strong>
        <span>Page visits: <?= $pageTrackingOn ? 'on' : 'off' ?> · Searches and provider actions: <?= $demandTrackingOn ? 'on' : 'off' ?>.
            <?php if (!$pageTrackingOn && can('settings.manage')): ?><a href="<?= e(url('admin/settings')) ?>">Review page-view setting</a>.<?php endif; ?>
            <?php if (!$demandTrackingOn && can('feature_flags.manage')): ?><a href="<?= e(url('admin/feature-flags')) ?>">Review demand tracking flag</a>.<?php endif; ?></span>
    </div>
<?php endif; ?>

<div class="insight-stat-grid" aria-label="Website performance summary">
    <?php foreach ([
        ['Visitors', $summary['visitors'], 'Anonymous first-party sessions'],
        ['Page views', $summary['page_views'], $summary['pages_per_visitor'] !== null ? $summary['pages_per_visitor'] . ' pages per visitor' : 'No visitor baseline yet'],
        ['Provider searches', $summary['searches'], $summary['no_results'] . ' returned no results'],
        ['Provider profiles opened', $summary['profile_views'], 'Deliberate provider interest'],
        ['Contact actions', $summary['contact_actions'], 'Calls, email, websites and directions'],
        ['Confirmed provider uses', $summary['confirmed_uses'], 'Customer or stronger confirmation'],
    ] as [$metricLabel, $value, $hint]): ?>
        <article class="insight-stat">
            <strong><?= number_format((int) $value) ?></strong>
            <span><?= $this->e($metricLabel) ?></span>
            <small><?= $this->e($hint) ?></small>
        </article>
    <?php endforeach; ?>
</div>

<div class="insight-definition-note">
    <strong>Who visited?</strong>
    <span><?= number_format((int) $summary['visitors']) ?> anonymous visitor sessions and <?= number_format((int) $summary['signed_in_visitors']) ?> signed-in visitors were recorded. The platform does not store visitor IP addresses or attempt to identify anonymous people.</span>
</div>

<section class="card insight-decision-summary" aria-labelledby="insight-decision-heading">
    <div><p class="eyebrow">What the numbers say</p><h2 id="insight-decision-heading">Demand and directory usefulness</h2></div>
    <dl>
        <div><dt>Search success</dt><dd><?= $summary['search_success_rate'] === null ? 'Not enough data' : $this->e((string) $summary['search_success_rate']) . '%' ?><small><?= number_format((int) $summary['successful_searches']) ?> searches returned at least one result</small></dd></div>
        <div><dt>Search to contact</dt><dd><?= $summary['search_to_contact'] === null ? 'Not enough data' : $this->e((string) $summary['search_to_contact']) . '%' ?><small>Interest signal only—not a completed job</small></dd></div>
        <div><dt>Latest page visit</dt><dd><?= $this->e((string) ($summary['last_page_view_at'] ?? 'None recorded')) ?><small>Confirms page tracking is alive</small></dd></div>
        <div><dt>Latest provider action</dt><dd><?= $this->e((string) ($summary['last_demand_event_at'] ?? 'None recorded')) ?><small>Profile, contact or funnel event</small></dd></div>
    </dl>
</section>

<details class="card insight-panel insight-panel--scroll" open data-mobile-collapse>
    <summary><h2>Daily website pulse</h2><span>Visits over time</span></summary>
    <div class="insight-panel-body">
        <p class="muted">Daily page views and different anonymous visitor sessions. Use the pattern—not a single day—to judge whether outreach is increasing use.</p>
        <div class="table-wrap"><table class="data data--compact"><thead><tr><th>Date</th><th>Page views</th><th>Visitors</th></tr></thead><tbody>
        <?php foreach (array_slice($insights['daily'], -31) as $row): ?><tr><td><?= $this->e((string) $row['label']) ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['secondary']) ?></td></tr><?php endforeach; ?>
        <?php if ($insights['daily'] === []): ?><tr><td colspan="3" class="muted">No daily visits recorded for this period.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
</details>

<div class="insight-grid insight-grid--two">
    <details class="card insight-panel" open data-mobile-collapse>
        <summary><h2>Services people wanted</h2><span>Report details</span></summary>
        <div class="insight-panel-body">
        <p class="muted">Searches show intent; a search is not a completed job.</p>
        <div class="table-wrap"><table class="data">
            <thead><tr><th>Service</th><th>Searches</th><th>No results</th></tr></thead>
            <tbody>
            <?php foreach ($insights['services'] as $row): ?><tr><td><?= $this->e((string) $row['label']) ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['secondary']) ?></td></tr><?php endforeach; ?>
            <?php if ($insights['services'] === []): ?><tr><td colspan="3" class="muted">No service searches recorded for this period.</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
    </details>

    <details class="card insight-panel" open data-mobile-collapse>
        <summary><h2>What visitors clicked</h2><span>Report details</span></summary>
        <div class="insight-panel-body">
        <p class="muted">Contact actions are interest signals, not proof that work occurred.</p>
        <div class="table-wrap"><table class="data">
            <thead><tr><th>Action</th><th>Clicks</th><th>Visitors</th></tr></thead>
            <tbody>
            <?php foreach ($insights['actions'] as $row): ?><tr><td><?= $this->e($humanise((string) $row['label'])) ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['secondary']) ?></td></tr><?php endforeach; ?>
            <?php if ($insights['actions'] === []): ?><tr><td colspan="3" class="muted">No provider contact actions recorded.</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
    </details>
</div>

<details class="card insight-panel insight-panel--scroll" open data-mobile-collapse>
    <summary><h2>Providers attracting interest</h2><span>Report details</span></summary>
    <div class="insight-panel-body">
    <div class="admin-section-heading">
        <div><p class="muted">Impressions show appearance in results; profile views and contacts show progressively stronger interest.</p></div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/providers?' . $qs)) ?>">Detailed provider report</a>
    </div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>Provider</th><th>Result appearances</th><th>Profile views</th><th>Contact actions</th></tr></thead>
        <tbody>
        <?php foreach ($insights['providers'] as $row): ?>
            <tr><td><a href="<?= e(url('admin/providers/show?id=' . (int) $row['provider_id'])) ?>"><?= $this->e((string) $row['label']) ?></a></td><td><?= number_format((int) $row['impressions']) ?></td><td><?= number_format((int) $row['profile_views']) ?></td><td><strong><?= number_format((int) $row['contacts']) ?></strong></td></tr>
        <?php endforeach; ?>
        <?php if ($insights['providers'] === []): ?><tr><td colspan="4" class="muted">No provider interest recorded for this period.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</details>

<div class="insight-grid insight-grid--three">
    <?php foreach ([
        ['Most viewed pages', $insights['pages'], 'Page people opened', 'Times viewed', 'Different visitors'],
        ['Visitor sources', $insights['sources'], 'Source', 'Visitors', 'Views'],
        ['Devices', $insights['devices'], 'Device', 'Visitors', 'Views'],
    ] as [$heading, $rows, $first, $second, $third]): ?>
        <details class="card insight-panel<?= $heading === 'Most viewed pages' ? ' insight-panel--scroll' : '' ?>" open data-mobile-collapse>
            <summary><h2><?= $this->e($heading) ?></h2><span>Report details</span></summary>
            <div class="insight-panel-body">
            <?php if ($heading === 'Most viewed pages'): ?><p class="muted">Plain-English page names are shown first; the smaller path underneath is the website address used by the system.</p><?php endif; ?>
            <div class="table-wrap"><table class="data data--compact">
                <thead><tr><th><?= $this->e($first) ?></th><th><?= $this->e($second) ?></th><th><?= $this->e($third) ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?><tr><td><strong><?= $this->e((string) $row['label']) ?></strong><?php if ($heading === 'Most viewed pages' && isset($row['route'])): ?><small><?= $this->e((string) $row['route']) ?></small><?php endif; ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['secondary']) ?></td></tr><?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="3" class="muted">No data yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div></div>
        </details>
    <?php endforeach; ?>
</div>

<details class="card insight-panel insight-panel--scroll" open data-mobile-collapse>
    <summary><h2>Where demand came from</h2><span>Report details</span></summary>
    <div class="insight-panel-body">
    <div class="table-wrap"><table class="data">
        <thead><tr><th>Town, suburb or postcode</th><th>Searches</th><th>No results</th></tr></thead>
        <tbody>
        <?php foreach ($insights['locations'] as $row): ?><tr><td><?= $this->e((string) $row['label']) ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['secondary']) ?></td></tr><?php endforeach; ?>
        <?php if ($insights['locations'] === []): ?><tr><td colspan="3" class="muted">No location demand recorded.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</details>

<details class="card insight-panel insight-panel--scroll" open data-mobile-collapse>
    <summary><h2>Coverage gaps needing attention</h2><span>Report details</span></summary>
    <div class="insight-panel-body">
    <p class="muted">Real searches that returned no providers. Repeated gaps are the best places to verify and recruit suitable providers next.</p>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>Location</th><th>Service wanted</th><th>Empty searches</th><th>Most recent</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($insights['coverage_gaps'] as $row): ?>
            <?php $location = trim((string) $row['location_name'] . (!empty($row['state_abbr']) ? ', ' . $row['state_abbr'] : '')); ?>
            <tr>
                <td><strong><?= $this->e($location) ?></strong></td>
                <td><?= $this->e((string) $row['service_name']) ?></td>
                <td><?= number_format((int) $row['searches']) ?></td>
                <td><?= $this->e((string) $row['last_searched']) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/providers?' . http_build_query(['town' => $row['location_name'], 'category' => $row['category_id']]))) ?>">Inspect providers</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($insights['coverage_gaps'] === []): ?><tr><td colspan="5" class="muted">No zero-result searches were recorded in this period.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</details>

<nav class="insight-report-links" aria-label="Related analytics reports">
    <a href="<?= e(url('admin/demand/funnel?' . $qs)) ?>">Conversion funnel</a>
</nav>

<?php $this->endSection(); ?>
