<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class VanAssistPublicUxTest extends TestCase
{
    public function testHomepageSurfacesCoreTravellerJourneysWithoutOverstatingTrust(): void
    {
        $view = $this->source('app/Views/public/home.php');

        self::assertStringContainsString('fuel-and-travel-stops', $view);
        self::assertStringContainsString("url('stays')", $view);
        self::assertStringContainsString("url('services')", $view);
        self::assertStringContainsString('Claimed, verified and unclaimed listings are labelled clearly', $view);
        self::assertStringNotContainsString('Popular service categories', $view);
        self::assertStringNotContainsString('Getting tired?', $view);
        self::assertStringNotContainsString('provider-conversion', $view);
        self::assertStringNotContainsString("include('partials.listing-accuracy-notice')", $view);
        self::assertStringContainsString('unclaimed listings', $this->source('app/Views/partials/listing-accuracy-notice.php'));
        self::assertStringNotContainsString('Verified local providers', $view);
        self::assertStringNotContainsString('Coverage in remote towns', $view);
    }

    public function testServiceDirectoryProvidesDirectFuelChargingAndStayPaths(): void
    {
        $view = $this->source('app/Views/public/services-index.php');

        self::assertStringContainsString('Popular service searches', $view);
        self::assertStringContainsString('fuel-and-travel-stops', $view);
        self::assertStringContainsString('ev-charging', $view);
        self::assertStringContainsString("url('stays')", $view);
    }

    public function testChangedJourneyComponentsHavePhoneLayoutsAndReducedMotionSupport(): void
    {
        $css = $this->source('public/assets/css/app.css');

        self::assertStringContainsString('.service-intent-grid, .service-directory-grid { grid-template-columns: 1fr; }', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        self::assertStringContainsString('.hero-capabilities a:hover,.hero-capabilities a:focus-visible', $css);
        self::assertStringContainsString('.hero--visual .hero-grid,.hero-search-panel,.hero-search-panel .search-card{display:contents}', $css);
        self::assertStringContainsString('.hero-capabilities{order:2', $css);
        self::assertStringContainsString('.hero-search-panel .structured-search-form{order:3', $css);
        self::assertStringContainsString('.ask-vanassist-home{order:4', $css);
        self::assertStringContainsString('.service-directory-card:focus-visible', $css);
    }

    public function testPublicIdentityIsTypographyLedWithDedicatedProfessionalFavicon(): void
    {
        $header = $this->source('app/Views/partials/header.php');
        $layout = $this->source('app/Views/layouts/public.php');

        self::assertStringContainsString('brand--wordmark', $header);
        self::assertStringNotContainsString('<img class="brand-mark"', $header);
        self::assertStringContainsString("['favicon']", $layout);
        self::assertStringContainsString('/assets/brands/vanassist/favicon.svg', $layout);
    }

    public function testVanAssistHeaderLinksAskWhenFeatureFlagEnabled(): void
    {
        $header = $this->source('app/Views/partials/header.php');
        $footer = $this->source('app/Views/partials/footer.php');

        self::assertStringContainsString('AiSearchFeature::enabled()', $header);
        self::assertStringContainsString("url('ask')", $header);
        self::assertStringContainsString('Ask VanAssist', $header);
        self::assertStringContainsString('AiSearchFeature::enabled()', $footer);
        self::assertStringContainsString("url('ask')", $footer);
    }

    public function testRadiusSearchUsesCoordinateCategoryLookup(): void
    {
        $controller = $this->source('app/Controllers/Site/SearchController.php');
        $provider = $this->source('app/Models/Provider.php');

        self::assertStringContainsString('Provider::forCategoryNear', $controller);
        self::assertStringContainsString('public static function forCategoryNear', $provider);
        self::assertStringContainsString('HAVING distance_km <= ?', $provider);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
