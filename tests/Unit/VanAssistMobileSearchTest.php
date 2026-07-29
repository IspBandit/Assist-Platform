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
        self::assertStringContainsString("tile.openstreetmap.org/", $script);
        self::assertStringContainsString("card.addEventListener('focusin'", $script);
        self::assertStringContainsString("setResultsView('list')", $script);
        self::assertStringContainsString("mapCanvas.addEventListener('pointermove'", $script);
        self::assertStringContainsString('Math.log2(distance /', $script);
        self::assertStringContainsString("mapCanvas.addEventListener('wheel'", $script);
        self::assertStringContainsString("event.key === 'ArrowLeft'", $script);
        self::assertStringContainsString('.provider-card--compact', $css);
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
}
