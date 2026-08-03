<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use PHPUnit\Framework\TestCase;

final class DemoCatalogueVolumeTest extends TestCase
{
    public function testMigration119ExpandsDemoCatalogueOnly(): void
    {
        $root = dirname(__DIR__, 3);
        $sql = (string) file_get_contents($root . '/database/migrations/119_polaris_demo_catalogue_volume.sql');
        self::assertStringContainsString('is_demo = 1', $sql);
        self::assertStringContainsString('demo-alpine-family', $sql);
        self::assertStringContainsString('family-bunkhouse', $sql);
        self::assertStringContainsString('tour-lite', $sql);
        self::assertStringContainsString('desert-trek', $sql);
        self::assertStringContainsString('snowline-explorer', $sql);
        self::assertStringContainsString('kids-bunk-21', $sql);
        self::assertStringContainsString('pop-top-weekender', $sql);
        self::assertStringContainsString('NOT EXISTS', $sql);
        self::assertStringContainsString('Not a real business', $sql);
        self::assertStringNotContainsString('polaris_ai_import', $sql);
    }

    public function testHomeEmptyStateReferencesDemoMigrations(): void
    {
        $root = dirname(__DIR__, 3);
        $home = (string) file_get_contents($root . '/app/Views/polaris/home.php');
        self::assertStringContainsString('103', $home);
        self::assertStringContainsString('119', $home);
        self::assertStringContainsString('is_demo', $home);
    }
}
