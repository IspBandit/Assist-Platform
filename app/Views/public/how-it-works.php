<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="hero hero--center">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-copy">
                <span class="hero-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5 3 7v12l6-2 6 2 6-2V5l-6 2-6-2Z"/><path d="M9 5v12M15 7v12"/></svg>
                    Simple, free during launch, no obligation
                </span>
                <h1>How VanAssist <span class="accent">works</span></h1>
                <p class="lead">One place to connect caravan and RV owners, mobile providers, and parks across regional Australia — by grouping real demand, town by town.</p>
                <div class="hero-cta">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('for-providers')) ?>">Join as a provider</a>
                </div>
            </div>
        </div>
    </div>
    <div class="hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 42 C 240 84 480 4 720 26 C 960 48 1200 82 1440 40 L1440 80 L0 80 Z" fill="#fbf8f1"/></svg>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid grid-3">
            <div class="card audience-card">
                <span class="audience-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 16V9a3 3 0 0 1 3-3h10a4 4 0 0 1 4 4v6h-2"/><path d="M3 16h7"/><circle cx="13" cy="16" r="2.5"/><path d="M7 9h4v4H7z"/></svg>
                </span>
                <h2>For caravan owners</h2>
                <ol class="steps-list">
                    <li>Tell us your town and what you need.</li>
                    <li>We match you with providers covering your area or planning a visit.</li>
                    <li>If none are available, your request adds to local demand and we notify relevant providers.</li>
                </ol>
            </div>
            <div class="card audience-card">
                <span class="audience-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2-2 2.5-2.5Z"/></svg>
                </span>
                <h2>For providers</h2>
                <ol class="steps-list">
                    <li>See where demand is building across regional towns.</li>
                    <li>Plan proposed or confirmed service runs.</li>
                    <li>Choose which requests and runs you take on — no obligation.</li>
                </ol>
            </div>
            <div class="card audience-card">
                <span class="audience-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
                </span>
                <h2>For caravan parks</h2>
                <ol class="steps-list">
                    <li>Refer guests with a simple form or QR code.</li>
                    <li>See upcoming provider visits near your park.</li>
                    <li>Organise a park service day when demand builds.</li>
                </ol>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
