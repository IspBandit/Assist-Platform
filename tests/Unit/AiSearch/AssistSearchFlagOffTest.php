<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Services\FeatureFlag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AssistSearchFlagOffTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setValue(null, null);
        parent::tearDown();
    }

    public function testFeatureDefaultsOffWhenUnset(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setValue(null, []);

        self::assertFalse(AiSearchFeature::enabled());
    }

    public function testFlagConstantMatchesSeedKey(): void
    {
        self::assertSame('assist_ai_search', AiSearchFeature::FLAG);
        $seeds = require dirname(__DIR__, 3) . '/database/seeds/data.php';
        self::assertArrayHasKey('assist_ai_search', $seeds['feature_flags']);
        self::assertFalse($seeds['feature_flags']['assist_ai_search'][0]);
    }

    public function testAskRouteIsRegistered(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertNotFalse($routes);
        self::assertStringContainsString("/ask", $routes);
        self::assertStringContainsString('AssistSearchController@form', $routes);
        self::assertStringContainsString('/find', $routes);
    }

    public function testMigrationCreatesAssistSearchesAndFlag(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/085_assist_ai_search.sql');
        self::assertNotFalse($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS assist_searches', $sql);
        self::assertStringContainsString("'assist_ai_search'", $sql);
        self::assertStringContainsString('is_enabled', $sql);
    }
}
