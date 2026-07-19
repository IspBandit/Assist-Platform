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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>
                    Founding provider access &middot; free during launch
                </span>
                <h1>Turn regional demand into <span class="accent">organised, profitable runs.</span></h1>
                <p class="lead">See where demand is building across regional towns, plan service runs around real registered requests, and accept only the work you want.</p>
                <div class="hero-cta">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('for-providers/register')) ?>">Register interest</a>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('how-it-works')) ?>">How it works</a>
                </div>
                <ul class="hero-trust">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        No fees during launch
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        Accept only the work you want
                    </li>
                </ul>
            </div>

            <div class="hero-art">
                <img class="hero-photo" src="<?= e(asset('img/hero-providers.jpg')) ?>" width="1536" height="1024"
                     alt="Mobile caravan and RV repair technician with a fully fitted-out service van in a regional Australian town"
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
                <h2>Why join</h2>
                <ul class="feature-list">
                    <li>Free founding provider access during launch.</li>
                    <li>Customer demand is grouped by town and region.</li>
                    <li>Plan regional runs around real, registered demand.</li>
                    <li>You stay in control — accept only the work you want.</li>
                    <li>No fees during the initial launch.</li>
                    <li>Optional licence and insurance verification builds trust.</li>
                </ul>
            </div>
            <div class="card">
                <h2>Apply to join</h2>
                <p>Provider onboarding opens as part of the next rollout phase. Register your interest and we'll send your secure onboarding link.</p>
                <a class="btn btn-primary btn-lg" href="<?= e(url('for-providers/register')) ?>">Register interest</a>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
