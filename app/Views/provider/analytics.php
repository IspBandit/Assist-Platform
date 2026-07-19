<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $provider */
/** @var bool $disabled */
/** @var string $range */
/** @var string $rangeLabel */
/** @var string $from */
/** @var string $to */
/** @var array<string,mixed>|null $summary */
$this->extend('layouts.public');

$pct = static function (?float $v): string {
    return $v === null ? '<span class="muted" title="Sample too small to report">—</span>' : e((string) $v) . '%';
};
$ranges = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'fy' => 'This financial year', 'pfy' => 'Previous financial year', 'custom' => 'Custom range'];
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Analytics</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'analytics']); ?>

        <?php if ($disabled): ?>
            <div class="card"><p class="mb-0">Analytics is not enabled yet. Once VanAssist turns on demand analytics, your search appearances, profile views, contacts and confirmed jobs will appear here.</p></div>
        <?php else: $s = $summary; ?>
            <form method="get" action="<?= e(url('provider/analytics')) ?>" class="card btn-row" style="align-items:flex-end;gap:1rem">
                <label>Period
                    <select name="range">
                        <?php foreach ($ranges as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $range === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>From <input type="date" name="from" value="<?= e_attr($from) ?>"></label>
                <label>To <input type="date" name="to" value="<?= e_attr($to) ?>"></label>
                <button type="submit" class="btn btn-secondary">Apply</button>
            </form>
            <p class="muted"><?= $this->e($rangeLabel) ?></p>

            <h2 style="margin-top:1.5rem">Visibility <span class="muted" style="font-size:.9rem">(estimated interest)</span></h2>
            <div class="grid grid-4">
                <?php
                $stat = static function (string $label, $value, string $hint = '') {
                    echo '<div class="card stack" style="gap:.25rem"><div style="font-size:1.6rem;font-weight:700">' . e((string) $value) . '</div>'
                        . '<div class="muted">' . e($label) . '</div>'
                        . ($hint !== '' ? '<div class="muted" style="font-size:.8rem">' . e($hint) . '</div>' : '') . '</div>';
                };
                $stat('Search appearances', $s['impressions']);
                $stat('Unique searches', $s['unique_impressions']);
                $stat('Profile views', $s['profile_views']);
                $stat('Unique profile views', $s['unique_profile_views']);
                $stat('Phone clicks', $s['clicks']['phone']);
                $stat('Email clicks', $s['clicks']['email']);
                $stat('Website clicks', $s['clicks']['website']);
                $stat('Directions clicks', $s['clicks']['directions']);
                ?>
            </div>

            <h2 style="margin-top:1.5rem">Jobs <span class="muted" style="font-size:.9rem">(confirmed where stated)</span></h2>
            <div class="grid grid-4">
                <?php
                $stat('Requests', $s['requests']);
                $stat('Responses', $s['responses']);
                $stat('Quotes', $s['quotes']);
                $stat('Selections', $s['selections']);
                $stat('Bookings', $s['bookings']);
                $stat('Completed (reported)', $s['completed']);
                $stat('Customer-confirmed jobs', $s['customer_confirmed']);
                $stat('Mutually confirmed jobs', $s['mutually_confirmed']);
                $stat('Provider-reported only', $s['provider_confirmed'], 'awaiting customer confirmation');
                $stat('Repeat customers', $s['repeat_customers']);
                $stat('Reviews', $s['reviews']);
                $stat('Average rating', $s['avg_rating'] > 0 ? $s['avg_rating'] . ' / 5' : '—');
                ?>
            </div>

            <h2 style="margin-top:1.5rem">Conversion</h2>
            <div class="grid grid-3">
                <div class="card stack" style="gap:.25rem"><div style="font-size:1.4rem;font-weight:700"><?= $pct($s['response_rate']) ?></div><div class="muted">Response rate</div></div>
                <div class="card stack" style="gap:.25rem"><div style="font-size:1.4rem;font-weight:700"><?= $pct($s['view_to_contact']) ?></div><div class="muted">Profile view → contact</div></div>
                <div class="card stack" style="gap:.25rem"><div style="font-size:1.4rem;font-weight:700"><?= $pct($s['contact_to_confirmed']) ?></div><div class="muted">Contact → confirmed use</div></div>
            </div>
            <p class="muted" style="font-size:.85rem">Contact clicks show interest, not completed jobs. "Confirmed" figures come only from outcomes you or the customer confirmed. Percentages are hidden where there are fewer than 5 in the denominator.</p>

            <div class="grid grid-2" style="margin-top:1rem">
                <div class="card">
                    <h3 style="margin-top:0">Top requested categories</h3>
                    <ul class="list-plain">
                        <?php foreach ($s['top_categories'] as $row): ?><li><?= $this->e((string) $row['name']) ?> <span class="muted">(<?= (int) $row['c'] ?>)</span></li><?php endforeach; ?>
                        <?php if ($s['top_categories'] === []): ?><li class="muted">No data yet.</li><?php endif; ?>
                    </ul>
                </div>
                <div class="card">
                    <h3 style="margin-top:0">Top service locations</h3>
                    <ul class="list-plain">
                        <?php foreach ($s['top_locations'] as $row): ?><li><?= $this->e((string) $row['name']) ?> <span class="muted">(<?= (int) $row['c'] ?>)</span></li><?php endforeach; ?>
                        <?php if ($s['top_locations'] === []): ?><li class="muted">No data yet.</li><?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
