<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-copy">
                <span class="hero-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v7h-7"/></svg>
                    Free partner profile &amp; QR referrals
                </span>
                <h1>Help your guests find the <span class="accent">right caravan service</span>, effortlessly.</h1>
                <p class="lead">Skip the outdated contact lists. Offer a simple guest referral form and a park-specific QR code, and see upcoming provider visits near you.</p>
                <div class="hero-cta">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('caravan-parks/apply')) ?>">Apply to partner</a>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('how-it-works')) ?>">How it works</a>
                </div>
                <ul class="hero-trust">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        Free during launch
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        No provider lock-in
                    </li>
                </ul>
            </div>

            <div class="hero-art">
                <img class="hero-photo" src="<?= e(asset('img/hero-parks.jpg')) ?>" width="1536" height="1024"
                     alt="Friendly caravan park host at reception holding a QR referral code, with caravans on grassy sites behind"
                     loading="eager" fetchpriority="high">
            </div>
        </div>
    </div>
    <div class="hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 42 C 240 84 480 4 720 26 C 960 48 1200 82 1440 40 L1440 80 L0 80 Z" fill="#fbf8f1"/></svg>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid grid-2">
            <div class="card">
                <h2>Partner benefits</h2>
                <ul class="feature-list">
                    <li>Free partner profile.</li>
                    <li>Guest referral forms and a park-specific QR code.</li>
                    <li>Notifications of upcoming provider visits nearby.</li>
                    <li>Organise a park service day when demand builds.</li>
                    <li>No requirement to recommend any specific provider.</li>
                </ul>
            </div>
            <div class="card">
                <h2>Become a partner</h2>
                <p>Apply in a couple of minutes. Set up your profile, print your QR code and start helping guests — free during launch.</p>
                <a class="btn btn-primary btn-lg" href="<?= e(url('caravan-parks/apply')) ?>">Apply to partner</a>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
