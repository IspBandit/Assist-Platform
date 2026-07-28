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
        self::assertStringContainsString('ev-charging', $view);
        self::assertStringContainsString("url('stays')", $view);
        self::assertStringContainsString('Claimed and verified status shown clearly', $view);
        self::assertStringContainsString('unclaimed listings', $view);
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

        self::assertStringContainsString('.journey-launcher-grid, .service-intent-grid, .service-directory-grid { grid-template-columns: 1fr; }', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        self::assertStringContainsString('.journey-launcher-card:focus-visible', $css);
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
