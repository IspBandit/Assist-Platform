<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class VanAssistPublicUxTest extends TestCase
{
    public function testAskOutcomeLayerIsFeatureGatedAndAccessible(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Site/AssistSearchController.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/public/assist-search.php');
        self::assertIsString($controller); self::assertIsString($view);
        self::assertStringContainsString('OutcomeFeature::enabled()', $controller);
        self::assertStringContainsString('What I understood', $view);
        self::assertStringContainsString('Safest next action', $view);
        self::assertStringContainsString('Why this fits', $view);
        self::assertStringContainsString('aria-labelledby="ask-outcome-heading"', $view);
    }

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

    public function testFindAndDirectoryEndpointsAcceptLegacyTextInputAliases(): void
    {
        $searchController = $this->source('app/Controllers/Site/SearchController.php');
        $providerController = $this->source('app/Controllers/Site/ProviderController.php');

        self::assertStringContainsString('$location = trim((string) $request->input(\'location\', \'\'));', $searchController);
        self::assertStringContainsString('if ($location === \'\') {', $searchController);
        self::assertStringContainsString('$location = trim((string) $request->input(\'text\', \'\'));', $searchController);
        self::assertStringContainsString('$search = trim((string) $request->input(\'q\', \'\'));', $providerController);
        self::assertStringContainsString('if ($search === \'\') {', $providerController);
        self::assertStringContainsString('$search = trim((string) $request->input(\'text\', \'\'));', $providerController);
    }

    public function testLocationEndpointsAcceptLatitudeLongitudeAliases(): void
    {
        $locationController = $this->source('app/Controllers/Site/LocationController.php');
        $searchController = $this->source('app/Controllers/Site/SearchController.php');
        $providerController = $this->source('app/Controllers/Site/ProviderController.php');

        self::assertStringContainsString('$latRaw = $request->input(\'lat\');', $locationController);
        self::assertStringContainsString('$latRaw = $request->input(\'latitude\');', $locationController);
        self::assertStringContainsString('$lngRaw = $request->input(\'lng\');', $locationController);
        self::assertStringContainsString('$lngRaw = $request->input(\'longitude\');', $locationController);

        self::assertStringContainsString('$latRaw = $request->input(\'lat\');', $searchController);
        self::assertStringContainsString('$lngRaw = $request->input(\'lng\');', $searchController);
        self::assertStringContainsString('$latRaw = $request->input(\'lat\');', $providerController);
        self::assertStringContainsString('$lngRaw = $request->input(\'lng\');', $providerController);
    }

    public function testPublicMeasurementIsBrandScopedAndContainsNoPersonalFields(): void
    {
        $layout = $this->source('app/Views/layouts/public.php');
        $script = $this->source('public/assets/js/app.js');

        self::assertStringContainsString('$layoutBrand->analytics()', $layout);
        self::assertStringContainsString("data-brand=", $layout);
        self::assertStringContainsString("window.assistMeasure", $script);
        self::assertStringContainsString("'search_submitted'", $script);
        self::assertStringContainsString("'provider_open'", $script);
        self::assertStringContainsString("'stay_open'", $script);
        self::assertStringContainsString("'phone_click'", $script);
        self::assertStringContainsString("'provider_claim_submitted'", $script);
        self::assertStringNotContainsString('contact_email', $script);
        self::assertStringNotContainsString('contact_phone', $script);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
