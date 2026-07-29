<?php if (\App\Services\Turnstile::enabled()): ?>
    <div class="cf-turnstile" data-sitekey="<?= e_attr(\App\Services\Turnstile::siteKey()) ?>" data-theme="light" data-size="flexible"></div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
