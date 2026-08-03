<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\SearchController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class VanAssistMobileSearchTest extends TestCase
{
    public function testDirectResultsKeepFeaturedSeparateThenRankOrganicVerifiedAndNearest(): void
    {
        $rank = new ReflectionMethod(SearchController::class, 'rankDirectMatches');
        $rows = $rank->invoke(new SearchController(), [
            ['id' => 1, 'business_name' => 'Far unverified', 'is_featured' => 0, 'is_verified' => 0, 'distance_km' => 3],
            ['id' => 2, 'business_name' => 'Featured', 'is_featured' => 1, 'is_verified' => 0, 'distance_km' => 20],
            ['id' => 3, 'business_name' => 'Near verified', 'is_featured' => 0, 'is_verified' => 1, 'distance_km' => 8],
            ['id' => 4, 'business_name' => 'Near unverified', 'is_featured' => 0, 'is_verified' => 0, 'distance_km' => 1],
        ]);

        self::assertSame([2, 3, 4, 1], array_column($rows, 'id'));
    }

    public function testResultPageProvidesMapListParityAndTravellerShortcuts(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/public/search-results.php');
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/app.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/app.css');

        self::assertStringContainsString('data-results-view="list"', $view);
        self::assertStringContainsString('data-results-view="map"', $view);
        self::assertStringContainsString('data-results-map-summary-list', $view);
        self::assertStringContainsString('data-results-map-zoom-in', $view);
        self::assertStringContainsString('data-results-map-zoom-out', $view);
        self::assertStringContainsString('data-results-map-fit', $view);
        self::assertStringContainsString('data-results-map-summary-toggle', $view);
        self::assertStringContainsString('data-results-map-summary-drag', $view);
        self::assertStringContainsString('Places to stay', $view);
        self::assertStringContainsString('provider-result-', $view);
        self::assertStringContainsString('$mapResultNumbers', $view);
        self::assertStringContainsString("'mapResultNumber' =>", $view);
        self::assertStringContainsString('Map pin', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/partials/provider-result-card.php'));
        self::assertStringContainsString("tile.openstreetmap.org/", $script);
        self::assertStringContainsString("card.addEventListener('focusin'", $script);
        self::assertStringContainsString("setResultsView('list')", $script);
        self::assertStringContainsString("mapCanvas.addEventListener('pointermove'", $script);
        self::assertStringContainsString('Math.log2(distance /', $script);
        self::assertStringContainsString("mapCanvas.addEventListener('wheel'", $script);
        self::assertStringContainsString("event.key === 'ArrowLeft'", $script);
        self::assertStringContainsString('.provider-card--compact', $css);
        self::assertStringContainsString('.provider-map-reference', $css);
        self::assertStringContainsString('.results-map-pin::after{font-size:.78rem}', $css);
        self::assertStringContainsString('.provider-card--compact{min-height:0', $css);
        self::assertStringContainsString('.provider-card--compact .provider-card-badges .badge:nth-child(n+3){display:none}', $css);
        self::assertStringContainsString('touch-action:none', $css);
        self::assertStringContainsString('.results-map-summary.is-collapsed', $css);
        self::assertStringContainsString('min-height:44px', $css);
    }

    public function testMapTileHostIsNarrowlyAllowedByContentSecurityPolicy(): void
    {
        $security = require dirname(__DIR__, 2) . '/config/security.php';
        self::assertStringContainsString("img-src 'self' data: https://tile.openstreetmap.org", $security['csp']);
        self::assertStringNotContainsString('img-src *', $security['csp']);
    }

    public function testProviderCollectionsUseConciseRowsAcrossPublicViews(): void
    {
        $root = dirname(__DIR__, 2);
        $card = (string) file_get_contents($root . '/app/Views/partials/provider-result-card.php');
        $css = (string) file_get_contents($root . '/public/assets/css/app.css');

        self::assertStringContainsString('$compact = !isset($compact) || $compact !== false;', $card);
        self::assertStringContainsString('if (!$compact && $description', $card);
        self::assertStringContainsString('.provider-card-grid,.provider-results{grid-template-columns:1fr}', $css);
        self::assertStringContainsString('.provider-card--compact .provider-card-badges{display:none}', $css);
        self::assertStringContainsString('.provider-card--compact .provider-card-actions .provider-card-link:first-child{display:none}', $css);

        foreach (['providers-index.php', 'service-category.php', 'region.php', 'town.php', 'assist-search.php'] as $view) {
            self::assertStringContainsString('provider-card-grid', (string) file_get_contents($root . '/app/Views/public/' . $view), $view);
        }
    }

    public function testEveryPublicDiscoveryJourneyCanInheritDeviceLocation(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string) file_get_contents($root . '/public/assets/js/app.js');
        $home = (string) file_get_contents($root . '/app/Views/public/home.php');
        $services = (string) file_get_contents($root . '/app/Views/public/services-index.php');

        foreach (['search-results.php', 'providers-index.php', 'service-category.php', 'stays.php', 'request-form.php'] as $view) {
            self::assertStringContainsString('data-auto-location', (string) file_get_contents($root . '/app/Views/public/' . $view), $view);
        }
        self::assertGreaterThanOrEqual(8, substr_count($home, 'data-location-link'));
        self::assertGreaterThanOrEqual(5, substr_count($services, 'data-location-link'));
        self::assertStringContainsString("sessionStorage.setItem('va-current-location'", $script);
        self::assertStringContainsString("document.querySelectorAll('a[data-location-link]')", $script);
        self::assertStringContainsString("form[data-location-manual=\"1\"] input[name=\"location\"]", $script);
        self::assertStringContainsString("target.searchParams.set('lat'", $script);
        self::assertStringContainsString("target.searchParams.set('location'", $script);
    }

    public function testGpsRemainsTheDistanceOriginForServiceAndStayPages(): void
    {
        $root = dirname(__DIR__, 2);
        $category = (string) file_get_contents($root . '/app/Controllers/Site/CategoryController.php');
        $parks = (string) file_get_contents($root . '/app/Controllers/Site/ParkController.php');

        self::assertStringContainsString('$originLat = $gpsLat ??', $category);
        self::assertStringContainsString('if ($gpsLat !== null && $gpsLng !== null)', $category);
        self::assertStringContainsString('Device coordinates are the accurate origin', $parks);
    }
}
