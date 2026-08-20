<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\DataSources\Connectors\OsmOfflineSeedConnector;
use App\Services\OsmRefreshService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OsmOfflineSeedConnectorTest extends TestCase
{
    public function testDisabledWithoutForceThrows(): void
    {
        $connector = new OsmOfflineSeedConnector();
        if ((bool) config('ai_search.osm_offline_enabled', false)) {
            self::markTestSkipped('AI_OSM_OFFLINE_ENABLED is on in this environment');
        }
        $this->expectException(RuntimeException::class);
        $connector->search(['limit' => 1], [], []);
    }

    public function testForceReadsSeedWithoutOverpass(): void
    {
        $path = OsmRefreshService::resolveSeedPath();
        if ($path === null) {
            self::markTestSkipped('OSM seed file not present');
        }
        $connector = new OsmOfflineSeedConnector();
        $hits = $connector->search(
            ['query' => 'autoelec', 'state' => 'ACT', 'limit' => 5],
            [],
            ['force' => true, 'seed_path' => $path, 'limit' => 5]
        );
        self::assertNotEmpty($hits);
        self::assertArrayHasKey('external_id', $hits[0]);
        self::assertArrayHasKey('business_name', $hits[0]);
        self::assertStringStartsWith('osm-', (string) $hits[0]['external_id']);
    }

    public function testOrchestratorDoesNotReferenceLiveOverpass(): void
    {
        $orch = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Platform/AiSearch/SearchOrchestrator.php');
        self::assertStringNotContainsString('OsmRefreshService', $orch);
        self::assertStringNotContainsString('Overpass', $orch);
        $adapter = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Platform/AiSearch/Adapters/DatasetSearchAdapter.php');
        self::assertStringNotContainsString('OsmRefreshService', $adapter);
    }
}
