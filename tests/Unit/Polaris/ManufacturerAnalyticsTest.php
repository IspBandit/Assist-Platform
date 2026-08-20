<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\AnalyticsService;
use PHPUnit\Framework\TestCase;

final class ManufacturerAnalyticsTest extends TestCase
{
    public function testShapeManufacturerSummaryMapsViewsAndSaves(): void
    {
        $summary = AnalyticsService::shapeManufacturerSummary(
            30,
            [
                ['event_name' => 'rv_viewed', 'event_count' => 12],
                ['event_name' => 'rv_saved', 'event_count' => 3],
                ['event_name' => 'other', 'event_count' => 99],
            ],
            [
                ['id' => 1, 'name' => 'Southern Cross', 'slug' => 'southern-cross', 'views' => 10, 'saves' => 2],
                ['id' => 2, 'name' => 'Range Runner', 'slug' => 'range-runner', 'views' => 2, 'saves' => 1],
            ]
        );

        self::assertSame(30, $summary['days']);
        self::assertSame(12, $summary['views']);
        self::assertSame(3, $summary['saves']);
        self::assertCount(2, $summary['by_model']);
        self::assertSame('Southern Cross', $summary['by_model'][0]['name']);
        self::assertSame(10, $summary['by_model'][0]['views']);
    }

    public function testShapeManufacturerSummaryEmptyIsHonest(): void
    {
        $summary = AnalyticsService::shapeManufacturerSummary(7, [], []);
        self::assertSame(7, $summary['days']);
        self::assertSame(0, $summary['views']);
        self::assertSame(0, $summary['saves']);
        self::assertSame([], $summary['by_model']);
    }

    public function testPortalAnalyticsWiringIsManufacturerScoped(): void
    {
        $root = dirname(__DIR__, 3);
        $service = (string) file_get_contents($root . '/app/Services/Polaris/AnalyticsService.php');
        self::assertStringContainsString('function manufacturerSummary', $service);
        self::assertStringContainsString('manufacturer_id = ?', $service);
        self::assertStringContainsString("entity_type = \\'model\\'", $service);
        self::assertStringContainsString('rv_viewed', $service);
        self::assertStringContainsString('rv_saved', $service);

        $controller = (string) file_get_contents($root . '/app/Controllers/Site/ManufacturerPortalController.php');
        self::assertStringContainsString("polaris.portal.analytics", $controller);
        self::assertStringContainsString('manufacturerSummary', $controller);
        self::assertStringNotContainsString(
            "portalSection(\$request, 'analytics'",
            $controller
        );

        $view = (string) file_get_contents($root . '/app/Views/polaris/portal/analytics.php');
        self::assertStringContainsString('Detail views', $view);
        self::assertStringContainsString('Find impressions', $view);
        self::assertStringContainsString('role="status"', $view);
    }
}
