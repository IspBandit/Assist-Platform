<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var string $requestUrl */
/** @var string $qrDataUri */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<style>
@media print {
    header, footer, nav { display: none !important; }
    .no-print { display: none !important; }
    .poster { box-shadow: none !important; border: none !important; }
    body { background: #fff !important; }
}
</style>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1 class="no-print">QR code &amp; materials</h1>
        <div class="no-print"><?php $this->include('partials.park-nav', ['active' => 'materials']); ?></div>

        <div class="card no-print stack">
            <p class="muted" style="margin:0">Print this poster and display it at reception or in your camp kitchen. Guests scan the code to request caravan or RV help — your park is credited automatically.</p>
            <div class="btn-row">
                <a class="btn btn-secondary" href="<?= e($qrDataUri) ?>" download="<?= e_attr($park['slug'] . '-vanassist-qr.svg') ?>">Download QR (SVG)</a>
            </div>
            <p class="muted" style="margin:0;font-size:.85rem">To print, use your browser's print option (Ctrl/Cmd + P).</p>
        </div>

        <div class="card poster" style="text-align:center;padding:2.5rem">
            <p style="font-size:1.1rem;letter-spacing:.05em;text-transform:uppercase;color:var(--green,#0f6e6e);margin:0 0 .5rem">VanAssist</p>
            <h2 style="margin:0 0 .25rem">Need help with your caravan or RV?</h2>
            <p class="muted" style="margin:0 0 1.5rem">Scan to tell us what's wrong — we'll coordinate trusted local providers. Free to ask.</p>
            <img src="<?= e($qrDataUri) ?>" alt="QR code to request assistance" style="width:280px;height:280px;max-width:80%">
            <p style="margin:1.5rem 0 0;font-weight:600"><?= $this->e((string) $park['name']) ?></p>
            <p class="muted" style="margin:.25rem 0 0;word-break:break-all"><?= $this->e($requestUrl) ?></p>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
