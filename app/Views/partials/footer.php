<?php
use App\Services\Settings;
$footerBrand = current_brand();
$footerMeta = $footerBrand->metadata();
$bizAbn = (string) Settings::get('business_abn', '');
$bizPhone = (string) Settings::get('contact_phone', '');
$brandContact = $footerBrand->contact();
$brandEmail = (string) ($brandContact['support_email'] ?? '');
$bizEmail = $footerBrand->id() === 'vanassist' ? (string) Settings::get('contact_email', $brandEmail) : $brandEmail;
?>
<footer class="site-footer">
    <?php if ($footerBrand->id() === 'vanassist'): ?>
        <div class="footer-action"><div class="container"><div><span>Not sure where to begin?</span><strong>Start with your location and the help you need.</strong></div><a class="btn btn-light" href="<?= e(url('find')) ?>">Find nearby help</a></div></div>
    <?php endif; ?>
    <div class="container footer-main">
        <div class="footer-brand-column">
            <a class="footer-wordmark" href="<?= e(url('/')) ?>"><?= $this->e($footerBrand->name()) ?></a>
            <p><?= $this->e($footerMeta['tagline'] ?? '') ?></p>
            <p class="footer-trust-copy">A focused brand powered by Assist Platform Enterprise. Directory information is clearly labelled; confirm suitability and current details directly.</p>
            <?php if ($bizPhone !== '' || $bizEmail !== ''): ?><address><?php if ($bizPhone !== ''): ?><a href="tel:<?= e_attr(preg_replace('/\s+/', '', $bizPhone)) ?>"><?= $this->e($bizPhone) ?></a><?php endif; ?><?php if ($bizEmail !== ''): ?><a href="mailto:<?= e_attr($bizEmail) ?>"><?= $this->e($bizEmail) ?></a><?php endif; ?></address><?php endif; ?>
        </div>
        <?php if ($footerBrand->id() === 'vanassist'): ?>
            <div><h4>Find</h4><ul><li><a href="<?= e(url('find')) ?>">RV service providers</a></li><li><a href="<?= e(url('services')) ?>">Browse services</a></li><li><a href="<?= e(url('stays')) ?>">Places to stay</a></li><li><a href="<?= e(url('regions')) ?>">Browse regions</a></li><li><a href="<?= e(url('service-runs')) ?>">Service runs</a></li></ul></div>
            <div><h4>Get involved</h4><ul><li><a href="<?= e(url('request-assistance')) ?>">Request assistance</a></li><li><a href="<?= e(url('for-providers')) ?>">For providers</a></li><li><a href="<?= e(url('for-caravan-parks')) ?>">For caravan parks</a></li><li><a href="<?= e(url('how-it-works')) ?>">How it works</a></li></ul></div>
        <?php else: ?>
            <div><h4>Explore</h4><ul><?php foreach ($footerBrand->navigation() as $link): ?><li><a href="<?= e(url(ltrim($link['path'], '/'))) ?>"><?= $this->e($link['label']) ?></a></li><?php endforeach; ?></ul></div>
            <div><h4>For business</h4><ul><li><a href="<?= e(url('for-providers')) ?>">Provider opportunity</a></li><li><a href="<?= e(url('for-providers/register')) ?>">Register a business</a></li><li><a href="<?= e(url('login')) ?>">Provider sign in</a></li></ul></div>
        <?php endif; ?>
        <div><h4>Trust and support</h4><ul><li><a href="<?= e(url('about')) ?>">About</a></li><li><a href="<?= e(url('contact')) ?>">Contact</a></li><li><a href="<?= e(url('privacy-policy')) ?>">Privacy</a></li><li><a href="<?= e(url('terms-of-use')) ?>">Terms</a></li><li><a href="<?= e(url('disclaimer')) ?>">Disclaimer</a></li><li><a href="<?= e(url('accessibility-statement')) ?>">Accessibility</a></li></ul></div>
    </div>
    <div class="container footer-bottom"><p>&copy; <?= date('Y') ?> <?= $this->e($footerBrand->legalName()) ?><?php if ($bizAbn !== ''): ?> &middot; ABN <?= $this->e($bizAbn) ?><?php endif; ?>.</p><p>General information only. Verify specifications, suitability, licensing and availability where required.</p></div>
</footer>
