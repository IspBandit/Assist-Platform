<?php
use App\Services\Settings;
use App\Platform\AiSearch\Support\AiSearchFeature;
$footerBrand = current_brand();
$footerBrandId = $footerBrand->id();
$footerMeta = $footerBrand->metadata();
$bizAbn = (string) Settings::get('business_abn', '');
$bizPhone = (string) Settings::get('contact_phone', '');
$brandContact = $footerBrand->contact();
$brandEmail = (string) ($brandContact['support_email'] ?? '');
$bizEmail = $footerBrandId === 'vanassist' ? (string) Settings::get('contact_email', $brandEmail) : $brandEmail;
$pwaBrands = ['vanassist', 'towsmart', 'trailerwise'];
$supportsInstall = in_array($footerBrandId, $pwaBrands, true);
$installLabel = match ($footerBrandId) {
    'towsmart' => 'Save TowSmart to your phone',
    'trailerwise' => 'Save TrailerWise to your phone',
    default => 'Save VanAssist to your phone',
};
?>
<footer class="site-footer">
    <?php if ($footerBrandId === 'vanassist'): ?>
        <div class="footer-action"><div class="container"><div><span>Not sure where to begin?</span><strong>Start with your location and the help you need.</strong></div><a class="btn btn-light" href="<?= e(url('find')) ?>">Find nearby help</a></div></div>
    <?php elseif ($footerBrandId === 'towsmart'): ?>
        <div class="footer-action"><div class="container"><div><span>Ready before the road?</span><strong>Run a weight check with your loaded figures.</strong></div><a class="btn btn-light" href="<?= e(url('calculator')) ?>">Check my combination</a></div></div>
    <?php elseif ($footerBrandId === 'trailerwise'): ?>
        <div class="footer-action"><div class="container"><div><span>Need trailer help?</span><strong>Find repairers, inspections and specialists near you.</strong></div><a class="btn btn-light" href="<?= e(url('providers')) ?>">Find trailer services</a></div></div>
    <?php endif; ?>
    <div class="container footer-main">
        <div class="footer-brand-column">
            <a class="footer-wordmark" href="<?= e(url('/')) ?>"><?= $this->e($footerBrand->name()) ?></a>
            <p><?= $this->e($footerMeta['tagline'] ?? '') ?></p>
            <p class="footer-trust-copy">A focused brand powered by Assist Platform Enterprise. Directory information is clearly labelled; confirm suitability and current details directly.</p>
            <?php if ($bizPhone !== '' || $bizEmail !== ''): ?><address><?php if ($bizPhone !== ''): ?><a href="tel:<?= e_attr(preg_replace('/\s+/', '', $bizPhone)) ?>"><?= $this->e($bizPhone) ?></a><?php endif; ?><?php if ($bizEmail !== ''): ?><a href="mailto:<?= e_attr($bizEmail) ?>"><?= $this->e($bizEmail) ?></a><?php endif; ?></address><?php endif; ?>
            <?php if ($supportsInstall): ?><button class="btn btn-light install-app-button" type="button" data-install-app><?= $this->e($installLabel) ?></button><?php endif; ?>
        </div>
        <?php if ($footerBrandId === 'vanassist'): ?>
            <div><h2>Find</h2><ul><li class="footer-mobile-primary"><a data-location-link href="<?= e(url('find')) ?>">RV service providers</a></li><?php if (AiSearchFeature::enabled()): ?><li class="footer-mobile-primary"><a href="<?= e(url('ask')) ?>">Ask VanAssist</a></li><?php endif; ?><li><a href="<?= e(url('services')) ?>">Browse services</a></li><li class="footer-mobile-primary"><a data-location-link href="<?= e(url('stays')) ?>">Places to stay</a></li><li><a href="<?= e(url('regions')) ?>">Browse regions</a></li><li><a href="<?= e(url('service-runs')) ?>">Service runs</a></li></ul></div>
            <div><h2>Get involved</h2><ul><li class="footer-mobile-primary"><a href="<?= e(url('request-assistance')) ?>">Request assistance</a></li><li class="footer-mobile-primary"><a href="<?= e(url('for-providers')) ?>">For providers</a></li><li><a href="<?= e(url('for-caravan-parks')) ?>">For caravan parks</a></li><li><a href="<?= e(url('how-it-works')) ?>">How it works</a></li></ul></div>
        <?php elseif ($footerBrandId === 'towsmart'): ?>
            <div><h4>Tools</h4><ul><li><a href="<?= e(url('calculator')) ?>">Weight calculator</a></li><li><a href="<?= e(url('tow-guide')) ?>">Tow guide</a></li><li><a href="<?= e(url('checklist')) ?>">Checklist</a></li><li><a href="<?= e(url('rules')) ?>">Rules &amp; compliance</a></li></ul></div>
            <div><h4>Account &amp; directory</h4><ul><li><a href="<?= e(url('account/towing-combinations')) ?>">My combinations</a></li><li><a href="<?= e(url('providers')) ?>">Towing specialists</a></li><li><a href="<?= e(url('register')) ?>">Create a free account</a></li><li><a href="<?= e(url('for-providers')) ?>">For providers</a></li></ul></div>
        <?php elseif ($footerBrandId === 'trailerwise'): ?>
            <div><h4>Find</h4><ul><li><a href="<?= e(url('providers')) ?>">Find trailer services</a></li><li><a href="<?= e(url('services')) ?>">Service categories</a></li><li><a href="<?= e(url('rules')) ?>">Rules &amp; compliance</a></li><li><a href="<?= e(url('marketplace')) ?>">Sale and hire listings</a></li></ul></div>
            <div><h4>For business</h4><ul><li><a href="<?= e(url('for-providers')) ?>">Provider opportunity</a></li><li><a href="<?= e(url('for-providers/register')) ?>">Register a business</a></li><li><a href="<?= e(url('login')) ?>">Provider sign in</a></li></ul></div>
        <?php else: ?>
            <div><h4>Explore</h4><ul><?php foreach ($footerBrand->navigation() as $link): ?><li><a href="<?= e(url(ltrim($link['path'], '/'))) ?>"><?= $this->e($link['label']) ?></a></li><?php endforeach; ?></ul></div>
            <div><h4>For business</h4><ul><li><a href="<?= e(url('for-providers')) ?>">Provider opportunity</a></li><li><a href="<?= e(url('for-providers/register')) ?>">Register a business</a></li><li><a href="<?= e(url('login')) ?>">Provider sign in</a></li></ul></div>
        <?php endif; ?>
        <div><h2>Trust and support</h2><ul><li><a href="<?= e(url('about')) ?>">About</a></li><li class="footer-mobile-primary"><a href="<?= e(url('contact')) ?>">Contact</a></li><li class="footer-mobile-primary"><a href="<?= e(url('privacy-policy')) ?>">Privacy</a></li><li class="footer-mobile-primary"><a href="<?= e(url('terms-of-use')) ?>">Terms</a></li><li class="footer-mobile-primary"><a href="<?= e(url('disclaimer')) ?>">Disclaimer</a></li><li><a href="<?= e(url('accessibility-statement')) ?>">Accessibility</a></li></ul></div>
    </div>
    <div class="container footer-bottom"><p>&copy; <?= date('Y') ?> <?= $this->e($footerBrand->legalName()) ?><?php if ($bizAbn !== ''): ?> &middot; ABN <?= $this->e($bizAbn) ?><?php endif; ?>.</p><p>General information only. Verify specifications, suitability, licensing and availability where required.</p></div>
</footer>
<?php if ($supportsInstall): ?>
<dialog class="install-app-dialog" data-install-dialog aria-labelledby="install-app-title">
    <div class="install-app-dialog__inner">
        <button class="install-app-dialog__close" type="button" data-install-close aria-label="Close">&times;</button>
        <span class="eyebrow">Keep <?= $this->e($footerBrand->name()) ?> handy</span>
        <h2 id="install-app-title"><?= $this->e($installLabel) ?></h2>
        <div data-install-ios hidden><p>On this iPhone or iPad:</p><ol><li>Tap <strong>Share</strong>.</li><li>Tap <strong>Add to Home Screen</strong>, then <strong>Add</strong>.</li></ol></div>
        <div data-install-android hidden><p>On this Android phone:</p><ol><li>Open the browser menu <strong>⋮</strong>.</li><li>Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>.</li></ol></div>
        <div data-install-desktop hidden><p>Open <?= $this->e($footerBrand->name()) ?> on your phone, then use your phone browser’s <strong>Add to Home Screen</strong> or <strong>Install app</strong> option.</p></div>
        <p class="muted"><?= $this->e($footerBrand->name()) ?> will open from your home screen with your primary journeys one tap away.</p>
    </div>
</dialog>
<?php endif; ?>
