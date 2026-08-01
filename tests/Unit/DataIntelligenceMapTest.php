<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DataIntelligenceMapTest extends TestCase
{
    public function testNationalHeatMapRendersAustraliaStatesLegendAndAccessiblePoints(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Views/admin/data-intelligence/index.php');
        $css = file_get_contents($root . '/public/assets/css/app.css');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringContainsString('class="map-land"', $view);
        self::assertStringContainsString('class="map-boundary"', $view);
        self::assertStringContainsString("'WA'=>", $view);
        self::assertStringContainsString("'TAS'=>", $view);
        self::assertStringContainsString('Australian Capital Territory', $view);
        self::assertStringContainsString('class="heat-point"', $view);
        self::assertStringContainsString('tabindex="0"', $view);
        self::assertStringContainsString('Higher opportunity', $view);
        self::assertStringContainsString('.map-state-label', $css);
        self::assertStringContainsString('prefers-reduced-motion:reduce', $css);
    }
}
