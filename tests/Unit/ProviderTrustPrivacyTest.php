<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\ContactActionController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ProviderTrustPrivacyTest extends TestCase
{
    public function testUnclaimedStatusNeverPublishesCanonicalContactFields(): void
    {
        $targetFor = new ReflectionMethod(ContactActionController::class, 'targetFor');
        $controller = new ContactActionController();
        $privateOnly = [
            'is_unclaimed' => 1,
            'show_public_phone' => 0,
            'show_public_email' => 0,
            'phone' => '0400 111 222',
            'email' => 'private@example.test',
            'public_phone' => '',
            'public_email' => '',
        ];

        self::assertNull($targetFor->invoke($controller, 'phone', $privateOnly));
        self::assertNull($targetFor->invoke($controller, 'email', $privateOnly));

        $profile = $this->source('app/Views/public/provider-profile.php');
        $card = $this->source('app/Views/partials/provider-result-card.php');
        self::assertStringNotContainsString('$provider[\'phone\']', $profile);
        self::assertStringNotContainsString('$provider[\'email\']', $profile);
        self::assertStringNotContainsString('$p[\'phone\']', $card);
    }

    public function testExplicitPublicContactFieldsRemainUsable(): void
    {
        $targetFor = new ReflectionMethod(ContactActionController::class, 'targetFor');
        $controller = new ContactActionController();
        $public = [
            'is_unclaimed' => 1,
            'show_public_phone' => 1,
            'show_public_email' => 1,
            'phone' => '0400 000 000',
            'email' => 'private@example.test',
            'public_phone' => '(07) 4000 1234',
            'public_email' => 'public@example.test',
        ];

        self::assertSame('tel:0740001234', $targetFor->invoke($controller, 'phone', $public));
        self::assertSame('mailto:public@example.test', $targetFor->invoke($controller, 'email', $public));
    }

    public function testAccuracyNoticeAndClaimRequestAreVisibleAcrossKeyJourneys(): void
    {
        $notice = $this->source('app/Views/partials/listing-accuracy-notice.php');
        self::assertStringContainsString('Check important details before you travel or book.', $notice);
        self::assertStringContainsString("url('disclaimer')", $notice);
        self::assertStringContainsString("url('contact')", $notice);

        foreach (['home.php', 'search-results.php', 'stays.php'] as $view) {
            self::assertStringContainsString(
                "include('partials.listing-accuracy-notice')",
                $this->source('app/Views/public/' . $view),
            );
        }

        $profile = $this->source('app/Views/public/provider-profile.php');
        self::assertStringContainsString('Request to claim or correct this listing', $profile);
        self::assertStringContainsString("for-providers/register?listing=", $profile);
    }

    public function testProviderRegistrationSeparatesOnboardingFromMarketingConsent(): void
    {
        $controller = $this->source('app/Controllers/Site/PageController.php');
        $view = $this->source('app/Views/public/provider-interest.php');
        self::assertStringContainsString('$marketingOptIn,', $controller);
        self::assertStringContainsString("'express_web'", $controller);
        self::assertStringContainsString('Optional and unticked by default', $view);
        self::assertStringContainsString('does not prove ownership or grant listing access', $view);
    }

    public function testProviderNavigationIsGroupedAndHasACompactMobileMenu(): void
    {
        $nav = $this->source('app/Views/partials/provider-nav.php');
        $css = $this->source('public/assets/css/app.css');
        foreach (['Overview', 'Your listing', 'Trust', 'Work', 'Growth'] as $group) {
            self::assertStringContainsString("'{$group}'", $nav);
        }
        self::assertStringContainsString('provider-nav-mobile', $nav);
        self::assertStringContainsString('.provider-nav-mobile{display:block}', $css);
    }

    public function testProviderLandingUsesNeutralUnbrandedHeroArtwork(): void
    {
        $view = $this->source('app/Views/public/for-providers.php');
        self::assertStringContainsString('provider-rv.webp', $view);
        self::assertStringNotContainsString('hero-providers.jpg', $view);
        self::assertStringContainsString('alt=""', $view);
    }

    public function testProductionReleaseDefersProviderProcessingToRootCron(): void
    {
        $release = $this->source('.github/workflows/production-release.yml');
        self::assertStringContainsString('root-owned `process_provider_import_queue` cron task', $release);
        self::assertStringNotContainsString('docker compose exec -T app php cron/run.php process_provider_import_queue', $release);

        $maintenance = $this->source('.github/workflows/provider-import-maintenance.yml');
        self::assertStringContainsString('docker compose exec -T app php cron/run.php process_provider_import_queue', $maintenance);
        self::assertStringNotContainsString('sudo /usr/local/sbin/assist-cron process_provider_import_queue', $maintenance);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
