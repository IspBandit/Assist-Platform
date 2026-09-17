<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Services\Search\ProviderSearchRadiusLadder;
use PHPUnit\Framework\TestCase;

final class ProviderSearchRadiusLadderTest extends TestCase
{
    public function testExplicitRadiusIsNeverWidened(): void
    {
        $calls = [];
        $result = ProviderSearchRadiusLadder::expand(static function (int $km) use (&$calls): array {
            $calls[] = $km;

            return $km >= 50 ? [['id' => 1], ['id' => 2], ['id' => 3]] : [];
        }, 50);

        self::assertSame([50], $calls);
        self::assertFalse($result['expanded']);
        self::assertSame(50, $result['radius_km']);
        self::assertCount(3, $result['rows']);
        self::assertNull($result['message']);
    }

    public function testLadderExpandsUntilMinResults(): void
    {
        $calls = [];
        $result = ProviderSearchRadiusLadder::expand(static function (int $km) use (&$calls): array {
            $calls[] = $km;
            if ($km < 150) {
                return $km === 75 ? [['id' => 1]] : [];
            }

            return [['id' => 1], ['id' => 2], ['id' => 3]];
        });

        self::assertContains(25, $calls);
        self::assertContains(75, $calls);
        self::assertContains(150, $calls);
        self::assertTrue($result['expanded']);
        self::assertSame(150, $result['radius_km']);
        self::assertCount(3, $result['rows']);
        self::assertNotNull($result['message']);
        self::assertStringContainsString('25', (string) $result['message']);
        self::assertStringContainsString('150', (string) $result['message']);
    }

    public function testLadderStopsAtLastStepWhenStillSparse(): void
    {
        $result = ProviderSearchRadiusLadder::expand(static function (int $km): array {
            return $km >= 300 ? [['id' => 9]] : [];
        });

        self::assertSame(300, $result['radius_km']);
        self::assertTrue($result['expanded']);
        self::assertCount(1, $result['rows']);
    }
}
