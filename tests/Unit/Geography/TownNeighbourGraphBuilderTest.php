<?php

declare(strict_types=1);

namespace Tests\Unit\Geography;

use App\Services\Geography\TownNeighbourGraphBuilder;
use PHPUnit\Framework\TestCase;

final class TownNeighbourGraphBuilderTest extends TestCase
{
    public function testPicksNearestSameStateTownsWithinMaxKm(): void
    {
        $edges = TownNeighbourGraphBuilder::edgesFromTowns([
            ['id' => 1, 'state_id' => 1, 'latitude' => -20.0765, 'longitude' => 146.2614], // Charters
            ['id' => 2, 'state_id' => 1, 'latitude' => -20.0760, 'longitude' => 146.2620], // ~0.1 km
            ['id' => 3, 'state_id' => 1, 'latitude' => -20.2000, 'longitude' => 146.4000], // ~20 km
            ['id' => 4, 'state_id' => 1, 'latitude' => -19.2500, 'longitude' => 146.8000], // ~100 km
            ['id' => 5, 'state_id' => 2, 'latitude' => -20.0765, 'longitude' => 146.2614], // other state
        ], 50, 8);

        $fromOne = array_values(array_filter(
            $edges,
            static fn (array $e): bool => $e['town_id'] === 1
        ));
        $neighbourIds = array_map(static fn (array $e): int => $e['neighbour_town_id'], $fromOne);

        self::assertContains(2, $neighbourIds);
        self::assertContains(3, $neighbourIds);
        self::assertNotContains(4, $neighbourIds);
        self::assertNotContains(5, $neighbourIds);
        self::assertNotContains(1, $neighbourIds);
    }

    public function testRespectsNeighbourLimitAndAddsReverseEdges(): void
    {
        $towns = [
            ['id' => 10, 'state_id' => 1, 'latitude' => 0.0, 'longitude' => 0.0],
        ];
        for ($i = 1; $i <= 12; $i++) {
            $towns[] = [
                'id' => 10 + $i,
                'state_id' => 1,
                'latitude' => 0.0,
                'longitude' => 0.01 * $i, // roughly 1.1 km steps
            ];
        }

        $edges = TownNeighbourGraphBuilder::edgesFromTowns($towns, 50, 3);
        $fromHub = array_values(array_filter(
            $edges,
            static fn (array $e): bool => $e['town_id'] === 10
        ));
        self::assertCount(3, $fromHub);
        self::assertSame([11, 12, 13], array_map(
            static fn (array $e): int => $e['neighbour_town_id'],
            $fromHub
        ));

        $reverse = array_values(array_filter(
            $edges,
            static fn (array $e): bool => $e['town_id'] === 11 && $e['neighbour_town_id'] === 10
        ));
        self::assertCount(1, $reverse);
        self::assertSame($fromHub[0]['distance_km'], $reverse[0]['distance_km']);
    }

    public function testExcludesSelfLinksAndMissingCoordinates(): void
    {
        $edges = TownNeighbourGraphBuilder::edgesFromTowns([
            ['id' => 1, 'state_id' => 1, 'latitude' => -27.0, 'longitude' => 153.0],
            ['id' => 2, 'state_id' => 1, 'latitude' => -27.01, 'longitude' => 153.01],
            ['id' => 3, 'state_id' => 1, 'latitude' => 0.0, 'longitude' => 0.0], // far
        ], 50, 8);

        foreach ($edges as $edge) {
            self::assertNotSame($edge['town_id'], $edge['neighbour_town_id']);
        }
        self::assertNotEmpty($edges);
    }

    public function testMigrateRunnerHooksNeighbourRebuildAfterCoordinates(): void
    {
        $runner = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/migrate.php');
        $coordAt = strpos($runner, 'TownCoordinateActivation::afterMigrations()');
        $neighbourAt = strpos($runner, 'TownNeighbourGraphBuilder::afterMigrations()');
        self::assertNotFalse($coordAt);
        self::assertNotFalse($neighbourAt);
        self::assertLessThan($neighbourAt, $coordAt);
    }
}
