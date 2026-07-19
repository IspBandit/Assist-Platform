<?php
/** @var \App\Core\View $this */
/** @var array<string,string> $settings */
/** @var array<string,string> $launchModes */
/** @var string $launchMode */
/** @var bool $maintenance */
/** @var bool $analyticsOn */
/** @var array<string,int> $demoCounts */
/** @var array<int,array{label:string,ok:?bool,hint:string}> $checklist */
/** @var bool $isSuperAdmin */
$this->extend('layouts.admin');
$s = static fn (string $k, string $d = '') => $settings[$k] ?? $d;
$demoTotal = array_sum($demoCounts);
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0">Settings</h1>
</div>

<form method="post" action="<?= e(url('admin/settings')) ?>" class="stack">
    <?= csrf_field() ?>

    <div class="card">
        <h2 style="margin-top:0">General</h2>
        <div class="grid grid-2">
            <div class="form-group"><label for="site_name">Site name</label><input type="text" id="site_name" name="site_name" value="<?= e_attr($s('site_name', 'VanAssist')) ?>"></div>
            <div class="form-group"><label for="tagline">Tagline</label><input type="text" id="tagline" name="tagline" value="<?= e_attr($s('tagline')) ?>"></div>
        </div>
        <div class="form-group"><label for="free_launch_message">Free launch message</label><input type="text" id="free_launch_message" name="free_launch_message" value="<?= e_attr($s('free_launch_message')) ?>"></div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Contact &amp; business identity</h2>
        <div class="grid grid-2">
            <div class="form-group"><label for="contact_email">Public contact email</label><input type="email" id="contact_email" name="contact_email" value="<?= e_attr($s('contact_email')) ?>"></div>
            <div class="form-group"><label for="contact_phone">Public contact phone</label><input type="text" id="contact_phone" name="contact_phone" value="<?= e_attr($s('contact_phone')) ?>"></div>
            <div class="form-group"><label for="business_legal_name">Legal name</label><input type="text" id="business_legal_name" name="business_legal_name" value="<?= e_attr($s('business_legal_name')) ?>"></div>
            <div class="form-group"><label for="business_structure">Business structure</label><input type="text" id="business_structure" name="business_structure" value="<?= e_attr($s('business_structure')) ?>"></div>
            <div class="form-group"><label for="business_abn">ABN</label><input type="text" id="business_abn" name="business_abn" value="<?= e_attr($s('business_abn')) ?>"></div>
            <div class="form-group"><label for="facebook_url">Facebook URL</label><input type="text" id="facebook_url" name="facebook_url" value="<?= e_attr($s('facebook_url')) ?>"></div>
        </div>
        <div class="form-group"><label for="business_address">Business address</label><input type="text" id="business_address" name="business_address" value="<?= e_attr($s('business_address')) ?>"></div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Email sending (SMTP)</h2>
        <p class="muted" style="margin:.2rem 0 1rem;font-size:.85rem">Outgoing mail server used to send all VanAssist email. For GoDaddy/cPanel use the secure SSL/TLS settings from your email account. These values are stored in the database and override any values in <code>.env</code>.</p>
        <div class="grid grid-2">
            <div class="form-group"><label for="mail_host">SMTP host</label><input type="text" id="mail_host" name="mail_host" value="<?= e_attr($s('mail_host')) ?>" placeholder="e.g. sg2plzcpnl509286.prod.sin2.secureserver.net"></div>
            <div class="form-group"><label for="mail_username">Username</label><input type="text" id="mail_username" name="mail_username" value="<?= e_attr($s('mail_username')) ?>" placeholder="full email address" autocomplete="off"></div>
            <div class="form-group">
                <label for="mail_port">Port</label>
                <input type="number" id="mail_port" name="mail_port" value="<?= e_attr($s('mail_port', '465')) ?>" min="1" max="65535">
            </div>
            <div class="form-group">
                <label for="mail_encryption">Encryption</label>
                <?php $enc = $s('mail_encryption', 'ssl'); ?>
                <select id="mail_encryption" name="mail_encryption">
                    <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
                    <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
                    <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="mail_password">Password</label>
                <input type="password" id="mail_password" name="mail_password" value="" autocomplete="new-password" placeholder="<?= trim($s('mail_password')) !== '' ? 'Saved — leave blank to keep' : 'Not set' ?>">
                <small class="muted">Leave blank to keep the current password. <?= trim($s('mail_password')) !== '' ? 'A password is currently saved.' : 'No password saved yet.' ?></small>
            </div>
            <div class="form-group"><label for="mail_from_address">From address</label><input type="email" id="mail_from_address" name="mail_from_address" value="<?= e_attr($s('mail_from_address')) ?>" placeholder="vanassist@condrendigital.com.au"></div>
            <div class="form-group"><label for="mail_from_name">From name</label><input type="text" id="mail_from_name" name="mail_from_name" value="<?= e_attr($s('mail_from_name', 'VanAssist')) ?>"></div>
        </div>
        <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">After saving, send a test from <a href="<?= e(url('admin/email-templates')) ?>">Email templates</a>. Test emails send on the next email cron run.</p>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Launch &amp; availability</h2>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="launch_mode">Launch mode</label>
                <select id="launch_mode" name="launch_mode">
                    <?php foreach ($launchModes as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $launchMode === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <ul class="muted" style="margin:.2rem 0 1rem;font-size:.85rem;padding-left:1.1rem">
            <li><strong>Private</strong> — the public site is hidden behind a "Coming soon" page; only signed-in admins can see it. Not indexed by search engines.</li>
            <li><strong>Provider onboarding</strong> / <strong>Local pilot</strong> — the site is publicly visible but kept out of search engines (noindex) while you build up listings.</li>
            <li><strong>Public launch</strong> — fully live; search-engine indexing is enabled by default (you can still override it under <a href="<?= e(url('admin/seo')) ?>">SEO settings</a>).</li>
        </ul>
        <label><input type="checkbox" name="maintenance_mode" value="1" <?= $maintenance ? 'checked' : '' ?>> Maintenance mode (visitors see a holding page; admins still get in). Use this for short, deliberate downtime regardless of launch mode.</label>
        <div class="form-group"><label for="maintenance_message">Maintenance message</label><input type="text" id="maintenance_message" name="maintenance_message" value="<?= e_attr($s('maintenance_message')) ?>"></div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Analytics</h2>
        <label><input type="checkbox" name="analytics_enabled" value="1" <?= $analyticsOn ? 'checked' : '' ?>> Enable first-party page-view analytics</label>
        <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">Privacy-friendly and cookie-free: records the page path and a coarse referrer source only. No third-party scripts, so it never conflicts with the site security policy. View results under <a href="<?= e(url('admin/reports')) ?>">Reports</a>.</p>
    </div>

    <div class="btn-row"><button type="submit" class="btn btn-primary">Save settings</button></div>
</form>

<div class="card">
    <h2 style="margin-top:0">Production readiness</h2>
    <ul class="list-plain">
        <?php foreach ($checklist as $item): ?>
            <li style="padding:.4rem 0;border-bottom:1px solid #f0ede5">
                <?php if ($item['ok'] === true): ?>
                    <span style="color:#245b30;font-weight:700">&#10003;</span>
                <?php elseif ($item['ok'] === false): ?>
                    <span style="color:#8e271c;font-weight:700">&#10007;</span>
                <?php else: ?>
                    <span class="muted" style="font-weight:700">&bull;</span>
                <?php endif; ?>
                <strong><?= $this->e($item['label']) ?></strong>
                <span class="muted" style="font-size:.85rem"> — <?= $this->e($item['hint']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="card">
    <h2 style="margin-top:0">Demo data</h2>
    <?php if ($demoTotal > 0): ?>
        <p>There are <strong><?= (int) $demoTotal ?></strong> demonstration record(s):
            providers <?= (int) $demoCounts['providers'] ?>,
            runs <?= (int) $demoCounts['service_runs'] ?>,
            requests <?= (int) $demoCounts['service_requests'] ?>,
            parks <?= (int) $demoCounts['caravan_parks'] ?>.
        </p>
        <form method="post" action="<?= e(url('admin/settings/remove-demo')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">Remove all demo data</button>
        </form>
    <?php else: ?>
        <p class="muted">No demonstration records remain.</p>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
