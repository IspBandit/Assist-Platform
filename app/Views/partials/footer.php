<?php
/** @var \App\Core\View $this */
use App\Services\Settings;
$bizAbn = (string) Settings::get('business_abn', '');
$bizPhone = (string) Settings::get('contact_phone', '');
$bizEmail = (string) Settings::get('contact_email', '');
?>
<footer class="site-footer">
    <div class="container">
        <div class="grid grid-4">
            <div>
                <h4>VanAssist</h4>
                <p class="muted"><?= $this->e((string) Settings::get('tagline', 'Caravan help, wherever you travel.')) ?></p>
                <?php if ($bizPhone !== '' || $bizEmail !== ''): ?>
                    <p class="muted" style="margin:.5rem 0 0">
                        <?php if ($bizPhone !== ''): ?><a href="tel:<?= e_attr(preg_replace('/\s+/', '', $bizPhone)) ?>"><?= $this->e($bizPhone) ?></a><?php endif; ?>
                        <?php if ($bizEmail !== ''): ?><br><a href="mailto:<?= e_attr($bizEmail) ?>"><?= $this->e($bizEmail) ?></a><?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <h4>Explore</h4>
                <ul>
                    <li><a href="<?= e(url('services')) ?>">Services</a></li>
                    <li><a href="<?= e(url('regions')) ?>">Regions</a></li>
                    <li><a href="<?= e(url('service-runs')) ?>">Service runs</a></li>
                    <li><a href="<?= e(url('providers')) ?>">Providers</a></li>
                    <li><a href="<?= e(url('how-it-works')) ?>">How it works</a></li>
                </ul>
            </div>
            <div>
                <h4>Join</h4>
                <ul>
                    <li><a href="<?= e(url('for-providers')) ?>">For providers</a></li>
                    <li><a href="<?= e(url('for-caravan-parks')) ?>">For caravan parks</a></li>
                    <li><a href="<?= e(url('request-assistance')) ?>">Request assistance</a></li>
                </ul>
            </div>
            <div>
                <h4>About</h4>
                <ul>
                    <li><a href="<?= e(url('about')) ?>">About</a></li>
                    <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
                    <li><a href="<?= e(url('privacy-policy')) ?>">Privacy</a></li>
                    <li><a href="<?= e(url('terms-of-use')) ?>">Terms</a></li>
                    <li><a href="<?= e(url('disclaimer')) ?>">Disclaimer</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> VanAssist<?php if ($bizAbn !== ''): ?> · ABN <?= $this->e($bizAbn) ?><?php endif; ?>. VanAssist is a matching and coordination platform and does not guarantee provider workmanship. Customers must verify suitability and licensing where required.</p>
        </div>
    </div>
</footer>
