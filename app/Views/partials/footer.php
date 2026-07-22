<?php
use App\Services\Settings;
$footerBrand = current_brand();
$footerMeta = $footerBrand->metadata();
$bizAbn = (string) Settings::get('business_abn', '');
$bizPhone = (string) Settings::get('contact_phone', '');
$brandContact = $footerBrand->contact();
$brandEmail = (string) ($brandContact['support_email'] ?? '');
$bizEmail = $footerBrand->id() === 'vanassist'
    ? (string) Settings::get('contact_email', $brandEmail)
    : $brandEmail;
?>
<footer class="site-footer"><div class="container"><div class="grid grid-4">
<div><h4><?= $this->e($footerBrand->name()) ?></h4><p class="muted"><?= $this->e($footerMeta['tagline'] ?? '') ?></p><?php if ($bizPhone !== '' || $bizEmail !== ''): ?><p class="muted"><?php if ($bizPhone !== ''): ?><a href="tel:<?= e_attr(preg_replace('/\s+/', '', $bizPhone)) ?>"><?= $this->e($bizPhone) ?></a><?php endif; ?><?php if ($bizEmail !== ''): ?><br><a href="mailto:<?= e_attr($bizEmail) ?>"><?= $this->e($bizEmail) ?></a><?php endif; ?></p><?php endif; ?></div>
<?php if ($footerBrand->id() === 'vanassist'): ?><div><h4>Explore</h4><ul><li><a href="<?= e(url('services')) ?>">Services</a></li><li><a href="<?= e(url('stays')) ?>">Places to stay</a></li><li><a href="<?= e(url('regions')) ?>">Regions</a></li><li><a href="<?= e(url('service-runs')) ?>">Service runs</a></li><li><a href="<?= e(url('providers')) ?>">Providers</a></li></ul></div><div><h4>Join</h4><ul><li><a href="<?= e(url('for-providers')) ?>">For providers</a></li><li><a href="<?= e(url('for-caravan-parks')) ?>">For caravan parks</a></li><li><a href="<?= e(url('request-assistance')) ?>">Request assistance</a></li></ul></div><?php else: ?><div><h4>Explore</h4><ul><?php foreach ($footerBrand->navigation() as $link): ?><li><a href="<?= e(url(ltrim($link['path'], '/'))) ?>"><?= $this->e($link['label']) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
<div><h4>About</h4><ul><li><a href="<?= e(url('about')) ?>">About</a></li><li><a href="<?= e(url('contact')) ?>">Contact</a></li><li><a href="<?= e(url('privacy-policy')) ?>">Privacy</a></li><li><a href="<?= e(url('terms-of-use')) ?>">Terms</a></li><li><a href="<?= e(url('disclaimer')) ?>">Disclaimer</a></li></ul></div>
</div><div class="footer-bottom"><p>&copy; <?= date('Y') ?> <?= $this->e($footerBrand->legalName()) ?><?php if ($bizAbn !== ''): ?> &middot; ABN <?= $this->e($bizAbn) ?><?php endif; ?>. Information is general only; verify specifications, suitability and licensing where required.</p></div></div></footer>
